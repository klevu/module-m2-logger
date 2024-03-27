<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Provider;

use Klevu\Logger\Service\Provider\LogFileNameProvider;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Provider\LogFileNameProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogFileNameProvider
 */
class LogFileNameProviderTest extends TestCase
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

    public function testImplements_LogFileNameProviderInterface(): void
    {
        $this->assertInstanceOf(
            LogFileNameProviderInterface::class,
            $this->instantiateLogFileNameProvider(),
        );
    }

    public function testPreference_ForLogFileNameProviderInterface(): void
    {
        $logFileNameProviderInterface = $this->objectManager->create(LogFileNameProviderInterface::class);
        $this->assertInstanceOf(
            LogFileNameProvider::class,
            $logFileNameProviderInterface,
        );
    }

    public function testGet_ReturnsLogFileName_ForStore(): void
    {
        $this->createStore();
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => 'configuration.log',
        ]);
        $store = $this->storeFixturesPool->get('test_store');
        $actual = $storeLogsProvider->get($store->getId());
        $this->assertSame('klevu-' . $store->getCode() . '-configuration.log', $actual);
    }

    /**
     * @magentoConfigFixture default/general/single_store_mode/enabled 1
     * @magentoConfigFixture klevu_test_store_1_store general/single_store_mode/enabled 1
     */
    public function testGet_ReturnsLogFileName_ForStoreWhenSSMIsEnabled(): void
    {
        $this->createStore();
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => 'configuration.log',
        ]);
        $store = $this->storeFixturesPool->get('test_store');
        $actual = $storeLogsProvider->get($store->getId());
        $this->assertSame('klevu-' . $store->getCode() . '-configuration.log', $actual);
    }

    public function testGet_ReturnsFileNameWithOutStoreCode_IfStoreNotFound(): void
    {
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => 'indexing.log',
        ]);
        $actual = $storeLogsProvider->get(7867589809);
        $this->assertSame('klevu-indexing.log', $actual);
    }

    public function testGet_ReturnsFileNameWithOutBaseFileName_IfStoreNotFound(): void
    {
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => '',
        ]);
        $actual = $storeLogsProvider->get(7867589809);
        $this->assertSame('klevu-general.log', $actual);
    }

    public function testGet_ReturnsFileNameWithOutBaseFileName_ForStore(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => '',
        ]);
        $actual = $storeLogsProvider->get($store->getId());
        $this->assertSame('klevu-' . $store->getCode() . '-general.log', $actual);
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testGet_ThrowsValidatorException_IfFilenameContainsIllegalCharacters_dataProvider
     */
    public function testGet_ThrowsValidatorException_IfFilenameContainsIllegalCharacters(string $fileName): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage(
            "File Name value contains illegal characters. " .
            "Received '" . $fileName . "'. " .
            "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
        );
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => $fileName,
        ]);
        $storeLogsProvider->get($store->getId());
    }

    /**
     * @return string[][]
     */
    public function testGet_ThrowsValidatorException_IfFilenameContainsIllegalCharacters_dataProvider(): array
    {
        return [
            ['@123_default.log'],
            ['#123_default.log'],
            ['$123_default.log'],
            ['123~default.log'],
            ['123\default.log'],
            ['123_default`log'],
            ['base/directory.log'],
        ];
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ThrowsValidatorException_IfFilenameIsEmpty(): void
    {
        $this->createStore();
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('Filename Validation Failed: File Name value cannot be empty.');
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => ' ',
        ]);
        $store = $this->storeFixturesPool->get('test_store');
        $storeLogsProvider->get($store->getId());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ThrowsValidatorException_IfFileExtensionIsNotAllowed(): void
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage(
            "File Name extension is not a permitted value. " .
            "Received 'csv'; expected one of 'log'.",
        );
        $storeLogsProvider = $this->instantiateLogFileNameProvider([
            'baseFileName' => 'klevu_analytics.csv',
        ]);
        $store = $this->storeFixturesPool->get('test_store');
        $storeLogsProvider->get($store->getId());
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogFileNameProvider
     */
    private function instantiateLogFileNameProvider(?array $params = []): LogFileNameProvider
    {
        return $this->objectManager->create(
            LogFileNameProvider::class,
            $params,
        );
    }
}
