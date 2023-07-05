# PHP SDK for **maib** ecommerce API
API docs: [https://docs.maibmerchants.md](https://docs.maibmerchants.md)

It is necessary to be registered on [maibmerchants](https://maibmerchants.md).

## Requirements
PHP >= 5.6

The following PHP extensions are required:

 * curl
 * json
## Install
### Composer
Install via Composer:
```
composer require maib-ecomm/maib-sdk-php
```
To use the package, use Composer's autoload:
```
require_once 'vendor/autoload.php';
```
### Manual
Download the latest release and include the *config.php*  file in your project.
```
require_once('/path/to/examples/config.php');
```
## Getting started with Composer
Add SDK classes:
```
<?php 
require_once 'vendor/autoload.php';

use MaibEcomm\MaibSdk\MaibAuthRequest;
use MaibEcomm\MaibSdk\MaibApiRequest;
```
Add Project configurations:
```
define('PROJECT_ID', 'YOUR_PROJECT_ID');
define('PROJECT_SECRET', 'YOUR_PROJECT_SECRET');
define('SIGNATURE_KEY', 'YOUR_SIGNATURE_SECRET');
```
Project Secret and Signature Key are available after Project activation.

**The Signature Key is required to validate the notification signature on the Callback URL ([/examples/callbackUrl.php](https://github.com/maib-ecomm/maib-sdk-php/blob/main/examples/callbackUrl.php)).**
## SDK usage examples
### Get Access Token with Project ID and Project Secret:
```
$auth = MaibAuthRequest::create()->generateToken(PROJECT_ID, PROJECT_SECRET);

// Save received data in your DB
$token = $auth->accessToken;
$tokenExpiresAt = time() + $auth->expiresIn;
$refreshToken = $auth->refreshToken;
$refreshExpiresAt = time() + $auth->refreshExpiresIn;
```
### Get Access Token with Refresh Token:
```
$auth = MaibAuthRequest::create()->generateToken($refreshToken);

// Save received data in your DB
$token = $auth->accessToken;
$tokenExpiresAt = time() + $auth->expiresIn;
$refreshToken = $auth->refreshToken;
$refreshExpiresAt = time() + $auth->refreshExpiresIn;
```
### Direct Payment:
```
// Set up payment required parameters
$data = array(
    'amount' => 10.25,
    'currency' => 'EUR',
    'clientIp' => '135.250.245.121'
);

// Initiate Direct Payment
$pay = MaibApiRequest::create()->pay($data, $token);

// Save payment ID in your DB
$payUrl = $pay->payUrl;
$payId = $pay->payId;

// Redirect Client to the maib checkout page
header("Location: " . $payUrl);
die;
```
Payment status and data you will receive on the Callback URL.

### Two-Step Payment. Payment authorization (hold):
```
// Set up payment required parameters
$data = array(
    'amount' => 10.25,
    'currency' => 'EUR',
    'clientIp' => '135.250.245.121'
);

// Initiate Payment Authorization
$hold = MaibApiRequest::create()->hold($data, $token);

// Save payment ID in your DB
$payUrl = $hold->payUrl;
$payId = $hold->payId;

// Redirect Client to the maib checkout page
header("Location: " . $payUrl);
die;
```
Payment status and data you will receive on the Callback URL.

### Two-Step Payment. Payment capture (complete):
```
// Payment ID is required parameter
$data = array(
   'payId' => 'f16a9006-128a-46bc-8e2a-77a6ee99df75'
);

// Complete 2-Step Payment
$complete = MaibApiRequest::create()->complete($data, $token);

// Display request response
$jsonData = json_encode($complete);
echo $jsonData;

// Receive Payment status and data 
$payId = $complete->payId;
$cardNumber = $complete->cardNumber;
$status = $complete->status;
$statusMessage= $complete->statusMessage;
$confirmAmount = $complete->confirmAmount;
```

### Refund Payment:
```
// Payment ID is required parameter
$data = array(
   'payId' => 'f16a9006-128a-46bc-8e2a-77a6ee99df75'
);

// Initiate Refund Payment
$refund = MaibApiRequest::create()->refund($data, $token);

// Display request response
$jsonData = json_encode($refund);
echo $jsonData;

// Receive Refund status
$payId = $refund->payId;
$status = $refund->status;
$statusMessage= $refund->statusMessage;
$refundAmount = $refund->refundAmount;
```
### Payment Information:
```
// Payment ID
$id = 'f16a9006-128a-46bc-8e2a-77a6ee99df75';

// Initiate Payment Info
$payInfo = MaibApiRequest::create()->payInfo($id, $token);

// Display request response
$jsonData = json_encode($payInfo);
echo $jsonData;

// Receive Payment status and data 
$payId = $payInfo->payId;
$status = $payInfo->status;
$statusMessage = $payInfo->statusMessage;
$amount = $payInfo->amount;
$currency = $payInfo->currency;
$cardNumber = $payInfo->cardNumber;
$rrn = $payInfo->rrn;
$approval = $payInfo->approval;
```
### Recurring Payments. Card Registration:
```
// Set up required parameters
$data = array(
    'email' => 'customer@example.com',
    'billerExpiry' => '1230',
    'currency' => 'EUR',
    'clientIp' => '135.250.245.121'
);

// Initiate Card Registration for Recurring Payments
$saveRecurring = MaibApiRequest::create()->saveRecurring($data, $token);

// Save payId in your system
$payUrl = $saveRecurring->payUrl;
$payId = $saveRecurring->payId;

// Redirect Client to the maib checkout page
header("Location: " . $payUrl);
die;
```
Recurring Payments data (billerId/billerExpiry) you will receive on the Callback URL.

### Recurring Payments. Execute Recurring Payment:
```
// Set up required parameters
$data = array(
    'billerId' => 't78i8006-458a-46bc-9e0a-89a6ee11df68',
    'amount' => 6.25,
    'currency' => 'EUR'
);

// Execute Recurring Payment
$executeRecurring = MaibApiRequest::create()->executeRecurring($data, $token);

// Display request response
$jsonData = json_encode($executeRecurring);
echo $jsonData;

// Save payment status and data in your system
$billerId = $executeRecurring->billerId;
$payId = $executeRecurring->payId;
$orderId = $executeRecurring->orderId;
$status = $executeRecurring->status;
$statusMessage= $executeRecurring->statusMessage;
$amount = $executeRecurring->amount;
$currency = $executeRecurring->currency;
$cardNumber = $executeRecurring->cardNumber;
$rrn = $executeRecurring->rrn;
$approval = $executeRecurring->approval;
```
### One-Click Payments. Card Registration:
```
// Set up required parameters
$data = array(
    'email' => 'customer@example.com',
    'billerExpiry' => '1230',
    'currency' => 'EUR',
    'clientIp' => '135.250.245.121'
);

// Initiate Card Registration for One-Click Payments
$saveOneclick = MaibApiRequest::create()->saveOneclick($data, $token);

// Save payId in your system
$payUrl = $saveOneclick->payUrl;
$payId = $saveOneclick->payId;

// Redirect Client to maib checkout page
header("Location: " . $payUrl);
die;
```
One-Click Payments data (billerId/billerExpiry) you will receive on the Callback URL.

### One-Click Payments. Execute One-Click Payment:
```
// Set up required parameters
$data = array(
    'billerId' => 't78i8006-458a-46bc-9e0a-89a6ee11df68',
    'amount' => 6.25,
    'currency' => 'EUR',
    'clientIp' => '135.250.245.121'
);

// Execute One-Click Payment
$executeOneclick = MaibApiRequest::create()->executeOneclick($data, $token);

// Save payment ID in your DB
$payUrl = $executeOneclick->payUrl;
$payId = $executeOneclick->payId;

// Redirect Client to the maib checkout page for 3D-Secure authentication
header("Location: " . $payUrl);
die;
```
One-Click Payment status and data you will receive on the Callback URL.
