<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\ViewModel\Config\Button;

use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Configuration\ViewModel\ButtonInterface;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\ViewModel\Config\Button\LogDownload as LogDownloadViewModel;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogDownloadViewModel
 * @magentoAppArea adminhtml
 */
class LogDownloadTest extends TestCase
{
    use FileSystemTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var MockObject|RequestInterface|null
     */
    private MockObject|RequestInterface|null $mockRequest = null; // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing, Generic.Files.LineLength.TooLong

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
        $this->mockRequest = $this->getMockBuilder(RequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->storeFixturesPool->rollback();
    }

    public function testImplementsArgumentInterface(): void
    {
        $this->assertInstanceOf(
            ButtonInterface::class,
            $this->instantiateLogDownloadViewModel(),
        );
    }

    public function testGetId_ReturnsString(): void
    {
        $download = $this->instantiateLogDownloadViewModel();
        $id = $download->getId();
        $this->assertIsString($id);
        $this->assertSame('klevu_logger_download_logs_button', $id);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetLabel_ReturnsPhrase_WhenNoLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $download->getLabel();
        $this->assertSame('No Logs To Download', $label->getText());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetLabel_ReturnsPhrase(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $download->getLabel();
        $this->assertSame('Download Logs', $label->getText());
    }

    public function testGetClass_ReturnsNull(): void
    {
        $download = $this->instantiateLogDownloadViewModel();
        $class = $download->getClass();
        $this->assertNull($class);
    }

    public function testGetStyle_ReturnsNull(): void
    {
        $download = $this->instantiateLogDownloadViewModel();
        $class = $download->getClass();
        $this->assertNull($class);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAction_ReturnsEmptyStringForStore_WithNoLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $action = $download->getAction();

        $this->assertSame('', $action);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAction_ReturnsStringForStore_withLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => $store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $action = $download->getAction();

        $this->assertIsString($action);
        $this->assertStringContainsString('klevu_logger/download/logs/store/' . $store->getId(), $action);
    }

    public function testIsVisible_ReturnsTrue(): void
    {
        $download = $this->instantiateLogDownloadViewModel();
        $isVisible = $download->isVisible();
        $this->assertTrue($isVisible);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testIsDisabled_ReturnsTrue_WhenNoLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $download->isDisabled();
        $this->assertTrue($isDisabled);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testIsDisabled_ReturnsFalse_WhenLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('test.log', null, $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProviderInterface::class, [
            'request' => $this->mockRequest,
        ]);

        $download = $this->instantiateLogDownloadViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $download->isDisabled();
        $this->assertFalse($isDisabled);
    }

    /**
     * @param mixed[]|null $arguments
     *
     * @return LogDownloadViewModel
     */
    private function instantiateLogDownloadViewModel(?array $arguments = []): LogDownloadViewModel
    {
        return $this->objectManager->create(LogDownloadViewModel::class, $arguments);
    }
}
