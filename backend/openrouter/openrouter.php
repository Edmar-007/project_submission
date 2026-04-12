<?php
/**
 * Simple OpenRouter API client wrapper
 * - Provides a minimal interface to call OpenRouter endpoints
 * - Reads API key from OPENROUTER_API_KEY environment variable
 * - Uses curl for HTTP requests
 */

class OpenRouterClient
{
    private $apiKey;
    private $baseUrl;

    public function __construct($baseUrl = 'https://api.openrouter.ai') {
        $this->baseUrl = rtrim($baseUrl, '/');
        // API key may be set as OPENROUTER_API_KEY in environment or in config
        $this->apiKey = getenv('OPENROUTER_API_KEY');
    }

    /**
     * Perform a request to an OpenRouter endpoint
     * @param string $endpoint e.g. '/v1/models/list'
     * @param array $data associative array payload
     * @param string $method 'GET'|'POST' etc.
     * @param array $headers optional additional headers
     * @return array|false response decoded as array or false on error
     */
    public function request($endpoint, $data = [], $method = 'POST', $headers = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $method = strtoupper($method);

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $payload = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Basic timeout and error handling
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($err) {
            return [
                'success' => false,
                'error' => $err,
                'code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Return raw response if not JSON or failed to decode
            return [
                'success' => false,
                'response' => $response,
                'code' => $httpCode,
            ];
        }

        // If API returns { success: true, ... } or similar, pass through
        return [
            'success' => true,
            'code' => $httpCode,
            'data' => $decoded,
        ];
    }

    // Convenience helper for model listing (example)
    public function listModels($filters = [])
    {
        return $this->request('/v1/models', $filters, 'GET');
    }

    // Example: run a prompt through a model
    public function runModel($modelId, $input = [])
    {
        return $this->request('/v1/models/' . urlencode($modelId) . '/run', $input, 'POST');
    }
}
?>