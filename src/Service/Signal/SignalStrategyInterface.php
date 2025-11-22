<?php
declare(strict_types=1);

namespace Sse\Service\Signal;

interface SignalStrategyInterface
{
    /**
     * @param string $key
     * @return void
     */
    public function touch(string $key): void;

    /**
     * @param string $key
     * @return integer
     */
    public function getLastModified(string $key): int;
}