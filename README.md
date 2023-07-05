#  Maib Payment Gateway for WooCommerce
Accept Visa / Mastercard / Apple Pay / Google Pay on your store with the Maib Payment Gateway for WooCommerce

## Description
To be able to use this plugin you must be registered on the [maibmerchants.md](https://maibmerchants.md) platform.

Immediately after registration, you will be able to make payments in the test environment with the access data from the Test Project.

In order to make real payments you must make at least one successful transaction in the test environment and complete the necessary steps to activate the Production Project.

## Functional
1. **Two types of payments** (depending on your Project settings):

  *Direct payments* - if the transaction is successful the amount is withdrawn from the customer's account.

  *Two-step payments* - if the transaction is successful, the amount is only blocked on the customer's account (authorized transaction), in order to withdraw the amount, you will need to complete the transaction from the created order using the Action: Finish two-step payment.

2. **Three currencies** (depending on your Project settings): MDL / USD / EUR.
3. **Payment refund** - through the standard WooCommerce refund function from the created order (see screenshot). The amount returned may be less than or equal to the transaction amount. The return action is possible only once for each successful transaction.

## Requirements
- Registration on the maibmerchants.md platform
- WordPress ≥ v. 4.8
- WooCommerce ≥ v. 3.3
- PHP ≥ v. 5.6 (with curl and json extensions enabled)

## Installation
### From the WP admin dashboard
1. Go to **Plugins > Add new**.
2. Search for the _Maib Payment Gateway for WooCommerce_ plugin.
3. Click "Install Now" and wait until the plugin is installed successfully.
4. Click "Activate Now" to activate the plugin.

### Manual
1. Download the plugin from the WordPress repository.
2. Unzip the file.
3. Upload the plugin folder (eg via FTP) to the /wp-content/plugins/ directory.
4. Go to the Plugins menu and activate the plugin.



