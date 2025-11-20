<?php
declare(strict_types=1);

namespace Sse\Sse;

use Cake\Cache\Cache;
use Cake\Core\Configure;

class SseTrigger
{
    public static function push(string $cacheKey, ?string $cacheConfig = null): bool
    {
        $cacheConfig = $cacheConfig ?? Configure::read('Sse.cacheConfig') ?? 'default';

        return Cache::write($cacheKey, microtime(true), $cacheConfig);
    }
}