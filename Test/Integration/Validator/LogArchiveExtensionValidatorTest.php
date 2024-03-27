<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\Logger\Validator\LogArchiveExtensionValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogArchiveExtensionValidator
 */
class LogArchiveExtensionValidatorTest extends TestCase
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
            $this->instantiateLogExtensionValidator(),
        );
    }

    /**
     * @dataProvider testValidationFails_WhenExtensionIsNotString_DataProvider
     */
    public function testValidationFails_WhenExtensionIsNotString(mixed $invalidExtension): void
    {
        $extensionValidator = $this->instantiateLogExtensionValidator();
        $isValid = $extensionValidator->isValid($invalidExtension);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($extensionValidator->hasMessages(), 'Has Error Messages');
        $errors = $extensionValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Extension value must be string. Received "' . get_debug_type($invalidExtension) . '".',
            $firstError->render(),
        );
    }

    /**
     * @return mixed[][]
     */
    public function testValidationFails_WhenExtensionIsNotString_DataProvider(): array
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
        $extensionValidator = $this->instantiateLogExtensionValidator();
        $isValid = $extensionValidator->isValid('    ');

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($extensionValidator->hasMessages(), 'Has Error Messages');
        $errors = $extensionValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            'Extension value cannot be empty.',
            $firstError->render(),
        );
    }

    /**
     * @dataProvider testValidationFails_WhenExtensionIsNotAllowed_DataProvider
     */
    public function testValidationFails_WhenExtensionIsNotAllowed(string $invalidExtension): void
    {
        $extensionValidator = $this->instantiateLogExtensionValidator();
        $isValid = $extensionValidator->isValid($invalidExtension);

        $this->assertFalse($isValid, 'Is Valid');
        $this->assertTrue($extensionValidator->hasMessages(), 'Has Error Messages');
        $errors = $extensionValidator->getMessages();
        $this->assertCount(1, $errors);
        $keys = array_keys($errors);
        $firstError = $errors[$keys[0]];
        $this->assertInstanceOf(Phrase::class, $firstError);
        $this->assertSame(
            "File extension is not a permitted value. Received '" . $invalidExtension .
            "'; expected one of '" . implode(',', ['bz', 'gz', 'zip',]) . "'.",
            $firstError->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testValidationFails_WhenExtensionIsNotAllowed_DataProvider(): array
    {
        return [
            ['log'],
            ['txt'],
            ['gaz'],
            ['tar'],
        ];
    }

    /**
     * @dataProvider testValidationSuccess_WhenExtensionIsAllowed_DataProvider
     */
    public function testValidationSuccess_WhenExtensionIsAllowed(string $extension): void
    {
        $extensionValidator = $this->instantiateLogExtensionValidator();
        $isValid = $extensionValidator->isValid($extension);

        $this->assertTrue($isValid, 'Is Valid');
        $this->assertFalse($extensionValidator->hasMessages(), 'Has Error Messages');
    }

    /**
     * @return string[][]
     */
    public function testValidationSuccess_WhenExtensionIsAllowed_DataProvider(): array
    {
        return [
            ['bz'],
            ['gz'],
            ['zip'],
        ];
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogArchiveExtensionValidator
     */
    private function instantiateLogExtensionValidator(?array $params = []): LogArchiveExtensionValidator
    {
        return $this->objectManager->create(
            LogArchiveExtensionValidator::class,
            $params,
        );
    }
}
