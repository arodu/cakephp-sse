<?php
declare(strict_types=1);

namespace Sse\Sse;

use Cake\Cache\Cache;
use Cake\Core\Configure;

class SseTrigger
{
    /**
     * Push a new payload into the SSE cache queue
     *
     * @param string $cacheKey The cache key to use for storing the SSE data.
     * @param mixed $payload The data payload to push.
     * @param string|null $cacheConfig Optional cache configuration name.
     * @return bool True on success, false on failure.
     */
    public static function push(string $cacheKey, mixed $payload, ?string $cacheConfig = null): bool
    {
        $config = $cacheConfig ?? Configure::read('Sse.cacheConfig') ?? 'default';
        $currentQueue = Cache::read($cacheKey, $config) ?: [];
        if (!is_array($currentQueue)) {
            $currentQueue = [];
        }
        $currentQueue[] = $payload;

        return Cache::write($cacheKey, $currentQueue, $config);
    }
}