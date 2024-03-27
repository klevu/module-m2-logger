<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Controller\Adminhtml\Download;

use Klevu\Logger\Controller\Adminhtml\Download\Logs as DownloadLogsController;
use Klevu\Logger\Test\Integration\Controller\Adminhtml\Traits\GetAdminFrontNameTrait;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogSizeValidator;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Laminas\Http\Header\ContentType;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Laminas\Http\Headers;
use Magento\Backend\App\Response\Http\FileFactory as HttpFileFactory;
use Magento\Backend\Model\Auth;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Request as TestFrameworkRequest;
use Magento\TestFramework\Response as TestFrameworkResponse;
use Magento\TestFramework\TestCase\AbstractBackendController as AbstractBackendControllerTestCase;

/**
 * @covers DownloadLogsController
 */
class LogsTest extends AbstractBackendControllerTestCase
{
    use FileSystemTrait;
    use GetAdminFrontNameTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->uri = $this->getAdminFrontName() . '/klevu_logger/download/logs';
        $this->resource = 'Klevu_LoggerApi::developer_logging';
    }

    /**
     * @dataProvider testRoute_Returns404_ForNoneGetRequests_DataProvider
     */
    public function testRoute_Returns404_ForNoneGetRequests(string $httpMethod, int $responseCode): void
    {
        if ($this->uri === null) {
            $this->markTestIncomplete('testRoute_Returns404_ForNoneGetRequests test is not complete');
        }
        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setMethod($httpMethod);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();

        $this->dispatch($this->uri);
        $this->assertSame($responseCode, $response->getHttpResponseCode());
    }

    /**
     * @return mixed[][]
     */
    public function testRoute_Returns404_ForNoneGetRequests_DataProvider(): array
    {
        return [
            [HttpRequest::METHOD_GET, 302],
            [HttpRequest::METHOD_PUT, 404],
            [HttpRequest::METHOD_DELETE, 404],
        ];
    }

    public function testRedirects_WhenNoLogsExist(): void
    {
        $this->deleteAllLogs();
        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParams(['store' => 1]);

        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertTrue($response->isRedirect());

        $messages = $this->getMessages();
        $this->assertContains('There are no logs to download for store 1: Default Store View (default).', $messages);
    }

    public function testRedirects_WhenInvalidStoreProvided(): void
    {
        $invalidStoreId = 1438756783425;
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFile();

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParams(['store' => $invalidStoreId]);

        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertTrue($response->isRedirect());

        $messages = $this->getMessages();
        $this->assertContains('There are no logs to download for store ID ' . $invalidStoreId . '.', $messages);
    }

    public function testRedirects_WhenFileSizeExceedsLimit(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFileWithContents();

        // set low limit to trigger validator
        $logSizeValidator = $this->objectManager->create(LogSizeValidator::class, [
            'downloadLimit' => 1,
        ]);
        $this->objectManager->addSharedInstance($logSizeValidator, LogSizeValidator::class);

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParams(['store' => 1]);

        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertTrue($response->isRedirect());

        $messages = $this->getMessages();
        $this->assertContains(
            'File Size Exceeds Limit: File size is too large to download via admin.' .
            ' Please ask your developers to download the logs from the server.',
            $messages,
        );
    }

    public function testReturns_FileAsStream(): void
    {
        $authStorageMock = $this->createPartialMock(
            BackendAuthSession::class,
            ['isFirstPageAfterLogin', 'processLogout', 'processLogin'],
        );
        $mockAuth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->once())
            ->method('getAuthStorage')
            ->willReturn($authStorageMock);
        $httpFileFactory = $this->objectManager->create(HttpFileFactory::class, [
            'auth' => $mockAuth,
        ]);
        $this->objectManager->addSharedInstance($httpFileFactory, HttpFileFactory::class);

        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFileWithContents();

        /** @var TestFrameworkRequest $request */
        $request = $this->getRequest();
        $request->setParams(['store' => 1]);

        $this->dispatch($this->uri);

        /** @var TestFrameworkResponse $response */
        $response = $this->getResponse();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
        $this->assertFalse($response->isRedirect());

        /** @var Headers $headers */
        $headers = $response->getHeaders();
        $this->assertTrue($headers->has('content-type'));
        $contentType = $headers->get('content-type');
        $this->assertInstanceOf(ContentType::class, $contentType);
        $this->assertSame('application/octet-stream', $contentType->getMediaType());
        $this->assertTrue($headers->has('content-disposition'));
        $contentDisposition = $headers->get('content-disposition');
        $contentDispositionValue = $contentDisposition->getFieldValue();
        $this->assertStringMatchesFormat(
            'attachment; filename="klevu_log_default_%d.tar.gz"',
            $contentDispositionValue,
        );
    }
}
