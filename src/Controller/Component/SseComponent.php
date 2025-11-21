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
     * @param string $watchCacheKey
     * @param array $options
     * @return Response
     */
    public function stream(string $watchCacheKey, array $options = []): Response
    {
        $stream = $this->_buildStream($watchCacheKey, $options);

        return $this->getController()->getResponse()
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Connection', 'keep-alive')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withBody($stream);
    }

    /**
     * @param string $watchCacheKey
     * @param array $options
     * @return \Cake\Http\CallbackStream
     */
    protected function _buildStream(string $watchCacheKey, array $options = []): CallbackStream
    {
        $config = Hash::merge($this->getConfig(), $options);

        return new CallbackStream(function () use ($watchCacheKey, $config) {
            set_time_limit(0);
            $lastHeartbeat = time();
            session_write_close();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $queue = Cache::read($watchCacheKey, $config['cacheConfig']);
                if (!empty($queue) && is_array($queue)) {

                    foreach ($queue as $payload) {
                        $evtName = is_array($payload) && isset($payload['event'])
                            ? $payload['event']
                            : $config['eventName'];

                        echo "event: " . $evtName . "\n";
                        echo "data: " . json_encode($payload) . "\n\n";
                    }

                    Cache::write($watchCacheKey, [], $config['cacheConfig']);
                    $lastHeartbeat = time();
                } elseif (time() - $lastHeartbeat >= $config['heartbeat']) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeat = time();
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep((int)$config['poll']);
            }
        });
    }
}
