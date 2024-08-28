<?php

/**
 * Plugin Name: Maib Payment Gateway for WooCommerce
 * Description: Accept Visa / Mastercard / Apple Pay / Google Pay on your store with the Maib Payment Gateway for WooCommerce.
 * Plugin URI: https://github.com/maib-ecomm/woocommerce-maib
 * Version: 1.0.0
 * Author: maib
 * Author URI: https://github.com/maib-ecomm
 * Developer: maib
 * Developer URI: https://github.com/maib-ecomm
 * Text Domain: maib-payment-gateway-for-woocommerce
 * Domain Path: /languages
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 4.8
 * Tested up to: 6.5.4
 * WC requires at least: 3.3
 * WC tested up to: 7.8.0
 */

if (!defined('ABSPATH'))
{
    exit; // Exit if accessed directly
}

/**
 * Define plugin constants.
 */
define('MAIB_GATEWAY_PLUGIN_FILE', __FILE__);
define('MAIB_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAIB_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Include necessary files.
 */
require_once MAIB_GATEWAY_PLUGIN_DIR . 'includes/maib-sdk-php/src/MaibAuthRequest.php';
require_once MAIB_GATEWAY_PLUGIN_DIR . 'includes/maib-sdk-php/src/MaibApiRequest.php';
require_once MAIB_GATEWAY_PLUGIN_DIR . 'includes/maib-sdk-php/src/MaibSdk.php';

class_alias("MaibEcomm\MaibSdk\MaibAuthRequest", "MaibAuthRequest");
class_alias("MaibEcomm\MaibSdk\MaibApiRequest", "MaibApiRequest");

add_action('plugins_loaded', 'maib_payment_gateway_init', 0);

/**
 * Initialize the MAIB payment gateway.
 */
function maib_payment_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    // Hook the custom function to the 'before_woocommerce_init' action
    add_action('before_woocommerce_init', function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        }
    });

    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action( 'woocommerce_blocks_loaded', 'maib_register_order_approval_payment_method_type' );

    load_plugin_textdomain('maib-payment-gateway-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    class MaibPaymentGateway extends WC_Payment_Gateway
    {
        #region Constants
        const MAIB_MOD_ID = 'maib';
        const MAIB_MOD_TITLE = 'Maib Payment Gateway';
        const MAIB_MOD_DESC = 'Visa / Mastercard / Apple Pay / Google Pay';
        const MAIB_MOD_PREFIX = 'maib_';

        const MAIB_TRANSACTION_TYPE_CHARGE = 'direct';
        const MAIB_TRANSACTION_TYPE_AUTHORIZATION = 'twostep';

        const MAIB_MOD_TRANSACTION_TYPE = self::MAIB_MOD_PREFIX . 'transaction_type';

        const MAIB_SUPPORTED_CURRENCIES = ['MDL', 'EUR', 'USD'];
        const MAIB_ORDER_TEMPLATE = 'Order #%1$s';
        #endregion
        
        public static $log_enabled = false;
        public static $log = false;

        protected $logo_type, $debug, $transaction_type, $order_template;
        protected $maib_project_id, $maib_project_secret, $maib_signature_key;
        protected $completed_order_status, $hold_order_status, $failed_order_status;
        protected $maib_access_token = 'maib_access_token';
        protected $maib_refresh_token = 'maib_refresh_token';

        public function __construct()
        {
            $this->id = self::MAIB_MOD_ID;
            $this->method_title = self::MAIB_MOD_TITLE;
            $this->method_description = self::MAIB_MOD_DESC;
            $this->has_fields = false;
            $this->supports = array(
                'products',
                'refunds',
                'custom_order_tables',
            );

            #region Initialize user set variables
            $this->enabled = $this->get_option('enabled', 'yes');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $this->icon = MAIB_GATEWAY_PLUGIN_URL . 'assets/img/maib_logo.svg';

            $this->debug = 'yes' === $this->get_option('debug', 'no');
            self::$log_enabled = $this->debug;

            $this->transaction_type = $this->get_option('transaction_type', self::MAIB_TRANSACTION_TYPE_CHARGE);
            $this->order_template = $this->get_option('order_template', self::MAIB_ORDER_TEMPLATE);

            $this->maib_project_id = $this->get_option('maib_project_id');
            $this->maib_project_secret = $this->get_option('maib_project_secret');
            $this->maib_signature_key = $this->get_option('maib_signature_key');

            $this->completed_order_status = $this->get_option('completed_order_status');
            $this->hold_order_status = $this->get_option('hold_order_status');
            $this->failed_order_status = $this->get_option('failed_order_status');

            // Routes
            $this->route_return_ok = 'maib/return/ok';
            $this->route_return_fail = 'maib/return/fail';
            $this->route_callback = 'maib/callback';

            $this->init_form_fields();
            $this->init_settings();
            #endregion
            
            if (is_admin()) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'clear_transients'));
            }

            add_action('woocommerce_api_' . $this->route_return_ok, [$this, 'route_return_ok']);
            add_action('woocommerce_api_' . $this->route_return_fail, [$this, 'route_return_fail']);
            add_action('woocommerce_api_' . $this->route_callback, [$this, 'route_callback']);
        }

        /**
        * Clear acess token transients when settings saved
        */
        public function clear_transients()
        {
            delete_transient($this->maib_access_token);
            delete_transient($this->maib_refresh_token);

            // Save the new settings
            $this->process_admin_options();
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'checkbox',
                    'label' => __('Enable Maib Payment Gateway', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => 'yes'
                ) ,
                
                'title' => array(
                    'title' => __('Title', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'text',
                    'description' => __('Payment method title that the customer will see during checkout.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true,
                    'default' => __('Pay online', 'maib-payment-gateway-for-woocommerce')
                ) ,

                'description' => array(
                    'title' => __('Description', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see during checkout.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true,
                    'default' => __('Visa / Mastercard / Apple Pay / Google Pay', 'maib-payment-gateway-for-woocommerce')
                ) ,

                'debug' => array(
                    'title' => __('Debug mode', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => 'yes',
                    'description' => sprintf('<a href="%2$s&source=maib_gateway&paged=1">%1$s</a>', __('View logs', 'maib-payment-gateway-for-woocommerce') , self::get_logs_url()) ,
                    'desc_tip' => __('Save debug messages to the WooCommerce System Status logs. Note: this may log personal information. Use this for debugging purposes only and delete the logs when finished.', 'maib-payment-gateway-for-woocommerce')
                ) ,

                'transaction_type' => array(
                    'title' => __('Transaction type', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Direct payment - if the transaction is successful the amount is withdrawn from the customer account. 
                    Two-step payment - if the transaction is successful the amount is just on hold on the customer account, to withdraw the amount use the Order action: Complete Two-Step Payment.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true,
                    'default' => self::MAIB_TRANSACTION_TYPE_CHARGE,
                    'options' => array(
                        self::MAIB_TRANSACTION_TYPE_CHARGE => __('Direct Payment', 'maib-payment-gateway-for-woocommerce') ,
                        self::MAIB_TRANSACTION_TYPE_AUTHORIZATION => __('Two-Step Payment', 'maib-payment-gateway-for-woocommerce')
                    )
                ) ,

                'order_template' => array(
                    'title' => __('Order description', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'text',
                    // translators: %1$s - order ID, %2$ - order items summary
                    'description' => __('Format: <code>%1$s</code> - Order ID, <code>%2$s</code> - Order items summary', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => __('Order description that the customer will see on the bank payment page.', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => self::MAIB_ORDER_TEMPLATE
                ) ,

                'connection_settings' => array(
                    'title' => __('Connection Settings - <a href="https://maibmerchants.md" target="_blank">maibmerchants.md</a>', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'title'
                ) ,

                'maib_project_id' => array(
                    'title' => __('Project ID <span class="required">*</span>', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'text',
                    'description' => __('It is available after registration.', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => ''
                ) ,

                'maib_project_secret' => array(
                    'title' => __('Project Secret <span class="required">*</span>', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'password',
                    'description' => __('It is available after Project activation.', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => ''
                ) ,

                'maib_signature_key' => array(
                    'title' => __('Signature Key <span class="required">*</span>', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'password',
                    'description' => __('It is available after Project activation.', 'maib-payment-gateway-for-woocommerce') ,
                    'default' => ''
                ) ,

                'status_settings' => array(
                    'title' => __('Order status', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'title'
                ) ,

                'completed_order_status' => array(
                    'title' => __('Payment completed', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('The completed order status after successful payment. By default: Processing.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true
                ) ,

                'hold_order_status' => array(
                    'title' => __('Two-Step Payment authorized', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('Order status when payment on hold. By default: On hold.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true
                ) ,

                'failed_order_status' => array(
                    'title' => __('Payment failed', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('Order status when payment failed. By default: Failed.', 'maib-payment-gateway-for-woocommerce') ,
                    'desc_tip' => true
                ) ,

                'payment_notification' => array(
                    'title' => __('Project Settings', 'maib-payment-gateway-for-woocommerce') ,
                    'description' => __('Add these links to the Project settings in maibmerchants.md', 'maib-payment-gateway-for-woocommerce') ,
                    'type' => 'title'
                ) ,

                'maib_ok_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Ok URL', 'maib-payment-gateway-for-woocommerce') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_ok))) ,
                    'type' => 'title'
                ) ,

                'maib_fail_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Fail URL', 'maib-payment-gateway-for-woocommerce') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_fail))) ,
                    'type' => 'title'
                ) ,

                'maib_callback_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Callback URL', 'maib-payment-gateway-for-woocommerce') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_callback))) ,
                    'type' => 'title'
                )
            );
        }

        #region Payment
        
        /**
         * Process the payment and redirect client.
         *
         * @since 1.0.0
         * @param  int $order_id Order ID.
         * @return array
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $client_name = substr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name() , 0, 128);

            $items = $order->get_items();
            $product_items = array();

            foreach ($items as $item)
            {
                $product = $item->get_product();

                $product_id = strval($item->get_product_id());
                $product_name = $item->get_name();
                $product_price = number_format($product->get_price(), 2, '.', '');
                $product_quantity = $item->get_quantity();

                $product_items[] = array(
                    'id' => $product_id,
                    'name' => substr($product_name, 0, 128),
                    'price' => $product_price,
                    'quantity' => $product_quantity,
                );
            }

            $nonce = wp_create_nonce('verify_order');

            $params = [
                'amount' => number_format($order->get_total(), 2, '.', ''),
                'currency' => $order->get_currency(),
                'clientIp' => self::get_client_ip(),
                'language' => self::get_language(),
                'description' => substr(sanitize_text_field($this->get_order_description($order)), 0, 124),
                'orderId' => strval($order->get_id()),
                'clientName' => sanitize_text_field($client_name),
                'email' => sanitize_email($order->get_billing_email()),
                'phone' => substr(sanitize_text_field($order->get_billing_phone()), 0, 40),
                'delivery' => number_format($order->get_shipping_total(), 2, '.', ''),
                'okUrl' => esc_url(add_query_arg(['nonce' => $nonce], sprintf('%s/wc-api/%s', get_bloginfo('url'), $this->route_return_ok))),
                'failUrl' => esc_url(add_query_arg(['nonce' => $nonce], sprintf('%s/wc-api/%s', get_bloginfo('url'), $this->route_return_fail))),
                'callbackUrl' => esc_url(add_query_arg(['nonce' => $nonce], sprintf('%s/wc-api/%s', get_bloginfo('url'), $this->route_callback))),
                'items' => $product_items,
            ];

            if (empty($this->maib_project_id) || empty($this->maib_project_secret) || empty($this->maib_signature_key))
            {
                $this->log('One or more of the required fields is empty in Maib Payment Gateway settings.');
                wc_add_notice(__('One or more of the required fields (Project ID, Project Secret, Signature Key) is empty in Maib Payment Gateway settings.', 'maib-payment-gateway-for-woocommerce') , 'error');
                return;
            }

            if (!in_array($params['currency'], self::MAIB_SUPPORTED_CURRENCIES))
            {
                $this->log(sprintf('Unsupported currency %s for order %d', $params['currency'], $order_id) , 'error');
                wc_add_notice(__('This currency is not supported by Maib Payment Gateway. Please choose a different currency (MDL / EUR / USD).', 'maib-payment-gateway-for-woocommerce') , 'error');
                return;
            }

            $this->log(sprintf('Order params for send to maib API: %s, order_id: %d', wp_json_encode($params, JSON_PRETTY_PRINT) , $order_id) , 'info');

            try
            {
                switch ($this->transaction_type)
                {
                    case self::MAIB_TRANSACTION_TYPE_CHARGE:
                        $this->log('Initiate Direct Payment', 'info');
                        $response = MaibApiRequest::create()->pay($params, $this->get_access_token());
                        $this->log(sprintf('Response from pay endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
                    break;

                    case self::MAIB_TRANSACTION_TYPE_AUTHORIZATION:
                        $this->log('Initiate Two-Step Payment', 'info');
                        $response = MaibApiRequest::create()->hold($params, $this->get_access_token());
                        $this->log(sprintf('Response from hold endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
                    break;

                    default:
                        $this->log(sprintf('Unknown transaction type: %1$s Order ID: %2$s', $this->transaction_type, $order_id) , WC_Log_Levels::ERROR);
                    break;
                }
            }
            catch(Exception $ex)
            {
                $this->log($ex, 'error');
            }

            if (!$response || !isset($response->payId))
            {
                $this->log(sprintf('No valid response from maib, order_id: %d', $order_id) , 'error');
                wc_add_notice(__('Payment initiation failed via maib gateway, please try again later.', 'maib-payment-gateway-for-woocommerce') , 'error');
                return;
            }

            update_post_meta($order_id, '_transaction_id', $response->payId);
            self::set_post_meta($order_id, self::MAIB_MOD_TRANSACTION_TYPE, $this->transaction_type);

            $order->update_meta_data('_transaction_id', $response->payId);
            $order->add_order_note('maib Payment ID: <br>' . $response->payId);
            $order->save();

            $redirect_to = $response->payUrl;

            $this->log(sprintf('Order id: %d, redirecting user to maib gateway: %s', $order_id, $redirect_to) , 'notice');

            return ['result' => 'success', 'redirect' => $redirect_to, ];
        }

        /**
         * Process a refund
         *
         * @param  int    $order_id Order ID.
         * @param  float  $amount Refund amount.
         * @param  string $reason Refund reason.
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);

            if (!$order->get_transaction_id())
            {
                $this->log('Refund not possible, payment ID missing in order data.', 'error');
                return new \WP_Error('error', __('Refund not possible, payment ID missing in order data.', 'maib-payment-gateway-for-woocommerce'));
            }

            $this->log(sprintf('Start refund, Order id: %s / Refund amount: %s', $order->get_id() , $amount) , 'info');
            
            $params = [
                'payId' => strval($order->get_transaction_id()), 
                'refundAmount' => isset($amount) ? (float) number_format($amount, 2, '.', '') : 0.0, 
            ];

            try
            {
                $this->log('Initiate Refund', 'info');
                $response = MaibApiRequest::create()->refund($params, $this->get_access_token());
                $this->log(sprintf('Response from refund endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
            }
            catch(Exception $ex)
            {
                $this->log($ex, 'error');
            }

            if (false === $response || !isset($response->status))
            {
                return new \WP_Error('error', __('Refund failed! Please see Maib Payment Gateway logs.', 'maib-payment-gateway-for-woocommerce'));
            }

            if ($response->status === 'REVERSED')
            {
                return new \WP_Error('error', __('Payment already refunded!', 'maib-payment-gateway-for-woocommerce'));
            }

            $this->log('Success ~ Refund done!', 'info');
            $order_note = sprintf('Refunded! Refund details: %s', wp_json_encode($response, JSON_PRETTY_PRINT));
            $order->add_order_note($order_note);

            return true;
        }

        /**
         * Process complete two-step payment
         */
        public function complete_transaction($order_id, $order)
        {
            $order = wc_get_order($order_id);

            if (!$order->get_transaction_id())
            {
                $this->log('Complete Two-Step payment not possible, transaction id missing.', 'error');
                return new \WP_Error('error', __('Complete Two-Step payment not possible, payment id missing.', 'maib-payment-gateway-for-woocommerce'));
            }

            $this->log(sprintf('Start complete two-step payment, Order id: %s', $order->get_id()) , 'info');

            $params = [
                'payId' => strval($order->get_transaction_id()), 
            ];

            // Initiate Complete Two-Step payment
            try
            {
                $response = MaibApiRequest::create()->complete($params, $this->get_access_token());
                $this->log(sprintf('Response from complete endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
            }
            catch(Exception $ex)
            {
                $this->log($ex, 'error');
            }

            if (false === $response || !isset($response->status) || $response->status !== 'OK')
            {
                return new \WP_Error('error', __('Complete Two-Step payment failed! Please see Maib Payment Gateway logs.', 'maib-payment-gateway-for-woocommerce'));
            }

            $this->log('Success ~ Two-Step payment completed!', 'info');

            if ($this->completed_order_status != 'default')
            {
                $order->update_status($this->completed_order_status);
            }
            else
            {
                $order->update_status('processing');
            }

            $order_note = sprintf('Two-Step payment completed! Details: %s', wp_json_encode($response, JSON_PRETTY_PRINT));
            $order->add_order_note($order_note);

            return true;
        }

        #endregion

        #region Utility
        
        /**
         * Logging method.
         *
         * @since 1.0.0
         * @param string $message Log message.
         * @param string $level Optional. Default 'info'. Possible values:
         * emergency|alert|critical|error|warning|notice|info|debug.
         */
        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled)
            {
                if (empty(self::$log))
                {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, ['source' => 'maib_gateway']);
            }
        }

        /**
         * Get access token from transient or set it.
         */
        public function get_access_token()
        {
            $access_token = get_transient($this->maib_access_token);
            $refresh_token = get_transient($this->maib_refresh_token);

            if (false === $access_token) {
                if (false === $refresh_token) {
                    $this->log('Get access token with Project ID / Secret', 'info');
                    $response = $this->generateAccessToken($this->maib_project_id, $this->maib_project_secret);
                } else {
                    $this->log('Get access token with refresh token', 'info');
                    $response = $this->generateAccessToken($refresh_token);
                }

                if ($response && isset($response->accessToken, $response->expiresIn)) {
                    set_transient($this->maib_access_token, $response->accessToken, $response->expiresIn);
                    set_transient($this->maib_refresh_token, $response->refreshToken, $response->refreshExpiresIn);
                    $access_token = $response->accessToken;
                } else {
                    $this->log('API did not return an access token.', 'critical');
                }
            } else {
                $this->log('Get access token from cache', 'info');
            }

            return $access_token;
        }

        /**
         * Generate access token from maib API.
         */
        private function generateAccessToken($projectIdOrRefreshToken, $projectSecret = null)
        {
            try {
                if ($projectSecret !== null) {
                    $response = MaibAuthRequest::create()->generateToken($projectIdOrRefreshToken, $projectSecret);
                } else {
                    $response = MaibAuthRequest::create()->generateToken($projectIdOrRefreshToken);
                }
                $this->log('Access token generated successfully.', 'info');
                return $response;
            } catch (Exception $ex) {
                $this->log($ex, 'error');
                return null;
            }
        }

        /**
         * Getting all available woocommerce order statuses
         *
         * @return array
         */
        public function getPaymentOrderStatuses()
        {
            $order_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
            $statuses = ['default' => __('Default status', 'maib-payment-gateway-for-woocommerce') ];
            if ($order_statuses)
            {
                foreach ($order_statuses as $k => $v)
                {
                    $statuses[str_replace('wc-', '', $k) ] = $v;
                }
            }

            return $statuses;
        }

        /**
         * Notification on Callback URL
         */
        public function route_callback()
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $json = file_get_contents('php://input');
                $data = json_decode($json, true);
            
                if (!isset($data['signature']) || !isset($data['result'])) {
                    $this->log('Callback URL - Signature or Payment data not found in notification.', 'error');
                    exit();
                }
            } else {
                $message = sprintf(__('This Callback URL works and should not be called directly.', 'maib-payment-gateway-for-woocommerce'), $this->method_title);
                wc_add_notice($message, 'notice');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }
            
            if (!isset($data['signature']) || !isset($data['result']))
            {
                $this->log('Callback URL - Signature or Payment data not found in notification.', 'error');
                exit();
            }
            
            $this->log(sprintf('Notification on Callback URL: %s', wp_json_encode($data, JSON_PRETTY_PRINT)) , 'info');
            $data_result = $data['result']; // Data from "result" object
            $sortedDataByKeys = $this->sortByKeyRecursive($data_result); // Sort an array by key recursively
            $key = $this->maib_signature_key; // Signature Key from Project settings
            $sortedDataByKeys[] = $key; // Add checkout Secret Key to the end of data array
            $signString = $this->implodeRecursive(':', $sortedDataByKeys); // Implode array recursively
            $sign = base64_encode(hash('sha256', $signString, true)); // Result Hash
            
            $pay_id = isset($data_result['payId']) ? $data_result['payId'] : false;
            $order_id = isset($data_result['orderId']) ? (int)$data_result['orderId'] : false;
            $status = isset($data_result['status']) ? $data_result['status'] : false;
            $order = wc_get_order($order_id);

            if ($sign !== $data['signature'])
            {    
                echo "ERROR";
                $this->log(sprintf('Signature is invalid: %s', $sign) , 'info');
                $order->add_order_note('Callback Signature is invalid!');
                exit();
            }
            
            echo "OK";
            $this->log(sprintf('Signature is valid: %s', $sign) , 'info');

            if (!$order_id || !$status)
            {
                $this->log('Callback URL - Order ID or Status not found in notification.', 'error');
                exit();
            }

            if (!$order)
            {
                $this->log('Callback URL - Order ID not found in woocommerce Orders.', 'error');
                exit();
            }
            
            if (in_array($order->get_status(), array('pending', 'failed')))
            {
                if ($status === 'OK')
                {
                    switch ($this->transaction_type)
                    {
                        case self::MAIB_TRANSACTION_TYPE_CHARGE:
                            $this->payment_complete($order, $pay_id);
                        break;

                        case self::MAIB_TRANSACTION_TYPE_AUTHORIZATION:
                            $this->payment_hold($order, $pay_id);
                        break;

                        default:
                            $this->log(sprintf('Unknown transaction type: %1$s Order ID: %2$s', $this->transaction_type, $order_id) , 'error');
                        break;
                    }
                }
                else
                {
                    $this->payment_failed($order, $pay_id);
                }
            }

            $order_note = sprintf('maib transaction details: %s', wp_json_encode($data_result, JSON_PRETTY_PRINT));
            $order->add_order_note($order_note);

            exit();
        }

        // Helper function: Sort an array by key recursively
        private function sortByKeyRecursive(array $array)
        {
            ksort($array, SORT_STRING);
            foreach ($array as $key => $value)
            {
                if (is_array($value))
                {
                    $array[$key] = $this->sortByKeyRecursive($value);
                }
            }
            return $array;
        }

        // Helper function: Implode array recursively
        private function implodeRecursive($separator, $array)
        {
            $result = '';
            foreach ($array as $item)
            {
                $result .= (is_array($item) ? $this->implodeRecursive($separator, $item) : (string)$item) . $separator;
            }
            return substr($result, 0, -1);
        }

        /**
         * Redirect back from maib gateway if fail payment
         */
        public function route_return_fail()
        {
            list($order_id, $pay_id) = (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'verify_order')) 
                ? [
                    isset($_GET['orderId']) ? absint($_GET['orderId']) : false,
                    isset($_GET['payId']) ? sanitize_text_field(wp_unslash($_GET['payId'])) : false
                ] 
                : wp_die(esc_html__('Security check failed.', 'textdomain'));

            $order = wc_get_order($order_id);

            if (!$order_id || !$pay_id)
            {
                $this->log('Fail URL - Order ID or Pay ID not found in redirect url.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'maib-payment-gateway-for-woocommerce') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }
            if (!$order)
            {
                $this->log('Fail URL - Order not found.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'maib-payment-gateway-for-woocommerce') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }

            // translators: %1$ - order ID, %3$s - plugin name, %3$s - error message from API
            $message = sprintf(__('Order #%1$s payment failed via %2$s. %3$s', 'maib-payment-gateway-for-woocommerce') , $order_id, self::MAIB_MOD_TITLE, $response->statusMessage);
            $this->log($message, 'notice');
            wc_add_notice($message, 'error');
            wp_safe_redirect($order->get_checkout_payment_url());
            exit();
        }

        /**
         * Redirect back from maib gateway if success payment
         */
        public function route_return_ok()
        {
            list($order_id, $pay_id) = (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'verify_order')) 
                ? [
                    isset($_GET['orderId']) ? absint($_GET['orderId']) : false,
                    isset($_GET['payId']) ? sanitize_text_field(wp_unslash($_GET['payId'])) : false
                ] 
                : wp_die(esc_html__('Security check failed.', 'textdomain'));

            $order = wc_get_order($order_id);

            if (!$order_id || !$pay_id)
            {
                $this->log('Ok URL - Order ID or Pay ID not found in redirect url.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'maib-payment-gateway-for-woocommerce') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }

            if (!$order)
            {
                $this->log('Ok URL - Order not found in woocommerce Orders.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'maib-payment-gateway-for-woocommerce') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }

            wp_safe_redirect($this->get_safe_return_url($order));
            exit();
        }

        /**
         * Send request to pay-info endpoint
         *
         * @param  string $pay_id The payment ID
         * @param  string $token  The access token
         * @param  int    $order_id The order ID
         */
        private function send_payment_info_request($pay_id, $token, $order_id)
        {
            $order = wc_get_order($order_id);
            $this->log(sprintf('Payment ID send to pay-info endpoint: %s, order_id: %d', $pay_id, $order_id) , 'info');

            try
            {
                $response = MaibApiRequest::create()->payInfo($pay_id, $token);
                $this->log(sprintf('Response from pay-info endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
            }
            catch(Exception $ex)
            {
                $this->log($ex, 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'maib-payment-gateway-for-woocommerce') , 'error');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit();
            }

            if ($response && $response->status === 'OK')
            {

                switch ($this->transaction_type)
                {
                    case self::MAIB_TRANSACTION_TYPE_CHARGE:
                        $this->payment_complete($order, $pay_id);
                    break;

                    case self::MAIB_TRANSACTION_TYPE_AUTHORIZATION:
                        $this->payment_hold($order, $pay_id);
                    break;

                    default:
                        $this->log(sprintf('Unknown transaction type: %1$s Order ID: %2$s', $this->transaction_type, $order_id) , 'error');
                    break;
                }

                wp_safe_redirect($this->get_safe_return_url($order));
                $order_note = sprintf('maib transaction details: %s', wp_json_encode($response, JSON_PRETTY_PRINT));
                $order->add_order_note($order_note);
                exit();
            }
            else
            {
                $this->payment_failed($order, $pay_id);
                // translators: %1$ - order ID, %3$s - plugin name, %3$s - error message from API
                $message = sprintf(__('Order #%1$s payment failed via %2$s. %3$s', 'maib-payment-gateway-for-woocommerce') , $order_id, self::MAIB_MOD_TITLE, $response->statusMessage);
                wc_add_notice($message, 'error');
                $this->log($message, 'notice');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit();
            }
        }

        /**
         * Get return url (order received page) in a safe manner.
         */
        public function get_safe_return_url($order)
        {
            if ($order->get_user_id() === get_current_user_id())
            {
                return $this->get_return_url($order);
            }
            else
            {
                return wc_get_endpoint_url('order-received', '', wc_get_page_permalink('checkout'));
            }
        }

        /**
         * Payment complete.
         * @param WC_Order $order Order object.
         * @param string   $pay_id Payment ID.
         * @return bool
         */
        public function payment_complete($order, $pay_id)
        {
            if ($order->payment_complete())
            {
                // translators: %1$s - payment ID
                $order_note = sprintf(__('Payment (%1$s) successful.', 'maib-payment-gateway-for-woocommerce') , $pay_id);
                if ($this->completed_order_status != 'default')
                {
                    WC()
                        ->cart
                        ->empty_cart();
                    $order->update_status($this->completed_order_status, $order_note);
                }
                else
                {
                    $order->add_order_note($order_note);
                }

                $this->log($order_note, 'notice');

                return true;
            }
            return false;
        }

        /**
         * Payment hold.
         * @param WC_Order $order Order object.
         * @param string   $pay_id Payment ID.
         * @return bool
         */
        public function payment_hold($order, $pay_id)
        {
            if ($order->payment_complete())
            {
                // translators: %1$s - payment ID
                $order_note = sprintf(__('Payment (%1$s) on hold.', 'maib-payment-gateway-for-woocommerce') , $pay_id);
                $newOrderStatus = $this->hold_order_status != 'default' ? $this->hold_order_status : 'on-hold';
                $order->update_status($newOrderStatus, $order_note);
                $this->log($order_note, 'notice');

                return true;
            }
            return false;
        }

        /**
         * Payment failed.
         * @param WC_Order $order Order object.
         * @param string   $pay_id Payment ID.
         * @return bool
         */
        public function payment_failed($order, $pay_id)
        {
            // translators: %1$s - payment ID
            $order_note = sprintf(__('Payment (%1$s) failed.', 'maib-payment-gateway-for-woocommerce') , $pay_id);
            $newOrderStatus = $this->failed_order_status != 'default' ? $this->failed_order_status : 'failed';
            if ($order->has_status('failed'))
            {
                $order->add_order_note($order_note);
                $this->log($order_note, 'notice');
                return true;
            }
            else
            {
                $this->log($order_note, 'notice');
                return $order->update_status($newOrderStatus, $order_note);
            }
        }

        /**
         * Format the price with two decimals.
         *
         * @param float $price The price to format.
         * @return string The formatted price.
         */
        protected static function price_format($price)
        {
            $decimals = 2;
            return number_format($price, $decimals, '.', '');
        }

        /**
         * Get the language code from the current locale.
         *
         * @return string The language code.
         */
        protected static function get_language()
        {
            $lang = get_locale();
            return substr($lang, 0, 2);
        }

        /**
         * Get the client's IP address.
         *
         * @return string The client's IP address.
         */
        protected static function get_client_ip()
        {
            // Check if WC_Geolocation class exists and the method is available
            if (class_exists('WC_Geolocation') && method_exists('WC_Geolocation', 'get_ip_address')) {
                return WC_Geolocation::get_ip_address();
            } else {
                // Fallback method to retrieve the IP address
                return self::get_fallback_ip_address();
            }
        }

        /**
         * Fallback method to retrieve the client's IP address if WC_Geolocation is not available.
         *
         * @return string The client's IP address.
         */
        protected static function get_fallback_ip_address()
        {
            if (array_key_exists('HTTP_X_REAL_IP', $_SERVER)) {
                return self::validate_ip( sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP'])));
            }
    
            if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
                return self::validate_ip( sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP'])));
            }

            if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
                $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
                if (is_array($ips) && ! empty($ips)) {
                    return self::validate_ip(trim($ips[0]));
                }
            }
    
            if (array_key_exists('HTTP_FORWARDED', $_SERVER)) {
                // Using regex instead of explode() for a smaller code footprint.
                // Expected format: Forwarded: for=192.0.2.60;proto=http;by=203.0.113.43,for="[2001:db8:cafe::17]:4711"...
                preg_match(
                    '/(?<=for\=)[^;,]*/i', // We catch everything on the first "for" entry, and validate later.
                    sanitize_text_field(wp_unslash($_SERVER['HTTP_FORWARDED'])),
                    $matches
                );
    
                if (strpos($matches[0] ?? '', '"[') !== false) { // Detect for ipv6, eg "[ipv6]:port".
                    preg_match(
                        '/(?<=\[).*(?=\])/i', // We catch only the ipv6 and overwrite $matches.
                        $matches[0],
                        $matches
                    );
                }
    
                if (!empty($matches)) {
                    return self::validate_ip(trim($matches[0]));
                }
            }

            return '0.0.0.0';
        }

        /**
         * Uses filter_var() to validate and return ipv4 and ipv6 addresses
         * Will return 0.0.0.0 if the ip is not valid. This is done to group and still rate limit invalid ips.
         *
         * @param string $ip ipv4 or ipv6 ip string.
         *
         * @return string
         */
        protected static function validate_ip($ip)
        {
            $ip = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                array(FILTER_FLAG_NO_RES_RANGE, FILTER_FLAG_IPV6)
            );

            return $ip ?: '0.0.0.0';
        }

        /**
         * Get the URL to view logs in WooCommerce.
         *
         * @return string The URL to view logs.
         */
        protected static function get_logs_url()
        {
            return add_query_arg(array(
                'page' => 'wc-status',
                'tab' => 'logs',
            ) , admin_url('admin.php'));
        }

        /**
         * Get the URL to plugin settings in WooCommerce.
         *
         * @return string The URL to plugin settings.
         */
        public static function get_settings_url()
        {
            return add_query_arg(array(
                'page' => 'wc-settings',
                'tab' => 'checkout',
                'section' => self::MAIB_MOD_ID
            ) , admin_url('admin.php'));
        }

        /**
         * Get the transaction type of an order.
         *
         * @param int $order_id The ID of the order.
         * @return string The transaction type.
         */
        protected static function get_order_transaction_type($order_id)
        {
            $transaction_type = get_post_meta($order_id, self::MAIB_MOD_TRANSACTION_TYPE, true);
            return $transaction_type;
        }

        /**
         * Set post meta with fallback to update if already exists.
         *
         * @param int    $post_id    The ID of the post.
         * @param string $meta_key   The meta key.
         * @param mixed  $meta_value The meta value.
         * @return void
         */
        protected static function set_post_meta($post_id, $meta_key, $meta_value)
        {
            if (!add_post_meta($post_id, $meta_key, $meta_value, true))
            {
                update_post_meta($post_id, $meta_key, $meta_value);
            }
        }

        /**
         * Get the description of an order.
         *
         * @param object $order The order object.
         * @return string The order description.
         */
        protected function get_order_description($order)
        {
            $description = sprintf($this->order_template, $order->get_id(), self::get_order_items_summary($order));
            return apply_filters(self::MAIB_MOD_ID . '_order_description', $description, $order);
        }

        /**
         * Get the summary of items in an order.
         *
         * @param object $order The order object.
         * @return string The summary of order items.
         */
        protected static function get_order_items_summary($order)
        {
            $items = $order->get_items();
            $items_names = array_map(function ($item)
            {
                return $item->get_name();
            }
            , $items);

            return join(', ', $items_names);
        }
        #endregion

        #region Admin

        /**
         * Add plugin settings link in the plugin list.
         *
         * @param array $links The existing plugin links.
         * @return array The modified plugin links.
         */
        public static function plugin_links($links)
        {
            $plugin_links = array(
                sprintf('<a href="%1$s">%2$s</a>', esc_url(self::get_settings_url()) , __('Settings', 'maib-payment-gateway-for-woocommerce'))
            );

            return array_merge($plugin_links, $links);
        }

        /**
         * Modify order actions based on the payment method.
         *
         * @param array $actions The existing order actions.
         * @return array The modified order actions.
         */
        static function order_actions($actions)
        {
            global $maib_order;

            // Check if $maib_order is null or not an instance of WC_Order
            if (!isset($maib_order) || !($maib_order instanceof WC_Order)) {
                return $actions;
            }

            if ($maib_order->get_payment_method() !== self::MAIB_MOD_ID) {
                return $actions;
            }

            $transaction_type = get_post_meta($maib_order->get_id(), self::MAIB_MOD_TRANSACTION_TYPE, true);
            if ($transaction_type === self::MAIB_TRANSACTION_TYPE_AUTHORIZATION) {
                $actions['maib_complete_transaction'] = sprintf(__('Complete Two-Step Payment', 'maib-payment-gateway-for-woocommerce'), self::MAIB_MOD_TITLE);
            }

            return $actions;
        }

        /**
         * Action to complete a transaction.
         *
         * @param object $order The WooCommerce order object.
         * @return mixed The result of completing the transaction.
         */
        static function action_complete_transaction($order)
        {
            $order_id = $order->get_id();

            $plugin = new self();
            return $plugin->complete_transaction($order_id, $order);
        }

        #endregion

        #region WooCommerce

        /**
         * Add the gateway to WooCommerce payment methods.
         *
         * @param array $methods The existing payment methods.
         * @return array The modified payment methods.
         */
        public static function add_gateway($methods)
        {
            $methods[] = self::class;
            return $methods;
        }

        /**
         * Check if WooCommerce is active.
         *
         * @return bool True if WooCommerce is active, false otherwise.
         */
        public static function is_wc_active()
        {
            return class_exists('WooCommerce');
        }

        #endregion
    }

    if (!MaibPaymentGateway::is_wc_active())
        return;

    // Add gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', array(
        MaibPaymentGateway::class ,
        'add_gateway'
    ));

    add_action('wp_enqueue_scripts', 'enqueue_payment_gateway_styles');
    
    function enqueue_payment_gateway_styles()
    {
        // Get the version of your plugin from the plugin header
        $plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
        $plugin_version = $plugin_data['Version'];
    
        // Enqueue the custom CSS file with the plugin version
        wp_enqueue_style('payment-gateway-styles', MAIB_GATEWAY_PLUGIN_URL . 'assets/css/style.css', array(), $plugin_version);
    }

    #region Admin init
    if (is_admin())
    {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__) , array(
            MaibPaymentGateway::class ,
            'plugin_links'
        ));

        //Add WooCommerce order actions
        add_filter('woocommerce_order_actions', array(
            MaibPaymentGateway::class ,
            'order_actions'
        ));

        add_action('woocommerce_order_action_maib_complete_transaction', array(
            MaibPaymentGateway::class ,
            'action_complete_transaction'
        ));
    }
    #endregion
}

#region Register activation hooks
function maib_payment_gateway_activation()
{
    maib_payment_gateway_init();

    if (!class_exists('MaibPaymentGateway')) die('WooCommerce is required for this plugin to work!');
}

register_activation_hook(__FILE__, 'maib_payment_gateway_activation');
#endregion

/**
 * Custom function to register a payment method type
 */
function maib_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the MAIB Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of MaibPaymentGateway_Blocks
            $payment_method_registry->register( new MaibPaymentGateway_Blocks );
        }
    );
}

?>