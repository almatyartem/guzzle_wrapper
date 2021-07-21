<?php

namespace GuzzleWrapper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use RpContracts\Logger;
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
     * RequestProvider constructor.
     * @param string $endpoint
     * @param int $attemptsCountWhenServerError
     * @param int $sleepTimeBetweenAttempts
     * @param Logger|null $logger
     */
    public function __construct(
        string $endpoint,
        int $attemptsCountWhenServerError = 1,
        int $sleepTimeBetweenAttempts = 1,
        Logger $logger = null
    )
    {
        $this->httpClient = new Client(['verify' => false]);
        $this->endpoint = $endpoint;
        $this->attemptsCountWhenServerError = $attemptsCountWhenServerError;
        $this->sleepTimeBetweenAttempts = $sleepTimeBetweenAttempts;
        $this->logger = $logger;
    }

    /**
     * @param string $api
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $addHeaders
     * @param bool $postAsForm
     * @return Response
     */
    public function request(string $url, string $method = 'get', array $data = [], array $addHeaders = [], bool $postAsForm = false) : Response
    {
        $options = [];

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

        return $response;
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
        $e = null;
        $errorsBag = [];

        do{
            if($currentAttempt > 0)
            {
                sleep($this->sleepTimeBetweenAttempts);
            }
            try
            {
                $response = $this->httpClient->request($method, $this->endpoint . '/' .$url, $options);
            }
            catch (\Throwable $e)
            {
                $errorsBag[] = $e;
            }

            $currentAttempt++;
        }
        while(($e instanceof ServerException) and ($currentAttempt <= $this->attemptsCountWhenServerError));

        return new ResponseWrapper($response, ($errorsBag ? $errorsBag : null));
    }
}
