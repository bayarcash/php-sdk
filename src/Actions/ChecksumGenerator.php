<?php

namespace Bayarcash\Actions;

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

    /**
     * Checksum for saveCard(): the payer is the customer, so pass the customer's
     * full name and email as stored (getCustomer($id)->fullName / ->email), not
     * cleaned or trimmed. `payment_channel` is 12 (Credit Card) when left out.
     * The checksum is optional: leave it out of the body instead if you prefer.
     */
    public function createSaveCardChecksumValue($secretKey, array $data, string $customerFullName, string $customerEmail)
    {
        $data['payment_channel'] = $data['payment_channel'] ?? 12;
        $data['payer_name'] = $customerFullName;
        $data['payer_email'] = $customerEmail;

        return $this->createPaymentIntentChecksumValue($secretKey, $data);
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
