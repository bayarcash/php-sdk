<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use PHPUnit\Framework\TestCase;
use Webimpian\BayarcashSdk\Bayarcash;

class CallbackVerificationTest extends TestCase
{
    private const SECRET = 'secret-key';

    /** Sign the way the console does: ksort, implode('|'), HMAC-SHA256, nulls as ''. */
    private function sign(array $signed): string
    {
        $signed = array_map(function ($value) {
            return $value ?? '';
        }, $signed);
        ksort($signed);

        return hash_hmac('sha256', implode('|', $signed), self::SECRET);
    }

    private function chargeFields(): array
    {
        return [
            'record_type' => 'recurring_charge',
            'transaction_id' => 'trx_1',
            'exchange_reference_number' => 'BPGR2026100600000042',
            'exchange_transaction_id' => 'chg-1',
            'order_number' => null,
            'currency' => 'MYR',
            'amount' => '49.90',
            'payer_name' => 'Jane Doe',
            'payer_email' => 'jane@example.com',
            'payer_bank_name' => null,
            'status' => '3',
            'status_description' => 'Approved',
            'datetime' => '2026-10-06 10:15:00',
            'charge_source' => 'subscription',
            'subscription_id' => 'rsub_1',
            'installment_id' => null,
            'customer_id' => 'rcus_1',
            'card_brand' => 'visa',
            'card_country' => 'MY',
            'card_last4' => '1118',
            'card_exp_month' => '12',
            'card_exp_year' => '2030',
        ];
    }

    /** What arrives: form encoding drops the null fields, unsigned extras ride along. */
    private function received(array $signed): array
    {
        return array_filter($signed, function ($value) {
            return $value !== null;
        }) + ['checksum' => $this->sign($signed)];
    }

    public function test_charge_callback_verifies_with_null_fields_absent(): void
    {
        $body = $this->received($this->chargeFields()) + [
            'payer_telephone_number' => '60123456789', // sent, not signed
            'payment_gateway_id' => '12',
        ];

        $this->assertArrayNotHasKey('order_number', $body);
        $this->assertTrue((new Bayarcash('t'))->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_charge_callback_signs_the_card_it_ran_on(): void
    {
        $body = $this->received($this->chargeFields());
        $body['card_last4'] = '9999';

        $this->assertFalse((new Bayarcash('t'))->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_charge_callback_verifies_a_failed_charge_without_a_card(): void
    {
        $signed = array_merge($this->chargeFields(), [
            'status' => '2', 'status_description' => 'Declined',
            'card_brand' => null, 'card_country' => null, 'card_last4' => null,
            'card_exp_month' => null, 'card_exp_year' => null,
        ]);
        $body = $this->received($signed);

        $this->assertArrayNotHasKey('card_last4', $body);
        $this->assertTrue((new Bayarcash('t'))->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_charge_callback_ignores_an_unsigned_extra_field(): void
    {
        // There is no recurring_intent_id any more; a stray key is not part of the checksum.
        $body = $this->received($this->chargeFields()) + ['recurring_intent_id' => 'rint_1'];

        $this->assertTrue((new Bayarcash('t'))->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_charge_callback_cannot_be_repointed_at_another_subscription(): void
    {
        $body = $this->received($this->chargeFields());
        $body['subscription_id'] = 'rsub_2';

        $this->assertFalse((new Bayarcash('t'))->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_charge_callback_rejects_a_wrong_secret_or_missing_checksum(): void
    {
        $body = $this->received($this->chargeFields());
        $sdk = new Bayarcash('t');

        $this->assertFalse($sdk->verifyRecurringChargeCallbackData($body, 'other-secret'));

        unset($body['checksum']);
        $this->assertFalse($sdk->verifyRecurringChargeCallbackData($body, self::SECRET));
    }

    public function test_return_url_landing_verifies_and_rejects_a_tampered_status(): void
    {
        // What the console appends to the merchant's return_url after the provider's
        // card page: the signed twelve, then datetime and checksum outside the signature.
        $signed = [
            'recurring_type' => 'subscription',
            'status' => 'active',
            'customer_id' => 'rcus_1',
            'subscription_id' => 'rsub_1',
            'installment_id' => '',
            'transaction_id' => 'trx_9',
            'exchange_reference_number' => 'BPGR-2026100700000021',
            'exchange_transaction_id' => 'aa9757ea-ee89-46d0-8107-62f0b1d209bb',
            'currency' => 'MYR',
            'amount' => '120.00',
            'transaction_status' => '3',
            'transaction_status_description' => 'Successful',
        ];
        $query = $signed + ['datetime' => '2026-10-07T10:15:00+08:00', 'checksum' => $this->sign($signed)];
        $sdk = new Bayarcash('t');

        $this->assertTrue($sdk->verifyRecurringReturnUrlData($query, self::SECRET));

        $query['status'] = 'failed';
        $this->assertFalse($sdk->verifyRecurringReturnUrlData($query, self::SECRET));
    }

    public function test_return_url_landing_does_not_sign_a_recurring_intent_id(): void
    {
        $signed = [
            'recurring_type' => 'installment', 'status' => 'expired', 'customer_id' => 'rcus_1',
            'subscription_id' => '', 'installment_id' => 'rins_1', 'transaction_id' => '',
            'exchange_reference_number' => '', 'exchange_transaction_id' => '', 'currency' => 'MYR',
            'amount' => '', 'transaction_status' => '', 'transaction_status_description' => '',
        ];
        $query = $signed + ['recurring_intent_id' => 'rint_x', 'checksum' => $this->sign($signed)];

        $this->assertTrue((new Bayarcash('t'))->verifyRecurringReturnUrlData($query, self::SECRET));
    }

    public function test_return_url_landing_verifies_an_enrolment_before_its_first_charge(): void
    {
        $signed = [
            'recurring_type' => 'subscription',
            'status' => 'pending',
            'customer_id' => 'rcus_1',
            'subscription_id' => 'rsub_1',
            'installment_id' => '',
            'transaction_id' => '',
            'exchange_reference_number' => '',
            'exchange_transaction_id' => '',
            'currency' => 'MYR',
            'amount' => '',
            'transaction_status' => '',
            'transaction_status_description' => '',
        ];

        $this->assertTrue((new Bayarcash('t'))->verifyRecurringReturnUrlData(
            $signed + ['checksum' => $this->sign($signed)],
            self::SECRET
        ));
    }
}
