<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class MaibPaymentGateway_Blocks
 *
 * This class integrates the MAIB payment gateway with WooCommerce blocks.
 */
final class MaibPaymentGateway_Blocks extends AbstractPaymentMethodType {

    private $gateway; // Holds an instance of the MAIB payment gateway class.
    protected $name = 'maib'; // The name identifier for the payment method.

    /**
     * Initializes the payment gateway.
     */
    public function initialize()
    {
        // Get the gateway settings from WooCommerce options.
        $this->settings = get_option( 'woocommerce_maib_settings', [] );
        // Create a new instance of the MAIB payment gateway.
        $this->gateway = new MaibPaymentGateway();
    }

    /**
     * Checks if the payment gateway is active.
     *
     * @return bool
     */
    public function is_active()
    {
        // Return the availability status of the gateway.
        return $this->gateway->is_available();
    }

    /**
     * Registers and returns the script handles for the payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        // Register the script for the MAIB payment gateway integration.
        wp_register_script(
            'maib-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry', // WooCommerce Blocks registry.
                'wc-settings', // WooCommerce settings.
                'wp-element', // WordPress element.
                'wp-html-entities', // WordPress HTML entities.
                'wp-i18n', // WordPress internationalization.
            ],
            '1.0.0',
            true // Load script in the footer.
        );

        // Set script translations if the function exists.
        if( function_exists( 'wp_set_script_translations' ) )
        {            
            wp_set_script_translations( 'maib-blocks-integration');
        }

        // Return the script handle.
        return [ 'maib-blocks-integration' ];
    }

    /**
     * Returns the payment method data.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        // Return the title and description of the payment method.
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'icon' => $this->gateway->icon,
        ];
    }
}

?>