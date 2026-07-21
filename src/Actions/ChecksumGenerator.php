<?php

namespace Webimpian\BayarcashSdk\Actions;

trait ChecksumGenerator
{
    public function createChecksumValue($secretKey, $payload)
    {
        ksort($payload);
        $payloadString = implode('|', $payload);

        return hash_hmac('sha256', $payloadString, $secretKey);
    }

    // Old typo version, kept for backward compatibility
    public function createPaymentIntenChecksumValue($secretKey, $data)
    {
        return $this->createPaymentIntentChecksumValue($secretKey, $data);
    }

    public function createPaymentIntentChecksumValue($secretKey, $data)
    {
	    $paymentChannel = $data['payment_channel'] ?? [];

	    $paymentChannel = is_array($paymentChannel)
		    ? $paymentChannel
		    : [$paymentChannel];

	    $paymentChannel = implode(',', $paymentChannel);

	    $payload = [
		    'payment_channel' => $paymentChannel,
		    'order_number' => $data['order_number'],
		    'amount' => $data['amount'],
		    'payer_name' => $data['payer_name'],
		    'payer_email' => $data['payer_email'],
	    ];

	    ksort($payload);
	    $payloadString = implode('|', $payload);

	    return hash_hmac('sha256', $payloadString, $secretKey);
    }

    public function createFpxDirectDebitEnrolmentChecksumValue($secretKey, $data)
    {
        $payload = [
            'order_number' => $data['order_number'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'],
            'payer_email' => $data['payer_email'],
            'payer_telephone_number' => $data['payer_telephone_number'],
            'payer_id_type' => $data['payer_id_type'],
            'payer_id' => $data['payer_id'],
            'application_reason' => $data['application_reason'],
            'frequency_mode' => $data['frequency_mode'],
        ];

        ksort($payload);
        $payloadString = implode('|', $payload);

        return hash_hmac('sha256', $payloadString, $secretKey);
    }

    public function createFpxDirectDebitMaintenanceChecksumValue($secretKey, $data)
    {
        $payload = [
            'amount' => $data['amount'],
            'payer_email' => $data['payer_email'],
            'payer_telephone_number' => $data['payer_telephone_number'],
            'application_reason' => $data['application_reason'],
            'frequency_mode' => $data['frequency_mode'],
        ];

        ksort($payload);
        $payloadString = implode('|', $payload);

        return hash_hmac('sha256', $payloadString, $secretKey);
    }
}
