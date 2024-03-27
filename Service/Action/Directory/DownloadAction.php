<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Action\Directory;

use Klevu\Logger\Exception\ConfigurationException;
use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidArchiverException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\InvalidFileExtensionException;
use Klevu\LoggerApi\Service\Action\Directory\ArchiveActionInterface;
use Klevu\LoggerApi\Service\Action\Directory\DownloadActionInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Io\File as FileIo;

class DownloadAction implements DownloadActionInterface
{
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $validator;
    /**
     * @var ArchiveActionInterface
     */
    private readonly ArchiveActionInterface $archiveAction;
    /**
     * @var FileIo
     */
    private readonly FileIo $fileIo;

    /**
     * @param LogValidatorInterface $validator
     * @param ArchiveActionInterface $archiveAction
     * @param FileIo $fileIo
     */
    public function __construct(
        LogValidatorInterface $validator,
        ArchiveActionInterface $archiveAction,
        FileIo $fileIo,
    ) {
        $this->validator = $validator;
        $this->archiveAction = $archiveAction;
        $this->fileIo = $fileIo;
    }

    /**
     * @param string $directory
     *
     * @return string|null
     * @throws ConfigurationException
     * @throws EmptyDirectoryException
     * @throws FileSystemException
     * @throws InvalidDirectoryException
     */
    public function execute(string $directory): ?string
    {
        if (!$this->validator->isValid(value: $directory)) {
            $errors = $this->validator->hasMessages()
                ? $this->validator->getMessages()
                : [];
            throw new InvalidDirectoryException(
                __(
                    'Invalid Directory: %1',
                    implode(separator: ',', array: $errors),
                ),
            );
        }
        try {
            $pathInfo = $this->fileIo->getPathInfo(path: $directory);
            $destinationFilePath = $pathInfo['dirname'];

            return $this->archiveAction->execute(
                directory: $directory,
                archiveDirectory: $destinationFilePath,
            );
        } catch (InvalidArchiverException | InvalidFileExtensionException $exception) {
            throw new ConfigurationException(
                phrase: __(
                    'There is a configuration issue: %1',
                    $exception->getMessage(),
                ),
                cause: $exception,
            );
        }
    }
}
