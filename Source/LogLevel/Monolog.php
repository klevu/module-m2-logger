<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Source\LogLevel;

use Magento\Framework\Data\OptionSourceInterface;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;

class Monolog implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     * @return mixed[][]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Logger::ERROR,
                'label' => __('Errors Only'),
            ],
            [
                'value' => Logger::INFO,
                'label' => __('Standard'),
            ],
            [
                'value' => Logger::DEBUG,
                'label' => __('Verbose'),
            ],
        ];
    }
}
