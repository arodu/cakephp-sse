<?php

declare(strict_types=1);

namespace Sse\Service\Payload;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Log\Log;
use Redis; // Native Redis extension phpredis

class RedisPayloadStrategy implements PayloadStrategyInterface
{
    protected string $cacheConfig;
    protected int $ttl;

    public function __construct()
    {
        $this->cacheConfig = Configure::read('Sse.redis_config') ?? 'sse_redis';
        $this->ttl = 3600;
    }

    /**
     * @return \Redis
     */
    protected function getConnection()
    {
        // Obtenemos el driver y luego su conexión interna
        return Cache::pool($this->cacheConfig)?->driver()->getConnection();
    }

    public function store(string $key, mixed $data): bool
    {
        try {
            $redis = $this->getConnection();
            $payload = json_encode($data);

            return $redis->setex($key, $this->ttl, $payload);
        } catch (\Exception $e) {
            Log::error('SSE Redis Store Error: ' . $e->getMessage());

            return false;
        }
    }

    public function fetch(string $key): mixed
    {
        try {
            $redis = $this->getConnection();
            $payload = $redis->get($key);

            return $payload ? json_decode($payload, true) : null;
        } catch (\Exception $e) {
            Log::error('SSE Redis Fetch Error: ' . $e->getMessage());

            return null;
        }
    }
}
