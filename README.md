[![N|Solid](https://www.maib.md/images/logo.svg)](https://www.maib.md)
#  Maib Payment Gateway Plugin for WooCommerce
Accept Visa / Mastercard / Apple Pay / Google Pay on your store with the Maib Payment Gateway Plugin for WooCommerce

## Description
You can familiarize yourself with the integration steps and website requirements [here](https://docs.maibmerchants.md/en/integration-steps-and-requirements).

To test the integration, you will need access to a Test Project (Project ID / Project Secret / Signature Key). For this, please submit a request to the email address: ecom@maib.md.

To process real payments, you must have the e-commerce contract signed and complete at least one successful transaction using the Test Project data and test card details.

After signing the contract, you will receive access to the maibmerchants platform and be able to activate the Production Project.

### Steps to activate the Production Project
1. Completed Profile
2. Validated Profile
3. E-commerce contract 

## Functional
**Two types of payments** (depending on your Project settings):

  *- Direct payments* - if the transaction is successful the amount is withdrawn from the customer's account.

  *- Two-step payments* - if the transaction is successful, the amount is only blocked on the customer's account (authorized transaction), in order to withdraw the amount, you will need to complete the transaction from the created order using the action _Finish two-step payment_.

**Three currencies**: MDL / USD / EUR (depending on your Project settings).

**Payment refund** - through the standard WooCommerce refund function from the created order. The amount returned may be less than or equal to the transaction amount. The return action is possible only once for each successful transaction.

## Requirements
- Registration on the maibmerchants.md platform
- WordPress ≥ v. 4.8
- WooCommerce ≥ v. 3.3
- PHP ≥ v. 7.2 (with _curl_ and _json_ extensions enabled)

## Installation
### From the WP admin dashboard
1. Go to **Plugins > Add new**.
2. Search for the _Maib Payment Gateway for WooCommerce_ plugin.
3. Click _Install Now_ and wait until the plugin is installed successfully.
4. Click _Activate Now_ to activate the plugin.

### Manual
1. Download the plugin from the WordPress repository.
2. Unzip the file.
3. Upload the plugin folder (eg via FTP) to the _/wp-content/plugins/_ directory.
4. Go to the **Plugins** menu and activate the plugin.

## Settings
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

## Troubleshooting
Enable debug mode in the plugin settings and access the log file.

If you require further assistance, please don't hesitate to contact the **maib** ecommerce support team by sending an email to ecom@maib.md. 

In your email, make sure to include the following information:
- Merchant name
- Project ID
- Date and time of the transaction with errors
- Errors from log file
