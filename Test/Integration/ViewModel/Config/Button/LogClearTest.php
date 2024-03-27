<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\ViewModel\Config\Button;

use Klevu\Configuration\Service\GetBearerTokenInterface;
use Klevu\Configuration\Service\Provider\StoreScopeProvider;
use Klevu\Configuration\ViewModel\ButtonInterface;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\ViewModel\Config\Button\LogClear as LogClearViewModel;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogClearViewModel
 * @magentoAppArea adminhtml
 */
class LogClearTest extends TestCase
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

    public function testImplements_ButtonInterface(): void
    {
        $this->assertInstanceOf(
            ButtonInterface::class,
            $this->objectManager->create(LogClearViewModel::class),
        );
    }

    public function testGetId_ReturnsString(): void
    {
        $clear = $this->instantiateLogClearViewModel();
        $id = $clear->getId();
        $this->assertIsString($id);
        $this->assertSame('klevu_logger_clear_logs_button', $id);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetLabel_ReturnsPhrase_WhenNoArchive(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $clear->getLabel();
        $this->assertSame('No Archive To Clear', $label->getText());
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetLabel_ReturnsPhrase(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('test.log', '.archive', $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $clear->getLabel();
        $this->assertSame('Clear Archive', $label->getText());
    }

    public function testGetClass_ReturnsNull(): void
    {
        $clear = $this->instantiateLogClearViewModel();
        $class = $clear->getClass();
        $this->assertNull($class);
    }

    public function testGetStyle_ReturnsNull(): void
    {
        $clear = $this->instantiateLogClearViewModel();
        $class = $clear->getClass();
        $this->assertNull($class);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAction_ReturnsEmptyString_ForStore_WithNoLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $action = $clear->getAction();

        $this->assertSame('', $action);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAction_ReturnsString_ForStore_WithLogs(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('archive_test.log', '.archive', $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $mockUserContext = $this->getMockBuilder(UserContextInterface::class)
            ->getMock();
        $mockUserContext->method('getUserId')
            ->willReturn(1);
        $mockUserContext->method('getUserType')
            ->willReturn(UserContextInterface::USER_TYPE_ADMIN);

        $getBearerToken = $this->objectManager->create(type: GetBearerTokenInterface::class, arguments: [
            'userContext' => $mockUserContext,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
            'getBearerToken' => $getBearerToken,
        ]);
        $action = $clear->getAction();

        $this->assertIsString($action);
        $this->assertStringContainsString('/rest/', $action);
        $this->assertStringContainsString('/V1/klevu_logger/clear_logs/store/' . $store->getId(), $action);
    }

    public function testIsVisible_ReturnsTrue(): void
    {
        $clear = $this->instantiateLogClearViewModel();
        $isVisible = $clear->isVisible();
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
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $clear->isDisabled();
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
        $this->createLogFile('test.log', '.archive', $store->getCode());

        $this->mockRequest->method('getParams')
            ->willReturn(['store' => (string)$store->getId()]);
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $clear = $this->instantiateLogClearViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $clear->isDisabled();
        $this->assertFalse($isDisabled);
    }

    /**
     * @param mixed[]|null $arguments
     *
     * @return LogClearViewModel
     */
    private function instantiateLogClearViewModel(?array $arguments = []): LogClearViewModel
    {
        return $this->objectManager->create(LogClearViewModel::class, $arguments);
    }
}
