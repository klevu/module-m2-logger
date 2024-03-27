<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service;

use Klevu\Logger\Service\ClearLogs;
use Klevu\Logger\Service\Provider\StoreLogsDirectoryProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\ClearLogsInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers ClearLogs
 */
class ClearLogsTest extends TestCase
{
    use FileSystemTrait;
    use StoreTrait;

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
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->storeFixturesPool->rollback();
    }

    public function testImplements_ClearLogsServiceInterface(): void
    {
        $this->assertInstanceOf(
            ClearLogsInterface::class,
            $this->instantiateClearLogs(),
        );
    }

    public function testPreference_ForClearLogsServiceInterface(): void
    {
        $this->assertInstanceOf(
            ClearLogs::class,
            $this->objectManager->create(ClearLogsInterface::class),
        );
    }

    public function testExecuteThrows_NoSuchEntityException_WhenStoreIdIncorrect(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory('.archive');

        $this->expectException(NoSuchEntityException::class);

        $clearLogs = $this->instantiateClearLogs();
        $clearLogs->execute(87654387);
    }

    public function testDirectory_IsRemoved(): void
    {
        $fileSystemDriver = $this->objectManager->create(File::class);
        $this->deleteAllLogs();
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->createLogFile(
            'klevu_log_default_123456789.tar',
            '.archive',
            $store->getCode(),
        );
        $path = $this->getArchiveDirectoryPath($store->getCode());
        $this->assertTrue($fileSystemDriver->isExists($path));

        $clearLogs = $this->instantiateClearLogs();
        $success = $clearLogs->execute($store->getId());

        $this->assertTrue($success);
        $this->assertFalse($fileSystemDriver->isExists($path));
    }

    /**
     * @param mixed[]|null $params
     *
     * @return ClearLogs
     */
    private function instantiateClearLogs(?array $params = []): ClearLogs
    {
        return $this->objectManager->create(
            ClearLogs::class,
            $params,
        );
    }

    /**
     * @param string $storeCode
     *
     * @return string
     * @throws FileSystemException
     */
    private function getArchiveDirectoryPath(string $storeCode = 'default'): string
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $path = $directoryList->getPath(DirectoryList::LOG);
        $path .= DIRECTORY_SEPARATOR . StoreLogsDirectoryProvider::KLEVU_LOGS_DIRECTORY;
        $path .= DIRECTORY_SEPARATOR . '.archive';
        $path .= DIRECTORY_SEPARATOR . $storeCode;

        return $path;
    }
}
