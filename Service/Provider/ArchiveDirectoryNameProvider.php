<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Logger\Service\Provider;

use Klevu\LoggerApi\Service\Provider\ArchiveDirectoryNameProviderInterface;

class ArchiveDirectoryNameProvider implements ArchiveDirectoryNameProviderInterface
{
    private const ARCHIVE_DIRECTORY_NAME = '.archive';

    /**
     * @return string
     */
    public function get(): string
    {
        return self::ARCHIVE_DIRECTORY_NAME;
    }
}
