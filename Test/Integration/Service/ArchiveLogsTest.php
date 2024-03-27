<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service;

use Klevu\Logger\Exception\InvalidFilePathException;
use Klevu\Logger\Exception\NoLogsException;
use Klevu\Logger\Service\Action\Directory\ArchiveAction;
use Klevu\Logger\Service\ArchiveLogs;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\ArchiveLogsInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

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
            $this->instantiateArchiveLogs(),
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
    public function testExecuteThrows_NoLogsException_WhenNoLogsInDirectory(): void
    {
        $this->deleteAllLogs();

        $storeKey = 'test_store';
        $this->createStore([
            'key' => $storeKey,
        ]);
        $store = $this->storeFixturesPool->get($storeKey);

        $this->createStoreLogsDirectory('', $store->getCode());

        $this->expectException(NoLogsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'No logs to archive for store %s: %s (%s).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
        );

        $archiveLogs = $this->instantiateArchiveLogs();
        $archiveLogs->execute($store->getId());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecuteThrows_NoLogsException_WhenStoreIdIncorrect(): void
    {
        $this->deleteAllLogs();
        $storeId = 87654387;

        $this->expectException(NoLogsException::class);
        $this->expectExceptionMessage('No logs to archive for store ID: ' . $storeId);

        $this->createLogFile();

        $archiveLogs = $this->instantiateArchiveLogs();
        $archiveLogs->execute($storeId);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testDirectory_IsArchived(): void
    {
        $storeKey = 'test_store';
        $this->createStore([
            'key' => $storeKey,
        ]);
        $store = $this->storeFixturesPool->get($storeKey);

        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());
        $this->createLogFile('test2.log', null, $store->getCode());

        $archiveLogs = $this->instantiateArchiveLogs();
        $archive = $archiveLogs->execute($store->getId());

        $this->assertStringMatchesFormat(
            '%A' . DIRECTORY_SEPARATOR . 'klevu_log_' . $store->getCode() . '_%d.tar.gz',
            $archive,
        );
        $this->assertFileExists($archive);
        $this->assertFileDoesNotExist(rtrim($archive, '.gz'));
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecuteThrowsException_WhenInvalidArchivePathReturned(): void
    {
        $this->expectException(InvalidFilePathException::class);
        $this->expectExceptionMessageMatches(
            '/Invalid archive path returned. ' . preg_quote(ArchiveLogs::class, '\\') .
            '::validateArchivePath: Directory \(.*\) does not exist/',
        );

        $storeKey = 'test_store';
        $this->createStore([
            'key' => $storeKey,
        ]);
        $store = $this->storeFixturesPool->get($storeKey);

        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());
        $this->createLogFile('test2.log', null, $store->getCode());
        $path = $this->getStoreLogsDirectoryPath(null, $store->getCode());

        $mockArchiveAction = $this->getMockBuilder(ArchiveAction::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockArchiveAction->expects($this->once())
            ->method('execute')
            ->willReturn(
                $path . DIRECTORY_SEPARATOR . 'some_incorrect_path' . DIRECTORY_SEPARATOR . 'archive.tar.gz',
            );

        $archiveLogs = $this->instantiateArchiveLogs([
            'archiveAction' => $mockArchiveAction,
        ]);
        $archiveLogs->execute($store->getId());
    }

    /**
     * @param mixed[]|null $params
     *
     * @return ArchiveLogs
     */
    private function instantiateArchiveLogs(?array $params = []): ArchiveLogs
    {
        return $this->objectManager->create(
            ArchiveLogs::class,
            $params,
        );
    }
}
