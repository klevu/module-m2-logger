<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Handler;

use Klevu\Logger\Handler\LogDisabled;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Handler\HandlerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogIfConfigured
 */
class LogDisabledTest extends TestCase
{
    use FileSystemTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
    }

    public function testImplements_HandlerInterface(): void
    {
        $this->assertInstanceOf(
            HandlerInterface::class,
            $this->instantiateHandlerLogIfConfigured(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 500
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 200
     */
    public function testIsHandling_ReturnsFalse(): void
    {
        $record = ['level' => 400];
        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertFalse(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogDisabled
     */
    private function instantiateHandlerLogIfConfigured(?array $params = []): LogDisabled
    {
        return $this->objectManager->create(
            LogDisabled::class,
            $params,
        );
    }
}
