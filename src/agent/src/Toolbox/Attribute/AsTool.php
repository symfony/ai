<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Attribute;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsTool
{
    /**
     * @param array<string, mixed> $metadata Arbitrary custom data attached to the tool, carried to
     *                                       {@see \Symfony\AI\Platform\Tool\Tool::getMetadata()}
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $method = '__invoke',
        public readonly array $metadata = [],
    ) {
    }
}
