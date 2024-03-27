<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogArchiveDirectoryPathValidator as ArchiveDirectoryPathValidatorVirtualType;
use Klevu\Logger\Validator\LogDirectoryValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers ArchiveDirectoryPathValidatorVirtualType
 */
class LogArchiveDirectoryPathValidatorTest extends TestCase
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

    public function testImplements_ValidatorInterface(): void
    {
        $this->assertInstanceOf(
            LogValidatorInterface::class,
            $this->instantiateLogArchiveDirectoryPathValidator(),
        );
    }

    public function testValidationSuccess_WhenDirectoryNotExists(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';

        $this->assertDirectoryExists($directoryPath);

        $logDirectoryValidator = $this->instantiateLogArchiveDirectoryPathValidator();
        $this->assertTrue($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertFalse($logDirectoryValidator->hasMessages(), 'Has Error Messages');
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogDirectoryValidator
     */
    private function instantiateLogArchiveDirectoryPathValidator(?array $params = []): LogDirectoryValidator
    {
        return $this->objectManager->create(
            ArchiveDirectoryPathValidatorVirtualType::class, // virtualType
            $params,
        );
    }
}
