<?php
declare(strict_types=1);

namespace Sse\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\TestSuite\TestCase;
use Sse\Controller\Component\SseComponent;

/**
 * Sse\Controller\Component\SseComponent Test Case
 */
class SseComponentTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Sse\Controller\Component\SseComponent
     */
    protected $Sse;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $registry = new ComponentRegistry();
        $this->Sse = new SseComponent($registry);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Sse);

        parent::tearDown();
    }
}
