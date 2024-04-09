<?php

namespace Outofbox\OutofboxSDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use LogicException;
use Outofbox\OutofboxSDK\API\AuthTokenRequest;
use Outofbox\OutofboxSDK\API\RequestInterface;
use Outofbox\OutofboxSDK\API\ResponseInterface;
use Outofbox\OutofboxSDK\API\ShopOrders\GetShopOrderRequest;
use Outofbox\OutofboxSDK\Exception\OutofboxAPIException;
use Outofbox\OutofboxSDK\Serializer\ProductDenormalizer;
use Outofbox\OutofboxSDK\Serializer\ShipmentDenormalizer;
use Outofbox\OutofboxSDK\Serializer\ShopOrderDenormalizer;
use PHPUnit\Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;

/**
 * Class OutofboxAPIClient
 *
 * @package Outofbox\OutofboxSDK
 *
 * @method API\AuthTokenResponse sendAuthTokenRequest(API\AuthTokenRequest $request)
 * @method API\ProductsListResponse sendProductsListRequest(API\ProductsListRequest $request)
 * @method API\ProductsListResponse sendContractorsProductsListRequest(API\Products\ContractorProductsListRequest $request)
 * @method API\ProductViewResponse sendProductViewRequest(API\ProductViewRequest $request)
 * @method API\Products\ProductUpdateResponse sendProductUpdateRequest(API\Products\ProductUpdateRequest $request)
 * @method API\Categories\CategoriesListResponse sendCategoriesListRequest(API\Categories\CategoriesListRequest $request)
 * @method API\Warehouse\StoresListResponse sendStoresListRequest(API\Warehouse\StoresListRequest $request)
 * @method API\ShopOrders\CreateShopOrderResponse sendCreateShopOrderRequest(API\ShopOrders\CreateShopOrderRequest $request)
 * @method API\ShopOrders\GetShopOrderResponse sendGetShopOrderRequest(API\ShopOrders\GetShopOrderRequest $request)
 * @method API\Shipments\ShipmentRegisterResponse sendShipmentRegisterRequest(API\Shipments\ShipmentRegisterRequest $request)
 * @method API\Shipments\ShipmentByBarcodeResponse sendShipmentByBarcodeRequest(API\Shipments\ShipmentByBarcodeRequest $request)
 */
class OutofboxAPIClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static array $default_http_client_options = [
        'connect_timeout' => 4,
        'timeout' => 10
    ];

    protected string $base_uri;
    protected string $username;
    protected string $token;
    protected Client $httpClient;
    protected Serializer $serializer;

    private ProductDenormalizer $productDenormalizer;
    private ShopOrderDenormalizer $shopOrderDenormalizer;
    private ShipmentDenormalizer $shipmentDenormalizer;

    /**
     * OutofboxAPIClient constructor.
     */
    public function __construct(string $base_uri, string $username, string $token, Client|array|null $httpClientOrOptions = null)
    {
        $this->base_uri = $base_uri;
        $this->username = $username;
        $this->token = $token;

        $this->httpClient = self::createHttpClient($httpClientOrOptions);

        $this->logger = new NullLogger();


        $this->shipmentDenormalizer = new ShipmentDenormalizer();
        $this->productDenormalizer = new ProductDenormalizer();
        $this->shopOrderDenormalizer = new ShopOrderDenormalizer();

        $this->serializer = new Serializer([
            $this->productDenormalizer,
            $this->shipmentDenormalizer,
            $this->shopOrderDenormalizer,
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, new PhpDocExtractor())

        ]);
    }

    /**
     * Get auth token
     */
    public function getAuthToken(string $password, array|Client|null $httpClientOrOptions = null): string
    {
        $response = $this->sendAuthTokenRequest(new AuthTokenRequest($this->username, $password));
        return $response->getToken();
    }

    /**
     * @return Model\ShopOrder
     */
    public function getShopOrder(string $order_number)
    {
        return $this->sendGetShopOrderRequest(GetShopOrderRequest::createWithShopOrderNumber($order_number))->getShopOrder();
    }

    public function __call($name, array $arguments)
    {
        if (str_starts_with($name, 'send')) {
            return call_user_func_array([$this, 'sendRequest'], $arguments);
        }
        $this->logger->debug(\sprintf('Method [%s] not found in [%s].', $name, __CLASS__));
        throw new \BadMethodCallException(\sprintf('Method [%s] not found in [%s].', $name, __CLASS__));
    }

    /**
     * @throws OutofboxAPIException
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            //$this->logger->debug('sendRequest before wait');
            /** @var Response $response */
            $response = $this->createAPIRequestPromise($request)->wait();
            //$this->logger->debug('sendRequest after wait');
        } catch (BadResponseException $e) {
            self::handleErrorResponse($e->getResponse(), $this->logger);
            throw new OutofboxAPIException('Outofbox API Request error: ' . $e->getMessage());
        }

        return $this->createAPIResponse($response, $request->getResponseClass());
    }

    public function createAPIRequestPromise(RequestInterface $request):PromiseInterface
    {
        $request_params = $request->createHttpClientParams();

        $this->logger->debug('Outofbox API request {method} {uri}', [
            'method' => $request->getHttpMethod(),
            'uri' => $request->getUri(),
            'request_params' => $request_params
        ]);

        if ($this->token) {
            $request_params = array_merge_recursive($request_params, [
                'headers' => [
                    'X-WSSE' => $this->generateWsseHeader()
                ]
            ]);
        }

        if (!isset($request_params['base_uri'])) {
            $request_params['base_uri'] = $this->base_uri;
        }

        /*
        $stack = HandlerStack::create();
        $stack->push(
            Middleware::log(
                $this->logger,
                new MessageFormatter(MessageFormatter::DEBUG)
            )
        );

        $params['handler'] = $stack;
        */

        return $this->httpClient->requestAsync($request->getHttpMethod(), $request->getUri(), $request_params);
    }

    /**
     * @throws OutofboxAPIException
     */
    protected function createAPIResponse(\Psr\Http\Message\ResponseInterface $response, string $apiResponseClass):ResponseInterface
    {
        //$this->logger->debug('API RESPONSE 1');
        $response_string = (string)$response->getBody();
        $response_data = json_decode($response_string, true);
        //$this->logger->debug('API RESPONSE 2');

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->debug('Invalid response data');
            throw new OutofboxAPIException('Invalid response data');
        }
        //$this->logger->debug('API RESPONSE 3');

        if (isset($response_data['code'], $response_data['message'])) {
            $this->logger->debug('Outofbox API Error: ' . $response_data['message'], $response_data['code']);
            throw new OutofboxAPIException('Outofbox API Error: ' . $response_data['message'], $response_data['code']);
        }
        //$this->logger->debug('API RESPONSE 4');

        try {
            /** @var ResponseInterface $response */

            $this->shipmentDenormalizer->setLogger($this->logger);
            $this->productDenormalizer->setLogger($this->logger);
            $this->shopOrderDenormalizer->setLogger($this->logger);

            $response = $this->serializer->denormalize($response_data, $apiResponseClass);
        } catch (RuntimeException $e) {
            $this->logger->debug('Unable to decode response: ' . $e->getMessage());
            throw new OutofboxAPIException('Unable to decode response: ' . $e->getMessage());
        } catch(LogicException $exception ){
            $this->logger->debug('Unable to decode response due logick errors: ' . $exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->debug('Unable to decode response due errors: ' . $exception->getMessage());
        } catch(Throwable $exception){
            $this->logger->debug('Unable to decode response due errors2: ' . $exception->getMessage());
        }


        //$this->logger->debug('API RESPONSE 5');
        return $response;
    }

    /**
     * @throws OutofboxAPIException
     */
    protected static function handleErrorResponse(\Psr\Http\Message\ResponseInterface|null $response = null, LoggerInterface $logger = null): void
    {
        if (!$response) {
            return;
        }

        $response_string = (string)$response->getBody();

        if ($response_string) {
            $response_data = json_decode($response_string, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $logger?->debug('Unable to decode error response data. Error: ' . json_last_error_msg());
                throw new OutofboxAPIException('Unable to decode error response data. Error: ' . json_last_error_msg());
            }

            if (isset($response_data['error'])) {
                $exception = new OutofboxAPIException($response_data['error']['message']);

                if (isset($response_data['error']['code'])) {
                    $exception->setErrorCode($response_data['error']['code']);
                }
                $logger?->debug($exception->getMessage());
                throw $exception;
            }
        }
    }

    protected function generateWsseHeader(): string
    {
        $nonce = hash('sha512', uniqid('', true));
        $created = date('c');
        $digest = base64_encode(sha1(base64_decode($nonce) . $created . $this->token, true));

        return sprintf(
            'UsernameToken Username="%s", PasswordDigest="%s", Nonce="%s", Created="%s"',
            $this->username,
            $digest,
            $nonce,
            $created
        );
    }

    public function setHttpClient(Client $httpClient): self
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    protected static function createHttpClient(array|Client|null $httpClientOrOptions = null): Client
    {
        $httpClient = null;
        if ($httpClientOrOptions instanceof Client) {
            $httpClient = $httpClientOrOptions;
        } elseif (is_array($httpClientOrOptions)) {
            $httpClient = new Client($httpClientOrOptions);
        } else {
            $httpClient = new Client(self::$default_http_client_options);
        }

        return $httpClient;
    }
}
