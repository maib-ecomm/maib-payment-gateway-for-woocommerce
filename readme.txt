=== Maib Payment Gateway for WooCommerce ===
Contributors: maib
Tags: woocommerce, moldova, maib, payment, gateway
Requires at least: 4.8
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Maib Payment Gateway for WooCommerce

== Description ==

Accept Visa / Mastercard / Apple Pay / Google Pay on your store with the Maib Payment Gateway for WooCommerce.

This plugin is developed by [maib](https://www.maib.md/en) and is based on [maib e-commerce API](https://docs.maibmerchants.md/en).

You can familiarize yourself with the integration steps and website requirements [here](https://docs.maibmerchants.md/en/integration-steps-and-requirements).

To test the integration, you will need access to a Test Project (Project ID / Project Secret / Signature Key). For this, please submit a request to the email address: ecom@maib.md.

To process real payments, you must have the e-commerce contract signed and complete at least one successful transaction using the Test Project data and test card details.

After signing the contract, you will receive access to the maibmerchants platform and be able to activate the Production Project.

= Features =

* Two types of payments: Direct payments & Two-step payments (depending on your Project settings);
* Three currencies: MDL / USD / EUR (depending on your Project settings);
* Payment refund: Partial or full refund;
* Admin order action: Complete Two-Step Payment;

= Getting Started =

* [Installation Instructions](./installation/)
* [Frequently Asked Questions](./faq/)


== Installation ==

= Installation in dashboard =
1. Go to **Plugins > Add new**.
2. Search for the *Maib Payment Gateway for WooCommerce* plugin.
3. Click *Install Now* and wait until the plugin is installed successfully.
4. Click *Activate Now* to activate the plugin.

= Manual installation =
1. Download the plugin from the WordPress or [GitHub](https://github.com/maib-ecomm/maib-payment-gateway-for-woocommerce) repository.
2. Unzip the downloaded file.
3. If the plugin was downloaded from GitHub, rename the extracted folder to *maib-payment-gateway-for-woocommerce*.
4. Upload the *maib-payment-gateway-for-woocommerce* folder to the */wp-content/plugins/* directory.
5. Go to the **Plugins** menu in your WordPress dashboard and activate the *Maib Payment Gateway for WooCommerce* plugin.

= Settings =
1. **Enable/Disable** Maib Payment Gateway method
2. **Title** - Title of the payment method displayed to the customer on the checkout page.
3. **Description** - Description of the payment method displayed to the customer on the checkout page.
4. **Debug Mode** - Save debug messages to the WooCommerce System Status logs.
5. **Transaction type** - Direct Payment or Two-Step Payments (depending on your Project settings).
6. **Order description** - Description of the order displayed to the customer on the card data entry page.
7. **Project ID** - Project ID from maibmerchants.md
8. **Project Secret** - Project Secret from maibmerchants.md. It is available after project activation.
9. **Signature Key** - Signature Key for validating notifications on Callback URL. It is available after project activation.
10. **Order status: Payment completed** -  Order status when payment is successful. By default: Processing.
11. **Order status: Two-Step Payment authorized** - Order status when transaction is successfully authorized. By default: On hold.
12. **Order status: Payment failed** - Order status when payment is failed. By default: Failed.
13. **Project Settings** - Add links for Ok URL / Fail URL / Callback URL in the respective fields of the Project settings in maibmerchants.md.

== Frequently Asked Questions ==

= How can I configure the plugin settings? =

Access *WooCommerce > Settings > Payments > Maib Payment Gateway* to configure the plugin.

= Where can I get the Test Project data? =

You can request access data for API testing (Project ID / Project Secret / Signature Key) at the email address: ecom@maib.md, specifying the website or application for which you want to perform the integration.

= What are the test card data? =

* **Cardholder:** Test Test
* **Card number:** 5102180060101124
* **Exp. date:** 06/28
* **CVV:** 760

= What currencies are supported? =

MDL (Moldovan Leu), EUR (Euro) and USD (United States Dollar).

= What is the difference between payment types? =

* **Direct payments** - if the transaction is successful the amount is withdrawn from the customer's account.

* **Two-step payments** - if the transaction is successful, the amount is only blocked on the customer's account (authorized transaction), in order to withdraw the amount, you will need to complete the transaction from the created order using the action *Finish two-step payment*.

= Troubleshooting =

Enable debug mode in the plugin settings and access the log file.

If you require further assistance, please don't hesitate to contact the maib ecommerce support team by sending an email to ecom@maib.md.

In your email, make sure to include the following information:

* Merchant name
* Project ID
* Date and time of the transaction with errors
* Errors from log file

== Screenshots ==
1. Plugin settings
4. Refund
5. Order actions

== Changelog ==

See project releases on [GitHub](https://github.com/maib-ecomm/maib-payment-gateway-for-woocommerce/releases) for details.

= 1.0.0 =
Initial release
