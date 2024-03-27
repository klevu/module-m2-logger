<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Provider;

use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Service\Provider\StoreLogsArchiveDirectoryPathProvider as ArchiveDirectoryPathProviderVirtualType;
use Klevu\Logger\Service\Provider\StoreLogsArchiveDirectoryProvider as ArchiveDirectoryProviderVirtualType;
use Klevu\Logger\Service\Provider\StoreLogsDirectoryPathProvider;
use Klevu\Logger\Service\Provider\StoreLogsDirectoryProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers StoreLogsDirectoryProvider
 */
class StoreLogsDirectoryProviderTest extends TestCase
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
            StoreLogsDirectoryProviderInterface::class,
            $this->objectManager->create(StoreLogsDirectoryProvider::class),
        );
    }

    public function testHasLogs_ReturnsFalse_IfStoreIdIncorrect(): void
    {
        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $this->assertFalse($storeLogsProvider->hasLogs(2999689));
    }

    public function testHasLogs_ReturnsFalse_WhenFileSystemExceptionIsThrown(): void
    {
        $exception = new FileSystemException(__('Some Core Error Creating Directory'));
        $mockFileWriter = $this->getMockBuilder(WriteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFileWriter->expects($this->once())
            ->method('create')
            ->willThrowException($exception);
        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider([
            'fileSystemWrite' => $mockFileWriter,
        ]);

        $this->assertFalse($storeLogsProvider->hasLogs(1));
    }

    public function testHasLogs_ReturnsFalse_WhenDirectoryIsEmpty(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $this->assertFalse($storeLogsProvider->hasLogs(1));
    }

    public function testHasLogs_ReturnsTrue_WhenLogsFilesPresent(): void
    {
        $this->createLogFile();
        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $this->assertTrue($storeLogsProvider->hasLogs(1));
    }

    public function testHasLogs_ReturnsFalse_IfStoreIdIncorrect_ForArchive(): void
    {
        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $this->assertFalse($storeLogsProvider->hasLogs(2999689));
    }

    public function testHasLogs_ReturnsFalse_WhenFileSystemExceptionIsThrown_ForArchive(): void
    {
        $exception = new FileSystemException(__('File not found'));
        $mockFileWriter = $this->getMockBuilder(WriteInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFileWriter->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider([
            'fileSystemWrite' => $mockFileWriter,
        ]);

        $this->assertFalse($storeLogsProvider->hasLogs(1));
    }

    public function testHasLogs_ReturnsFalse_WhenDirectoryIsEmpty_ForArchive(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory('.archive');
        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $this->assertFalse($storeLogsProvider->hasLogs(1));
    }

    public function testHasLogs_ReturnsTrue_WhenLogsFilesPresent_ForArchive(): void
    {
        $this->createLogFile('test.log', '.archive');

        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $this->assertTrue($storeLogsProvider->hasLogs(1));
    }

    public function testGet_ReturnsEmptyArray_WhenDirectoryIsMissing(): void
    {
        $this->deleteAllLogs();

        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';

        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $logs = $storeLogsProvider->getLogs($directoryPath);
        $this->assertCount(0, $logs);
        $this->assertEmpty($logs);
    }

    public function testGet_ThrowsException_WhenDirectoryPathIsInCorrect(): void
    {
        $this->createLogFile();

        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryLogPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath = $directoryLogPath . DIRECTORY_SEPARATOR . 'klewaoirugbhiqurgvu';
        $expectedPath = $directoryLogPath . DIRECTORY_SEPARATOR . 'klevu';

        $exceptionMessage = 'Invalid Directory Provided: '
            . 'Klevu\Logger\Service\Provider\StoreLogsDirectoryProvider::validateProvidedDirectory'
            . ' - Directory path (' . $directoryPath . DIRECTORY_SEPARATOR . ')'
            . ' does not contain required path (' . $expectedPath . DIRECTORY_SEPARATOR . ')';

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $logs = $storeLogsProvider->getLogs($directoryPath);
        $this->assertCount(0, $logs);
        $this->assertEmpty($logs);
    }

    public function testGet_ReturnsArray_WhenDirectoryHasLogs(): void
    {
        $this->createLogFile();

        $storeLogsPathProvider = $this->instantiateStoreLogsPathProvider();
        $storeLogsProvider = $this->instantiateStoreLogsDirectoryProvider();
        $path = $storeLogsPathProvider->get(1);
        $logs = $storeLogsProvider->getLogs($path);
        $this->assertCount(1, $logs);
        $this->assertNotEmpty($logs);
        $keys = array_keys($logs);
        $this->assertStringContainsString('var/log/klevu/default/test.log', $logs[$keys[0]]);
    }

    public function testGet_ReturnsEmptyArray_WhenDirectoryIsMissing_ForArchive(): void
    {
        $this->deleteAllLogs();

        $storeLogsPathProvider = $this->instantiateStoreLogsArchivePathProvider();
        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $path = $storeLogsPathProvider->get(1);
        $logs = $storeLogsProvider->getLogs($path);
        $this->assertCount(0, $logs);
        $this->assertEmpty($logs);
    }

    public function testGet_ThrowsException_WhenDirectoryPathIsInCorrect_ForArchive(): void
    {
        $this->deleteAllLogs();
        $this->createLogFile('test.log', '.archive');

        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryLogPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath = $directoryLogPath . DIRECTORY_SEPARATOR . 'aurgvu' . DIRECTORY_SEPARATOR . '.archive';
        $expectedPath = $directoryLogPath . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR . '.archive';

        $exceptionMessage = 'Invalid Directory Provided: '
            . 'Klevu\Logger\Service\Provider\StoreLogsDirectoryProvider::validateProvidedDirectory'
            . ' - Directory path (' . $directoryPath . DIRECTORY_SEPARATOR . ')'
            . ' does not contain required path (' . $expectedPath . DIRECTORY_SEPARATOR . ')';

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $storeLogsProvider->getLogs($directoryPath);
    }

    public function testGet_ReturnsArray_WhenDirectoryHasLogs_ForArchive(): void
    {
        $this->deleteAllLogs();
        $this->createLogFile('test.log', '.archive');

        $storeLogsPathProvider = $this->instantiateStoreLogsArchivePathProvider();
        $storeLogsProvider = $this->instantiateStoreLogsArchiveDirectoryProvider();
        $path = $storeLogsPathProvider->get(1);
        $logs = $storeLogsProvider->getLogs($path);
        $this->assertCount(1, $logs);
        $this->assertNotEmpty($logs);
        $keys = array_keys($logs);
        $this->assertStringContainsString('var/log/klevu/.archive/default/test.log', $logs[$keys[0]]);
    }

    /**
     * @param mixed[]|null $params
     *
     * @return StoreLogsDirectoryProvider
     */
    private function instantiateStoreLogsDirectoryProvider(?array $params = []): StoreLogsDirectoryProvider
    {
        return $this->objectManager->create(
            StoreLogsDirectoryProvider::class,
            $params,
        );
    }

    /**
     * @param mixed[]|null $params
     *
     * @return StoreLogsDirectoryProvider
     */
    private function instantiateStoreLogsArchiveDirectoryProvider(?array $params = []): StoreLogsDirectoryProvider
    {
        return $this->objectManager->create(
            ArchiveDirectoryProviderVirtualType::class, // virtualType
            $params,
        );
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

    /**
     * @param mixed[]|null $params
     *
     * @return StoreLogsDirectoryPathProvider
     */
    private function instantiateStoreLogsArchivePathProvider(?array $params = []): StoreLogsDirectoryPathProvider
    {
        return $this->objectManager->create(
            ArchiveDirectoryPathProviderVirtualType::class, // virtualType
            $params,
        );
    }
}
