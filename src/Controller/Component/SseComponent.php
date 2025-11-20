<?php

declare(strict_types=1);

namespace Sse\Controller\Component;

use Cake\Controller\Component;
use Cake\Http\CallbackStream;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Utility\Hash;

class SseComponent extends Component
{
    protected array $_defaultConfig = [
        'poll' => 2,
        'eventName' => 'message',
        'heartbeat' => 30,
        'cacheConfig' => 'default',
    ];

    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        $this->setConfig(Configure::read('Sse') ?? []);
        $this->setConfig($config ?? []);
    }

    /**
     * @param callable $dataCallback
     * @param string $watchCacheKey
     * @param array $options
     * @return Response
     */
    public function stream(callable $dataCallback, string $watchCacheKey, array $options = []): Response
    {
        $stream = $this->_buildStream($dataCallback, $watchCacheKey, $options);

        $response = $this->getController()->getResponse();
        $response = $response
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withBody($stream);

        return $response;
    }

    /**
     * @param callable $dataCallback
     * @param string $watchCacheKey
     * @param array $options
     * @return CallbackStream
     */
    protected function _buildStream(callable $dataCallback, string $watchCacheKey, array $options = []): CallbackStream
    {
        $config = Hash::merge($this->getConfig(), $options);

        return new CallbackStream(function () use ($dataCallback, $watchCacheKey, $config) {
            set_time_limit(0);
            $lastSentTimestamp = null;
            $lastHeartbeat = time();
            session_write_close();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $currentTimestamp = Cache::read($watchCacheKey, $config['cacheConfig']);
                if ($currentTimestamp > $lastSentTimestamp) {
                    $data = $dataCallback();
                    echo "event: " . $config['eventName'] . "\n";
                    echo "data: " . json_encode($data) . "\n\n";
                    $lastSentTimestamp = $currentTimestamp;
                    $lastHeartbeat = time();
                } else if (time() - $lastHeartbeat > $config['heartbeat']) {
                    echo ": \n\n";
                    $lastHeartbeat = time();
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep($config['poll']);
            }
        });
    }
}
