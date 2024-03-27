<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Test\Integration\Source\LogLevel;

use Klevu\Logger\Source\LogLevel\Monolog;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;
use Magento\TestFramework\Helper\Bootstrap;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers Monolog
 */
class MonologTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testImplementsOptionSourceInterface(): void
    {
        $this->assertInstanceOf(
            OptionSourceInterface::class,
            $this->instantiateMonoLog(),
        );
    }

    public function testSourceReturnsOptions(): void
    {
        $monolog = $this->instantiateMonoLog();
        $options = $monolog->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);
        $keys = array_keys($options);

        $expectedOptions = [
            0 => [
                'value' => Logger::ERROR,
                'label' => 'Errors Only',
            ],
            1 => [
                'value' => Logger::INFO,
                'label' => 'Standard',
            ],
            2 => [
                'value' => Logger::DEBUG,
                'label' => 'Verbose',
            ],
        ];

        foreach ($expectedOptions as $key => $expectedOption) {
            $option = $options[$keys[$key]];
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertSame($expectedOption['value'], $option['value']);
            $this->assertInstanceOf(Phrase::class, $option['label']);
            $this->assertSame($expectedOption['label'], $option['label']->render());
        }
    }

    /**
     * @return Monolog
     */
    private function instantiateMonoLog(): Monolog
    {
        return $this->objectManager->create(Monolog::class);
    }
}
