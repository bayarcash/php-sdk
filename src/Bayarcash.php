<?php

namespace Bayarcash;

use GuzzleHttp\Client as HttpClient;
use Bayarcash\Resources\FpxBankResource;
use Bayarcash\Resources\PortalResource;
use Bayarcash\Resources\PaymentIntentResource;
use Bayarcash\Resources\TransactionResource;

class Bayarcash
{
    use Actions\FpxDirectDebitPaymentIntent,
        Actions\CallbackVerifications,
        Actions\ChecksumGenerator,
        Actions\ManualBankTransfer,
        MakesHttpRequests;

    /*
     * Payment Channels
     */
    const FPX = 1;
    const MANUAL_TRANSFER = 2;
    const FPX_DIRECT_DEBIT = 3;
    const FPX_LINE_OF_CREDIT = 4;
    const DUITNOW_DOBW = 5;
    const DUITNOW_QR = 6;
    const SPAYLATER = 7;
    const BOOST_PAYFLEX = 8;
    const QRISOB = 9;
    const QRISWALLET = 10;
    const NETS = 11;
    const CREDIT_CARD = 12;
    const ALIPAY = 13;
    const WECHATPAY = 14;
    const PROMPTPAY = 15;
    const TOUCH_N_GO = 16;
    const BOOST_WALLET = 17;
    const GRABPAY = 18;
    const GRABPL = 19;
    const SHOPEE_PAY = 21;

    /**
     * The Bayarcash API Key.
     *
     * @var string
     */
    protected $token;

    /**
     * The Guzzle HTTP Client instance.
     *
     * @var \GuzzleHttp\Client
     */
    public $guzzle;

    /**
     * Number of seconds a request is retried.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * Whether to use sandbox environment.
     *
     * @var bool
     */
    private $sandbox = false;

    /**
     * The API version to use.
     *
     * @var string
     */
    private $apiVersion = 'v2';

    /**
     * Create a new BayarcashSdk instance.
     *
     * @param  string|null  $token
     * @return void
     */
    public function __construct(string $token)
    {
        $this->token = $token;
        $this->initializeGuzzle();
    }

    /**
     * Initialize or reinitialize the Guzzle HTTP client.
     *
     * @return void
     */
    private function initializeGuzzle()
    {
        $this->guzzle = new HttpClient([
            'base_uri' => $this->getBaseUri(),
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Transform the items of the collection to the given class.
     *
     * @param  array  $collection
     * @param  string  $class
     * @param  array  $extraData
     * @return array
     */
    protected function transformCollection($collection, $class, $extraData = [])
    {
        return array_map(function ($data) use ($class, $extraData) {
            return new $class($data + $extraData, $this);
        }, $collection);
    }

    /**
     * Set the api key and setup the guzzle request object.
     *
     * @param  string  $token
     * @param  \GuzzleHttp\Client|null  $guzzle
     * @return $this
     */
    public function setToken($token, $guzzle = null)
    {
        $this->token = $token;
        if ($guzzle) {
            $this->guzzle = $guzzle;
        } else {
            $this->initializeGuzzle();
        }
        return $this;
    }

    /**
     * Set the environment to sandbox and reinitialize the client.
     *
     * @param  \GuzzleHttp\Client|null  $guzzle
     * @return $this
     */
    public function useSandbox($guzzle = null)
    {
        $this->sandbox = true;
        if ($guzzle) {
            $this->guzzle = $guzzle;
        } else {
            $this->initializeGuzzle();
        }
        return $this;
    }

    /**
     * Set a new timeout.
     *
     * @param  int  $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get the timeout.
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the API version and reinitialize the client with the new base URI.
     *
     * @param  string  $version
     * @return $this
     */
    public function setApiVersion($version)
    {
        $this->apiVersion = $version;
        $this->initializeGuzzle();
        return $this;
    }

    /**
     * Get the API version currently in use.
     *
     * @return string
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Get the base URI based on the API version and environment.
     *
     * @return string
     */
    private function getBaseUri()
    {
        if ($this->apiVersion === 'v3') {
            return $this->sandbox
                ? 'https://api.console.bayarcash-sandbox.com/v3/'
                : 'https://api.console.bayar.cash/v3/';
        }

        return $this->sandbox
            ? 'https://console.bayarcash-sandbox.com/api/v2/'
            : 'https://console.bayar.cash/api/v2/';
    }

    /**
     * Get list of FPX banks.
     *
     * @return array
     */
    public function fpxBanksList()
    {
        return $this->transformCollection(
            $this->get('banks'),
            FpxBankResource::class
        );
    }

    /**
     * Get list of portals.
     *
     * @return array
     */
    public function getPortals()
    {
        $response = $this->get('portals');

        return $this->transformCollection(
            $response['data'] ?? $response,
            PortalResource::class
        );
    }

    /**
     * Get available payment channels for a specific portal by portal key.
     *
     * @param  string  $portalKey
     * @return array
     */
    public function getChannels(string $portalKey): array
    {
        $portals = $this->getPortals();

        foreach ($portals as $portal) {
            if ($portal->portalKey === $portalKey) {
                return $portal->paymentChannels;
            }
        }

        return [];
    }

    /**
     * Create a new payment intent.
     *
     * @param  array  $data
     * @return \Bayarcash\Resources\PaymentIntentResource
     */
    public function createPaymentIntent(array $data)
    {
        return new PaymentIntentResource(
            $this->post('payment-intents', $data),
            $this
        );
    }

    /**
     * Get transaction details.
     *
     * @param  string  $id
     * @return \Bayarcash\Resources\TransactionResource
     */
    public function getTransaction($id)
    {
        return new TransactionResource(
            $this->get('transactions/' . $id),
            $this
        );
    }

    /**
     * Get payment intent by ID.
     *
     * @param  string  $paymentIntentId
     * @return \Bayarcash\Resources\PaymentIntentResource
     * @throws \Exception If the API version is not v3
     */
    public function getPaymentIntent(string $paymentIntentId)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getPaymentIntent method is only available for API version v3.');
        }

        return new PaymentIntentResource(
            $this->get('payment-intents/' . $paymentIntentId),
            $this
        );
    }

	/**
	 * Cancel payment intent.
	 *
	 * @param  string  $paymentIntentId
	 * @return \Bayarcash\Resources\PaymentIntentResource
	 * @throws \Exception If the API version is not v3
	 */
	public function cancelPaymentIntent(string $paymentIntentId)
	{
		if ($this->apiVersion !== 'v3') {
			throw new \Exception('The cancelPaymentIntent method is only available for API version v3.');
		}

		return new PaymentIntentResource(
			$this->delete('payment-intents/' . $paymentIntentId),
			$this
		);
	}

    /**
     * Get all transactions with optional filters.
     *
     * @param  array  $parameters
     * @return array
     * @throws \Exception If the API version is not v3
     */
    public function getAllTransactions(array $parameters = [])
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getAllTransactions method is only available for API version v3.');
        }

