<?php

namespace Bayarcash\Tests\Recurring;

use Bayarcash\Fpx;
use Bayarcash\Resources\TransactionResource;
use Bayarcash\Tests\TestCase;

class OnDemandPaymentTest extends TestCase
{
    public function test_create_on_demand_payment_returns_the_charge(): void
    {
        $bayarcash = $this->sdk([
            $this->json(CustomerTest::chargeRow(['id' => 'trx_3', 'exchange_transaction_id' => 'chg-3', 'amount' => 25.0]), 201),
        ]);

        $charge = $bayarcash->createOnDemandPayment([
            'portal_key' => 'pk_test_portal',
            'customer_id' => 'rcus_1',
            'amount' => 25.00,
            'description' => 'Top up',
        ]);

        $this->assertInstanceOf(TransactionResource::class, $charge);
        $this->assertSame('trx_3', $charge->id);
        $this->assertSame('chg-3', $charge->exchangeTransactionId);
        $this->assertEquals(Fpx::STATUS_SUCCESS, $charge->status);
        $this->assertSame('rcus_1', $charge->recurringCustomerId);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('payments', (string) $this->lastRequest()->getUri());
        $this->assertSame('Top up', $this->lastBody()['description']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
    }

    /*
     * Every field the API's TransactionResource can answer is declared: an undeclared
     * one becomes a dynamic property, deprecated since PHP 8.2.
     */
    public function test_the_charge_declares_every_field_the_api_answers(): void
    {
        $bayarcash = $this->sdk([
            $this->json(CustomerTest::chargeRow([
                'transaction_type' => 'Deduction', 'application_reason' => 'Monthly', 'payment_intent_id' => 'pi_1',
            ]), 201),
        ]);

        $charge = $bayarcash->createOnDemandPayment([
            'portal_key' => 'pk_test_portal', 'customer_id' => 'rcus_1', 'amount' => 49.9, 'description' => 'Top up',
        ]);

        $declared = array_keys(get_class_vars(TransactionResource::class));
        foreach (['platformFees', 'nettAmount', 'transactionType', 'applicationReason', 'paymentIntentId'] as $field) {
            $this->assertContains($field, $declared, $field . ' is not declared');
        }
        $this->assertSame(0.5, $charge->platformFees);
        $this->assertSame(49.4, $charge->nettAmount);
        $this->assertSame('Deduction', $charge->transactionType);
        $this->assertSame('Monthly', $charge->applicationReason);
        $this->assertSame('pi_1', $charge->paymentIntentId);
    }

    public function test_create_on_demand_payment_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'trx_3'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createOnDemandPayment(['customer_id' => 'rcus_1', 'amount' => 25.00]);
    }

    /*
     * The merchant's `reference` dedupes an on-demand charge (unique per merchant, forever),
     * so the API takes no Idempotency-Key and the SDK sends none.
     */
    public function test_on_demand_sends_no_idempotency_key(): void
    {
        $bayarcash = $this->sdk([$this->json(CustomerTest::chargeRow(), 201)]);

        $bayarcash->createOnDemandPayment([
            'portal_key' => 'pk_test_portal', 'reference' => 'TOPUP-0001',
            'customer_id' => 'rcus_1', 'amount' => 25.00, 'description' => 'Top up',
        ]);

        $this->assertFalse($this->lastRequest()->hasHeader('Idempotency-Key'));
    }

    /*
     * The reference goes in the body as given (the API validates it), and the charge
     * answers it back as `order_number`, beside its BPGR exchange reference.
     */
    public function test_reference_is_sent_in_the_body(): void
    {
        $bayarcash = $this->sdk([
            $this->json(CustomerTest::chargeRow(['order_number' => 'TOPUP-0001']), 201),
        ]);

        $charge = $bayarcash->createOnDemandPayment([
            'portal_key' => 'pk_test_portal', 'reference' => 'TOPUP-0001',
            'customer_id' => 'rcus_1', 'amount' => 25.00, 'description' => 'Top up',
        ]);

        $this->assertSame('TOPUP-0001', $this->lastBody()['reference']);
        $this->assertSame('TOPUP-0001', $charge->orderNumber);
        $this->assertSame('BPGR2026100600000042', $charge->exchangeReferenceNumber);
    }

    /*
     * A repeat with the same reference and body answers the stored charge with 200, and
     * one still settling answers 202: both are answers, not errors.
     */
    public function test_a_repeat_answer_is_the_charge_not_an_error(): void
    {
        $bayarcash = $this->sdk([
            $this->json(CustomerTest::chargeRow(['order_number' => 'TOPUP-0001']), 200),
            $this->json(CustomerTest::chargeRow(['order_number' => 'TOPUP-0001', 'status' => 1]), 202),
        ]);
        $data = [
            'portal_key' => 'pk_test_portal', 'reference' => 'TOPUP-0001',
            'customer_id' => 'rcus_1', 'amount' => 25.00, 'description' => 'Top up',
        ];

        $this->assertSame('TOPUP-0001', $bayarcash->createOnDemandPayment($data)->orderNumber);
        $this->assertEquals(1, $bayarcash->createOnDemandPayment($data)->status);
    }

    public function test_get_payment_returns_a_transaction_record(): void
    {
        $bayarcash = $this->sdk([
            $this->json(CustomerTest::chargeRow(['id' => 'trx_9', 'exchange_transaction_id' => 'chg-9', 'amount' => 25.0])),
        ]);

        // Accepts the provider charge id or the trx_ id.
        $payment = $bayarcash->getPayment('chg-9');

        $this->assertInstanceOf(TransactionResource::class, $payment);
        $this->assertSame('trx_9', $payment->id);
        $this->assertSame('chg-9', $payment->exchangeTransactionId);
        $this->assertSame('BPGR2026100600000042', $payment->exchangeReferenceNumber);
        $this->assertSame('2026-10-06 10:15:00', $payment->createdAt);
        $this->assertSame(25.0, $payment->amount);
        $this->assertEquals(Fpx::STATUS_SUCCESS, $payment->status);
        $this->assertSame('rcus_1', $payment->recurringCustomerId);
        $this->assertStringContainsString('payments/chg-9', (string) $this->lastRequest()->getUri());
    }
}
