<?php
declare(strict_types=1);

namespace Sse\Service\Payload;

use Cake\Cache\Cache;
use Cake\Core\Configure;

class CachePayloadStrategy implements PayloadStrategyInterface
{
    protected string $configName;

    public function __construct()
    {
        $this->configName = Configure::read('Sse.cache_config') ?? 'default';
    }

    public function store(string $key, mixed $data): bool
    {
        return Cache::write($key, $data, $this->configName);
    }

    public function fetch(string $key): mixed
    {
        return Cache::read($key, $this->configName);
    }
}