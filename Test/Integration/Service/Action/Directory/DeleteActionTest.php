<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Action\Directory;

use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Service\Action\Directory\DeleteAction;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Action\Directory\DeleteActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers DeleteAction
 */
class DeleteActionTest extends TestCase
{
    use FileSystemTrait;

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

    public function testImplements_ClearActionInterface(): void
    {
        $this->assertInstanceOf(
            DeleteActionInterface::class,
            $this->instantiateDeleteAction(),
        );
    }

    public function testPreference_ForClearActionInterface(): void
    {
        $this->assertInstanceOf(
            DeleteAction::class,
            $this->objectManager->create(DeleteActionInterface::class),
        );
    }

    public function testExecute_ThrowsException_WhenDirectoryMissing(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Directory Validation Exception: Directory (' . $directoryPath . ') does not exist',
        );

        $deleteAction = $this->objectManager->create(DeleteActionInterface::class);
        $deleteAction->execute($directoryPath);
    }

    public function testExecute_ThrowsException_WhenDirectoryNotWritable(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($directoryPath, 0555); // not writable

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessageMatches(
            '/Directory Validation Exception: Directory .* is not writable/',
        );

        $deleteAction = $this->objectManager->create(DeleteActionInterface::class);
        $deleteAction->execute($directoryPath);
    }

    public function testExecute_RemovesDirectory(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($directoryPath);

        $exceptionMessage = 'Mock File System Exception - Deletion Failed';
        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $mockFileSystemDriver = $this->getMockBuilder(File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockFileSystemDriver->expects($this->once())
            ->method('deleteDirectory')
            ->willThrowException(new InvalidDirectoryException(__($exceptionMessage)));

        $deleteAction = $this->objectManager->create(DeleteActionInterface::class, [
            'fileSystemDriver' => $mockFileSystemDriver,
        ]);
        $deleteAction->execute($directoryPath);

        $fileSystemDriver = $this->objectManager->get(File::class);
        $this->assertFalse($fileSystemDriver->isExists($directoryPath), 'Directory Exists');
    }

    public function testExecute_ThrowsFileSystemException_WhenDeletionFails(): void
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $this->createDirectory($directoryPath);
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->createDirectory($directoryPath);

        $deleteAction = $this->objectManager->create(DeleteActionInterface::class);
        $deleteAction->execute($directoryPath);
    }

    /**
     * @param mixed[]|null $params
     *
     * @return DeleteAction
     */
    private function instantiateDeleteAction(?array $params = []): DeleteAction
    {
        return $this->objectManager->create(
            DeleteAction::class,
            $params,
        );
    }
}
