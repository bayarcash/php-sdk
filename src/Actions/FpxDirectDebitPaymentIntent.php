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
}
