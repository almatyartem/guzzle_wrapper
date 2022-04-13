<?php

namespace GuzzleWrapper;

use RpContracts\Cache;
use RpContracts\Logger;
use RpContracts\Response;

class RequestProviderWithCache extends BaseRequestProvider implements \RpContracts\RequestProvider
{
    /**
     * @var Cache|null
     */
    protected ?Cache $cacheProvider;

    /**
     * @var bool
     */
    protected bool $doNotCacheEmptyResponse;

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
        Cache $cacheProvider,
        string $endpoint,
        int $attemptsCountWhenServerError = 1,
        int $sleepTimeBetweenAttempts = 1,
        Logger $logger = null,
        array $defaultOptions = [],
        bool $doNotCacheEmptyResponse = true
    )
    {
        $this->cacheProvider = $cacheProvider;
        $this->doNotCacheEmptyResponse = $doNotCacheEmptyResponse;

        parent::__construct($endpoint, $attemptsCountWhenServerError, $sleepTimeBetweenAttempts, $logger, $defaultOptions);
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
        try {
            if($method == 'get' and !$ignoreCache and $this->cacheProvider and $this->cacheProvider->has($url))
            {
                $fromCache = $this->cacheProvider->get($url);
                if($fromCache instanceof Response)
                {
                    return $fromCache;
                }
            }
        }
        catch (\Exception $exception){}

        $response = parent::request($url, $method, $data, $addHeaders, $postAsForm);

        if($response->isSuccess() and $this->cacheProvider and $cacheTtl!==0)
        {
            if(!$this->doNotCacheEmptyResponse or $response->getContents())
            {
                $this->cacheProvider->put($url, $response, $cacheTtl);
            }
        }

        return $response;
    }
}
