<?php

declare(strict_types=1);

namespace Sse\Service\Payload;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;

class DatabasePayloadStrategy implements PayloadStrategyInterface
{
    use LocatorAwareTrait;

    protected Table $Payloads;

    public function __construct()
    {
        $this->Payloads = $this->fetchTable(Configure::read('Sse.payloads_model', 'Sse.Payloads'));
    }

    /**
     * @param string $key
     * @param mixed $data
     * @return boolean
     */
    public function store(string $key, mixed $data): bool
    {
        $entity = $this->Payloads->findOrCreate(['stream_key' => $key]);
        $entity->payload = json_encode($data);
        $entity->modified = DateTime::now();

        return (bool)$this->Payloads->save($entity);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function fetch(string $key): mixed
    {
        $result = $this->Payloads->find()
            ->select(['payload'])
            ->where(['stream_key' => $key])
            ->enableHydration(false)
            ->first();

        return $result ? json_decode($result['payload'], true) : null;
    }
}
