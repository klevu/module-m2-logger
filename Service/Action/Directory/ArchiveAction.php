<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Action\Directory;

use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidArchiverException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\InvalidFileExtensionException;
use Klevu\LoggerApi\Service\Action\Directory\ArchiveActionInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Archive\ArchiveInterface;
use Magento\Framework\Archive\Tar;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverInterface as FileSystemDriverInterface;
use Magento\Framework\Filesystem\Io\File as FileIo;

class ArchiveAction implements ArchiveActionInterface
{
    private const ARCHIVE_EXTENSION = 'tar';

    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $directoryValidator;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $archiveDirectoryValidator;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $archiveDirectoryPathValidator;
    /**
     * @var ArchiveInterface
     */
    private readonly ArchiveInterface $archiveCompression;
    /**
     * @var ArchiveInterface
     */
    private readonly ArchiveInterface $tarArchive;
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $extensionValidator;
    /**
     * @var FileSystemDriverInterface
     */
    private readonly FileSystemDriverInterface $fileSystemDriver;
    /**
     * @var FileIo
     */
    private readonly FileIo $fileIo;
    /**
     * @var string
     */
    private readonly string $extension;
    /**
     * @var string
     */
    private readonly string $fileNamePrefix;

    /**
     * @param LogValidatorInterface $directoryValidator
     * @param LogValidatorInterface $archiveDirectoryPathValidator
     * @param LogValidatorInterface $archiveDirectoryValidator
     * @param ArchiveInterface $archiveCompression
     * @param ArchiveInterface $tarArchive
     * @param LogValidatorInterface $extensionValidator
     * @param FileSystemDriverInterface $fileSystemDriver
     * @param FileIo $fileIo
     * @param string $extension
     * @param string $fileNamePrefix
     */
    public function __construct(
        LogValidatorInterface $directoryValidator,
        LogValidatorInterface $archiveDirectoryPathValidator,
        LogValidatorInterface $archiveDirectoryValidator,
        ArchiveInterface $archiveCompression,
        ArchiveInterface $tarArchive,
        LogValidatorInterface $extensionValidator,
        FileSystemDriverInterface $fileSystemDriver,
        FileIo $fileIo,
        string $extension = 'gz',
        string $fileNamePrefix = 'klevu_',
    ) {
        $this->directoryValidator = $directoryValidator;
        $this->archiveDirectoryValidator = $archiveDirectoryValidator;
        $this->archiveDirectoryPathValidator = $archiveDirectoryPathValidator;
        $this->archiveCompression = $archiveCompression;
        $this->tarArchive = $tarArchive;
        $this->extensionValidator = $extensionValidator;
        $this->fileSystemDriver = $fileSystemDriver;
        $this->extension = $extension;
        $this->fileNamePrefix = $fileNamePrefix;
        $this->fileIo = $fileIo;
    }

    /**
     * @param string $directory
     * @param string $archiveDirectory
     *
     * @return string
     * @throws EmptyDirectoryException
     * @throws FileSystemException
     * @throws InvalidArchiverException
     * @throws InvalidDirectoryException
     * @throws InvalidFileExtensionException
     */
    public function execute(string $directory, string $archiveDirectory): string
    {
        $this->validate(
            directory: $directory,
            archiveDirectory: $archiveDirectory,
        );

        return $this->archiveLogs(
            directory: $directory,
            archiveDirectory: $archiveDirectory,
        );
    }

    /**
     * @param string $directory
     * @param string $archiveDirectory
     *
     * @return void
     * @throws EmptyDirectoryException
     * @throws FileSystemException
     * @throws InvalidArchiverException
     * @throws InvalidDirectoryException
     * @throws InvalidFileExtensionException
     */
    private function validate(string $directory, string $archiveDirectory): void
    {
        $this->validateTarArchiver();
        $this->validateArchiveFileExtension();
        $this->validateDirectoryToArchive(directory: $directory);
        $this->validateContainsFiles(directory: $directory);
        $this->validateArchivePath(archiveDirectory: $archiveDirectory);
        $this->validateExtensionMatchesArchive();
    }

    /**
     * @return void
     * @throws InvalidArchiverException
     */
    private function validateTarArchiver(): void
    {
        if ($this->tarArchive instanceof Tar) {
            return;
        }
        throw new InvalidArchiverException(
            phrase: __(
                'Invalid archiver provided for $tarArchive must be %1',
                Tar::class,
            ),
        );
    }

    /**
     * @return void
     * @throws InvalidFileExtensionException
     */
    private function validateArchiveFileExtension(): void
    {
        if ($this->extensionValidator->isValid(value: $this->extension)) {
            return;
        }
        $errors = $this->extensionValidator->hasMessages()
            ? $this->extensionValidator->getMessages()
            : [];

        throw new InvalidFileExtensionException(
            phrase: __(
                '%1',
                implode(', ', $errors),
            ),
        );
    }

