<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Action\Directory;

use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\LoggerApi\Service\Action\Directory\DeleteActionInterface;
use Klevu\LoggerApi\Validator\LogValidatorInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DriverInterface as FileSystemDriverInterface;

class DeleteAction implements DeleteActionInterface
{
    /**
     * @var LogValidatorInterface
     */
    private readonly LogValidatorInterface $validator;
    /**
     * @var FileSystemDriverInterface
     */
    private readonly FileSystemDriverInterface $fileSystemDriver;

    /**
     * @param LogValidatorInterface $validator
     * @param FileSystemDriverInterface $fileSystemDriver
     */
    public function __construct(
        LogValidatorInterface $validator,
        FileSystemDriverInterface $fileSystemDriver,
    ) {
        $this->validator = $validator;
        $this->fileSystemDriver = $fileSystemDriver;
    }

    /**
     * @param string $directory
     *
     * @return bool
     * @throws FileSystemException
     * @throws InvalidDirectoryException
     */
    public function execute(string $directory): bool
    {
        $this->validateDirectoryPath(directory: $directory);

        return $this->fileSystemDriver->deleteDirectory(path: $directory);
    }

    /**
     * @param string $directory
     *
     * @return void
     * @throws InvalidDirectoryException
     */
    private function validateDirectoryPath(string $directory): void
    {
        if ($this->validator->isValid(value: $directory)) {
            return;
        }
        $errors = $this->validator->hasMessages()
            ? $this->validator->getMessages()
            : [];

        throw new InvalidDirectoryException(
            phrase: __(
                'Directory Validation Exception: %1',
                implode(separator: ',', array: $errors),
            ),
        );
    }
}
