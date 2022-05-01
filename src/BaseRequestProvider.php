<?php

namespace GuzzleWrapper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RpContracts\Logger;
use RpContracts\RequestData;
use RpContracts\Response;

class BaseRequestProvider implements \RpContracts\RequestProvider
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
     * @var array
     */
    protected array $defaultOptions = [];

    /**
     * @var int
     */
    protected static int $proxyNum = 0;

    /**
     * RequestProvider constructor.
     * @param string $endpoint
     * @param int $attemptsCountWhenServerError
     * @param int $sleepTimeBetweenAttempts
     * @param Logger|null $logger
     * @param array $defaultOptions
     */
    public function __construct(
        string $endpoint,
        int $attemptsCountWhenServerError = 1,
        int $sleepTimeBetweenAttempts = 1,
        Logger $logger = null,
        array $defaultOptions = []
    )
    {
        $this->httpClient = new Client(['verify' => false]);
        $this->endpoint = $endpoint;
        $this->attemptsCountWhenServerError = $attemptsCountWhenServerError;
        $this->sleepTimeBetweenAttempts = $sleepTimeBetweenAttempts;
        $this->logger = $logger;
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
        $options = $this->defaultOptions;

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
                'url' => $this->endpoint . '/' .$url,
                'method' => $method,
                'options' => $options
            ]);
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
     * @param $url
     * @param string $method
     * @param array $options
     * @param int $i
     * @return Response
     */
    protected function sendRequestHandler($url, string $method, array $options = []) : Response
    {
        if(isset($options['proxy']) and is_array($options['proxy']))
        {
            if(!isset($options['proxy'][self::$proxyNum]))
            {
                self::$proxyNum = 0;
            }

            $options['proxy'] = $options['proxy'][self::$proxyNum];

            self::$proxyNum++;
        }

        $currentAttempt = 0;
        $response = null;
        $errorsBag = [];

        do
        {
            $tryAgain = false;
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
                if ($e->hasResponse())
                {
                    $response = $e->getResponse();
                    if($response->getStatusCode() == 429 and isset($options['proxy']) and is_array($options['proxy']))
                    {
                        $errorsBag[] = $e;
                        $tryAgain = true;
                    }
                }

                $errorsBag[] = $e;
            }
            catch (\Throwable $e)
            {
                $tryAgain = true;
                $errorsBag[] = $e;
            }

            $currentAttempt++;
        }
        while($tryAgain and ($currentAttempt <= $this->attemptsCountWhenServerError));

        return new ResponseWrapper($response, ($errorsBag ? $errorsBag : null));
    }
}
