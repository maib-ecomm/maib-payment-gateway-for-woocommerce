<?php
namespace MaibEcomm\MaibSdk;

use RuntimeException;

class PaymentException extends RuntimeException {}

// Factory class responsible for creating new instances of the MaibAuth class.
class MaibApiRequest
{
    /**
     * Creates a new instance of MaibApi.
     *
     * @return MaibApi
     */
    public static function create()
    {
        // Create a new instance of the MaibSdk class and pass it to the MaibAuth constructor.
        $httpClient = new MaibSdk();
        return new MaibApi($httpClient);
    }
}

class MaibApi
{
    private $httpClient;
    
    /**
     * Constructs a new MaibApi instance.
     *
     * @param MaibSdk $httpClient The HTTP client for sending requests.
     */
    public function __construct(MaibSdk $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Sends a request to the pay endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function pay($data, $token)
    {
        $requiredParams = ['amount', 'currency', 'clientIp'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::DIRECT_PAY, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the hold endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function hold($data, $token)
    {
        $requiredParams = ['amount', 'currency', 'clientIp'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::HOLD, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the complete endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function complete($data, $token)
    {
        $requiredParams = ['payId'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::COMPLETE, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the refund endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function refund($data, $token)
    {
        $requiredParams = ['payId'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::REFUND, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the pay-info endpoint.
     *
     * @param string $id The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function payInfo($id, $token)
    {
        try
        {
            $this->validateIdParam($id);
            $this->validateAccessToken($token);
            return $this->sendRequestGet(MaibSdk::PAY_INFO, $id, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the delete-card endpoint.
     *
     * @param string $id The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function deleteCard($id, $token)
    {
        try
        {
            $this->validateIdParam($id);
            $this->validateAccessToken($token);
            return $this->sendRequestDelete(MaibSdk::DELETE_CARD, $id, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the savecard-recurring endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function saveRecurring($data, $token)
    {
        $requiredParams = ['billerExpiry', 'currency', 'clientIp'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::SAVE_REC, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the execute-recurring endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function executeRecurring($data, $token)
    {
        $requiredParams = ['billerId', 'amount', 'currency'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::EXE_REC, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the savecard-oneclick endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function saveOneclick($data, $token)
    {
        $requiredParams = ['billerExpiry', 'currency', 'clientIp'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::SAVE_ONECLICK, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a request to the execute-oneclick endpoint.
     *
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     */
    public function executeOneclick($data, $token)
    {
        $requiredParams = ['billerId', 'amount', 'currency', 'clientIp'];
        try
        {
            $this->validatePayParams($data, $requiredParams);
            $this->validateAccessToken($token);
            return $this->sendRequestPost(MaibSdk::EXE_ONECLICK, $data, $token);
        }
        catch(PaymentException $e)
        {
            error_log('Invalid request: ' . $e->getMessage());
            throw new PaymentException('Invalid request: ' . $e->getMessage());
        }
    }

    /**
     * Sends a POST request to the specified endpoint.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param array $data The parameters for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     * @return mixed The response from the API.
     */
    private function sendRequestPost($endpoint, $data, $token)
    {
        try
        {
            $response = $this
                ->httpClient
                ->post($endpoint, $data, $token);
        }
        catch(HttpException $e)
        {
            throw new PaymentException("HTTP error while sending POST request to endpoint $endpoint: {$e->getMessage() }");
        }
        return $this->handleResponse($response, $endpoint);
    }
    
    /**
     * Sends a GET request to the specified endpoint.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param string $id The ID for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     * @return mixed The response from the API.
     */
    private function sendRequestGet($endpoint, $id, $token)
    {
        try
        {
            $response = $this
                ->httpClient
                ->get($endpoint, $id, $token);
        }
        catch(HttpException $e)
        {
            throw new PaymentException("HTTP error while sending GET request to endpoint $endpoint: {$e->getMessage() }");
        }
        return $this->handleResponse($response, $endpoint);
    }

    /**
     * Sends a DELETE request to the specified endpoint.
     *
     * @param string $endpoint The endpoint to send the request to.
     * @param string $id The ID for the request.
     * @param string $token The authentication token.
     * @throws PaymentException if the request fails.
     * @return mixed The response from the API.
     */
    private function sendRequestDelete($endpoint, $id, $token)
    {
        try
        {
            $response = $this
                ->httpClient
                ->delete($endpoint, $id, $token);
        }
        catch(HttpException $e)
        {
            throw new PaymentException("HTTP error while sending DELETE request to endpoint $endpoint: {$e->getMessage() }");
        }
        return $this->handleResponse($response, $endpoint);
    }
    
    /**
     * Handles errors returned by the API.
     *
     * @param object $response The API response object.
     * @param string $endpoint The endpoint name.
     * @return mixed The result extracted from the response.
     * @throws RuntimeException If the API returns an error response or an invalid response.
     */
    private function handleResponse($response, $endpoint)
    {
        if (isset($response->ok) && $response->ok)
        {
            if (isset($response->result))
            {
                return $response->result;
            }
            else
            {
                throw new PaymentException("Invalid response received from server for endpoint $endpoint: missing 'result' field");
            }
        }
        else
        {
            if (isset($response->errors))
            {
                $error = $response->errors[0];
                throw new PaymentException("Error sending request to endpoint $endpoint: {$error->errorMessage} ({$error->errorCode})");
            }
            else
            {
                throw new PaymentException("Invalid response received from server for endpoint $endpoint: missing 'ok' and 'errors' fields");
            }
        }
    }

    /**
     * Validates the access token.
     *
     * @param string $token The access token to validate.
     * @throws PaymentException If the access token is not valid.
     */
    public function validateAccessToken($token)
    {
        if (!is_string($token) || empty($token))
        {
            throw new PaymentException("Access token is not valid. It should be a non-empty string.");
        }
    }

    /**
     * Validates the ID parameter.
     *
     * @param mixed $id The ID parameter to validate.
     * @throws PaymentException If the ID is missing or not valid.
     */ 
    private function validateIdParam($id)
    {
        if (!isset($id))
        {
            throw new PaymentException("Missing ID!");
        }
        if (!is_string($id) || strlen($id) !== 36)
        {
            throw new PaymentException("Invalid 'ID' parameter. Should be string of 36 characters.");
        }
    }

    /**
     * Validates the parameters.
     *
     * @param array $data Request data containing the parameters to validate.
     * @param array $requiredParams The list of required parameters.
     * @throws PaymentException If any of the parameters are missing or not valid.
     * @return bool Returns true if all parameters are valid.
     */
    private function validatePayParams($data, $requiredParams)
    {
        // Check that all required parameters are present
        foreach ($requiredParams as $param)
        {
            if (!isset($data[$param]))
            {
                throw new PaymentException("Missing required parameter: '$param'");
            }
        }
        // Check that parameters have the expected types and formats
        if (isset($data['billerId']) && (!is_string($data['billerId']) || strlen($data['billerId']) !== 36))
        {
            throw new PaymentException("Invalid 'billerId' parameter. Should be a string of 36 characters.");
        }
        if (isset($data['billerExpiry']) && (!is_string($data['billerExpiry']) || strlen($data['billerExpiry']) !== 4))
        {
            throw new PaymentException("Invalid 'billerExpiry' parameter. Should be a string of 4 characters.");
        }
        if (isset($data['payId']) && (!is_string($data['payId']) || strlen($data['payId']) > 36))
        {
            throw new PaymentException("Invalid 'payId' parameter. Should be a string of 36 characters.");
        }
        if (isset($data['confirmAmount']) && (!is_numeric($data['confirmAmount']) || $data['confirmAmount'] < 0 || is_string($data['confirmAmount'])))
        {
            throw new PaymentException("Invalid 'confirmAmount' parameter. Should be a numeric value > 0.");
        }
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] < 1 || is_string($data['amount'])))
        {
            throw new PaymentException("Invalid 'amount' parameter. Should be a numeric value >= 1.");
        }
        if (isset($data['refundAmount']) && (!is_numeric($data['refundAmount']) || $data['refundAmount'] < 0 || is_string($data['refundAmount'])))
        {
            throw new PaymentException("Invalid 'refundAmount' parameter. Should be a numeric value > 0.");
        }
        if (isset($data['currency']) && (!is_string($data['currency']) || !in_array($data['currency'], ['MDL', 'EUR', 'USD'])))
        {
            throw new PaymentException("Invalid 'currency' parameter. Currency should be one of 'MDL', 'EUR', or 'USD'.");
        }
        if (isset($data['clientIp']) && (!is_string($data['clientIp']) || !filter_var($data['clientIp'], FILTER_VALIDATE_IP)))
        {
            throw new PaymentException("Invalid 'clientIp' parameter. Please provide a valid IP address.");
        }

        if (isset($data['language']) && (!is_string($data['language']) || strlen($data['language']) !== 2))
        {
            throw new PaymentException("Invalid 'language' parameter. Should be a string of 2 characters.");
        }

        if (isset($data['description']) && (!is_string($data['description']) || strlen($data['description']) > 124))
        {
            throw new PaymentException("Invalid 'description' parameter. Should be a string and not exceed 124 characters.");
        }

        if (isset($data['clientName']) && (!is_string($data['clientName']) || strlen($data['clientName']) > 128))
        {
            throw new PaymentException("Invalid 'clientName' parameter. Should be a string and not exceed 128 characters.");
        }
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        {
            throw new PaymentException("Invalid 'email' parameter. Please provide a valid email address.");
        }
        if (isset($data['phone']) && (!is_string($data['phone']) || strlen($data['phone']) > 40))
        {
            throw new PaymentException("Invalid 'phone' parameter. Phone number should not exceed 40 characters.");
        }
        if (isset($data['orderId']) && (!is_string($data['orderId']) || strlen($data['orderId']) > 36))
        {
            throw new PaymentException("Invalid 'orderId' parameter. Should be a string and not exceed 36 characters.");
        }
        if (isset($data['delivery']) && (!is_numeric($data['delivery']) || $data['delivery'] < 0 || is_string($data['delivery'])))
        {
            throw new PaymentException("Invalid 'delivery' parameter. Delivery fee should be a numeric value >= 0.");
        }
        if (isset($data['items']) && (!is_array($data['items']) || empty($data['items'])))
        {
            throw new PaymentException("Invalid 'items' parameter. Items should be a non-empty array.");
        }
        if (isset($data['items']))
        {
            foreach ($data['items'] as $item)
            {
                if (isset($item['id']) && (!is_string($item['id']) || strlen($item['id']) > 36))
                {
                    throw new PaymentException("Invalid 'id' parameter in the 'items' array. Should be a string and not exceed 36 characters.");
                }
                if (isset($item['name']) && (!is_string($item['name']) || strlen($item['name']) > 128))
                {
                    throw new PaymentException("Invalid 'name' parameter in the 'items' array. Should be a string and not exceed 128 characters.");
                }
                if ((isset($item['price'])) && (!is_numeric($item['price']) || $item['price'] < 0 || is_string($item['price'])))
                {
                    throw new PaymentException("Invalid 'price' parameter in the 'items' array. Item price should be a numeric value >= 0.");
                }
                if ((isset($item['quantity'])) && (!is_numeric($item['quantity']) || $item['quantity'] < 0 || is_string($item['quantity'])))
                {
                    throw new PaymentException("Invalid 'quantity' parameter in the 'items' array. Item quantity should be a numeric value >= 0.");
                }
            }
        }
        if (isset($item['callbackUrl']) && (!is_string($data['callbackUrl']) || !filter_var($data['callbackUrl'], FILTER_VALIDATE_URL)))
        {
            throw new PaymentException('Invalid callbackUrl parameter! Should be a string url.');
        }
        if (isset($item['okUrl']) && (!is_string($data['okUrl']) || !filter_var($data['okUrl'], FILTER_VALIDATE_URL)))
        {
            throw new PaymentException('Invalid okUrl parameter. Should be a string url.');
        }
        if (isset($item['failUrl']) && (!is_string($data['failUrl']) || !filter_var($data['failUrl'], FILTER_VALIDATE_URL)))
        {
            throw new PaymentException('Invalid failUrl parameter. Should be a string url.');
        }

        return true;
    }
}
