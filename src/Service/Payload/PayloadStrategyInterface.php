<?php
declare(strict_types=1);

namespace Sse\Service\Payload;

interface PayloadStrategyInterface
{
    /**
     * @param string $key
     * @param mixed $data
     * @return boolean
     */
    public function store(string $key, mixed $data): bool;

    /**
     * @param string $key
     * @return mixed
     */
    public function fetch(string $key): mixed;
}