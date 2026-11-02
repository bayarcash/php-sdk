<?php

namespace Bayarcash\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Bayarcash\Bayarcash;

class ChecksumGeneratorTest extends TestCase
{
    private string $secret = 'sk_test_secret';

    private Bayarcash $sdk;

    protected function setUp(): void
    {
        $this->sdk = new Bayarcash('test-token');
    }

    /**
     * Reference recipe from the gateway server: ksort by key, implode values with '|',
     * then HMAC-SHA256 with the merchant secret. Pins the SDK to that contract.
     */
    private function expected(array $fields): string
    {
        ksort($fields);
        return hash_hmac('sha256', implode('|', $fields), $this->secret);
    }

    public function test_payment_intent_checksum_matches_server_recipe(): void
    {
        $data = [
            'payment_channel' => 5,
            'order_number' => 'ORD1',
            'amount' => '10.00',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
        ];

        $expected = $this->expected([
            'payment_channel' => '5',
            'order_number' => 'ORD1',
            'amount' => '10.00',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
        ]);

        $this->assertSame($expected, $this->sdk->createPaymentIntentChecksumValue($this->secret, $data));
    }

    public function test_payment_channel_int_and_single_element_array_are_equivalent(): void
    {
        $base = ['order_number' => 'ORD1', 'amount' => '10.00', 'payer_name' => 'John', 'payer_email' => 'a@b.com'];

        $asInt = $this->sdk->createPaymentIntentChecksumValue($this->secret, $base + ['payment_channel' => 5]);
        $asArray = $this->sdk->createPaymentIntentChecksumValue($this->secret, $base + ['payment_channel' => [5]]);

        $this->assertSame($asInt, $asArray);
    }

    public function test_multiple_payment_channels_are_comma_joined(): void
    {
        $data = ['order_number' => 'ORD1', 'amount' => '10.00', 'payer_name' => 'John', 'payer_email' => 'a@b.com', 'payment_channel' => [1, 2]];

        $expected = $this->expected([
            'payment_channel' => '1,2',
            'order_number' => 'ORD1',
            'amount' => '10.00',
            'payer_name' => 'John',
            'payer_email' => 'a@b.com',
        ]);

        $this->assertSame($expected, $this->sdk->createPaymentIntentChecksumValue($this->secret, $data));
    }

    public function test_misspelled_alias_matches_correct_method(): void
    {
        $data = ['order_number' => 'ORD1', 'amount' => '10.00', 'payer_name' => 'John', 'payer_email' => 'a@b.com', 'payment_channel' => 5];

        $this->assertSame(
            $this->sdk->createPaymentIntentChecksumValue($this->secret, $data),
            $this->sdk->createPaymentIntenChecksumValue($this->secret, $data)
        );
    }

    public function test_direct_debit_enrolment_checksum_matches_server_recipe(): void
    {
        $data = [
            'order_number' => 'ORD1',
            'amount' => '10.00',
            'payer_name' => 'John Doe',
            'payer_email' => 'john@example.com',
            'payer_telephone_number' => '0123456789',
            'payer_id_type' => 1,
            'payer_id' => '900101011234',
            'application_reason' => 'Monthly subscription',
            'frequency_mode' => 'MT',
        ];

        $this->assertSame(
            $this->expected($data),
            $this->sdk->createFpxDirectDebitEnrolmentChecksumValue($this->secret, $data)
        );
    }

    public function test_direct_debit_maintenance_checksum_matches_server_recipe(): void
    {
        $data = [
            'amount' => '10.00',
            'payer_email' => 'john@example.com',
            'payer_telephone_number' => '0123456789',
            'application_reason' => 'Update amount',
            'frequency_mode' => 'MT',
        ];

        $this->assertSame(
            $this->expected($data),
            $this->sdk->createFpxDirectDebitMaintenanceChecksumValue($this->secret, $data)
        );
    }
}
