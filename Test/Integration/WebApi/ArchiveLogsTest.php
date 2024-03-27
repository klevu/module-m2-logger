<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\WebApi;

use Klevu\Logger\Exception\InvalidArchiverException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\InvalidFileExtensionException;
use Klevu\Logger\Service\Action\Directory\ArchiveAction;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\WebApi\ArchiveLogs;
use Klevu\LoggerApi\Api\ArchiveLogsInterface;
use Klevu\LoggerApi\Api\Data\LogResponseInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers ArchiveLogs
 */
class ArchiveLogsTest extends TestCase
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

    public function testImplements_ArchiveLogsInterface(): void
    {
        $this->assertInstanceOf(
            ArchiveLogsInterface::class,
            $this->instantiateArchiveLogsWebApi(),
        );
    }

    public function testPreference_ForArchiveLogsInterface(): void
    {
        $this->assertInstanceOf(
            ArchiveLogs::class,
            $this->objectManager->create(ArchiveLogsInterface::class),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_ReturnsSuccessArray_WhenLogsArchived(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $this->deleteAllLogs();
        $this->createLogFile('archiveWebApi.log', null, $store->getCode());

        $webApi = $this->instantiateArchiveLogsWebApi();
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame(200, $response->getCode());
        $this->assertSame('success', $response->getStatus());
        $this->assertSame(
            sprintf(
                'Logs archived for store %s: %s (%s).',
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
    public function testExecute_ReturnsInfoArray_WhenNoLogsExist(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $this->deleteAllLogs();
        $this->createStoreLogsDirectory(null, $store->getCode());

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('info');

        $webApi = $this->instantiateArchiveLogsWebApi([
            'logger' => $mockLogger,
        ]);
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame(404, $response->getCode());
        $this->assertSame('info', $response->getStatus());
        $this->assertStringContainsString(
            sprintf(
                'No logs to archive for store %s: %s (%s).',
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
    public function testExecute_ReturnsErrorResponse_WhenIncorrectStoreProvided(): void
    {
        $storeId = 3458572572;
        $this->deleteAllLogs();

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('info');

        $webApi = $this->instantiateArchiveLogsWebApi([
            'logger' => $mockLogger,
        ]);
        $response = $webApi->execute($storeId);

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame(404, $response->getCode());
        $this->assertSame('info', $response->getStatus());
        $this->assertStringContainsString(
            'No logs to archive for store ID: ' . $storeId . '.',
            $response->getMessage(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testExecute_ReturnsErrorArray_WhenExceptionThrown_ByArchiveAction_DataProvider
     */
    public function testExecute_ReturnsErrorArray_WhenExceptionThrown_ByArchiveAction(string $exception): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());

        $exception = new $exception(__('Exception Message'));
        $mockArchiveAction = $this->getMockBuilder(ArchiveAction::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockArchiveAction->expects($this->once())
            ->method('execute')
            ->willThrowException($exception);
        $this->objectManager->addSharedInstance(
            $mockArchiveAction,
            ArchiveAction::class,
        );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error');

        $webApi = $this->instantiateArchiveLogsWebApi([
            'logger' => $mockLogger,
        ]);
        $response = $webApi->execute($store->getId());

        $this->assertInstanceOf(LogResponseInterface::class, $response);
        $this->assertSame(500, $response->getCode());
        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            sprintf(
                'Internal error: Archive could not be created. ' .
                'Check logs for store %s: %s (%s) for more information.',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
            $response->getMessage(),
        );
    }

    /**
     * @return string[][]
     */
    public function testExecute_ReturnsErrorArray_WhenExceptionThrown_ByArchiveAction_DataProvider(): array
    {
        return [
            [FileSystemException::class],
            [InvalidArchiverException::class],
            [InvalidDirectoryException::class],
            [InvalidFileExtensionException::class],
        ];
    }

    /**
     * @param mixed[] $params
     *
     * @return ArchiveLogs
     */
    private function instantiateArchiveLogsWebApi(?array $params = []): ArchiveLogs
    {
        return $this->objectManager->create(ArchiveLogs::class, $params);
    }
}
