<?php

namespace Webimpian\BayarcashSdk\Actions;

trait CallbackVerifications
{
    public function verifyDirectDebitBankApprovalCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            "record_type" => $callbackData['record_type'],
            "approval_date" => $callbackData['approval_date'],
            "approval_status" => $callbackData['approval_status'],
            "mandate_id" => $callbackData['mandate_id'],
            "mandate_reference_number" => $callbackData['mandate_reference_number'],
            "order_number" => $callbackData['order_number'],
            "payer_bank_code_hashed" => $callbackData['payer_bank_code_hashed'],
            "payer_bank_code" => $callbackData['payer_bank_code'],
            "payer_bank_account_no" => $callbackData['payer_bank_account_no'],
            "application_type" => $callbackData['application_type'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    public function verifyDirectDebitAuthorizationCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            'record_type' => $callbackData['record_type'],
            'transaction_id' => $callbackData['transaction_id'],
            'mandate_id' => $callbackData['mandate_id'],
            'exchange_reference_number' => $callbackData['exchange_reference_number'],
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'],
            'order_number' => $callbackData['order_number'],
            'currency' => $callbackData['currency'],
            'amount' => $callbackData['amount'],
            'payer_name' => $callbackData['payer_name'],
            'payer_email' => $callbackData['payer_email'],
            'payer_bank_name' => $callbackData['payer_bank_name'],
            'status' => $callbackData['status'],
            'status_description' => $callbackData['status_description'],
            'datetime' => $callbackData['datetime'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    public function verifyDirectDebitTransactionCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            'record_type' => $callbackData['record_type'],
            'batch_number' => $callbackData['batch_number'],
            'mandate_id' => $callbackData['mandate_id'],
            'mandate_reference_number' => $callbackData['mandate_reference_number'],
            'transaction_id' => $callbackData['transaction_id'],
            'datetime' => $callbackData['datetime'],
            'reference_number' => $callbackData['reference_number'],
            'amount' => $callbackData['amount'],
            'status' => $callbackData['status'],
            'status_description' => $callbackData['status_description'],
            'cycle' => $callbackData['cycle'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    public function verifyTransactionCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            'record_type' => $callbackData['record_type'],
            'transaction_id' => $callbackData['transaction_id'],
            'exchange_reference_number' => $callbackData['exchange_reference_number'],
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'],
            'order_number' => $callbackData['order_number'],
            'currency' => $callbackData['currency'],
            'amount' => $callbackData['amount'],
            'payer_name' => $callbackData['payer_name'],
            'payer_email' => $callbackData['payer_email'],
            'payer_bank_name' => $callbackData['payer_bank_name'],
            'status' => $callbackData['status'],
            'status_description' => $callbackData['status_description'],
            'datetime' => $callbackData['datetime'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    public function verifyReturnUrlCallbackData(array $callbackData, string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            'transaction_id' => $callbackData['transaction_id'],
            'exchange_reference_number' => $callbackData['exchange_reference_number'],
            'exchange_transaction_id' => $callbackData['exchange_transaction_id'],
            'order_number' => $callbackData['order_number'],
            'currency' => $callbackData['currency'],
            'amount' => $callbackData['amount'],
            'payer_bank_name' => $callbackData['payer_bank_name'],
            'status' => $callbackData['status'],
            'status_description' => $callbackData['status_description'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    public function verifyPreTransactionCallbackData(array $callbackData, ?string $secretKey)
    {
        $callbackChecksum = $callbackData['checksum'];

        $payload = [
            'record_type' => $callbackData['record_type'],
            'exchange_reference_number' => $callbackData['exchange_reference_number'],
            'order_number' => $callbackData['order_number'],
        ];

        ksort($payload);
        $payload = implode('|', $payload);

        return hash_hmac('sha256', $payload, $secretKey) === $callbackChecksum;
    }

    /**
     * Recurring charge callback (record_type `recurring_charge`), sent for SUCCESSFUL
     * subscription, installment and on-demand charges only.
     *
     * The body is form-encoded, which drops null fields (order_number and
     * payer_bank_name are always null here) while the checksum signs them as ''.
     * So the field list is fixed and a missing field counts as ''.
     */
    public function verifyRecurringChargeCallbackData(array $callbackData, string $secretKey): bool
    {
        return $this->verifyFixedFieldChecksum($callbackData, $secretKey, [
            'record_type', 'transaction_id', 'exchange_reference_number', 'exchange_transaction_id',
            'order_number', 'currency', 'amount', 'payer_name', 'payer_email', 'payer_bank_name',
            'status', 'status_description', 'datetime',
            'charge_source', 'subscription_id', 'installment_id', 'customer_id', 'recurring_intent_id',
        ]);
    }

    /**
     * Recurring lifecycle callback (record_type `recurring_subscription` or
     * `recurring_installment`, event `enrolment_completed` or `status_updated`).
     */
    public function verifyRecurringLifecycleCallbackData(array $callbackData, string $secretKey): bool
    {
        return $this->verifyFixedFieldChecksum($callbackData, $secretKey, [
            'record_type', 'event', 'subscription_id', 'installment_id', 'customer_id', 'plan_id',
            'status', 'previous_status', 'paused_reason', 'next_payment_date',
            'total_amount', 'installment_count', 'remaining_amount', 'datetime',
        ]);
    }

    /** ksort + implode('|') + HMAC-SHA256 over a fixed field list, '' for anything absent. */
    protected function verifyFixedFieldChecksum(array $callbackData, string $secretKey, array $fields): bool
    {
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = $callbackData[$field] ?? '';
        }

        ksort($payload);

        return hash_equals(hash_hmac('sha256', implode('|', $payload), $secretKey), (string) ($callbackData['checksum'] ?? ''));
    }
}
