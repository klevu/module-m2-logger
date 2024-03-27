<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogDirectoryValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogDirectoryValidator
 */
class LogDirectoryValidatorTest extends TestCase
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
            $this->instantiateLogDirectoryValidator(),
        );
    }

    /**
     * @dataProvider testValidationFails_WhenDirectoryPath_IsNotString_dataProvider
     */
    public function testValidationFails_WhenDirectoryPath_IsNotString(mixed $invalidPath): void
    {
        $logDirectoryValidator = $this->instantiateLogDirectoryValidator();
        $isValid = $logDirectoryValidator->isValid($invalidPath);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            "Directory Path value must be string. Received '" . get_debug_type($invalidPath) . "'.",
            $firstError->render(),
        );
    }

    /**
     * @return mixed[][]
     */
    public function testValidationFails_WhenDirectoryPath_IsNotString_dataProvider(): array
    {
        return [
            [null],
            [true],
            [false],
            [0],
            [1],
            [1.234],
            [new DataObject()],
            [json_decode(json_encode(['1' => '2']))],
        ];
    }

    public function testValidationFails_WhenDirectoryPath_IsEmpty(): void
    {
        $logDirectoryValidator = $this->instantiateLogDirectoryValidator();
        $isValid = $logDirectoryValidator->isValid('    ');

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Directory Path cannot be empty.',
            $firstError->render(),
        );
    }

    /**
     * @dataProvider testValidationFails_WhenDirectoryPath_ContainsInvalidCharacter_dataProvider
     */
    public function testValidationFails_WhenDirectoryPath_ContainsInvalidCharacter(string $invalidPath): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR;
        $path = $directoryPath . $invalidPath;

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator();
        $isValid = $logDirectoryValidator->isValid($path);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            expected: "Directory Path value contains illegal characters. " .
            "Received '" . $path . "'. " .
            "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
            actual: $firstError->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testValidationFails_WhenDirectoryPath_ContainsInvalidCharacter_dataProvider(): array
    {
        return [
            ['@12344'],
            ['aefnoin!'],
            ['default~store/'],
            ['store\\'],
            ['fashion\'s'],
        ];
    }

    /**
     * @dataProvider testValidationFails_WhenDirectoryPath_DoesNotInclude_VarLogKlevu_dataProvider
     */
    public function testValidationFails_WhenDirectoryPath_DoesNotInclude_VarLogKlevu(string $invalidPath): void
    {
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG) . DIRECTORY_SEPARATOR;
        $path = $directoryPath . $invalidPath;

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator();
        $isValid = $logDirectoryValidator->isValid($path);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logDirectoryValidator->hasMessages(), 'Has Error Messages');
        $errors = $logDirectoryValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Directory path (' . rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ')' .
            ' does not contain required path ' . '(' . $directoryPath . 'klevu' . DIRECTORY_SEPARATOR . ')',
            $firstError->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testValidationFails_WhenDirectoryPath_DoesNotInclude_VarLogKlevu_dataProvider(): array
    {
        return [
            ['klevu1/default'],
            ['Klevu/default'],
            ['klevu_default/store'],
            ['klevu-logs/default'],
        ];
    }

    public function testValidationSuccess_WhenDirectoryNotExists(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';

        $this->assertDirectoryExists($directoryPath);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => false,
        ]);
        $this->assertTrue($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertFalse($logDirectoryValidator->hasMessages(), 'Has Error Messages');
    }

    public function testValidationFails_WhenDirectoryNotExists_IsWritableValidation(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';

        $this->assertDirectoryExists($directoryPath);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
        ]);
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

    public function testValidationFails_WhenDirectoryHasWrongPermissions_IsWritableValidation(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory, 0555); // not writable

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
        ]);
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

    public function testValidationSuccess_WhenDirectoryExists_IsWritableValidation(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory);

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
        ]);
        $this->assertTrue($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertFalse($logDirectoryValidator->hasMessages(), 'Has Error Messages');
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

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
            'appendPath' => $archivePath,
        ]);
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
        $archivePath = '.archive';
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . $archivePath;
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory, 0555); // not writable

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
            'appendPath' => $archivePath,
        ]);
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
        $archivePath = '.archive';
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . $archivePath;
        $this->createDirectory($directoryPath);
        $storeDirectory = $directoryPath . DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($storeDirectory);

        $this->assertDirectoryExists($storeDirectory);

        $logDirectoryValidator = $this->instantiateLogDirectoryValidator([
            'validateIsWritable' => true,
            'appendPath' => $archivePath,
        ]);
        $this->assertTrue($logDirectoryValidator->isValid($storeDirectory), 'Is Valid');
        $this->assertFalse($logDirectoryValidator->hasMessages(), 'Has Error Messages');
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogDirectoryValidator
     */
    private function instantiateLogDirectoryValidator(?array $params = []): LogDirectoryValidator
    {
        return $this->objectManager->create(
            LogDirectoryValidator::class,
            $params,
        );
    }
}
