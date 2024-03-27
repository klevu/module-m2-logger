<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Validator;

use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class LogArchiveExtensionValidator extends AbstractValidator implements LogValidatorInterface
{
    /**
     * Lowercase array of extensions permitted in archive dir names
     * @const string[]
     */
    private const ALLOWED_EXTENSIONS = [
        'bz',
        'gz',
        'zip',
    ];

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        return $this->validateType($value)
            && $this->validateNotEmpty($value)
            && $this->validateExtension($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if (is_string($value)) {
            return true;
        }
        $this->_addMessages([
            __(
                'Extension value must be string. Received "%1".',
                get_debug_type($value),
            ),
        ]);

        return false;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function validateNotEmpty(string $value): bool
    {
        if (trim($value)) {
            return true;
        }
        $this->_addMessages([__('Extension value cannot be empty.')]);

        return false;
    }

    /**
     * Validates that the file has an extension, and that the extension is permitted
     *
     * @param string $value
     *
     * @return bool
     */
    private function validateExtension(string $value): bool
    {
        if (in_array(strtolower($value), self::ALLOWED_EXTENSIONS, true)) {
            return true;
        }
        $this->_addMessages([
            __(
                "File extension is not a permitted value. Received '%1'; expected one of '%2'.",
                $value,
                implode(',', self::ALLOWED_EXTENSIONS),
            ),
        ]);

        return false;
    }
}
