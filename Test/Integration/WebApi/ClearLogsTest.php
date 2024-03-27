<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\WebApi;

use Klevu\Logger\Service\Action\Directory\DeleteAction;
use Klevu\Logger\Service\Action\Directory\DeleteArchiveAction as DeleteArchiveActionVirtualType;
use Klevu\Logger\Service\Provider\StoreLogsDirectoryProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\WebApi\ClearLogs;
use Klevu\LoggerApi\Api\ClearLogsInterface;
use Klevu\LoggerApi\Api\Data\LogResponseInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
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

    public function testImplements_ClearLogsInterface(): void
    {
        $this->assertInstanceOf(
            ClearLogsInterface::class,
            $this->instantiateClearLogsWebApi(),
        );
    }

    public function testPreference_ForClearLogsInterface(): void
    {
        $this->assertInstanceOf(
            ClearLogs ::class,
            $this->objectManager->create(ClearLogsInterface::class),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsErrorArray_WhenIncorrectStoreProvided(): void
    {
        $this->deleteAllLogs();
        $storeId = 238572894572;

        $webApi = $this->instantiateClearLogsWebApi();
        $response = $webApi->execute($storeId);

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame('error', $response->getStatus());
        $this->assertSame(404, $response->getCode());
        $this->assertSame(
            'Store ID ' . $storeId . ' not found.',
            $response->getMessage(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsErrorArray_WhenInValidDirectory(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $this->deleteAllLogs();
        $webApi = $this->instantiateClearLogsWebApi();
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame('error', $response->getStatus());
        $this->assertSame(500, $response->getCode());
        $this->assertSame(
            sprintf(
                'Internal error: Archive could not be deleted. ' .
                'Check logs for store %s: %s (%s) for more information.',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
            $response->getMessage(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsErrorArray_WhenFileSystemExceptionThrown(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $this->deleteAllLogs();
        $this->createLogFile('klevu_log_12345.tar', '.archive', $store->getCode());

        $exception = new FileSystemException(__('File could not be deleted'));
        $mockDeleteAction = $this->getMockBuilder(DeleteAction::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockDeleteAction->expects($this->once())
            ->method('execute')
            ->willThrowException($exception);

        $this->objectManager->addSharedInstance(
            $mockDeleteAction,
            DeleteArchiveActionVirtualType::class, // virtual type // @phpstan-ignore-line
        );

        $webApi = $this->instantiateClearLogsWebApi();
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame('error', $response->getStatus());
        $this->assertSame(500, $response->getCode());
        $this->assertSame(
            sprintf(
                'Internal error: Archive could not be deleted. ' .
                'Check logs for store %s: %s (%s) for more information.',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
            $response->getMessage(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_RemovesLogs_AndReturnsSuccessArray_OnSuccess(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $fileSystemDriver = $this->objectManager->create(File::class);

        $this->deleteAllLogs();
        $this->createLogFile(fileName: 'klevu_log_12345.tar', path:'.archive', storeCode: $store->getCode());
        $path = $this->getArchiveDirectoryPath($store->getCode());

        $this->assertTrue($fileSystemDriver->isExists($path), 'File Exists Before API Call');

        $webApi = $this->instantiateClearLogsWebApi();
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame('success', $response->getStatus());
        $this->assertSame(200, $response->getCode());
        $this->assertSame(
            sprintf(
                'Logs cleared for store %s: %s (%s).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
            $response->getMessage(),
        );

        $this->assertFalse($fileSystemDriver->isExists($path), 'File Exists After API Call');
    }

    /**
     * @param mixed[]|null $params
     *
     * @return ClearLogs
     */
    private function instantiateClearLogsWebApi(?array $params = []): ClearLogs
    {
        return $this->objectManager->create(ClearLogs::class, $params);
    }

    /**
     * @param string|null $storeCode
     *
     * @return string
     * @throws FileSystemException
     */
    private function getArchiveDirectoryPath(?string $storeCode = 'default'): string
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $path = $directoryList->getPath(DirectoryList::LOG);
        $path .= DIRECTORY_SEPARATOR . StoreLogsDirectoryProvider::KLEVU_LOGS_DIRECTORY;
        $path .= DIRECTORY_SEPARATOR . '.archive';
        $path .= DIRECTORY_SEPARATOR . $storeCode;

        return $path;
    }
}
