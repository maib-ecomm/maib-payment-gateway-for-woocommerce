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
 * Tested up to: 6.5
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

add_action('plugins_loaded', 'woocommerce_maib_init', 0);

function woocommerce_maib_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    load_plugin_textdomain('wc-maib', false, dirname(plugin_basename(__FILE__)) . '/languages');

    class WC_Maib extends WC_Payment_Gateway
    {
        #region Constants
        const MOD_ID = 'maib';
        const MOD_TITLE = 'Maib Payment Gateway';
        const MOD_DESC = 'Visa / Mastercard / Apple Pay / Google Pay';
        const MOD_PREFIX = 'maib_';

        const TRANSACTION_TYPE_CHARGE = 'direct';
        const TRANSACTION_TYPE_AUTHORIZATION = 'twostep';

        const LOGO_TYPE_ALL = 'bank';
        const LOGO_TYPE_LIBER = 'systems';
        const LOGO_TYPE_NONE = 'none';

        const MOD_TRANSACTION_TYPE = self::MOD_PREFIX . 'transaction_type';
        const MOD_HOLD_STATUS = self::MOD_PREFIX . 'hold_order_status';
        const MOD_TRANSACTION_ID = self::MOD_PREFIX . 'transaction_id';
        const MOD_PAYMENT_ID = self::MOD_PREFIX . 'payment_id';

        const SUPPORTED_CURRENCIES = ['MDL', 'EUR', 'USD'];
        const ORDER_TEMPLATE = 'Order #%1$s';
        #endregion
        
        public static $log_enabled = false;
        public static $log = false;

        protected $logo_type, $debug, $transaction_type, $order_template;
        protected $maib_project_id, $maib_project_secret, $maib_signature_key;
        protected $completed_order_status, $hold_order_status, $failed_order_status;
        protected $transient_token_key = 'maib_access_token';
        protected $transient_refresh_key = 'maib_refresh_token';


        public function __construct()
        {
            $this->id = self::MOD_ID;
            $this->method_title = self::MOD_TITLE;
            $this->method_description = self::MOD_DESC;
            $this->has_fields = false;
            $this->supports = array(
                'products',
                'refunds'
            );

            #region Initialize user set variables
            $this->enabled = $this->get_option('enabled', 'yes');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $this->logo_type = $this->get_option('logo_type', self::LOGO_TYPE_ALL);
            $this->icon = apply_filters('woocommerce_maib_icon', self::get_logo_icon($this->logo_type));

            $this->debug = 'yes' === $this->get_option('debug', 'no');
            self::$log_enabled = $this->debug;

            $this->transaction_type = $this->get_option('transaction_type', self::TRANSACTION_TYPE_CHARGE);
            $this->order_template = $this->get_option('order_template', self::ORDER_TEMPLATE);

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

            delete_transient($this->transient_token_key);
            delete_transient($this->transient_refresh_key);

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
                    'title' => __('Enable/Disable', 'wc-maib') ,
                    'type' => 'checkbox',
                    'label' => __('Enable Maib Payment Gateway', 'wc-maib') ,
                    'default' => 'yes'
                ) ,
                'title' => array(
                    'title' => __('Title', 'wc-maib') ,
                    'type' => 'text',
                    'description' => __('Payment method title that the customer will see during checkout.', 'wc-maib') ,
                    'desc_tip' => true,
                    'default' => __('Pay online', 'wc-maib')
                ) ,
                'description' => array(
                    'title' => __('Description', 'wc-maib') ,
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see during checkout.', 'wc-maib') ,
                    'desc_tip' => true,
                    'default' => __('Visa / Mastercard / Apple Pay / Google Pay', 'wc-maib')
                ) ,
                /*'logo_type' => array(
                'title'       => __('Logo', 'wc-maib'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Payment method logo image that the customer will see during checkout.', 'wc-maib'),
                'desc_tip'    => true,
                'default'     => self::LOGO_TYPE_ALL,
                'options'     => array(
                self::LOGO_TYPE_ALL    => __('Visa / Mastercard / Apple Pay / Google Pay', 'wc-maib'),
                self::LOGO_TYPE_LIBER => __('maibliber / Visa / Mastercard / Apple Pay / Google Pay', 'wc-maib')
                )
                ),*/

                'debug' => array(
                    'title' => __('Debug mode', 'wc-maib') ,
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'wc-maib') ,
                    'default' => 'yes',
                    'description' => sprintf('<a href="%2$s">%1$s</a>', __('View logs', 'wc-maib') , self::get_logs_url()) ,
                    'desc_tip' => __('Save debug messages to the WooCommerce System Status logs. Note: this may log personal information. Use this for debugging purposes only and delete the logs when finished.', 'wc-maib')
                ) ,

                'transaction_type' => array(
                    'title' => __('Transaction type', 'wc-maib') ,
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Direct payment - if the transaction is successful the amount is withdrawn from the customer account. 
					Two-step payment - if the transaction is successful the amount is just on hold on the customer account, to withdraw the amount use the Order action: Complete Two-Step Payment.', 'wc-maib') ,
                    'desc_tip' => true,
                    'default' => self::TRANSACTION_TYPE_CHARGE,
                    'options' => array(
                        self::TRANSACTION_TYPE_CHARGE => __('Direct Payment', 'wc-maib') ,
                        self::TRANSACTION_TYPE_AUTHORIZATION => __('Two-Step Payment', 'wc-maib')
                    )
                ) ,

                'order_template' => array(
                    'title' => __('Order description', 'wc-maib') ,
                    'type' => 'text',
                    'description' => __('Format: <code>%1$s</code> - Order ID, <code>%2$s</code> - Order items summary', 'wc-maib') ,
                    'desc_tip' => __('Order description that the customer will see on the bank payment page.', 'wc-maib') ,
                    'default' => self::ORDER_TEMPLATE
                ) ,

                'connection_settings' => array(
                    'title' => __('Connection Settings - <a href="https://maibmerchants.md" target="_blank">maibmerchants.md</a>', 'wc-maib') ,
                    'type' => 'title'
                ) ,

                'maib_project_id' => array(
                    'title' => __('Project ID <span class="required">*</span>', 'wc-maib') ,
                    'type' => 'text',
                    'description' => __('It is available after registration.', 'wc-maib') ,
                    'default' => ''
                ) ,

                'maib_project_secret' => array(
                    'title' => __('Project Secret <span class="required">*</span>', 'wc-maib') ,
                    'type' => 'password',
                    'description' => __('It is available after Project activation.', 'wc-maib') ,
                    'default' => ''
                ) ,

                'maib_signature_key' => array(
                    'title' => __('Signature Key <span class="required">*</span>', 'wc-maib') ,
                    'type' => 'password',
                    'description' => __('It is available after Project activation.', 'wc-maib') ,
                    'default' => ''
                ) ,

                'status_settings' => array(
                    'title' => __('Order status', 'wc-maib') ,
                    'type' => 'title'
                ) ,

                'completed_order_status' => array(
                    'title' => __('Payment completed', 'wc-maib') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('The completed order status after successful payment. By default: Processing.', 'wc-maib') ,
                    'desc_tip' => true
                ) ,

                'hold_order_status' => array(
                    'title' => __('Two-Step Payment authorized', 'wc-maib') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('Order status when payment on hold. By default: On hold.', 'wc-maib') ,
                    'desc_tip' => true
                ) ,

                'failed_order_status' => array(
                    'title' => __('Payment failed', 'wc-maib') ,
                    'type' => 'select',
                    'options' => $this->getPaymentOrderStatuses() ,
                    'default' => 'none',
                    'description' => __('Order status when payment failed. By default: Failed.', 'wc-maib') ,
                    'desc_tip' => true
                ) ,

                'payment_notification' => array(
                    'title' => __('Project Settings', 'wc-maib') ,
                    'description' => __('Add these links to the Project settings in maibmerchants.md', 'wc-maib') ,
                    'type' => 'title'
                ) ,

                'maib_ok_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Ok URL', 'wc-maib') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_ok))) ,
                    'type' => 'title'
                ) ,

                'maib_fail_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Fail URL', 'wc-maib') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_fail))) ,
                    'type' => 'title'
                ) ,

                'maib_callback_url' => array(
                    'description' => sprintf('<b>%1$s:</b> <code>%2$s</code>', __('Callback URL', 'wc-maib') , esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_callback))) ,
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
                $product_price = $product->get_price();
                $product_quantity = $item->get_quantity();

                $product_items[] = array(
                    'id' => $product_id,
                    'name' => substr($product_name, 0, 128) ,
                    'price' => (float)number_format($product_price, 2, '.', '') ,
                    'quantity' => $product_quantity,
                );
            }

            $params = [
            'amount' => (float) number_format($order->get_total(), 2, '.', ''),
            'currency' => $order->get_currency(),
            'clientIp' => self::get_client_ip(),
            'language' => self::get_language(),
            'description' => substr($this->get_order_description($order), 0, 124),
            'orderId' => strval($order->get_id()),
            'clientName' => $client_name,
            'email' => $order->get_billing_email(),
            'phone' => substr($order->get_billing_phone(), 0, 40),
            'delivery' => (float) number_format($order->get_shipping_total(), 2, '.', ''),
			'okUrl' => esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_ok)),
			'failUrl' => esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_return_fail)),
            'callbackUrl'  => esc_url(sprintf('%s/wc-api/%s', get_bloginfo('url') , $this->route_callback)),
            'items' => $product_items,
             ];

            if (empty($this->maib_project_id) || empty($this->maib_project_secret) || empty($this->maib_signature_key))
            {
                $this->log('One or more of the required fields is empty in Maib Payment Gateway settings.');
                wc_add_notice(__('One or more of the required fields (Project ID, Project Secret, Signature Key) is empty in Maib Payment Gateway settings.', 'wc-maib') , 'error');
                return;
            }

            if (!in_array($params['currency'], self::SUPPORTED_CURRENCIES))
            {
                $this->log(sprintf('Unsupported currency %s for order %d', $params['currency'], $order_id) , 'error');
                wc_add_notice(__('This currency is not supported by Maib Payment Gateway. Please choose a different currency (MDL / EUR / USD).', 'wc-maib') , 'error');
                return;
            }

            $this->log(sprintf('Order params for send to maib API: %s, order_id: %d', wp_json_encode($params, JSON_PRETTY_PRINT) , $order_id) , 'info');

            try
            {
                switch ($this->transaction_type)
                {
                    case self::TRANSACTION_TYPE_CHARGE:
                        // Initiate Direct Payment
                        $response = MaibApiRequest::create()->pay($params, $this->get_access_token());
                        $this->log(sprintf('Response from pay endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
                    break;

                    case self::TRANSACTION_TYPE_AUTHORIZATION:
                        // Initiate Two-Step Payment
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
                wc_add_notice(__('Payment initiation failed via maib gateway, please try again later.', 'wc-maib') , 'error');
                return;
            }

            update_post_meta($order_id, '_transaction_id', $response->payId);
            self::set_post_meta($order_id, self::MOD_TRANSACTION_TYPE, $this->transaction_type);

            $order->add_order_note('maib Payment ID: <br>' . $response->payId);
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
                $this->log('Refund not possible, transaction id missing.', 'error');
                return new \WP_Error('error', __('Refund not possible, payment id missing.', 'wc-maib'));
            }

            $this->log(sprintf('Start refund, Order id: %s / Refund amount: %s', $order->get_id() , $amount) , 'info');

            $params = ['payId' => strval($order->get_transaction_id()) , 'refundAmount' => (float)number_format($amount, 2, '.', '') , ];

            // Initiate Refund
            try
            {
                $response = MaibApiRequest::create()->refund($params, $this->get_access_token());
                $this->log(sprintf('Response from refund endpoint: %s, order_id: %d', wp_json_encode($response, JSON_PRETTY_PRINT) , $order_id) , 'info');
            }
            catch(Exception $ex)
            {
                $this->log($ex, 'error');
            }

            if (false === $response || !isset($response->status))
            {
                return new \WP_Error('error', __('Refund failed! Please see Maib Payment Gateway logs.', 'wc-maib'));
            }

            if ($response->status === 'REVERSED')
            {
                return new \WP_Error('error', __('Payment already refunded!', 'wc-maib'));
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
                return new \WP_Error('error', __('Complete Two-Step payment not possible, payment id missing.', 'wc-maib'));
            }

            $this->log(sprintf('Start complete two-step payment, Order id: %s', $order->get_id()) , 'info');

            $params = ['payId' => strval($order->get_transaction_id()) , ];

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
                return new \WP_Error('error', __('Complete Two-Step payment failed! Please see Maib Payment Gateway logs.', 'wc-maib'));
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
            $access_token = get_transient($this->transient_token_key);
            $refresh_token = get_transient($this->transient_refresh_key);

            if (false === $access_token) {
                if (false === $refresh_token) {
                    $this->log('Request to maib API: Get access token with Project ID / Secret', 'info');
                    $response = $this->generateAccessToken($this->maib_project_id, $this->maib_project_secret);
                } else {
                    $this->log('Request to maib API: Get access token with refresh token', 'info');
                    $response = $this->generateAccessToken($refresh_token);
                }

                if ($response && isset($response->accessToken, $response->expiresIn)) {
                    set_transient($this->transient_token_key, $response->accessToken, $response->expiresIn);
                    set_transient($this->transient_refresh_key, $response->refreshToken, $response->refreshExpiresIn);
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
            $statuses = ['default' => __('Default status', 'wc-maib') ];
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
            if ($_SERVER['REQUEST_METHOD'] === 'GET')
            {
                $message = sprintf(__('This Callback URL works and should not be called directly.', 'wc-maib') , $this->method_title);
                wc_add_notice($message, 'notice');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
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
			
			if ($order->has_status('pending'))
            {
            if ($status === 'OK')
            {
                switch ($this->transaction_type)
                {
                    case self::TRANSACTION_TYPE_CHARGE:
                        $this->payment_complete($order, $pay_id);
                    break;

                    case self::TRANSACTION_TYPE_AUTHORIZATION:
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
            $order_id = isset($_GET['orderId']) ? (int)$_GET['orderId'] : false;
            $pay_id = isset($_GET['payId']) ? $_GET['payId'] : false;
            $order = wc_get_order($order_id);

            if (!$order_id || !$pay_id)
            {
                $this->log('Fail URL - Order ID or Pay ID not found in redirect url.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'wc-maib') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }
            if (!$order)
            {
                $this->log('Fail URL - Order not found.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'wc-maib') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }
			
            $message = sprintf(__('Order #%1$s payment failed via %2$s. %3$s', 'wc-maib') , $order_id, self::MOD_TITLE, $response->statusMessage);
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
            $order_id = isset($_GET['orderId']) ? (int)$_GET['orderId'] : false;
            $pay_id = isset($_GET['payId']) ? $_GET['payId'] : false;
            $order = wc_get_order($order_id);

            if (!$order_id || !$pay_id)
            {
                $this->log('Ok URL - Order ID or Pay ID not found in redirect url.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'wc-maib') , 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit();
            }

            if (!$order)
            {
                $this->log('Ok URL - Order not found in woocommerce Orders.', 'error');
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'wc-maib') , 'error');
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
                wc_add_notice(__('Something went wrong on redirect to website! Please contact us.', 'wc-maib') , 'error');
                wp_safe_redirect($order->get_checkout_payment_url());
                exit();
            }

            if ($response && $response->status === 'OK')
            {

                switch ($this->transaction_type)
                {
                    case self::TRANSACTION_TYPE_CHARGE:
                        $this->payment_complete($order, $pay_id);
                    break;

                    case self::TRANSACTION_TYPE_AUTHORIZATION:
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
                $message = sprintf(__('Order #%1$s payment failed via %2$s. %3$s', 'wc-maib') , $order_id, self::MOD_TITLE, $response->statusMessage);
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
                $order_note = sprintf(__('Payment (%1$s) successful.', 'wc-maib') , $pay_id);
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
                //$message = sprintf(__('Payment #%1$s paid successfully via %2$s.', 'wc-maib'), $pay_id, $this->method_title);
                //wc_add_notice($message, 'success');
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
                $order_note = sprintf(__('Payment (%1$s) on hold.', 'wc-maib') , $pay_id);
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
            $order_note = sprintf(__('Payment (%1$s) failed.', 'wc-maib') , $pay_id);
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

        protected static function get_logo_icon($logo_type)
        {
            switch ($logo_type)
            {
                case self::LOGO_TYPE_ALL:
                    return MAIB_GATEWAY_PLUGIN_URL . 'assets/img/maib_logo.svg';
                break;
                case self::LOGO_TYPE_LIBER:
                    return MAIB_GATEWAY_PLUGIN_URL . 'assets/img/paymentsystems.png';
                break;
                case self::LOGO_TYPE_NONE:
                    return '';
                break;
            }
            return '';
        }

        protected static function price_format($price)
        {
            $decimals = 2;
            return number_format($price, $decimals, '.', '');
        }

        protected static function get_language()
        {
            $lang = get_locale();
            return substr($lang, 0, 2);
        }

        protected static function get_client_ip()
        {
            return WC_Geolocation::get_ip_address();
        }

        protected static function get_logs_url()
        {
            return add_query_arg(array(
                'page' => 'wc-status',
                'tab' => 'logs',
                //'log_file' => ''
                
            ) , admin_url('admin.php'));
        }

        public static function get_settings_url()
        {
            return add_query_arg(array(
                'page' => 'wc-settings',
                'tab' => 'checkout',
                'section' => self::MOD_ID
            ) , admin_url('admin.php'));
        }

        protected static function get_order_transaction_type($order_id)
        {
            $transaction_type = get_post_meta($order_id, self::MOD_TRANSACTION_TYPE, true);
            return $transaction_type;
        }

        protected static function set_post_meta($post_id, $meta_key, $meta_value)
        {
            if (!add_post_meta($post_id, $meta_key, $meta_value, true))
            {
                update_post_meta($post_id, $meta_key, $meta_value);
            }
        }

        protected function get_order_description($order)
        {
            $description = sprintf(__($this->order_template, 'wc-maib') , $order->get_id() , self::get_order_items_summary($order));

            return apply_filters(self::MOD_ID . '_order_description', $description, $order);
        }

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
        public static function plugin_links($links)
        {
            $plugin_links = array(
                sprintf('<a href="%1$s">%2$s</a>', esc_url(self::get_settings_url()) , __('Settings', 'wc-maib'))
            );

            return array_merge($plugin_links, $links);
        }

        static function order_actions($actions)
        {
            global $theorder;
            if ($theorder->get_payment_method() !== self::MOD_ID)
            {
                return $actions;
            }

            $transaction_type = get_post_meta($theorder->get_id() , self::MOD_TRANSACTION_TYPE, true);
            if ($transaction_type === self::TRANSACTION_TYPE_AUTHORIZATION)
            {
                $actions['maib_complete_transaction'] = sprintf(__('Complete Two-Step Payment', 'wc-maib') , self::MOD_TITLE);
            }

            return $actions;
        }

        static function action_complete_transaction($order)
        {
            $order_id = $order->get_id();

            $plugin = new self();
            return $plugin->complete_transaction($order_id, $order);
        }

        #endregion
        #region WooCommerce
        public static function add_gateway($methods)
        {
            $methods[] = self::class;
            return $methods;
        }

        public static function is_wc_active()
        {
            return class_exists('WooCommerce');
        }
        #endregion
        

        
    }

    if (!WC_Maib::is_wc_active()) return;

    //Add gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', array(
        WC_Maib::class ,
        'add_gateway'
    ));

    add_action('wp_enqueue_scripts', 'enqueue_payment_gateway_styles');
    function enqueue_payment_gateway_styles()
    {
        // Enqueue the custom CSS file
        wp_enqueue_style('payment-gateway-styles', MAIB_GATEWAY_PLUGIN_URL . 'assets/css/style.css');
    }

    #region Admin init
    if (is_admin())
    {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__) , array(
            WC_Maib::class ,
            'plugin_links'
        ));

        //Add WooCommerce order actions
        add_filter('woocommerce_order_actions', array(
            WC_Maib::class ,
            'order_actions'
        ));
        add_action('woocommerce_order_action_maib_complete_transaction', array(
            WC_Maib::class ,
            'action_complete_transaction'
        ));

    }
    #endregion
    
}

#region Register activation hooks
function woocommerce_maib_activation()
{
    woocommerce_maib_init();

    if (!class_exists('WC_Maib')) die('WooCommerce is required for this plugin to work!');

}

register_activation_hook(__FILE__, 'woocommerce_maib_activation');
#endregion
?>
