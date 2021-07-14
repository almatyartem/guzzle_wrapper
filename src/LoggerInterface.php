<?

namespace GuzzleWrapper;

interface LoggerInterface
{
    /**
     * @param ResponseWrapper $result
     * @return mixed
     */
    public function log(ResponseWrapper $result);
}
