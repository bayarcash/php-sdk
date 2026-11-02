<?php

namespace Bayarcash\Actions;

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

    /**
     * Recurring charge callback (record_type `recurring_charge`): the only recurring
     * webhook, sent for EVERY charge (subscription, installment, on-demand, the first
     * charge of an enrolment included) on every change of its status - pending (1),
     * then successful (3) or failed (2).
     *
     * Signs the thirteen transaction-callback fields, the four that say what was
     * charged, and the five that describe the card (never a token). The body is
     * form-encoded, which drops null fields (order_number and payer_bank_name are
     * always null here; the card fields are null on a charge that named no card)
     * while the checksum signs them as ''. So the field list is fixed and a missing
     * field counts as ''.
     */
    public function verifyRecurringChargeCallbackData(array $callbackData, string $secretKey): bool
    {
        return $this->verifyFixedFieldChecksum($callbackData, $secretKey, [
            'record_type', 'transaction_id', 'exchange_reference_number', 'exchange_transaction_id',
            'order_number', 'currency', 'amount', 'payer_name', 'payer_email', 'payer_bank_name',
            'status', 'status_description', 'datetime',
            'charge_source', 'subscription_id', 'installment_id', 'customer_id',
            'card_brand', 'card_country', 'card_last4', 'card_exp_month', 'card_exp_year',
        ]);
    }

    /**
     * The payer's landing on your recurring return_url after the provider's card
     * page of an enrolment: the agreement's state (`status` as getSubscription() /
     * getInstallment() report it: pending, active, failed, expired, canceled, ...)
     * and its first charge, signed like a one-off return_url landing. The
     * transaction fields are '' until the first charge is booked. Verify, then read
     * the agreement by id, then act.
     */
    public function verifyRecurringReturnUrlData(array $queryData, string $secretKey): bool
    {
        return $this->verifyFixedFieldChecksum($queryData, $secretKey, [
            'recurring_type', 'status', 'customer_id', 'subscription_id', 'installment_id',
            'transaction_id', 'exchange_reference_number', 'exchange_transaction_id', 'currency', 'amount',
            'transaction_status', 'transaction_status_description',
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
