<?php

namespace Bayarcash\Actions;

trait ManualBankTransfer
{

    /**
     * Creates a manual bank transfer payment
     *
     * @param  array<string, mixed>  $data           Payment and customer details
     * @param  bool                  $allowRedirect  Whether to auto-follow HTTP redirects
     * @return mixed                 Response array or string based on API response format
     *
     * @throws \InvalidArgumentException  When required fields are missing or invalid
     * @throws \Exception                 When API request fails
     */
    public function createManualBankTransfer(array $data, bool $allowRedirect = false)
    {
        $this->validateManualTransferData($data);

        $data['bank_transfer_date'] = $data['bank_transfer_date'] ?? date('Y-m-d');

        $postFields = $this->prepareManualTransferPostFields($data);

        $response = $this->executeManualTransferRequest($postFields, $allowRedirect);

        return $this->processManualTransferResponse($response['body'], $response['http_code'], $allowRedirect);
    }

    /**
     * Updates status of an existing manual bank transfer
     *
     * @param  string  $refNo   Transaction reference number
     * @param  string  $status  New status code
     * @param  string  $amount  Transaction amount
     * @return mixed   API response
     *
     * @throws \Exception  When update fails
     */
    public function updateManualBankTransferStatus(string $refNo, string $status, string $amount)
    {
        $data = [
            'ref_no' => $refNo,
            'status' => $status,
            'amount' => $amount
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->getManualTransferBaseUrl() . '/manual-bank-transfer/update-status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            throw new \Exception('Connection failed: ' . $error);
        }

        return $this->handleApiResponse($response, $httpCode);
    }

    /**
     * Extracts structured data from HTML form response
     *
     * @param  string  $htmlResponse  HTML form from API response
     * @return array<string, string|null>  Extracted form data and metadata
     */
    public function parseManualBankTransferResponse(string $htmlResponse): array
    {
        $data = [];

        if (preg_match('/id="([^"]+)"/', $htmlResponse, $formIdMatch)) {
            $data['form_id'] = $formIdMatch[1];
        }

        if (preg_match('/action="([^"]+)"/', $htmlResponse, $actionMatch)) {
            $data['return_url'] = $actionMatch[1];
        }

        if (preg_match_all('/<input name="([^"]+)" type="hidden" value="([^"]*)">/', $htmlResponse, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $data[$match[1]] = $match[2];
            }
        }

        return $data;
    }

    /**
     * Gets the appropriate API base URL based on environment
     *
     * @return string The base URL for API requests
     */
    protected function getManualTransferBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://console.bayarcash-sandbox.com/api'
            : 'https://console.bayar.cash/api';
    }

    /**
     * Validates the data for manual bank transfer creation
     *
     * @param  array<string, mixed>  $data  Transfer data to validate
     * @return void
     *
     * @throws \InvalidArgumentException  When data validation fails
     */
    private function validateManualTransferData(array $data): void
    {
        $requiredFields = [
            'portal_key', 'buyer_name', 'buyer_email',
            'order_amount', 'order_no', 'payment_gateway',
            'merchant_bank_name', 'merchant_bank_account', 'merchant_bank_account_holder',
            'bank_transfer_type', 'bank_transfer_notes',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        if (!isset($data['payment_gateway']) || $data['payment_gateway'] != 2) {
            throw new \InvalidArgumentException("Invalid payment gateway. Value must be 2 for manual bank transfers.");
        }

        if (isset($data['proof_of_payment']) && !file_exists($data['proof_of_payment'])) {
            throw new \InvalidArgumentException("Proof of payment file does not exist");
        }
    }

    /**
     * Prepares the post fields for the API request
     *
     * @param  array<string, mixed>  $data  Transfer data
     * @return array<string, mixed>  Prepared post fields
     */
    private function prepareManualTransferPostFields(array $data): array
    {
        $postFields = [];

        foreach ($data as $key => $value) {
            if ($key !== 'proof_of_payment') {
                $postFields[$key] = $value;
            }
        }

        if (isset($data['proof_of_payment']) && file_exists($data['proof_of_payment'])) {
            $postFields['proof_of_payment'] = new \CURLFile(
                $data['proof_of_payment'],
                $this->getFileContentType($data['proof_of_payment']),
                basename($data['proof_of_payment'])
            );
        }

        return $postFields;
    }

    /**
     * Executes the API request for manual bank transfer
     *
     * @param  array<string, mixed>  $postFields     Data to send
     * @param  bool                  $allowRedirect  Whether to follow redirects
     * @return array{body: string, http_code: int}   Response data
     *
     * @throws \Exception  When the request fails
     */
    private function executeManualTransferRequest(array $postFields, bool $allowRedirect): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->getManualTransferBaseUrl() . '/manual-bank-transfer',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => $allowRedirect,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($error) {
            throw new \Exception('cURL Error: ' . $error);
        }

        return [
            'body' => $response,
            'http_code' => $httpCode
        ];
    }

    /**
     * Processes the API response for manual bank transfer
     *
     * @param  string  $response       Response body
     * @param  int     $httpCode       HTTP response code
     * @param  bool    $allowRedirect  Whether redirects were allowed
     * @return mixed                   Processed response
     *
     * @throws \Exception  When the response indicates an error
     */
    private function processManualTransferResponse(string $response, int $httpCode, bool $allowRedirect)
    {
        if ($httpCode >= 200 && $httpCode < 300) {
            if (strpos($response, '<form') !== false) {
                $parsedData = $this->parseManualBankTransferResponse($response);

                return [
                    'success' => true,
                    'html_form' => $response,
                    'form_data' => $parsedData,
                    'return_url' => $parsedData['return_url'] ?? null
                ];
            }
            elseif ($decodedResponse = json_decode($response)) {
                return $decodedResponse;
            }
            else {
                return $response;
            }
        }
        elseif ($httpCode >= 300 && $httpCode < 400 && !$allowRedirect) {
            return ['redirect_url' => $response];
        }
        else {
            return $this->handleApiError($response, $httpCode);
        }
    }

    /**
     * Handles API error responses
     *
     * @param  string  $response  Response body
     * @param  int     $httpCode  HTTP response code
     * @return never
     *
     * @throws \Exception  Always throws with error details
     */
    private function handleApiError(string $response, int $httpCode)
    {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
            throw new \Exception($decoded['message']);
        } else {
            throw new \Exception("API Error (HTTP $httpCode): " . substr($response, 0, 200));
        }
    }

    /**
     * Handles generic API responses, checking for errors
     *
     * @param  string  $response  Response body
     * @param  int     $httpCode  HTTP response code
     * @return mixed   Processed response data
     *
     * @throws \Exception  When the response indicates an error
     */
    private function handleApiResponse(string $response, int $httpCode)
    {
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true) ?: $response;
        } else {
            return $this->handleApiError($response, $httpCode);
        }
    }

    /**
     * Determines appropriate content type for file uploads
     *
     * @param  string  $filePath  Path to the file
     * @return string  Content type MIME string
     */
    private function getFileContentType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $contentTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'pdf'  => 'application/pdf',
        ];

        return $contentTypes[$extension] ?? 'application/octet-stream';
    }
}