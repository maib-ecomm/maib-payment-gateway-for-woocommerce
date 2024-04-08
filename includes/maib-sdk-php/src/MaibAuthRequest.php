<?php
namespace MaibEcomm\MaibSdk;

use RuntimeException;

class TokenException extends RuntimeException {}

// Factory class responsible for creating new instances of the MaibAuth class.
class MaibAuthRequest
{
    /**
     * Creates an instance of the MaibAuth class.
     *
     * @return MaibAuth The created instance.
     */
    public static function create()
    {
        // Create a new instance of the MaibSdk class and pass it to the MaibAuth constructor.
        $httpClient = new MaibSdk();
        return new MaibAuth($httpClient);
    }
}

class MaibAuth
{
    private $httpClient;
    
    /**
     * Constructs a new MaibAuth instance.
     *
     * @param MaibSdk $httpClient The HTTP client for sending requests.
     */
    public function __construct(MaibSdk $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Generates a new access token using the given project ID and secret or refresh token.
     *
     * @param string|null $ProjectIdOrRefresh The project ID or refresh token to use for generating the token.
     * @param string|null $projectSecret The project secret to use for generating the token.
     * @return array The response body as an associative array.
     * @throws RuntimeException If the API returns an error response.
     */
    public function generateToken($ProjectIdOrRefresh = null, $projectSecret = null)
    {
        if ($ProjectIdOrRefresh === null && $projectSecret === null)
        {
            throw new TokenException("Project ID and Project Secret or Refresh Token must be provided!");
        }

        $postData = array();

        if ($ProjectIdOrRefresh !== null && $projectSecret !== null)
        {
            if (!is_string($ProjectIdOrRefresh) || !is_string($projectSecret))
            {
                throw new TokenException("Project ID and Project Secret must be strings!");
            }

            $postData['projectId'] = $ProjectIdOrRefresh;
            $postData['projectSecret'] = $projectSecret;
        }
        elseif ($ProjectIdOrRefresh !== null && $projectSecret === null)
        {
            if (!is_string($ProjectIdOrRefresh))
            {
                throw new TokenException("Refresh Token must be a string!");
            }

            $postData['refreshToken'] = $ProjectIdOrRefresh;
        }

        try
        {
            $response = $this
                ->httpClient
                ->post(MaibSdk::GET_TOKEN, $postData);
        }
        catch(Exception $e)
        {
            throw new TokenException("HTTP error while sending POST request to endpoint generate-token: {$e->getMessage() }");
        }

        $result = $this->handleResponse($response, MaibSdk::GET_TOKEN);
        return $result;
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
                throw new TokenException("Invalid response received from server for endpoint $endpoint: missing 'result' field");
            }
        }
        else
        {
            if (isset($response->errors))
            {
                $error = $response->errors[0];
                throw new TokenException("Error sending request to endpoint $endpoint: {$error->errorMessage} ({$error->errorCode})");
            }
            else
            {
                throw new TokenException("Invalid response received from server for endpoint $endpoint: missing 'ok' and 'errors' fields");
            }
        }
    }
}
