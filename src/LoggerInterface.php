<?

namespace GuzzleWrapper;

interface LoggerInterface
{
    /**
     * @param ResponseWrapper $result
     * @param array $requestData
     * @return mixed
     */
    public function log(ResponseWrapper $result, array $requestData);
}
