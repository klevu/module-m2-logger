<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;

/**
 * Handler class used to entirely disable logging
 * Used to prevent Klevu logs being pushed into core system logs in addition to
 *  custom log files
 */
class LogDisabled extends BaseHandler
{
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    /**
     * Never handle message writing, disabling write operations
     *
     * @param mixed[] $record
     *
     * @return bool
     */
    public function isHandling(array $record): bool
    {
        return false;
    }
    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
}
