<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Validator;

use Klevu\Logger\Service\Provider\ArchiveDirectoryNameProvider;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\PathValidatorInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface as DirectoryWriteInterface;
use Magento\Framework\Validator\AbstractValidator;
use Psr\Log\LoggerInterface;

class LogDirectoryValidator extends AbstractValidator implements LogValidatorInterface
{
    /**
     * var/log/ directory to contain Klevu logs
     * @const string
     */
    private const KLEVU_LOG_DIRECTORY_IDENTIFIER = 'klevu';
    /**
     * Regular expression used to validate directory (does not include extension)
     * @const string
     */
    private const DIRECTORY_VALIDATION_REGEX = '/^[a-zA-Z0-9_\-\/][a-zA-Z0-9_\-\/\.]*$/';

    /**
     * @var DirectoryList
     */
    private readonly DirectoryList $directoryList;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var PathValidatorInterface
     */
    private readonly PathValidatorInterface $pathValidator;
    /**
     * @var DirectoryWriteInterface
     */
    private readonly DirectoryWriteInterface $fileSystemWrite;
    /**
     * @var bool
     */
    private readonly bool $validateIsWritable;
    /**
     * @var string
     */
    private readonly string $appendPath;

    /**
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param PathValidatorInterface $pathValidator
     * @param DirectoryWriteInterface $fileSystemWrite
     * @param ArchiveDirectoryNameProvider $archiveDirectoryNameProvider
     * @param bool $validateIsWritable
     * @param bool $isArchive
     */
    public function __construct(
        DirectoryList $directoryList,
        LoggerInterface $logger,
        PathValidatorInterface $pathValidator,
        DirectoryWriteInterface $fileSystemWrite,
        ArchiveDirectoryNameProvider $archiveDirectoryNameProvider,
        bool $validateIsWritable = true,
        bool $isArchive = false,
    ) {
        $this->directoryList = $directoryList;
        $this->logger = $logger;
        $this->pathValidator = $pathValidator;
        $this->fileSystemWrite = $fileSystemWrite;
        $this->validateIsWritable = $validateIsWritable;
        $this->appendPath = $isArchive ? $archiveDirectoryNameProvider->get() : '';
    }

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
        $isValid = $this->validateType(directoryPath: $value)
            && $this->validateIsNotEmpty(directoryPath: $value)
            && $this->validateDoesNotContainIllegalCharacters(directoryPath: $value)
            && $this->validateExpectedPath(directoryPath: $value);
        if ($this->validateIsWritable) {
            $isValid = $isValid
                && $this->validateDirectoryExists(directoryPath: $value)
                && $this->validateIsWritable(directoryPath: $value);
        }

        return $isValid;
    }

    /**
     * Validates value is of correct type (string or null)
     *
     * @param mixed $directoryPath
     *
     * @return bool
     */
    private function validateType(mixed $directoryPath): bool
    {
        if (is_string(value: $directoryPath)) {
            return true;
        }
        $this->_addMessages(messages: [
            __(
                "Directory Path value must be string. Received '%1'.",
                get_debug_type($directoryPath),
            ),
        ]);

        return false;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     */
    private function validateIsNotEmpty(string $directoryPath): bool
    {
        if (trim(string: $directoryPath)) {
            return true;
        }
        $this->_addMessages([__('Directory Path cannot be empty.')]);

        return false;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     */
    private function validateDoesNotContainIllegalCharacters(string $directoryPath): bool
    {
        if (preg_match(pattern: self::DIRECTORY_VALIDATION_REGEX, subject: $directoryPath)) {
            return true;
        }
        $this->_addMessages(messages: [
            __(
                "Directory Path value contains illegal characters. " .
                "Received '%1'. " .
                "Please ensure filename contains only alphanumeric, underscore, dash, or period characters.",
                $directoryPath,
            ),
        ]);

        return false;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     */
    private function validateExpectedPath(string $directoryPath): bool
    {
        try {
            $expectedDirectoryPath = $this->getExpectedDirectoryPath();
            // inject \Klevu\Logger\Validator\PathValidatorWithTrailingSlash to guard against
            // directories var/logs/klevu-logs/, var/logs/klevu1/, var/logs/klevu_logfile/, etc..
            // must be var/logs/klevu/
            // unless self::KLEVU_LOG_DIRECTORY_IDENTIFIER has been changed
            $this->pathValidator->validate(
                directoryPath: $expectedDirectoryPath,
                path: $directoryPath,
                absolutePath: true,
            );
        } catch (FileSystemException $exception) {
            $this->logger->error(
                message: 'Method: {method} - Info: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
            $this->_addMessages(messages: [
                __(
                    "Could not get expected directory path for validation. See logs for details.",
                ),
            ]);

            return false;
        } catch (ValidatorException) {
            $this->_addMessages(messages: [
                __(
                    'Directory path (%1) does not contain required path (%2)',
                    rtrim($directoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                    rtrim($expectedDirectoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                ),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     */
    private function validateDirectoryExists(string $directoryPath): bool
    {
        $isExists = false;
        try {
            $isExists = $this->fileSystemWrite->isExist($directoryPath);
            if (!$isExists) {
                $this->_addMessages(messages: [
                    __('Directory (%1) does not exist', $directoryPath),
                ]);
            }
        } catch (FileSystemException | ValidatorException $exception) {
            $this->_addMessages(messages: [
                __(
                    'There was an error validating the directory: %1. See logs for more details.',
                    $directoryPath,
                ),
            ]);
            $this->logger->error(
                message: 'Method: {method} - Info: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $isExists;
    }

    /**
     * @param string $directoryPath
     *
     * @return bool
     */
    private function validateIsWritable(string $directoryPath): bool
    {
        $isWritable = false;
        try {
            $isWritable = $this->fileSystemWrite->isWritable(path: $directoryPath);
            if (!$isWritable) {
                $this->_addMessages(messages: [
                    __('Directory (%1) is not writable', $directoryPath),
                ]);
            }
        } catch (FileSystemException | ValidatorException $exception) {
            $this->_addMessages(messages: [
                __(
                    'There was an error validating the directory: %1. See logs for more details.',
                    $directoryPath,
                ),
            ]);
            $this->logger->error(
                message: 'Method: {method} - Info: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $isWritable;
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    private function getExpectedDirectoryPath(): string
    {
        $expectedPath = $this->directoryList->getPath(code: DirectoryList::LOG);
        $expectedPath .= DIRECTORY_SEPARATOR . self::KLEVU_LOG_DIRECTORY_IDENTIFIER . DIRECTORY_SEPARATOR;
        if ($this->appendPath) {
            $expectedPath .= trim($this->appendPath) . DIRECTORY_SEPARATOR;
        }

        return $expectedPath;
    }
}
