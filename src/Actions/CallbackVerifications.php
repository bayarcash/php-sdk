<?php

namespace Webimpian\BayarcashSdk\Actions;

trait CallbackVerifications
{
    public function verifyDirectDebitBankApprovalCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            "record_type" => $callbackData['record_type'] ?? null,
            "approval_date" => $callbackData['approval_date'] ?? null,
            "approval_status" => $callbackData['approval_status'] ?? null,
            "mandate_id" => $callbackData['mandate_id'] ?? null,
            "mandate_reference_number" => $callbackData['mandate_reference_number'] ?? null,
            "order_number" => $callbackData['order_number'] ?? null,
            "payer_bank_code_hashed" => $callbackData['payer_bank_code_hashed'] ?? null,
            "payer_bank_code" => $callbackData['payer_bank_code'] ?? null,
            "payer_bank_account_no" => $callbackData['payer_bank_account_no'] ?? null,
            "application_type" => $callbackData['application_type'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }

    public function verifyDirectDebitAuthorizationCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            'record_type' => $callbackData['record_type'] ?? null,
            'transaction_id' => $callbackData['transaction_id'] ?? null,
            'mandate_id' => $callbackData['mandate_id'] ?? null,
            'application_type' => $callbackData['application_type'] ?? null,
            'exchange_reference_number' => $callbackData['exchange_reference_number'] ?? null,
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'] ?? null,
            'order_number' => $callbackData['order_number'] ?? null,
            'currency' => $callbackData['currency'] ?? null,
            'amount' => $callbackData['amount'] ?? null,
            'payer_name' => $callbackData['payer_name'] ?? null,
            'payer_email' => $callbackData['payer_email'] ?? null,
            'payer_bank_name' => $callbackData['payer_bank_name'] ?? null,
            'status' => $callbackData['status'] ?? null,
            'status_description' => $callbackData['status_description'] ?? null,
            'datetime' => $callbackData['datetime'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }

    public function verifyDirectDebitTransactionCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            'record_type' => $callbackData['record_type'] ?? null,
            'batch_number' => $callbackData['batch_number'] ?? null,
            'mandate_id' => $callbackData['mandate_id'] ?? null,
            'mandate_reference_number' => $callbackData['mandate_reference_number'] ?? null,
            'transaction_id' => $callbackData['transaction_id'] ?? null,
            'datetime' => $callbackData['datetime'] ?? null,
            'reference_number' => $callbackData['reference_number'] ?? null,
            'amount' => $callbackData['amount'] ?? null,
            'status' => $callbackData['status'] ?? null,
            'status_description' => $callbackData['status_description'] ?? null,
            'cycle' => $callbackData['cycle'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }

    public function verifyTransactionCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            'record_type' => $callbackData['record_type'] ?? null,
            'transaction_id' => $callbackData['transaction_id'] ?? null,
            'exchange_reference_number' => $callbackData['exchange_reference_number'] ?? null,
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'] ?? null,
            'order_number' => $callbackData['order_number'] ?? null,
            'currency' => $callbackData['currency'] ?? null,
            'amount' => $callbackData['amount'] ?? null,
            'payer_name' => $callbackData['payer_name'] ?? null,
            'payer_email' => $callbackData['payer_email'] ?? null,
            'payer_bank_name' => $callbackData['payer_bank_name'] ?? null,
            'status' => $callbackData['status'] ?? null,
            'status_description' => $callbackData['status_description'] ?? null,
            'datetime' => $callbackData['datetime'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }

    public function verifyReturnUrlCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            'transaction_id' => $callbackData['transaction_id'] ?? null,
            'exchange_reference_number' => $callbackData['exchange_reference_number'] ?? null,
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'] ?? null,
            'order_number' => $callbackData['order_number'] ?? null,
            'currency' => $callbackData['currency'] ?? null,
            'amount' => $callbackData['amount'] ?? null,
            'payer_bank_name' => $callbackData['payer_bank_name'] ?? null,
            'status' => $callbackData['status'] ?? null,
            'status_description' => $callbackData['status_description'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }

    public function verifyPreTransactionCallbackData(array $callbackData, ?string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'] ?? '';

        $payload = [
            'record_type' => $callbackData['record_type'] ?? null,
            'exchange_reference_number' => $callbackData['exchange_reference_number'] ?? null,
            'order_number' => $callbackData['order_number'] ?? null,
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_equals(hash_hmac('sha256', $payload, (string) $secretKey), (string) $callbackChecksum);
    }
}
