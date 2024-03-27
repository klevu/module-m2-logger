<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Service\Provider;

use Klevu\Logger\Service\Provider\ArchiveDirectoryNameProvider;
use Klevu\LoggerApi\Service\Provider\ArchiveDirectoryNameProviderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class ArchiveDirectoryNameProviderTest extends TestCase
{
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

    public function testImplements_ArchiveDirectoryNameProviderInterface(): void
    {
        $this->assertInstanceOf(
            ArchiveDirectoryNameProviderInterface::class,
            $this->instantiateArchiveDirectoryNameProvider(),
        );
    }

    public function testPreference_ForArchiveDirectoryNameProviderInterface(): void
    {
        $logFileNameProviderInterface = $this->objectManager->create(ArchiveDirectoryNameProviderInterface::class);
        $this->assertInstanceOf(
            ArchiveDirectoryNameProvider::class,
            $logFileNameProviderInterface,
        );
    }

    public function testGet_ReturnsDirectoryName(): void
    {
        $provider = $this->instantiateArchiveDirectoryNameProvider();
        $this->assertSame('.archive', $provider->get());
    }

    /**
     * @param mixed[]|null $params
     *
     * @return ArchiveDirectoryNameProvider
     */
    private function instantiateArchiveDirectoryNameProvider(?array $params = []): ArchiveDirectoryNameProvider
    {
        return $this->objectManager->create(
            ArchiveDirectoryNameProvider::class,
            $params,
        );
    }
}
