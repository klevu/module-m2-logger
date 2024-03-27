<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service;

use Klevu\LoggerApi\Service\FileNameSanitizerServiceInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\ValidatorException;

class FileNameSanitizerService implements FileNameSanitizerServiceInterface
{
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $fileNameValidator;

    /**
     * @param LogValidatorInterface $fileNameValidator
     */
    public function __construct(LogValidatorInterface $fileNameValidator)
    {
        $this->fileNameValidator = $fileNameValidator;
    }

    /**
     * @param string $fileName
     *
     * @return string
     * @throws ValidatorException
     */
    public function execute(string $fileName): string
    {
        $parts = explode(separator: DIRECTORY_SEPARATOR, string: $fileName);
        if (!$this->fileNameValidator->isValid(value: $parts[count($parts) - 1])) {
            $errors = $this->fileNameValidator->hasMessages()
                ? $this->fileNameValidator->getMessages()
                : [];
            throw new ValidatorException(
                phrase: __(
                    'Filename Validation Failed: %1',
                    implode('; ', $errors),
                ),
            );
        }
        $parts = array_filter(
            array: $parts,
            callback: static fn (string $value): bool => (
                !in_array(needle: $value, haystack: [' ', '.', '..'], strict: true)
            ),
        );

        return implode(separator: DIRECTORY_SEPARATOR, array: $parts);
    }
}
