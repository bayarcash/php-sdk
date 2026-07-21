<?php

namespace Webimpian\BayarcashSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webimpian\BayarcashSdk\Resources\PaymentIntentResource;
use Webimpian\BayarcashSdk\Resources\PortalResource;
use Webimpian\BayarcashSdk\Resources\TransactionResource;

class ResourceTest extends TestCase
{
    public function test_snake_case_keys_are_filled_as_camel_case(): void
    {
        $resource = new TransactionResource([
            'order_number' => 'ORDER123',
            'payer_email' => 'customer@example.com',
        ]);

        $this->assertSame('ORDER123', $resource->orderNumber);
        $this->assertSame('customer@example.com', $resource->payerEmail);
    }

    /**
     * Regression test: non-nullable typed properties used to throw
     * "must not be accessed before initialization" when the API omitted them.
     */
    public function test_omitted_typed_properties_default_to_null_instead_of_throwing(): void
    {
        $resource = new PaymentIntentResource(['order_number' => 'ORDER123']);

        $this->assertSame('ORDER123', $resource->orderNumber);
        $this->assertNull($resource->url);
        $this->assertNull($resource->amount);
        $this->assertNull($resource->status);
    }

    public function test_unknown_api_field_does_not_raise_deprecation(): void
    {
        // With #[AllowDynamicProperties] setting an undeclared field must not emit a
        // PHP 8.2+ dynamic-property deprecation. Asserted directly (no reliance on the
        // PHPUnit config) so it holds across PHP 7.4-8.5 and PHPUnit 9/10.
        $deprecations = [];
        set_error_handler(static function ($errno, $errstr) use (&$deprecations) {
            $deprecations[] = $errstr;
            return true;
        }, E_DEPRECATED);

        try {
            $resource = new PortalResource([
                'portal_key' => 'abc',
                'brand_new_field_from_api' => 'value',
            ]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, 'Unknown API field must not emit a deprecation');
        $this->assertSame('abc', $resource->portalKey);
        $this->assertSame('value', $resource->brandNewFieldFromApi);
    }

    public function test_to_array_excludes_sdk_instance_and_returns_attributes(): void
    {
        $resource = new TransactionResource([
            'id' => 'trx_1',
            'amount' => 10.50,
        ]);

        $array = $resource->toArray();

        $this->assertArrayNotHasKey('bayarcash', $array);
        $this->assertSame('trx_1', $array['id']);
        $this->assertSame(10.50, $array['amount']);
    }
}
