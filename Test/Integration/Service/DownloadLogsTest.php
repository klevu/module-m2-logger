<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service;

use Klevu\Logger\Exception\NoLogsException;
use Klevu\Logger\Exception\SizeExceedsLimitException;
use Klevu\Logger\Service\DownloadLogs;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogSizeValidator;
use Klevu\LoggerApi\Service\DownloadLogsInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers DownloadLogs
 */
class DownloadLogsTest extends TestCase
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

    public function testImplements_DownloadLogsInterface(): void
    {
        $this->assertInstanceOf(
            DownloadLogsInterface::class,
            $this->instantiateDownloadLogs(),
        );
    }

    public function testPreference_ForDownloadLogsInterface(): void
    {
        $this->assertInstanceOf(
            DownloadLogs::class,
            $this->objectManager->create(DownloadLogsInterface::class),
        );
    }

    public function testExecute_ThrowsNoLogsException_WhenNoLogsInDirectory(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory(null, $store->getCode());

        $this->expectException(NoLogsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'There are no logs to download for store %s: %s (%s).',
                $store->getId(),
                $store->getName(),
                $store->getCode(),
            ),
        );

        $downloadLogs = $this->instantiateDownloadLogs();
        $downloadLogs->execute($store->getId());
    }

    public function testExecute_ThrowsNoLogsException_WhenStoreIdIncorrect(): void
    {
        $this->deleteAllLogs();
        $storeId = 87658998;

        $this->expectException(NoLogsException::class);
        $this->expectExceptionMessage('There are no logs to download for store ID ' . $storeId . '.');

        $downloadLogs = $this->instantiateDownloadLogs();
        $downloadLogs->execute($storeId);
    }

    public function testExecuteThrows_SizeExceedsLimitException_WhenLogsAreTooLarge(): void
    {
        $this->deleteAllLogs();
        $this->createLogFileWithContents();

        $this->expectException(SizeExceedsLimitException::class);
        $this->expectExceptionMessage(
            'File Size Exceeds Limit: File size is too large to download via admin.' .
            ' Please ask your developers to download the logs from the server.',
        );

        $logSizeValidator = $this->objectManager->create(LogSizeValidator::class, [
            'downloadLimit' => 1,
        ]);

        $downloadLogs = $this->instantiateDownloadLogs([
            'logSizeValidator' => $logSizeValidator,
        ]);
        $downloadLogs->execute(1);
    }

    public function testExecute_ReturnsString(): void
    {
        $this->deleteAllLogs();
        $this->createLogFileWithContents();

        $downloadLogs = $this->instantiateDownloadLogs();
        $response = $downloadLogs->execute(1);

        $expectedPath = '#klevu\\' . DIRECTORY_SEPARATOR . 'klevu_log_default_\d*\.tar\.gz#';

        $this->assertMatchesRegularExpression(
            pattern: $expectedPath,
            string: $response,
        );
    }

    /**
     * @param mixed[]|null $params
     *
     * @return DownloadLogs
     */
    private function instantiateDownloadLogs(?array $params = []): DownloadLogs
    {
        return $this->objectManager->create(DownloadLogs::class, $params);
    }
}