    /**
     * @param string $directory
     *
     * @return void
     * @throws InvalidDirectoryException
     */
    private function validateDirectoryToArchive(string $directory): void
    {
        if ($this->directoryValidator->isValid(value: $directory)) {
            return;
        }
        $errors = $this->directoryValidator->hasMessages()
            ? $this->directoryValidator->getMessages()
            : [];

        throw new InvalidDirectoryException(
            phrase: __(
                'Directory Exception, %1',
                implode(', ', $errors),
            ),
        );
    }

    /**
     * @param string $directory
     *
     * @return void
     * @throws EmptyDirectoryException
     * @throws FileSystemException
     */
    private function validateContainsFiles(string $directory): void
    {
        $files = $this->fileSystemDriver->readDirectory(path: $directory);
        if (count($files)) {
            return;
        }
        throw new EmptyDirectoryException(
            phrase: __(
                'Requested directory (%1) is empty and can not be archived',
                $directory,
            ),
        );
    }

    /**
     * @param string $archiveDirectory
     *
     * @return void
     * @throws InvalidDirectoryException
     */
    private function validateArchivePath(string $archiveDirectory): void
    {
        if ($this->archiveDirectoryPathValidator->isValid(value: $archiveDirectory)) {
            return;
        }
        $errors = $this->archiveDirectoryPathValidator->hasMessages()
            ? $this->archiveDirectoryPathValidator->getMessages()
            : [];

        throw new InvalidDirectoryException(
            phrase: __(
                'Archive Directory Path Exception, %1',
                implode(', ', $errors),
            ),
        );
    }

    /**
     * @return void
     * @throws \LogicException
     */
    private function validateExtensionMatchesArchive(): void
    {
        $fqdn = explode('\\', $this->archiveCompression::class);
        $className = array_pop($fqdn);
        if ($className === 'Interceptor') {
            $className = array_pop($fqdn);
        }
        if ($className === ucwords($this->extension)) {
            return;
        }
        throw new \LogicException(
            message: __(
                'Provided Archive class (%1) does not match extension (%2)',
                $this->archiveCompression::class,
                $this->extension,
            )->render(),
        );
    }

    /**
     * @param string $directory
     * @param string $archiveDirectory
     *
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    private function archiveLogs(string $directory, string $archiveDirectory): string
    {
        $uncompressedArchive = $this->createArchive(
            targetPath: $directory,
            archiveDirectoryPath: $archiveDirectory,
        );
        $archive = $this->compressArchive(tarArchive: $uncompressedArchive);
        $this->deleteUnCompressedArchive(tarArchive: $uncompressedArchive);

        return $archive;
    }

    /**
     * @param string $targetPath
     * @param string $archiveDirectoryPath
     *
     * @return string
     * @throws FileSystemException
     * @throws LocalizedException
     */
    private function createArchive(string $targetPath, string $archiveDirectoryPath): string
    {
        $this->createArchiveDirectory(archiveDirectory: $archiveDirectoryPath);
        $destinationPath = $archiveDirectoryPath . DIRECTORY_SEPARATOR . $this->getFileName(directory: $targetPath);

        return $this->tarArchive->pack(
            source: $targetPath,
            destination: $destinationPath,
        );
    }

    /**
     * @param string $archiveDirectory
     *
     * @return void
     * @throws FileSystemException
     */
    private function createArchiveDirectory(string $archiveDirectory): void
    {
        if (
            $this->archiveDirectoryPathValidator->isValid(value: $archiveDirectory)
            && !$this->archiveDirectoryValidator->isValid(value: $archiveDirectory)
        ) {
            $this->fileSystemDriver->createDirectory(path: $archiveDirectory);
        }
    }

    /**
     * @param string $directory
     *
     * @return string
     */
    private function getFileName(string $directory): string
    {
        $pathInfo = $this->fileIo->getPathInfo(path: $directory);

        $fileName = ($pathInfo['extension'] ?? null)
            ? $this->fileIo->getPathInfo(path: $pathInfo['dirname'])['basename'] . '_' . $pathInfo['filename']
            : $pathInfo['basename'];

        return strtolower(
            $this->fileNamePrefix
            . $fileName
            . '_'
            . time()
            . '.'
            . self::ARCHIVE_EXTENSION,
        );
    }

    /**
     * @param string $tarArchive
     *
     * @return string
     */
    private function compressArchive(string $tarArchive): string
    {
        return $this->archiveCompression->pack(
            source: $tarArchive,
            destination: $tarArchive . '.' . $this->extension,
        );
    }

    /**
     * @param string $tarArchive
     *
     * @return void
     * @throws FileSystemException
     */
    private function deleteUnCompressedArchive(string $tarArchive): void
    {
        $this->fileSystemDriver->deleteFile(path: $tarArchive);
    }
}
