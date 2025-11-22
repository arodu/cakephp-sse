<?php

declare(strict_types=1);

namespace Sse\Service;

use Cake\Core\Configure;
use Sse\Service\Payload\CachePayloadStrategy;
use Sse\Service\Payload\DatabasePayloadStrategy;
use Sse\Service\Payload\PayloadStrategyInterface;
use Sse\Service\Payload\RedisPayloadStrategy;
use Sse\Service\Signal\FileSignalStrategy;
use Sse\Service\Signal\SignalStrategyInterface;

class SseService
{
    const PAYLOAD_CACHE_ENGINE = 'cache';
    const PAYLOAD_DATABASE_ENGINE = 'database';
    const PAYLOAD_REDIS_ENGINE = 'redis';

    const SIGNAL_FILE_ENGINE = 'file';

    protected static ?PayloadStrategyInterface $_payloadStrategy = null;
    protected static ?SignalStrategyInterface $_signalStrategy = null;

    /**
     * @param string $key
     * @param mixed $payload
     * @return boolean
     */
    public static function push(string $key, mixed $payload): bool
    {
        $saved = self::getPayloadStrategy()->store($key, $payload);
        if ($saved) {
            self::getSignalStrategy()->touch($key);
        }

        return $saved;
    }

    /**
     * @param string $key
     * @param integer $lastKnownTime
     * @return array|null
     */
    public static function check(string $key, int $lastKnownTime): ?array
    {
        $signal = self::getSignalStrategy();
        $modifiedAt = $signal->getLastModified($key);

        if ($modifiedAt > $lastKnownTime) {
            return [
                'time' => $modifiedAt,
                'data' => self::getPayloadStrategy()->fetch($key)
            ];
        }

        return null;
    }

    /**
     * Resets the strategies, for testing purposes.
     * 
     * @return void
     */
    public static function reset(): void
    {
        self::$_payloadStrategy = null;
        self::$_signalStrategy = null;
    }

    /**
     * @return SignalStrategyInterface
     */
    protected static function getSignalStrategy(): SignalStrategyInterface
    {
        if (self::$_signalStrategy) return self::$_signalStrategy;

        $configValue = Configure::read('Sse.signal_engine') ?? self::SIGNAL_FILE_ENGINE;
        $aliases = [
            self::SIGNAL_FILE_ENGINE => FileSignalStrategy::class,
        ];
        $className = $aliases[$configValue] ?? $configValue;
        self::$_signalStrategy = self::instantiateStrategy($className, SignalStrategyInterface::class);

        return self::$_signalStrategy;
    }

    /**
     * @return PayloadStrategyInterface
     */
    protected static function getPayloadStrategy(): PayloadStrategyInterface
    {
        if (self::$_payloadStrategy) return self::$_payloadStrategy;

        $configValue = Configure::read('Sse.payload_engine') ?? self::PAYLOAD_CACHE_ENGINE;
        $aliases = [
            self::PAYLOAD_DATABASE_ENGINE => DatabasePayloadStrategy::class,
            self::PAYLOAD_CACHE_ENGINE => CachePayloadStrategy::class,
            self::PAYLOAD_REDIS_ENGINE => RedisPayloadStrategy::class,
        ];
        $className = $aliases[$configValue] ?? $configValue;
        self::$_payloadStrategy = self::instantiateStrategy($className, PayloadStrategyInterface::class);

        return self::$_payloadStrategy;
    }

    /**
     * @param string $className
     * @param string $interface
     * @return object
     */
    private static function instantiateStrategy(string $className, string $interface): object
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("SSE Strategy: the class '$className' does not exist.");
        }

        $instance = new $className();

        if (!($instance instanceof $interface)) {
            throw new \InvalidArgumentException("SSE Strategy: the class '$className' must implement '$interface'.");
        }

        return $instance;
    }
}
