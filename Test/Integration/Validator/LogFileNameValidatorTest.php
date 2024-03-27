<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Validator;

use Klevu\Logger\Validator\LogFileNameValidator;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers LogFileNameValidator
 */
class LogFileNameValidatorTest extends TestCase
{
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

    public function testImplements_LogValidatorInterface(): void
    {
        $this->assertInstanceOf(
            LogValidatorInterface::class,
            $this->instantiateLogFileNameValidator(),
        );
    }

    /**
     *
     * @dataProvider testIsValid_ReturnsTrue_ForValidFileName_dataProvider
     */
    public function testIsValid_ReturnsTrue_ForValidFileName(string $fileName): void
    {
        $logFileNameValidator = $this->instantiateLogFileNameValidator();
        $this->assertTrue(
            $logFileNameValidator->isValid($fileName),
            PHP_EOL . implode(PHP_EOL, $logFileNameValidator->getMessages()),
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_ReturnsTrue_ForValidFileName_dataProvider(): array
    {
        return [
            ['klevu_default_indexing_2387562387.log'],
            ['klevu_default.configuration.2387562387.log'],
            ['klevu_default_analytics_2387562387.log'],
            ['klevu_french_configuration_223875623879.log'],
            ['klevu-german_indexing_23875623870.log'],
            ['klevu-arabic_indexing_387562387.LOG'],
            ['KLEVU_SEARCH-UPPERCASE.LOG'],
        ];
    }

    /**
     * @dataProvider testIsValid_ReturnsFalse_IfFilenameIsNotAString_dataProvider
     */
    public function testIsValid_ReturnsFalse_IfFilenameIsNotAString(mixed $fileName): void
    {
        $logFileNameValidator = $this->instantiateLogFileNameValidator();
        $this->assertFalse(
            $logFileNameValidator->isValid($fileName),
            PHP_EOL . implode(PHP_EOL, $logFileNameValidator->getMessages()),
        );
        $messages = $logFileNameValidator->getMessages();
        $this->assertIsArray($messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $this->assertSame(
            "File Name value must be string. Received '" . get_debug_type($fileName) . "'.",
            $phrase->render(),
        );
    }

    /**
     * @return mixed[][]
     */
    public function testIsValid_ReturnsFalse_IfFilenameIsNotAString_dataProvider(): array
    {
        return [
            [false],
            [null],
            [3.123],
            [123],
            [true],
            [[1, 2]],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider testIsValid_ReturnsFalse_IfFilenameIsEmpty_dataProvider
     */
    public function testIsValid_ReturnsFalse_IfFilenameIsEmpty(string $fileName): void
    {
        $logFileNameValidator = $this->instantiateLogFileNameValidator();
        $this->assertFalse(
            $logFileNameValidator->isValid($fileName),
            PHP_EOL . implode(PHP_EOL, $logFileNameValidator->getMessages()),
        );
        $messages = $logFileNameValidator->getMessages();
        $this->assertIsArray($messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $this->assertSame(
            __(
                'File Name value cannot be empty.',
            )->getText(),
            $phrase->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_ReturnsFalse_IfFilenameIsEmpty_dataProvider(): array
    {
        return [
            [' '],
            ['      '],
            ['.log'],
            ['  .log'],
        ];
    }

    /**
     *
     * @dataProvider testIsValid_ReturnsFalse_IfFilenameContainsIllegalCharacter_dataProvider
     */
    public function testIsValid_ReturnsFalse_IfFilenameContainsIllegalCharacter(string $fileName): void
    {
        $logFileNameValidator = $this->instantiateLogFileNameValidator();
        $this->assertFalse(
            $logFileNameValidator->isValid($fileName),
            PHP_EOL . implode(PHP_EOL, $logFileNameValidator->getMessages()),
        );
        $messages = $logFileNameValidator->getMessages();
        $this->assertIsArray($messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $this->assertSame(
            "File Name value contains illegal characters. " .
            "Received '" . $fileName . "'. " .
            "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
            $phrase->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_ReturnsFalse_IfFilenameContainsIllegalCharacter_dataProvider(): array
    {
        return [
            ['klevu_default_indexing_2387562387@#$.log'],
            ['klevu_french_indexing_234;*#$.log'],
            ['klevu_french_analytics!@#$.log'],
            ['klevu_french_configuration!@#$.log'],
            ['klevu_arabic_indexing!?~.log'],
            ['.klevu_german_indexing@.log'],
            ['var/log/klevu_german_indexing.log'],
        ];
    }

    /**
     * @dataProvider testIsValid_ReturnsFalse_IfFileExtensionIsNotAllowed_dataProvider
     *
     */
    public function testIsValid_ReturnsFalse_IfFileExtensionIsNotAllowed(string $fileName): void
    {
        $logFileNameValidator = $this->instantiateLogFileNameValidator();
        $this->assertFalse(
            $logFileNameValidator->isValid($fileName),
            PHP_EOL . implode(PHP_EOL, $logFileNameValidator->getMessages()),
        );
        $messages = $logFileNameValidator->getMessages();
        $this->assertIsArray($messages);
        /** @var Phrase $phrase */
        $phrase = $messages[0];
        $fileExtension = explode('.', $fileName);
        $this->assertSame(
            "File Name extension is not a permitted value. Received '" . $fileExtension[1]
            . "'; expected one of 'log'.",
            $phrase->render(),
        );
    }

    /**
     * @return string[][]
     */
    public function testIsValid_ReturnsFalse_IfFileExtensionIsNotAllowed_dataProvider(): array
    {
        return [
            ['klevu_arabic_analytics.txt'],
            ['klevu_default_indexing.md'],
            ['klevu_german_configuration.doc'],
            ['klevu_arabic_indexing.logg'],
        ];
    }

    /**
     * @param mixed[]|null $params
     *
     * @return LogFileNameValidator
     */
    private function instantiateLogFileNameValidator(?array $params = []): LogFileNameValidator
    {
        return $this->objectManager->create(
            LogFileNameValidator::class,
            $params,
        );
    }
}
