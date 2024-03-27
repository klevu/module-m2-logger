<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Action\Directory;

use Klevu\Logger\Exception\EmptyDirectoryException;
use Klevu\Logger\Exception\InvalidArchiverException;
use Klevu\Logger\Exception\InvalidDirectoryException;
use Klevu\Logger\Exception\InvalidFileExtensionException;
use Klevu\Logger\Service\Action\Directory\ArchiveAction;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\LoggerApi\Service\Action\Directory\ArchiveActionInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Archive\Bz;
use Magento\Framework\Archive\Gz;
use Magento\Framework\Archive\Tar;
use Magento\Framework\Archive\Zip;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers ArchiveAction
 */
class ArchiveActionTest extends TestCase
{
    use FileSystemTrait;
    use StoreTrait;

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
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->storeFixturesPool->rollback();
    }

    public function testImplements_ArchiveActionInterface(): void
    {
        $this->assertInstanceOf(
            ArchiveActionInterface::class,
            $this->instantiateArchiveAction(),
        );
    }

    public function testPreference_ForArchiveActionInterface(): void
    {
        $this->assertInstanceOf(
            ArchiveAction::class,
            $this->objectManager->create(ArchiveActionInterface::class),
        );
    }

    public function testExecute_ThrowsException_IfIncorrectTarArchiver(): void
    {
        $this->expectException(InvalidArchiverException::class);
        $this->expectExceptionMessage(
            'Invalid archiver provided for $tarArchive must be ' . Tar::class,
        );

        $archiveAction = $this->instantiateArchiveAction(['tarArchive' => new Zip()]);
        $archiveAction->execute('directory1', 'directory2');
    }

    /**
     * @dataProvider testExecute_ThrowsException_IfIncorrectExtension_dataProvider
     */
    public function testExecute_ThrowsException_IfIncorrectExtension(string $fileExtension): void
    {
        $allowedExtensions = ['bz', 'gz', 'zip',];

        $this->expectException(InvalidFileExtensionException::class);
        $this->expectExceptionMessage(
            "File extension is not a permitted value. Received '" . $fileExtension . "'; expected one of '"
            . implode(',', $allowedExtensions) . "'.",
        );

        $archiveAction = $this->instantiateArchiveAction(['extension' => $fileExtension]);
        $archiveAction->execute('directory1', 'directory2');
    }

    /**
     * @return string[][]
     */
    public function testExecute_ThrowsException_IfIncorrectExtension_dataProvider(): array
    {
        return [
            ['tar'],
            ['txt'],
            ['jpg'],
            ['log'],
        ];
    }

    public function testExecute_ThrowsException_IfLogDirectoryMissing(): void
    {
        $this->deleteAllLogs();
        $directoryPath = $this->createKlevuLogDirectory();
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Directory Exception, Directory (' . $directoryPath . ') does not exist',
        );

        $archiveAction = $this->instantiateArchiveAction();
        $archiveAction->execute($directoryPath, 'directory2');
    }

    public function testExecute_ThrowsExceptionIfArchivePathDirectoryNotValid(): void
    {
        $directoryPath = $this->createKlevuLogDirectory();
        $archiveDirectoryPath = $directoryPath;
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->deleteAllLogs();
        $this->createDirectory($directoryPath);
        $this->createLogFile();

        $expectedPath = $archiveDirectoryPath;
        $expectedPath .= DIRECTORY_SEPARATOR . '.archive' . DIRECTORY_SEPARATOR;

        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . 'archive_changed';
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;

        $this->expectException(InvalidDirectoryException::class);
        $this->expectExceptionMessage(
            'Archive Directory Path Exception, Directory path (' . $archiveDirectoryPath
            . ') does not contain required path (' . $expectedPath . ')',
        );

        $archiveAction = $this->instantiateArchiveAction();
        $archiveAction->execute($directoryPath, $archiveDirectoryPath);
    }

    /**
     * @dataProvider testExecute_ThrowsExceptionIfExtensionNotMatched_ForArchive_dataProvider
     */
    public function testExecute_ThrowsExceptionIfExtensionNotMatched_ForArchive(
        string $extension,
        string $archiveClass,
    ): void {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches(
            '/Provided Archive class \(' . preg_quote($archiveClass, '\\') . '.*\)' .
            ' does not match extension \(' . $extension . '\)/',
        );

        $directoryPath = $this->createKlevuLogDirectory();
        $archiveDirectoryPath = $directoryPath;
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . 'default';
        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->deleteAllLogs();
        $this->createDirectory($directoryPath);
        $this->createLogFile();

        $archiveAction = $this->instantiateArchiveAction(
            [
                'extension' => $extension,
                'archiveCompression' => $this->objectManager->get($archiveClass),
            ],
        );
        $archiveAction->execute($directoryPath, $archiveDirectoryPath);
    }

    /**
     * @return string[][]
     */
    public function testExecute_ThrowsExceptionIfExtensionNotMatched_ForArchive_dataProvider(): array
    {
        return [
            ['bz', Gz::class],
            ['bz', Zip::class],
            ['gz', Bz::class],
            ['gz', Zip::class],
            ['zip', Bz::class],
            ['zip', Gz::class],
        ];
    }

    public function testExecute_ThrowsExecption_IfNoLogsToArchive(): void
    {
        $directoryPath = $this->createKlevuLogDirectory();
        $archiveDirectoryPath = $directoryPath;
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . 'default';

        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->deleteAllLogs();
        $this->createDirectory($directoryPath);

        $this->expectException(EmptyDirectoryException::class);
        $this->expectExceptionMessage('Requested directory (' . $directoryPath . ') is empty and can not be archived');

        $archiveAction = $this->instantiateArchiveAction();
        $archiveAction->execute($directoryPath, $archiveDirectoryPath);
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testExecuteArchive_dataProvider
     */
    public function testExecute_ForDefaultStore(
        string $extension,
        string $archiveClass,
    ): void {
        $directoryPath = $this->createKlevuLogDirectory();
        $archiveDirectoryPath = $directoryPath;
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . 'default';

        $directoryPath .= DIRECTORY_SEPARATOR . 'default';
        $this->deleteAllLogs();
        $this->createDirectory($directoryPath);
        $this->createLogFile();
        $this->createLogFile('test2.log');

        $archiveAction = $this->instantiateArchiveAction([
            'extension' => $extension,
            'archiveCompression' => $this->objectManager->get($archiveClass),
        ]);
        $archive = $archiveAction->execute($directoryPath, $archiveDirectoryPath);

        $this->assertStringMatchesFormat(
            $archiveDirectoryPath . DIRECTORY_SEPARATOR . 'klevu_%s_default_%d.tar.' . $extension,
            $archive,
        );
        $this->assertFileExists($archive);
        $this->assertFileDoesNotExist(rtrim($archive, '.' . $extension));
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider testExecuteArchive_dataProvider
     */
    public function testExecute_ForOtherStore(
        string $extension,
        string $archiveClass,
    ): void {
        $this->createStore();
        $store = $this->storeFixturesPool->get('test_store');

        $directoryPath = $this->createKlevuLogDirectory();
        $archiveDirectoryPath = $directoryPath;
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . '.archive';
        $archiveDirectoryPath .= DIRECTORY_SEPARATOR . $store->getCode();
        $directoryPath .= DIRECTORY_SEPARATOR . $store->getCode();
        $this->deleteAllLogs();
        $this->createDirectory($directoryPath);
        $this->createLogFile('test.log', null, $store->getCode());
        $this->createLogFile('test2.log', null, $store->getCode());

        $archiveAction = $this->instantiateArchiveAction([
            'extension' => $extension,
            'archiveCompression' => $this->objectManager->get($archiveClass),
            'fileNamePrefix' => 'klevu_test_',
        ]);
        $archive = $archiveAction->execute($directoryPath, $archiveDirectoryPath);

        $this->assertStringMatchesFormat(
            $archiveDirectoryPath . DIRECTORY_SEPARATOR . 'klevu_test_' . $store->getCode() . '_%d.tar.' . $extension,
            $archive,
        );
        $this->assertFileExists($archive);
        $this->assertFileDoesNotExist(rtrim($archive, '.' . $extension));
    }

    /**
     * @return string[][]
     */
    public function testExecuteArchive_dataProvider(): array
    {
        return [
            ['zip', Zip::class],
            ['gz', Gz::class],
            ['bz', Bz::class],
        ];
    }

    /**
     * @param mixed[]|null $params
     *
     * @return ArchiveAction
     */
    private function instantiateArchiveAction(?array $params = []): ArchiveAction
    {
        return $this->objectManager->create(
            ArchiveAction::class,
            $params,
        );
    }

    /**
     * @return string
     * @throws FileSystemException
     */
    private function createKlevuLogDirectory(): string
    {
        $this->deleteAllLogs();
        $directoryList = $this->objectManager->get(DirectoryList::class);
        $directoryPath = $directoryList->getPath(DirectoryList::LOG);
        $directoryPath .= DIRECTORY_SEPARATOR . 'klevu';
        $this->createDirectory($directoryPath);

        return $directoryPath;
    }
}
