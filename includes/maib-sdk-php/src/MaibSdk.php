<?php
/**
 * PHP SDK for maib Ecommerce API
 *
 * @package maib-ecomm/maib-sdk-php
 * @category SDK
 * @author maib
 * @developer Lupu Constantin
 * @license MIT
 */
namespace MaibEcomm\MaibSdk;

use RuntimeException;

class ClientException extends RuntimeException {}

class MaibSdk
{
    // maib ecommerce API base url
    const BASE_URL = "https://api.maibmerchants.md/v1/";

    // maib ecommerce API endpoints
    const GET_TOKEN = "generate-token";
    const DIRECT_PAY = "pay";
    const HOLD = "hold";
    const COMPLETE = "complete";
    const REFUND = "refund";
    const PAY_INFO = "pay-info";
    const SAVE_REC = "savecard-recurring";
    const EXE_REC = "execute-recurring";
    const SAVE_ONECLICK = "savecard-oneclick";
    const EXE_ONECLICK = "execute-oneclick";
    const DELETE_CARD = "delete-card";

    // HTTP request methods
    const HTTP_GET = "GET";
    const HTTP_POST = "POST";
    const HTTP_DELETE = "DELETE";

    private static $instance;
    private $baseUri;

    public function __construct()
    {
        $this->baseUri = MaibSdk::BASE_URL;
    }

    /**
     * Get the instance of MaibSdk (Singleton pattern)
     *
     * @return MaibSdk
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send a POST request
     *
     * @param string $uri
     * @param array $data
     * @param string|null $token
     * @return mixed
     * @throws ClientException
     */
    public function post($uri, array $data = [], $token = null)
    {
        $url = $this->buildUrl($uri);
        $response = $this->sendRequest(self::HTTP_POST, $url, $data, $token);
        return $response;
    }

    /**
     * Send a GET request
     *
     * @param string $uri
     * @param string $id
     * @param string $token
     * @return mixed
     * @throws ClientException
     */
    public function get($uri, $id, $token)
    {
        $url = $this->buildUrl($uri, $id);
        $response = $this->sendRequest(self::HTTP_GET, $url, [], $token);
        return $response;
    }

    /**
     * Send a DELETE request
     *
     * @param string $uri
     * @param string $id
     * @param string $token
     * @return mixed
     * @throws ClientException
     */
    public function delete($uri, $id, $token)
    {
        $url = $this->buildUrl($uri, $id);
        $response = $this->sendRequest(self::HTTP_DELETE, $url, [], $token);
        return $response;
    }

    /**
     * Build the complete URL for the request
     *
     * @param string $uri
     * @param string|null $id
     * @return string
     */
    private function buildUrl($uri, $id = null)
    {
        $url = $this->baseUri . $uri;

        if ($id !== null) {
            $url .= "/" . $id;
        }

        return $url;
    }

    /**
     * Send a request using cURL and handle the response.
     *
     * @param string $method The HTTP method for the request.
     * @param string $url The complete URL for the request.
     * @param array $data The data to be sent with the request.
     * @param string|null $token The authorization token (optional).
     * @return mixed The decoded response from the API.
     * @throws ClientException if an error occurs during the request or if the response has an error status code.
     */
    private function sendRequest($method, $url, array $data = [], $token = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        if ($method === self::HTTP_POST) {
            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $headers = ["Content-Type: application/json"];
        } else {
            $headers = [];
        }

        if ($token !== null) {
            $headers[] = "Authorization: Bearer " . $token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorMessage = "An error occurred: " . curl_error($ch);
            curl_close($ch);
            throw new ClientException($errorMessage);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode >= 400) {
            $errorMessage = $this->getErrorMessage($response, $statusCode);
            throw new ClientException(
                "An error occurred: HTTP " . $statusCode . ": " . $errorMessage
            );
        }

        $decodedResponse = json_decode($response, false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientException(
                "Failed to decode response: " . json_last_error_msg()
            );
        }

        // Debugging statement to log the request and response
        error_log(
            "Request: $method $url " .
                json_encode($data) .
                " Response: $response"
        );

        return $decodedResponse;
    }

    /**
     * Retrieves the error message from the API response.
     *
     * @param string $response The API response.
     * @param int $statusCode The HTTP status code of the response.
     * @return string The error message extracted from the response, or a default message if not found.
     */
    private function getErrorMessage($response, $statusCode)
    {
        $errorMessage = "";
        if ($response) {
            $responseObj = json_decode($response);
            if (isset($responseObj->errors[0]->errorMessage)) {
                $errorMessage = $responseObj->errors[0]->errorMessage;
            } else {
                $errorMessage = "Unknown error details.";
            }
        }
        return $errorMessage;
    }
}
