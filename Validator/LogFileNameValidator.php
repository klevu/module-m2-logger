<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Validator;

use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Validator\AbstractValidator;

class LogFileNameValidator extends AbstractValidator implements LogValidatorInterface
{
    /**
     * @const string[]
     */
    private const ALLOWED_EXTENSIONS = ['log'];
    /**
     * Regular expression used to validate filename (does not include extension)
     * @const string
     */
    private const FILENAME_VALIDATION_REGEX = '/^[a-zA-Z0-9_\-][a-zA-Z0-9_\-\.]*$/';

    /**
     * @var FileIo
     */
    private readonly FileIo $fileIo;

    /**
     * @param FileIo $fileIo
     */
    public function __construct(
        FileIo $fileIo,
    ) {
        $this->fileIo = $fileIo;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        return $this->validateType(value: $value)
            && $this->validateNotEmpty(value: $value)
            && $this->validateDoesNotContainIllegalCharacters(value: $value)
            && $this->validateExtension(value: $value);
    }

    /**
     * Validates value is of correct type (string or null)
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if (is_string(value: $value)) {
            return true;
        }
        $this->_addMessages(messages: [
            __(
                "File Name value must be string. Received '%1'.",
                get_debug_type($value),
            ),
        ]);

        return false;
    }

    /**
     * Checks that the value is not empty and is not just a "dot file" (eg .log)
     *
     * @param string $value
     *
     * @return bool
     */
    private function validateNotEmpty(string $value): bool
    {
        $pathInfo = $this->fileIo->getPathInfo(path: $value);
        if (!trim(string: $pathInfo['filename'] ?? '')) {
            $this->_addMessages(messages: [__('File Name value cannot be empty.')]);

            return false;
        }

        return true;
    }

    /**
     * Validates only whitelisted characters are present in filename
     *
     * @param string $value
     *
     * @return bool
     */
    private function validateDoesNotContainIllegalCharacters(string $value): bool
    {
        if (preg_match(pattern: self::FILENAME_VALIDATION_REGEX, subject: $value)) {
            return true;
        }
        $this->_addMessages(messages: [
            __(
                "File Name value contains illegal characters. " .
                "Received '%1'. " .
                "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
                $value,
            ),
        ]);

        return false;
    }

    /**
     * Validates that the file name has an extension, and that the extension is permitted
     *
     * @param string $value
     *
     * @return bool
     */
    private function validateExtension(string $value): bool
    {
        $pathInfo = $this->fileIo->getPathInfo(path: $value);
        if (!trim(string: $pathInfo['extension'] ?? '')) {
            $this->_addMessages(messages: [__("File Name value must contain extension. Received '%1'.")]);

            return false;
        }

        if (
            !in_array(
                needle: strtolower(string: $pathInfo['extension']),
                haystack: self::ALLOWED_EXTENSIONS,
                strict: true,
            )
        ) {
            $this->_addMessages(messages: [
                __(
                    "File Name extension is not a permitted value. Received '%1'; expected one of '%2'.",
                    $pathInfo['extension'],
                    implode(separator: ',', array: self::ALLOWED_EXTENSIONS),
                ),
            ]);

            return false;
        }

        return true;
    }
}
