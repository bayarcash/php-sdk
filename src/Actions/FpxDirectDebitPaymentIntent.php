<?php

namespace Bayarcash\Actions;

use Bayarcash\Resources\FpxDirectDebitApplicationResource;
use Bayarcash\Resources\FpxDirectDebitResource;
use Bayarcash\Resources\TransactionResource;

trait FpxDirectDebitPaymentIntent
{
    public function createFpxDirectDebitEnrollment(array $data)
    {
        return new FpxDirectDebitApplicationResource(
            $this->post('mandates', $data),
            $this
        );
    }

    public function createFpxDirectDebitMaintenance($mandateId, array $data)
    {
        return new FpxDirectDebitApplicationResource(
            $this->put('mandates/' . $mandateId, $data),
            $this
        );
    }

    public function createFpxDirectDebitTermination($mandateId, array $data)
    {
        return new FpxDirectDebitApplicationResource(
            $this->delete('mandates/' . $mandateId, $data),
            $this
        );
    }

    public function getFpxDirectDebitTransaction($id)
    {
        return new TransactionResource(
            $this->get('mandates/transactions/' . $id),
            $this
        );
    }

    /**
     * @deprecated Misspelled alias, kept for backward compatibility. Use getFpxDirectDebitTransaction().
     */
    public function getfpxDirectDebitransaction($id)
    {
        return $this->getFpxDirectDebitTransaction($id);
    }

    public function getFpxDirectDebit($id)
    {
        return new FpxDirectDebitResource(
            $this->get('mandates/' . $id),
        );
    }

    public function getAllFpxDirectDebits(array $parameters = [])
    {
        $this->assertV3('The getAllFpxDirectDebits method');

        $allowed = ['order_number', 'status', 'payer_email'];
        $query = http_build_query(array_intersect_key($parameters, array_flip($allowed)));

        $response = $this->get('mandates' . ($query ? '?' . $query : ''));

        return [
            'data' => $this->transformCollection($response['data'] ?? [], FpxDirectDebitResource::class),
            'meta' => $response['meta'] ?? [],
        ];
    }

    public function getAllFpxDirectDebitTransactions(array $parameters = [])
    {
        $this->assertV3('The getAllFpxDirectDebitTransactions method');

        $allowed = ['order_number', 'status', 'exchange_reference_number', 'payer_email'];
        $query = http_build_query(array_intersect_key($parameters, array_flip($allowed)));

        $response = $this->get('mandates/transactions' . ($query ? '?' . $query : ''));

        return [
            'data' => $this->transformCollection($response['data'] ?? [], TransactionResource::class),
            'meta' => $response['meta'] ?? [],
        ];
    }

    public function activateFpxDirectDebit($mandateId)
    {
        $this->assertV3('The activateFpxDirectDebit method');

        return new FpxDirectDebitResource(
            $this->patch('mandates/' . $mandateId . '/activate'),
            $this
        );
    }

    public function deactivateFpxDirectDebit($mandateId)
    {
        $this->assertV3('The deactivateFpxDirectDebit method');

        return new FpxDirectDebitResource(
            $this->patch('mandates/' . $mandateId . '/deactivate'),
            $this
        );
    }
}