        $allowedParameters = [
            'order_number',
            'status',
            'payment_channel',
            'exchange_reference_number',
            'payer_email',
        ];

        // Filter out disallowed parameters
        $queryParams = array_filter(
            $parameters,
            function ($key) use ($allowedParameters) {
                return in_array($key, $allowedParameters);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Build query string
        $queryString = http_build_query($queryParams);
        $endpoint = 'transactions' . ($queryString ? '?' . $queryString : '');

        // Make the API request
        $response = $this->get($endpoint);

        // Return the transformed data and meta from the raw API response
        return [
            'data' => $this->transformCollection(
                $response['data'] ?? [],
                TransactionResource::class
            ),
            'meta' => $response['meta'] ?? [],
        ];
    }

    /**
     * Get transaction by order number.
     *
     * @param  string  $orderNumber
     * @return array
     * @throws \Exception If the API version is not v3
     */
    public function getTransactionByOrderNumber(string $orderNumber)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getTransactionByOrderNumber method is only available for API version v3.');
        }

        $response = $this->get('transactions?order_number=' . $orderNumber);
        return $this->transformCollection(
            $response['data'] ?? [],
            TransactionResource::class
        );
    }

    /**
     * Get transactions by payer email.
     *
     * @param  string  $email
     * @return array
     * @throws \Exception If the API version is not v3
     */
    public function getTransactionsByPayerEmail(string $email)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getTransactionsByPayerEmail method is only available for API version v3.');
        }

        $response = $this->get('transactions?payer_email=' . urlencode($email));
        return $this->transformCollection(
            $response['data'] ?? [],
            TransactionResource::class
        );
    }

    /**
     * Get transactions by status.
     *
     * @param  string  $status
     * @return array
     * @throws \Exception If the API version is not v3
     */
    public function getTransactionsByStatus(string $status)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getTransactionsByStatus method is only available for API version v3.');
        }

        $response = $this->get('transactions?status=' . $status);
        return $this->transformCollection(
            $response['data'] ?? [],
            TransactionResource::class
        );
    }

    /**
     * Get transactions by payment channel.
     *
     * @param  int  $channel
     * @return array
     * @throws \Exception If the API version is not v3
     */
    public function getTransactionsByPaymentChannel(int $channel)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getTransactionsByPaymentChannel method is only available for API version v3.');
        }

        $response = $this->get('transactions?payment_channel=' . $channel);
        return $this->transformCollection(
            $response['data'] ?? [],
            TransactionResource::class
        );
    }

    /**
     * Get transaction by exchange reference number.
     *
     * @param  string  $referenceNumber
     * @return \Bayarcash\Resources\TransactionResource|null
     * @throws \Exception If the API version is not v3
     */
    public function getTransactionByReferenceNumber(string $referenceNumber)
    {
        // Check if the API version is v3
        if ($this->apiVersion !== 'v3') {
            throw new \Exception('The getTransactionByReferenceNumber method is only available for API version v3.');
        }

        $response = $this->get('transactions?exchange_reference_number=' . urlencode($referenceNumber));
        $data = $response['data'] ?? [];
        if (empty($data)) {
            return null;
        }
        return $this->transformCollection($data, TransactionResource::class)[0] ?? null;
    }
}
