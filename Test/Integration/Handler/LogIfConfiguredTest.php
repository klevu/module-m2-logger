<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Handler;

use Klevu\Configuration\Service\Provider\ScopeProvider;
use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Logger\Handler\LogIfConfigured;
use Klevu\Logger\Service\IsLoggingEnabledService;
use Klevu\Logger\Service\Provider\LogFileNameProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Website\WebsiteFixturesPool;
use Klevu\TestFixtures\Website\WebsiteTrait;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\TestFramework\ObjectManager;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Handler\HandlerInterface;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Logger\Handler\LogIfConfigured
 * @phpstan-type Level Logger::DEBUG|Logger::INFO|Logger::NOTICE|Logger::WARNING|Logger::ERROR|Logger::CRITICAL|Logger::ALERT|Logger::EMERGENCY
 */
class LogIfConfiguredTest extends TestCase
{
    use FileSystemTrait;
    use StoreTrait;
    use WebsiteTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var StoreScopeProviderInterface|null
     */
    private ?StoreScopeProviderInterface $storeScopeProvider = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
        $this->websiteFixturesPool = $this->objectManager->create(WebsiteFixturesPool::class);
        $this->storeScopeProvider = $this->objectManager->get(StoreScopeProviderInterface::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->storeFixturesPool->rollback();
        $this->websiteFixturesPool->rollback();
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
    public function testIsHandling_ReturnsTrue_IfLogLevelIsMoreThenMinLevel_ForStore(): void
    {
        $record = ['level' => 400];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 500
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 200
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     */
    public function testIsHandling_ReturnsTrue_IfLogLevelIsMoreThenMinLevel_ForStoreWhenSSMIsEnabled(): void
    {
        $record = ['level' => 400];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 400
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 500
     */
    public function testIsHandling_ReturnsTrue_IfLogLevelIsEqualToMinLevel_ForStore(): void
    {
        $record = ['level' => 500];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 400
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 500
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     */
    public function testIsHandling_ReturnsTrue_IfLogLevelIsEqualToMinLevel_ForStoreWhenSSMIsEnabled(): void
    {
        $record = ['level' => 500];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 200
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 400
     */
    public function testIsHandling_ReturnsFalse_IfLogLevelIsLessThanMinLevel_ForStore(): void
    {
        $record = ['level' => 300];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertFalse(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 200
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 400
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     */
    public function testIsHandling_ReturnsFalse_IfLogLevelIsLessThanMinLevel_ForStoreWhenSSMIsEnabled(): void
    {
        $record = ['level' => 300];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertFalse(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     */
    public function testIsHandling_ReturnsTrue_IfMinLogLevelIsNotSet_ForStore(): void
    {
        $record = ['level' => 300];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());
        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     */
    public function testIsHandling_ReturnsTrue_IfMinLogLevelIsNotSet_ForStoreWhenSSMIsEnabled(): void
    {
        $record = ['level' => 300];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());
        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertTrue(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 200
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 400
     * @dataProvider testIsHandling_ReturnsFalse_IfLogLevelIsNotValid_ForStore_DataProvider
     */
    public function testIsHandling_ReturnsFalseIfLogLevelIsNotValid_ForStore(mixed $logLevels): void
    {
        $record = ['level' => $logLevels];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertFalse(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoCache all disabled
     * @magentoConfigFixture default/klevu_developer/logger/log_level_some_name 200
     * @magentoConfigFixture klevu_test_store_1_store klevu_developer/logger/log_level_some_name 400
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     * @dataProvider testIsHandling_ReturnsFalse_IfLogLevelIsNotValid_ForStore_DataProvider
     */
    public function testIsHandling_ReturnsFalseIfLogLevelIsNotValid_ForStoreWhenSSMIsEnabled(mixed $logLevels): void
    {
        $record = ['level' => $logLevels];
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $this->assertFalse(
            $logIfConfigured->isHandling($record),
        );
    }

    /**
     * @return mixed[][]
     */
    public function testIsHandling_ReturnsFalse_IfLogLevelIsNotValid_ForStore_DataProvider(): array
    {
        return [
            ['level'],
            [true],
            [[12]],
            [new \stdClass()],
            [false],
            [null],
            [1.23456],
            ['3.14e-14'],
        ];
    }

    /**
     * @magentoAppArea crontab
     */
    public function testWrite_WriteLogFile(): void
    {
        $this->deleteAllLogs();
        $record = $this->getRecord();
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->createStoreLogsDirectory(null, $store->getCode());
        $directory = $this->getStoreLogsDirectoryPath(null, $store->getCode());
        $fileName = $directory . DIRECTORY_SEPARATOR . 'klevu-' . $store->getCode() . '-some_log_name.log';

        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());
        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $logIfConfigured->write($record);
        $fileIo = $this->objectManager->get(File::class);
        $this->assertTrue($fileIo->fileExists($fileName));
        $fileContents = $fileIo->read($fileName);

        $this->assertStringContainsString(
            'randomTestMessage',
            $fileContents,
        );
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoDbIsolation disabled
     */
    public function testWrite_WriteMultipleLogFiles(): void
    {
        $this->deleteAllLogs();
        $record = $this->getRecord();

        $this->createWebsite();
        $websiteFixture = $this->websiteFixturesPool->get('test_website');
        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'key' => 'test_store_1',
        ]);
        $this->createStore([
            'website_id' => $websiteFixture->getId(),
            'code' => 'klevu_test_store_2',
            'key' => 'test_store_2',
        ]);
        $store1 = $this->storeFixturesPool->get('test_store_1');
        $store2 = $this->storeFixturesPool->get('test_store_2');
        $stores = [$store1, $store2];

        foreach ($stores as $store) {
            $this->createStoreLogsDirectory(null, $store->getCode());
        }
        $websiteRepository = $this->objectManager->get(WebsiteRepositoryInterface::class);
        $website = $websiteRepository->getById(id: (int)$websiteFixture->getId());
        $scopeProvider = $this->objectManager->get(ScopeProvider::class);
        $scopeProvider->setCurrentScope($website);

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured();
        $logIfConfigured->write($record);

        $fileIo = $this->objectManager->get(File::class);

        foreach ($stores as $store) {
            $directory = $this->getStoreLogsDirectoryPath(null, $store->getCode());
            $fileName = $directory . DIRECTORY_SEPARATOR . 'klevu-' . $store->getCode() . '-some_log_name.log';

            $this->assertTrue($fileIo->fileExists($fileName));
            $fileContents = $fileIo->read($fileName);

            $this->assertStringContainsString(
                needle: 'randomTestMessage',
                haystack: $fileContents,
                message: 'Store ID: ' . $store->getId(),
            );
        }
    }

    /**
     * @magentoAppArea crontab
     */
    public function testWrite_MaxNormalizeDepthSettingIsUsed(): void
    {
        $this->deleteAllLogs();
        $record = $this->getRecord(
            message: ['level1' => ['level2' => ['level 3' => ['level 4' => 'Level 4 message']]]],
        );
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->createStoreLogsDirectory(null, $store->getCode());
        $directory = $this->getStoreLogsDirectoryPath(null, $store->getCode());
        $fileName = $directory . DIRECTORY_SEPARATOR . 'klevu-' . $store->getCode() . '-some_log_name.log';

        $this->storeScopeProvider->setCurrentStoreByCode($store->getCode());

        $logIfConfigured = $this->instantiateHandlerLogIfConfigured([
            'maxNormalizeDepth' => 3,
        ]);
        $logIfConfigured->write($record);
        $fileIo = $this->objectManager->get(File::class);
        $this->assertTrue($fileIo->fileExists($fileName));
        $fileContents = $fileIo->read($fileName);

        $this->assertStringContainsString(
            'klevu.WARNING: {"level1":{"level2":{"level 3":"Over 3 levels deep, aborting normalization"}}}',
            $fileContents,
        );
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogIfConfigured
     */
    private function instantiateHandlerLogIfConfigured(?array $params = []): LogIfConfigured
    {
        if (!isset($params['logFileNameProvider'])) {
            $params['logFileNameProvider'] = $this->objectManager->create(LogFileNameProvider::class, [
                'baseFileName' => 'some_log_name.log',
            ]);
        }

        if (!isset($params['loggingEnabledService'])) {
            $params['loggingEnabledService'] = $this->objectManager->create(IsLoggingEnabledService::class, [
                'minLogLevelConfigPath' => 'klevu_developer/logger/log_level_some_name',
            ]);
        }

        return $this->objectManager->create(
            LogIfConfigured::class,
            $params,
        );
    }

    /**
     * @param int $level
     * @param mixed $message
     * @param mixed[] $context
     *
     * @return mixed[]
     *
     * @phpstan-param Level $level
     */
    private function getRecord(
        int $level = Logger::WARNING,
        mixed $message = 'randomTestMessage',
        array $context = [],
    ): array {
        return [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'klevu',
            'datetime' => \DateTime::createFromFormat(
                'U.u',
                sprintf('%.6F', microtime(true)),
            ),
            'extra' => [],
        ];
    }
}
