<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Block\Adminhtml\System\Config;

use Klevu\Configuration\Logger\Logger as ConfigurationLoggerVirtualType;
use Klevu\Configuration\Service\Provider\StoreScopeProviderInterface;
use Klevu\Configuration\Test\Integration\Controller\Adminhtml\GetAdminFrontNameTrait;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Request;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController as AbstractBackendControllerTestCase;

class ButtonTest extends AbstractBackendControllerTestCase
{
    use FileSystemTrait;
    use GetAdminFrontNameTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->uri = $this->getAdminFrontName() . '/admin/system_config/edit';
        $this->resource = 'Klevu_LoggerApi::developer_logging';
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
    }

    public function testAclHasAccess(): void
    {
        // AclHasAccess test is not required.
        // Override parent test
    }

    public function testAclNoAccess(): void
    {
        // AclNoAccess test is not required.
        // Override parent test
    }

    public function testButtons_whenNoLogs(): void
    {
        $this->deleteAllLogs();
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $storeScopeProvider = $this->objectManager->get(StoreScopeProviderInterface::class);
        $storeScopeProvider->setCurrentStoreById((int)$store->getId());

        /** @var Request $request */
        $request = $this->getRequest();
        $request->setParam('section', 'klevu_developer');
        $request->setParam('store', $store->getId());

        $this->dispatch($this->uri);
        /** @var Response $response */
        $response = $this->getResponse();

        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_download_logs_button"\s*title="No Logs To Download"' .
            '\s*type="button"\s*class="action-default scalable disabled"\s*disabled="disabled".*>#',
            $responseBody,
        );
        $downloadJsMatches = [];
        preg_match(
            '#setLocation\(\'http(s)?:\/\/.*\/' . $this->getAdminFrontName() .
            '\/klevu_logger\/download\/logs\/store\/' . $store->getId() . '\/key\/.*\/\'\)#',
            $responseBody,
            $downloadJsMatches,
        );
        $this->assertCount(0, $downloadJsMatches, 'Download JS');

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_archive_logs_button"\s*title="No Logs To Archive"' .
            '\s*type="button"\s*class="action-default scalable disabled"\s*disabled="disabled".*>#',
            $responseBody,
        );
        $archiveJsMatches = [];
        preg_match(
            '#url:\s*\'http(s)?:\/\/.*\/rest\/' . $store->getCode() . '\/V1' .
            '\/klevu_logger\/archive_logs\/store\/' . $store->getId() . '\/\'#',
            $responseBody,
            $archiveJsMatches,
        );
        $this->assertCount(0, $archiveJsMatches, 'Archive JS');

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_clear_logs_button"\s*title="No Archive To Clear"' .
            '\s*type="button"\s*class="action-default scalable disabled"\s*disabled="disabled".*>#',
            $responseBody,
        );
        $clearJsMatches = [];
        preg_match(
            '#url:\s*\'http(s)?:\/\/.*\/rest\/' . $store->getCode() . '\/V1' .
            '\/klevu_logger\/clear_logs\/store\/' . $store->getId() . '\/\'#',
            $responseBody,
            $clearJsMatches,
        );
        $this->assertCount(0, $clearJsMatches, 'Clear JS');
    }

    public function testButtons_WhenLogsExist(): void
    {
        $this->deleteAllLogs();
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $storeScopeProvider = $this->objectManager->get(StoreScopeProviderInterface::class);
        $storeScopeProvider->setCurrentStoreById((int)$store->getId());

        /** @phpstan-ignore-next-line */
        $logger = $this->objectManager->create(ConfigurationLoggerVirtualType::class);
        $logger->info('Some log message');

        /** @var Request $request */
        $request = $this->getRequest();
        $request->setParam('section', 'klevu_developer');
        $request->setParam('store', $store->getId());

        $this->dispatch($this->uri);
        /** @var Response $response */
        $response = $this->getResponse();

        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_download_logs_button"\s*title="Download Logs"' .
            '\s*type="button"\s*class="action-default scalable".*>#',
            $responseBody,
        );
        $downloadJsMatches = [];
        preg_match(
            '#setLocation\(\'http(s)?:\/\/.*\/' . $this->getAdminFrontName() .
            '\/klevu_logger\/download\/logs\/store\/' . $store->getId() . '\/key\/.*\/\'\)#',
            $responseBody,
            $downloadJsMatches,
        );
        $this->assertCount(1, $downloadJsMatches, 'Download JS');

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_archive_logs_button"\s*title="Archive Logs"' .
            '\s*type="button"\s*class="action-default scalable".*>#',
            $responseBody,
        );
        $archiveJsMatches = [];
        preg_match(
            '#url:\s*"http(s)?:\/\/.*\/rest\/' . $store->getCode() . '\/V1' .
            '\/klevu_logger\/archive_logs\/store\/' . $store->getId() . '\/"#',
            $responseBody,
            $archiveJsMatches,
        );
        $this->assertCount(1, $archiveJsMatches, 'Archive JS');

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_clear_logs_button"\s*title="No Archive To Clear"' .
            '\s*type="button"\s*class="action-default scalable disabled"\s*disabled="disabled".*>#',
            $responseBody,
        );
        $clearJsMatches = [];
        preg_match(
            '#url:\s*\'http(s)?:\/\/.*\/rest\/' . $store->getCode() . '\/V1' .
            '\/klevu_logger\/clear_logs\/store\/' . $store->getId() . '\/\'#',
            $responseBody,
            $clearJsMatches,
        );
        $this->assertCount(0, $clearJsMatches, 'Clear JS');
    }

    public function testClearLog_WhenArchiveExists(): void
    {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');
        $this->deleteAllLogs();
        $this->createLogFile('archive-test.log', '.archive', $store->getCode());

        $storeScopeProvider = $this->objectManager->get(StoreScopeProviderInterface::class);
        $storeScopeProvider->setCurrentStoreById((int)$store->getId());

        /** @var Request $request */
        $request = $this->getRequest();
        $request->setParam('section', 'klevu_developer');
        $request->setParam('store', $store->getId());

        $this->dispatch($this->uri);
        /** @var Response $response */
        $response = $this->getResponse();

        $httpResponseCode = $response->getHttpResponseCode();
        $this->assertNotSame(404, $httpResponseCode);
        $this->assertNotSame($this->expectedNoAccessResponseCode, $httpResponseCode);

        $responseBody = $response->getBody();

        $this->assertMatchesRegularExpression(
            '#<button\s*id="klevu_logger_clear_logs_button"\s*title="Clear Archive"' .
            '\s*type="button"\s*class="action-default scalable".*>#',
            $responseBody,
        );
        $clearJsMatches = [];
        preg_match(
            '#url:\s*"http(s)?:\/\/.*\/rest\/' . $store->getCode() . '\/V1' .
            '\/klevu_logger\/clear_logs\/store\/' . $store->getId() . '\/"#',
            $responseBody,
            $clearJsMatches,
        );
        $this->assertCount(1, $clearJsMatches, 'Clear JS');
    }
}
