<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogArchiveDirectoryValidator as ArchiveDirectoryValidatorVirtualType;
use Klevu\Logger\Validator\LogDirectoryValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers ArchiveDirectoryValidatorVirtualType
 */
class LogArchiveDirectoryValidatorTest extends TestCase
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
            $this->instantiateLogArchiveDirectoryValidator(),
        );
    }

    public function testValidationFails_WhenDirectoryMissing_IsWritableValidation_Archive(): void
    {
        $archivePath = '.archive';
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $this->assertDirectoryExists($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . $archivePath;
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';

        $logDirectoryValidator = $this->instantiateLogArchiveDirectoryValidator();
        $this->assertFalse($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Directory (' . $storeDirectory . ') does not exist',
            $firstError->render(),
        );
    }

    public function testValidationFails_WhenDirectoryHasWrongPermissions_IsWritableValidation_Archive(): void
    {
        $this->deleteAllLogs();
        $directoryPath = $this->createArchiveDirectory();
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory, 0555); // not writable

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogArchiveDirectoryValidator();
        $this->assertFalse($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Directory (' . $storeDirectory . ') is not writable',
            $firstError->render(),
        );
    }

    public function testValidationSuccess_WhenDirectoryExists_IsWritableValidation_Archive(): void
    {
        $this->deleteAllLogs();
        $directoryPath = $this->createArchiveDirectory();
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory);

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogArchiveDirectoryValidator();
        $this->assertTrue($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertFalse($logDirectoryValidator->hasMessages(), 'Has Error Messages');
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogDirectoryValidator
     */
    private function instantiateLogArchiveDirectoryValidator(?array $params = []): LogDirectoryValidator
    {
        return $this->objectManager->create(
            ArchiveDirectoryValidatorVirtualType::class, // @phpstan-ignore-line virtualType
            $params,
        );
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    private function createArchiveDirectory(): string
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);

        return $directoryPath;
    }
}
