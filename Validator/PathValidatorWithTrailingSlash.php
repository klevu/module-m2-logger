<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Validator;

use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\PathValidatorInterface;
use Magento\Framework\Filesystem\DriverInterface as FileSystemDriver;
use Magento\Framework\Phrase;

/**
 * @see \Magento\Framework\Filesystem\Directory\PathValidator
 * amended to add DIRECTORY_SEPARATOR to end of $realDirectoryPath
 * e.g. do not allow directories var/logs/klevu-logs/, var/logs/klevu1/, var/logs/klevu_logfile/, etc..
 * must be var/logs/klevu/
 * core class always removes final slash during validation
 */
class PathValidatorWithTrailingSlash implements PathValidatorInterface
{
    /**
     * @var FileSystemDriver
     */
    private readonly FileSystemDriver $fileSystemDriver;

    /**
     * @param FileSystemDriver $fileSystemDriver
     */
    public function __construct(FileSystemDriver $fileSystemDriver)
    {
        $this->fileSystemDriver = $fileSystemDriver;
    }

    /**
     * @param string $directoryPath
     * @param string $path
     * @param string|null $scheme
     * @param bool $absolutePath
     *
     * @return void
     * @throws ValidatorException
     */
    public function validate(
        string $directoryPath,
        string $path,
        ?string $scheme = null,
        bool $absolutePath = false,
    ): void {
        $realDirectoryPath = $this->fileSystemDriver->getRealPathSafety(path: $directoryPath);
        $realDirectoryPath = rtrim(string: $realDirectoryPath, characters: DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!$absolutePath) {
            $path = $this->fileSystemDriver->getAbsolutePath(
                basePath: $realDirectoryPath,
                path: $path,
                scheme: $scheme,
            );
        }
        $actualPath = $this->fileSystemDriver->getRealPathSafety(path: $path);
        $actualPath = rtrim(string: $actualPath, characters: DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (
            $path !== $realDirectoryPath
            && !str_starts_with(haystack: $actualPath, needle: $realDirectoryPath)
        ) {
            throw new ValidatorException(
                phrase: new Phrase(
                    text: "Path '%1' cannot be used with directory '%2'",
                    arguments: [$path, $directoryPath],
                ),
            );
        }
    }
}
