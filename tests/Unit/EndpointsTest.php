<?php

namespace Bayarcash\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Bayarcash\Bayarcash;
use Bayarcash\Resources\DuitNowQrResource;
use Bayarcash\Resources\FpxBankResource;
use Bayarcash\Resources\FpxDirectDebitResource;
use Bayarcash\Resources\PortalResource;

class EndpointsTest extends TestCase
{
    private array $history = [];

    private function sdk(array $responses): Bayarcash
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.console.bayar.cash/v3/',
            'http_errors' => false,
        ]);

        $sdk = new Bayarcash('test-token');
        $sdk->setApiVersion('v3');

        return $sdk->setToken('test-token', $client);
    }

    private function lastRequest()
    {
        return end($this->history)['request'];
    }

    public function test_get_server_status_hits_up(): void
    {
        $sdk = $this->sdk([new Response(200, [], 'OK')]);

        $this->assertSame('OK', $sdk->getServerStatus());
        $this->assertSame('/v3/up', $this->lastRequest()->getUri()->getPath());
    }

    public function test_regenerate_duitnow_qr_posts_to_intent_qr_endpoint(): void
    {
        $sdk = $this->sdk([new Response(201, [], json_encode(['transaction_id' => 'trx_1', 'qr_string' => '0002...']))]);

        $qr = $sdk->regenerateDuitNowQr('pi_123');

        $req = $this->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/v3/payment-intents/pi_123/duitnow-qr', $req->getUri()->getPath());
        $this->assertInstanceOf(DuitNowQrResource::class, $qr);
        $this->assertSame('trx_1', $qr->transactionId);
        $this->assertSame('0002...', $qr->qrString);
    }

    public function test_create_payment_intent_sends_idempotency_key(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['id' => 'pi_1']))]);

        $sdk->createPaymentIntent(['order_number' => 'ORD1', 'amount' => '10.00'], 'idem-123');

        $req = $this->lastRequest();
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/v3/payment-intents', $req->getUri()->getPath());
        $this->assertSame('idem-123', $req->getHeaderLine('Idempotency-Key'));

        parse_str((string) $req->getBody(), $body);
        $this->assertSame('ORD1', $body['order_number']);
        $this->assertSame('10.00', $body['amount']);
    }

    public function test_create_payment_intent_omits_idempotency_key_by_default(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['id' => 'pi_1']))]);

        $sdk->createPaymentIntent(['amount' => '10.00']);

        $this->assertFalse($this->lastRequest()->hasHeader('Idempotency-Key'));
    }

    public function test_create_duitnow_qr_payment_intent_sets_flag_and_maps_qr(): void
    {
        $sdk = $this->sdk([new Response(201, [], json_encode([
            'id' => 'pi_1',
            'duitnow_qr' => ['transaction_id' => 'trx_1', 'qr_string' => '0002...'],
        ]))]);

        $intent = $sdk->createDuitNowQrPaymentIntent(['payment_channel' => 6, 'amount' => '10.00']);

        parse_str((string) $this->lastRequest()->getBody(), $body);
        $this->assertSame('1', $body['generate_qr']);
        $this->assertInstanceOf(DuitNowQrResource::class, $intent->duitnowQr);
        $this->assertSame('trx_1', $intent->duitnowQr->transactionId);
        $this->assertSame('0002...', $intent->duitnowQr->qrString);
    }

    public function test_v3_only_methods_reject_a_v2_client(): void
    {
        $v2 = new Bayarcash('test-token');

        $this->expectException(\Exception::class);
        $v2->createDuitNowQrPaymentIntent(['payment_channel' => 6]);
    }

    public function test_idempotency_key_rejected_on_v2(): void
    {
        $v2 = new Bayarcash('test-token');

        $this->expectException(\Exception::class);
        $v2->createPaymentIntent(['amount' => '10.00'], 'idem-123');
    }

    public function test_transaction_exposes_identity_verification_fields(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode([
            'id' => 'trx_1',
            'payer_identity_verified' => true,
            'fpx_extra_info' => ['account_type' => 'CASA', 'account_number_verified' => true, 'buyer_id_verified' => false],
        ]))]);

        $trx = $sdk->getTransaction('trx_1');

        $this->assertTrue($trx->payerIdentityVerified);
        $this->assertSame('CASA', $trx->fpxExtraInfo['account_type']);
    }

    public function test_get_duitnow_qr_status_polls_transaction(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['status' => 1]))]);

        $status = $sdk->getDuitNowQrStatus('trx_1');

        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/v3/transactions/trx_1/duitnow-qr/status', $req->getUri()->getPath());
        $this->assertSame(1, $status['status']);
    }

    public function test_duitnow_dobw_banks_list_returns_bank_resources(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode([
            ['bank_name' => 'ACF Bank', 'bank_code' => 'ACFBMYK1', 'bank_availability' => true],
        ]))]);

        $banks = $sdk->duitNowDobwBanksList();

        $this->assertSame('/v3/duitnow/dobw/banks', $this->lastRequest()->getUri()->getPath());
        $this->assertInstanceOf(FpxBankResource::class, $banks[0]);
        $this->assertSame('ACFBMYK1', $banks[0]->bankCode);
    }

    public function test_get_portal_returns_portal_resource(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['id' => 'prt_1', 'portal_key' => 'key1']))]);

        $portal = $sdk->getPortal('prt_1');

        $this->assertSame('/v3/portals/prt_1', $this->lastRequest()->getUri()->getPath());
        $this->assertInstanceOf(PortalResource::class, $portal);
        $this->assertSame('key1', $portal->portalKey);
    }

    public function test_get_all_fpx_direct_debits_returns_data_and_meta(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode([
            'data' => [['id' => 'mnd_1']],
            'meta' => ['total' => 1],
        ]))]);

        $result = $sdk->getAllFpxDirectDebits(['status' => 3, 'ignored' => 'x']);

        $req = $this->lastRequest();
        $this->assertSame('/v3/mandates', $req->getUri()->getPath());
        $this->assertSame('status=3', $req->getUri()->getQuery());
        $this->assertInstanceOf(FpxDirectDebitResource::class, $result['data'][0]);
        $this->assertSame(['total' => 1], $result['meta']);
    }

    public function test_get_all_fpx_direct_debit_transactions_lists_mandate_transactions(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['data' => [['id' => 'trx_1']], 'meta' => []]))]);

        $result = $sdk->getAllFpxDirectDebitTransactions(['order_number' => 'DD001']);

        $req = $this->lastRequest();
        $this->assertSame('/v3/mandates/transactions', $req->getUri()->getPath());
        $this->assertSame('order_number=DD001', $req->getUri()->getQuery());
        $this->assertNotEmpty($result['data']);
    }

    public function test_activate_fpx_direct_debit_patches_activate(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['id' => 'mnd_1']))]);

        $mandate = $sdk->activateFpxDirectDebit('mnd_1');

        $req = $this->lastRequest();
        $this->assertSame('PATCH', $req->getMethod());
        $this->assertSame('/v3/mandates/mnd_1/activate', $req->getUri()->getPath());
        $this->assertInstanceOf(FpxDirectDebitResource::class, $mandate);
    }

    public function test_deactivate_fpx_direct_debit_patches_deactivate(): void
    {
        $sdk = $this->sdk([new Response(200, [], json_encode(['id' => 'mnd_1']))]);

        $sdk->deactivateFpxDirectDebit('mnd_1');

        $req = $this->lastRequest();
        $this->assertSame('PATCH', $req->getMethod());
        $this->assertSame('/v3/mandates/mnd_1/deactivate', $req->getUri()->getPath());
    }
}
