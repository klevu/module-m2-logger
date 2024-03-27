<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogSizeValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogSizeValidator
 */
class LogSizeValidatorTest extends TestCase
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
            $this->instantiateLogSizeValidator(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testIsValid_ThrowsException_ForInvalidArgumentType_DataProvider
     */
    public function testIsValid_ReturnsFalse_ForInvalidArgumentType(mixed $value): void
    {
        $logSizeValidator = $this->instantiateLogSizeValidator();
        $isValid = $logSizeValidator->isValid($value);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logSizeValidator->hasMessages(), 'Has Messages');
        $messages = $logSizeValidator->getMessages();
        $this->assertIsArray($messages, 'Messages');
        $this->assertCount(1, $messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $this->assertSame(
            'Invalid argument supplied. Expected string, received ' . get_debug_type($value),
            $phrase->render(),
        );
    }

    /**
     * @return mixed[][]
     */
    public function testIsValid_ThrowsException_ForInvalidArgumentType_DataProvider(): array
    {
        return [
            [0],
            [100],
            [1.2],
            [null],
            [false],
            [true],
            [['string']],
            [json_decode(json_encode(['1' => '2']), false)],
        ];
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testIsValid_ReturnsTrue_ForSmallFiles(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFile();
        $directoryPath = $this->getStoreLogsDirectoryPath();

        $logSizeValidator = $this->instantiateLogSizeValidator();
        $isValid = $logSizeValidator->isValid($directoryPath);

        $this->assertTrue($isValid, 'IsValid');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testIsValid_ReturnsFalse_ForFileSizeOverLimit(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFileWithContents();
        $directoryPath = $this->getStoreLogsDirectoryPath();

        $logSizeValidator = $this->instantiateLogSizeValidator([
            'downloadLimit' => 1,
        ]);
        $isValid = $logSizeValidator->isValid($directoryPath);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($logSizeValidator->hasMessages(), 'Has Messages');
        $messages = $logSizeValidator->getMessages();
        $this->assertIsArray($messages, 'Messages');
        $this->assertCount(1, $messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $this->assertSame(
            'File size is too large to download via admin.' .
            ' Please ask your developers to download the logs from the server.',
            $phrase->render(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testIsValid_ReturnsTrue_WhenLimitNull(): void
    {
        $this->deleteAllLogs();
        $this->createStoreLogsDirectory();
        $this->createLogFileWithContents();
        $directoryPath = $this->getStoreLogsDirectoryPath();

        $logSizeValidator = $this->instantiateLogSizeValidator([
            'downloadLimit' => null,
        ]);
        $isValid = $logSizeValidator->isValid($directoryPath);

        $this->assertTrue($isValid, 'IsValid');
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogSizeValidator
     */
    private function instantiateLogSizeValidator(?array $params = []): LogSizeValidator
    {
        return $this->objectManager->create(
            LogSizeValidator::class,
            $params,
        );
    }
}
