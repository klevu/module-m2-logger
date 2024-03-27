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
use Klevu\Logger\ViewModel\Config\Button\LogArchive as LogArchiveViewModel;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogArchiveViewModel
 * @magentoAppArea adminhtml
 */
class LogArchiveTest extends TestCase
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
            $this->instantiateLogArchiveViewModel(),
        );
    }

    public function testGetId_ReturnsString(): void
    {
        $archive = $this->instantiateLogArchiveViewModel();
        $id = $archive->getId();
        $this->assertIsString($id);
        $this->assertSame('klevu_logger_archive_logs_button', $id);
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
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $archive->getLabel();
        $this->assertSame('No Logs To Archive', $label->getText());
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
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $label = $archive->getLabel();
        $this->assertSame('Archive Logs', $label->getText());
    }

    public function testGetClass_ReturnsNull(): void
    {
        $archive = $this->instantiateLogArchiveViewModel();
        $class = $archive->getClass();
        $this->assertNull($class);
    }

    public function testGetStyle_ReturnsNull(): void
    {
        $archive = $this->instantiateLogArchiveViewModel();
        $class = $archive->getClass();
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

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $action = $archive->getAction();

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
        $this->createLogFile('test.log', null, $store->getCode());

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

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
            'getBearerToken' => $getBearerToken,
        ]);
        $action = $archive->getAction();

        $this->assertIsString($action);
        $this->assertStringContainsString('/rest/', $action);
        $this->assertStringContainsString('V1/klevu_logger/archive_logs/store/' . $store->getId(), $action);
    }

    public function testIsVisible_ReturnsTrue(): void
    {
        $archive = $this->instantiateLogArchiveViewModel();
        $isVisible = $archive->isVisible();
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

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $archive->isDisabled();
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
        $storeScopeProvider = $this->objectManager->create(StoreScopeProvider::class, [
            'request' => $this->mockRequest,
        ]);

        $archive = $this->instantiateLogArchiveViewModel([
            'storeScopeProvider' => $storeScopeProvider,
        ]);
        $isDisabled = $archive->isDisabled();
        $this->assertFalse($isDisabled);
    }

    /**
     * @param mixed[]|null $arguments
     *
     * @return LogArchiveViewModel
     */
    private function instantiateLogArchiveViewModel(?array $arguments = []): LogArchiveViewModel
    {
        return $this->objectManager->create(
            type: LogArchiveViewModel::class,
            arguments: $arguments,
        );
    }
}
