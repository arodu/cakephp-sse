<?php
declare(strict_types=1);

namespace Sse\Service\Signal;

class FileSignalStrategy implements SignalStrategyInterface
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = TMP . 'sse_signals' . DS;

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0777, true);
        }
    }

    /**
     * @param string $key
     * @return void
     */
    public function touch(string $key): void
    {
        touch($this->basePath . md5($key) . '.signal');
    }

    /**
     * @param string $key
     * @return integer
     */
    public function getLastModified(string $key): int
    {
        $file = $this->basePath . md5($key) . '.signal';
        clearstatcache(true, $file);

        if (!file_exists($file)) {
            return 0;
        }

        return filemtime($file);
    }
}
