<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Validator;

use Klevu\LoggerApi\Service\Provider\StoreLogsDirectoryProviderInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class LogSizeValidator extends AbstractValidator implements LogValidatorInterface
{
    /**
     * 1G
     */
    public const LOG_SIZE_LIMIT = 1073741824;

    /**
     * @var StoreLogsDirectoryProviderInterface
     */
    private readonly StoreLogsDirectoryProviderInterface $logsDirectoryProvider;
    /**
     * @var int|null
     */
    private readonly ?int $downloadLimit;

    /**
     * @param StoreLogsDirectoryProviderInterface $logsDirectoryProvider
     * @param int|null $downloadLimit
     */
    public function __construct(
        StoreLogsDirectoryProviderInterface $logsDirectoryProvider,
        ?int $downloadLimit = null,
    ) {
        $this->logsDirectoryProvider = $logsDirectoryProvider;
        $this->downloadLimit = $downloadLimit;
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        return $this->validateType(value: $value)
            && $this->validateLogDirSizeUnderLimit(value: $value);
    }

    /**
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
                'Invalid argument supplied. Expected string, received %1',
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
    private function validateLogDirSizeUnderLimit(string $value): bool
    {
        if (!$this->downloadLimit || $this->getLogDirectorySize(value: $value) <= $this->downloadLimit) {
            return true;
        }
        $this->_addMessages(messages: [
            __(
                'File size is too large to download via admin. ' .
                'Please ask your developers to download the logs from the server.',
            ),
        ]);

        return false;
    }

    /**
     * @param string $value
     *
     * @return int
     */
    private function getLogDirectorySize(string $value): int
    {
        $files = $this->logsDirectoryProvider->getLogsWithFileSize(directory: $value);
        $size = 0;
        foreach ($files as $file) {
            $size += (int)($file['size'] ?? 0);
        }

        return $size;
    }
}
