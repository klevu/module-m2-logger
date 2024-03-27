<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Api;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\WebApi\ArchiveLogs;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Integration\Api\AdminTokenServiceInterface;
use Magento\TestFramework\Bootstrap;
use Magento\TestFramework\Helper\Bootstrap as BootstrapHelper;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * @covers ArchiveLogs
 */
class ArchiveLogsTest extends WebapiAbstract
{
    use FileSystemTrait;

    private const RESOURCE_PATH = '/V1/klevu_logger/archive_logs/store/';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager;
    /**
     * @var RoleFactory|null
     */
    private ?RoleFactory $roleFactory;
    /**
     * @var RulesFactory|null
     */
    private ?RulesFactory $rulesFactory;
    /**
     * @var AdminTokenServiceInterface|null
     */
    private ?AdminTokenServiceInterface $adminTokens;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = BootstrapHelper::getObjectManager();
        $this->roleFactory = $this->objectManager->get(RoleFactory::class);
        $this->rulesFactory = $this->objectManager->get(RulesFactory::class);
        $this->adminTokens = $this->objectManager->get(AdminTokenServiceInterface::class);
    }

    /**
     * @dataProvider testErrorReturned_WhenIncorrectHttpMethod_DataProvider
     */
    public function testErrorReturned_WhenIncorrectHttpMethod(string $method): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '1';
        $this->createLogFile();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('{"message":"Request does not match any route.","trace":null}');

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . $storeId,
                'httpMethod' => $method,
            ],
        ];
        $requestData = ['store' => 3845769832457];
        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @return string[][]
     */
    public function testErrorReturned_WhenIncorrectHttpMethod_DataProvider(): array
    {
        return [
            [RestRequest::HTTP_METHOD_GET],
            [RestRequest::HTTP_METHOD_PUT],
            [RestRequest::HTTP_METHOD_DELETE],
        ];
    }

    /**
     * @magentoApiDataFixture Magento/User/_files/user_with_custom_role.php
     */
    public function testAclNoAccess(): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '1';
        $this->createLogFile();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches(
        // phpcs:ignore Generic.Files.LineLength.TooLong
            '/{"message":"The consumer isn\'t authorized to access %resources.","parameters":{"resources":"Klevu_LoggerApi::developer_logging"}.*/',
        );

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . $storeId,
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
                'token' => $this->getTokenForUserWithRoles(['Magento_Cms::save']),
            ],
        ];
        $requestData = ['store' => $storeId];

        $this->_webApiCall($serviceInfo, $requestData);
    }

    /**
     * @magentoApiDataFixture Magento/User/_files/user_with_custom_role.php
     */
    public function testAclHasAccess(): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '1';
        $this->createLogFile();

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . $storeId,
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
                'token' => $this->getTokenForUserWithRoles(['Klevu_LoggerApi::developer_logging']),
            ],
        ];
        $requestData = ['store' => $storeId];
        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertIsArray($response, 'Response');
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('success', $response['status']);
        $this->assertArrayHasKey('code', $response);
        $this->assertSame(200, $response['code']);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame('Logs Archived for Store Id ' . $storeId, $response['message']);
    }

    public function testInfoReturned_WhenInvalidStoreProvided(): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '3845769832457';
        $this->createLogFile();

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . $storeId,
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
            ],
        ];
        $requestData = ['store' => $storeId];
        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertIsArray($response, 'Response');
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('info', $response['status']);
        $this->assertArrayHasKey('code', $response);
        $this->assertSame(404, $response['code']);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame('No logs to archive for store ' . $storeId, $response['message']);
    }

    public function testInfoReturned_WhenDirectoryDoesNotExist(): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '1';
        $this->deleteAllLogs();

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . '1',
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
            ],
        ];
        $requestData = ['store' => $storeId];
        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertIsArray($response, 'Response');
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('info', $response['status']);
        $this->assertArrayHasKey('code', $response);
        $this->assertSame(404, $response['code']);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame('No logs to archive for store ' . $storeId, $response['message']);
    }

    public function testErrorReturned_WhenFileSystemExceptionThrown(): void
    {
        $this->markTestSkipped(
            'Cannot added shared instance to run in code triggered by an api call',
        );
    }

    public function testArchiveLogs_ReturnsSuccess(): void
    {
        $this->_markTestAsRestOnly();
        $storeId = '1';
        $this->createLogFile();

        $serviceInfo = [
            WebapiAbstract::ADAPTER_REST => [
                'resourcePath' => self::RESOURCE_PATH . $storeId,
                'httpMethod' => RestRequest::HTTP_METHOD_POST,
            ],
        ];
        $requestData = ['store' => $storeId];
        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertIsArray($response, 'Response');
        $this->assertArrayHasKey('status', $response);
        $this->assertSame('success', $response['status']);
        $this->assertArrayHasKey('code', $response);
        $this->assertSame(200, $response['code']);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame('Logs Archived for Store Id ' . $storeId, $response['message']);
    }

    /**
     * @param string[]|null $roles
     *
     * @return string
     * @throws AuthenticationException
     * @throws InputException
     * @throws LocalizedException
     */
    private function getTokenForUserWithRoles(?array $roles = []): string
    {
        $role = $this->roleFactory->create();
        // there is no repository for authorisation role
        $role->load('test_custom_role', 'role_name'); // @phpstan-ignore-line
        $rules = $this->rulesFactory->create();
        $rules->setRoleId($role->getId());
        if ($roles) {
            $rules->setResources($roles);
        }
        $rules->saveRel();

        //Using the admin user with custom role.
        return $this->adminTokens->createAdminAccessToken(
            'customRoleUser',
            Bootstrap::ADMIN_PASSWORD,
        );
    }
}
