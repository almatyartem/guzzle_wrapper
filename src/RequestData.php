<?php

namespace GuzzleWrapper;

class RequestData implements \RpContracts\RequestData
{
    protected string $url;
    protected string $method;
    protected array $data;
    protected array $addHeaders;
    protected bool $postAsForm;
    protected ?int $cacheTtl;
    protected bool $ignoreCache;

    public function __construct(
        string $url,
        string $method = 'get',
        array $data = [],
        array $addHeaders = [],
        bool $postAsForm = false,
        int $cacheTtl = null,
        bool $ignoreCache = false
    )
    {
        $this->url = $url;
        $this->method = $method;
        $this->data = $data;
        $this->addHeaders = $addHeaders;
        $this->postAsForm = $postAsForm;
        $this->cacheTtl = $cacheTtl;
        $this->ignoreCache = $ignoreCache;
    }

    /**
     * @return $this
     */
    public function ignoreCache() : RequestData
    {
        $this->cacheTtl = 0;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return ($this->data ? $this->data : null);
    }

    /**
     * @return array|null
     */
    public function getHeaders(): ?array
    {
        return ($this->addHeaders ? $this->addHeaders : null);
    }

    /**
     * @return bool
     */
    public function postAsForm(): bool
    {
        return $this->postAsForm;
    }

    /**
     * @return int|null
     */
    public function getCacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    /**
     * @return bool
     */
    public function shouldIgnoreCache(): bool
    {
        return $this->ignoreCache;
    }
}
