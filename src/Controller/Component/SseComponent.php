<?php

declare(strict_types=1);

namespace Sse\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Http\CallbackStream;
use Cake\Http\Response;
use Cake\Utility\Hash;
use Sse\Service\SseService;

class SseComponent extends Component
{
    protected array $_defaultConfig = [
        'poll' => 2, // seconds
        'eventName' => 'message',
        'heartbeat' => 25, // seconds
        'beforeSend' => null,
        'onLoop' => null,
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
     * @return CallbackStream
     */
    protected function _buildStream(string $watchCacheKey, array $options = []): CallbackStream
    {
        $config = Hash::merge($this->getConfig(), $options);

        return new CallbackStream(function () use ($watchCacheKey, $config) {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            ini_set('zlib.output_compression', '0');
            ini_set('output_buffering', 'Off');
            ini_set('implicit_flush', '1');

            while (ob_get_level() > 0) @ob_end_flush();

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            echo "event: connected\n";
            echo "data: {\"time\": \"" . date('H:i:s') . "\"}\n\n";
            flush();

            $lastCheck = 0;
            $lastHeartbeat = time();

            while (true) {
                if (connection_aborted()) break;

                if (isset($config['onLoop']) && is_callable($config['onLoop'])) {
                    call_user_func($config['onLoop']);
                }

                $update = SseService::check($watchCacheKey, $lastCheck);

                if ($update) {
                    $rawPayload = $update['data'];
                    $lastCheck = $update['time'];

                    if (isset($config['beforeSend']) && is_callable($config['beforeSend'])) {
                        $processedPayload = call_user_func($config['beforeSend'], $rawPayload);
                    } else {
                        $processedPayload = $rawPayload;
                    }

                    if (!empty($processedPayload) && is_array($processedPayload)) {
                        $evtName = $processedPayload['event'] ?? $config['eventName'];
                        unset($processedPayload['event']);

                        echo "event: " . $evtName . "\n";
                        echo "data: " . json_encode($processedPayload, JSON_THROW_ON_ERROR) . "\n\n";
                        flush();

                        $lastHeartbeat = time();
                    }
                } elseif (time() - $lastHeartbeat >= $config['heartbeat']) {
                    echo ": heartbeat\n\n";
                    flush();

                    $lastHeartbeat = time();
                }

                sleep((int)$config['poll']);
            }
        });
    }
}
