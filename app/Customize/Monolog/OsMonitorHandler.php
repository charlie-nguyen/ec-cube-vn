<?php
namespace Customize\Monolog;

class OsMonitorHandler extends \Monolog\Handler\AbstractHandler
{
    /**
     * OsMonitorHandler constructor.
     * @param int $level
     * @param bool $bubble
     * @throws \Exception
     */
    public function __construct(
        $level = \Monolog\Logger::WARNING,
        $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     *
     * @param array $record
     * @return bool
     */
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $token = getenv('OS_STORE_AUTH_TOKEN');
        $apiEndpoint = getenv('OS_STORE_API_ENDPOINT');
        $exception = isset($record['context']['exception']) ? $record['context']['exception'] : null;
        if ($token && $apiEndpoint) {
            $header = sprintf('Authorization: Bearer %s', $token);
            $endpoint =  $apiEndpoint . '/api/v1/monitor/exception';
            if ($exception instanceof \Exception) {
                $data = [
                    'domain' =>  isset($record['extra']['server']) ? $record['extra']['server'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Unknown'),
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'severity' => method_exists($exception, 'getSeverity') ? $exception->getSeverity() : '',
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'extra' => isset($record['extra']) ? json_encode($record['extra']) : '{}'
                ];
            } elseif (is_array($record['context']) && count($record['context']) === 4) {
                $data = [
                    'domain' =>  isset($record['extra']['server']) ? $record['extra']['server'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Unknown'),
                    'type' => 'Exception',
                    'message' => $record['context'][0],
                    'code' => 0,
                    'severity' => isset($record['level_name']) ? $record['level_name'] : 'ERROR',
                    'file' => $record['context'][1],
                    'line' => $record['context'][2],
                    'trace' => $record['context'][3],
                    'extra' => isset($record['extra']) ? json_encode($record['extra']) : '{}'
                ];
            }
            if (isset($data)) {
                $data = str_replace("'", "&#39;", json_encode($data));
                $cmd = sprintf('curl -X POST -H "Content-Type: application/json" -H "%s" -d \'%s\' %s', $header, $data, $endpoint);
                exec($cmd,$output);
            }
        }
    }
}