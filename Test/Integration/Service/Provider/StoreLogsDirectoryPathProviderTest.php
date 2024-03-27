<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Klevu\Logger\Test\Integration\Service\Provider;

use Klevu\Logger\Service\Provider\StoreLogsDirectoryPathProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryPathProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers StoreLogsDirectoryPathProvider
 */
class StoreLogsDirectoryPathProviderTest extends TestCase
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

    public function testImplements_StoreLogsDirectoryProviderInterface(): void
    {
        $this->assertInstanceOf(
            StoreLogsDirectoryPathProviderInterface::class,
            $this->objectManager->create(StoreLogsDirectoryPathProvider::class),
        );
    }

    public function testGetDirectoryPath_ThrowsException_IfStoreIdIncorrect(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $storeLogsProvider = $this->instantiateStoreLogsPathProvider();
        $storeLogsProvider->get(293857289324);
    }

    public function testGetDirectoryPath_ReturnsStringPath_ToExistingDirectory(): void
    {
        $storeLogsProvider = $this->instantiateStoreLogsPathProvider();
        $path = $storeLogsProvider->get(1);
        $this->assertStringContainsString('var/log/klevu/default', $path);
    }

    public function testGetDirectoryPath_ReturnsStringPath_WhenDirectoryIsMissing(): void
    {
        $this->deleteAllLogs();

        $storeLogsProvider = $this->instantiateStoreLogsPathProvider();
        $path = $storeLogsProvider->get(1);
        $this->assertStringContainsString('var/log/klevu/default', $path);
    }

    public function testGetDirectoryPath_ThrowsException_IfStoreIdIncorrect_ForArchive(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $storeLogsProvider = $this->instantiateStoreLogsPathProvider(['appendPath' => '.archive']);
        $storeLogsProvider->get(293857289324);
    }

    public function testGetDirectoryPath_ReturnsStringPath_ToExistingDirectory_ForArchive(): void
    {
        $storeLogsProvider = $this->instantiateStoreLogsPathProvider(['isArchive' => true]);
        $path = $storeLogsProvider->get(1);
        $this->assertStringContainsString('var/log/klevu/.archive/default', $path);
    }

    public function testGetDirectoryPath_ReturnsStringPath_WhenDirectoryIsMissing_ForArchive(): void
    {
        $this->deleteAllLogs();

        $storeLogsProvider = $this->instantiateStoreLogsPathProvider(['isArchive' => true]);
        $path = $storeLogsProvider->get(1);
        $this->assertStringContainsString('var/log/klevu/.archive/default', $path);
    }

    /**
     * @param mixed[]|null $params
     *
     * @return StoreLogsDirectoryPathProvider
     */
    private function instantiateStoreLogsPathProvider(?array $params = []): StoreLogsDirectoryPathProvider
    {
        return $this->objectManager->create(
            StoreLogsDirectoryPathProvider::class,
            $params,
        );
    }
}
