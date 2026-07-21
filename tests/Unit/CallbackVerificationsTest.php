<?php

namespace Webimpian\BayarcashSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webimpian\BayarcashSdk\Bayarcash;

class CallbackVerificationsTest extends TestCase
{
    private string $secret = 'sk_test_secret';

    private Bayarcash $sdk;

    protected function setUp(): void
    {
        $this->sdk = new Bayarcash('test-token');
    }

    /**
     * Sign exactly the fields a verifier checks, using the SDK's own generic
     * checksum primitive (same ksort + '|' + HMAC recipe the server uses).
     */
    private function sign(array $fields): string
    {
        return $this->sdk->createChecksumValue($this->secret, $fields);
    }

    public function test_pre_transaction_callback_round_trips(): void
    {
        $fields = [
            'record_type' => 'pre_transaction',
            'exchange_reference_number' => 'REF1',
            'order_number' => 'ORD1',
        ];
        $callback = $fields + ['checksum' => $this->sign($fields)];

        $this->assertTrue($this->sdk->verifyPreTransactionCallbackData($callback, $this->secret));

        $callback['order_number'] = 'TAMPERED';
        $this->assertFalse($this->sdk->verifyPreTransactionCallbackData($callback, $this->secret));
    }

    public function test_transaction_callback_round_trips(): void
    {
        $fields = [
            'record_type' => 'transaction',
            'transaction_id' => 'trx_1',
            'exchange_reference_number' => 'REF1',
            'exchange_transaction_id' => 'EX1',
            'order_number' => 'ORD1',
            'currency' => 'MYR',
            'amount' => '10.00',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
            'payer_bank_name' => 'Test Bank',
            'status' => '3',
            'status_description' => 'Approved',
            'datetime' => '2026-01-01 12:00:00',
        ];
        $callback = $fields + ['checksum' => $this->sign($fields)];

        $this->assertTrue($this->sdk->verifyTransactionCallbackData($callback, $this->secret));

        $callback['amount'] = '99.00';
        $this->assertFalse($this->sdk->verifyTransactionCallbackData($callback, $this->secret));
    }

    public function test_return_url_v3_callback_round_trips(): void
    {
        $fields = [
            'transaction_id' => 'trx_1',
            'exchange_reference_number' => 'REF1',
            'exchange_transaction_id' => 'EX1',
            'order_number' => 'ORD1',
            'currency' => 'MYR',
            'amount' => '10.00',
            'payer_bank_name' => 'Test Bank',
            'status' => '3',
            'status_description' => 'Approved',
        ];
        $callback = $fields + ['checksum' => $this->sign($fields)];

        $this->assertTrue($this->sdk->verifyReturnUrlCallbackData($callback, $this->secret));
    }

    public function test_direct_debit_bank_approval_callback_round_trips(): void
    {
        $fields = [
            'record_type' => 'bank_approval',
            'approval_date' => '2026-01-01',
            'approval_status' => 'approved',
            'mandate_id' => 'mdt_1',
            'mandate_reference_number' => 'MREF1',
            'order_number' => 'ORD1',
            'payer_bank_code_hashed' => 'hashed',
            'payer_bank_code' => 'ABB0233',
            'payer_bank_account_no' => '****1234',
            'application_type' => '01',
        ];
        $callback = $fields + ['checksum' => $this->sign($fields)];

        $this->assertTrue($this->sdk->verifyDirectDebitBankApprovalCallbackData($callback, $this->secret));
    }

    /**
     * Regression test: the authorization checksum MUST include application_type,
     * which the server signs. Before the fix the verifier omitted it and every
     * genuine authorization callback failed verification.
     */
    public function test_direct_debit_authorization_callback_includes_application_type(): void
    {
        $fields = [
            'record_type' => 'authorization',
            'transaction_id' => 'trx_1',
            'mandate_id' => 'mdt_1',
            'application_type' => '01',
            'exchange_reference_number' => 'REF1',
            'exchange_transaction_id' => 'EX1',
            'order_number' => 'ORD1',
            'currency' => 'MYR',
            'amount' => '10.00',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
            'payer_bank_name' => 'Test Bank',
            'status' => '3',
            'status_description' => 'Approved',
            'datetime' => '2026-01-01 12:00:00',
        ];
        $callback = $fields + ['checksum' => $this->sign($fields)];

        $this->assertTrue($this->sdk->verifyDirectDebitAuthorizationCallbackData($callback, $this->secret));

        // A callback missing application_type must NOT verify against a full-payload signature.
        $withoutAppType = $callback;
        unset($withoutAppType['application_type']);
        $this->assertFalse($this->sdk->verifyDirectDebitAuthorizationCallbackData($withoutAppType, $this->secret));
    }

    public function test_missing_checksum_returns_false_without_error(): void
    {
        $this->assertFalse($this->sdk->verifyPreTransactionCallbackData([
            'record_type' => 'pre_transaction',
            'order_number' => 'ORD1',
        ], $this->secret));
    }
}
