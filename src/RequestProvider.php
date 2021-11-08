<?php

namespace GuzzleWrapper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use RpContracts\Cache;
use RpContracts\Logger;
use RpContracts\RequestData;
use RpContracts\Response;

class RequestProvider implements \RpContracts\RequestProvider
{
    /**
     * @var Client
     */
    protected Client $httpClient;

    /**
     * @var string
     */
    protected string $endpoint;

    /**
     * @var int
     */
    protected int $attemptsCountWhenServerError;

    /**
     * @var int
     */
    protected int $sleepTimeBetweenAttempts;

    /**
     * @var Logger|null
     */
    protected ?Logger $logger;

    /**
     * @var Cache|null
     */
    protected ?Cache $cacheProvider;

    /**
     * @var bool
     */
    protected bool $doNotCacheEmptyResponse;

    /**
     * @var array
     */
    protected array $defaultOptions = [];

    /**
     * RequestProvider constructor.
     * @param string $endpoint
     * @param int $attemptsCountWhenServerError
     * @param int $sleepTimeBetweenAttempts
     * @param Logger|null $logger
     * @param Cache|null $cacheProvider
     * @param bool $doNotCacheEmptyResponse
     * @param array $defaultOptions
     */
    public function __construct(
        string $endpoint,
        int $attemptsCountWhenServerError = 1,
        int $sleepTimeBetweenAttempts = 1,
        Logger $logger = null,
        Cache $cacheProvider = null,
        bool $doNotCacheEmptyResponse = true,
        array $defaultOptions = []
    )
    {
        $this->httpClient = new Client(['verify' => false]);
        $this->endpoint = $endpoint;
        $this->attemptsCountWhenServerError = $attemptsCountWhenServerError;
        $this->sleepTimeBetweenAttempts = $sleepTimeBetweenAttempts;
        $this->logger = $logger;
        $this->cacheProvider = $cacheProvider;
        $this->doNotCacheEmptyResponse = $doNotCacheEmptyResponse;
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * @param array $defaultOptions
     * @return $this
     */
    public function setDefaultOptions(array $defaultOptions) : RequestProvider
    {
        $this->defaultOptions = $defaultOptions;

        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $addHeaders
     * @param bool $postAsForm
     * @param int|null $cacheTtl
     * @param bool $ignoreCache
     * @return Response
     */
    public function request(
        string $url,
        string $method = 'get',
        array $data = [],
        array $addHeaders = [],
        bool $postAsForm = false,
        int $cacheTtl = null,
        bool $ignoreCache = false
    ) : Response
    {
        if($method == 'get' and !$ignoreCache and $this->cacheProvider and $this->cacheProvider->has($url))
        {
            $fromCache = $this->cacheProvider->get($url);
            if($fromCache instanceof Response)
            {
                return $fromCache;
            }
        }

        $options = $this->defaultOptions;
        if(isset($options['proxy']) and is_array($options['proxy']))
        {
            $options['proxy'] = $options['proxy'][array_rand($options['proxy'])];
        }

        if($method != 'get' and $data)
        {
            if($postAsForm)
            {
                $options['form_params'] = $data;
            }
            else
            {
                $options['json'] = $data;
            }
        }

        $options['headers'] = ['Accept' => 'application/json'];

        if($addHeaders)
        {
            $options['headers'] = array_merge($options['headers'], $addHeaders);
        }

        $response = $this->sendRequestHandler($url, $method, $options);

        if($this->logger)
        {
            $this->logger->log($response, [
                'url' => $url,
                'method' => $method,
                'options' => $options
            ]);
        }

        if($response->isSuccess() and $this->cacheProvider and $cacheTtl!==0)
        {
            if(!$this->doNotCacheEmptyResponse or $response->getContents())
            {
                $this->cacheProvider->put($url, $response, $cacheTtl);
            }
        }

        return $response;
    }

    /**
     * @param RequestData $request
     * @return Response
     */
    public function performRequest(RequestData $request) : Response
    {
        return $this->request($request->getUrl(), $request->getMethod(), $request->getData(), $request->getHeaders(), $request->postAsForm(), $request->getCacheTtl(), $request->shouldIgnoreCache());
    }

    /**
     * @param string $method
     * @param $url
     * @param array $options
     * @return Response
     */
    protected function sendRequestHandler($url, string $method, array $options = []) : Response
    {
        $currentAttempt = 0;
        $response = null;
        $errorsBag = [];

        do{
            $e = null;
            if($currentAttempt > 0)
            {
                sleep($this->sleepTimeBetweenAttempts);
            }
            try
            {
                $response = $this->httpClient->request($method, $this->endpoint . '/' .$url, $options);
            }
            catch (RequestException $e)
            {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                }

                $errorsBag[] = $e;
            }
            catch (\Throwable $e)
            {
                $errorsBag[] = $e;
            }

            $currentAttempt++;
        }
        while(isset($e) and ($e instanceof ServerException) and ($currentAttempt <= $this->attemptsCountWhenServerError));

        return new ResponseWrapper($response, ($errorsBag ? $errorsBag : null));
    }
}
