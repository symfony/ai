<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Björn Altmann
 */
final class Nova
{
    public const MICRO = 'nova-micro';
    public const LITE = 'nova-lite';
    public const PRO = 'nova-pro';
    public const PREMIER = 'nova-premier';

    /**
     * @param array<string, mixed> $options The default options for the model usage
     */
    public static function create(
        string $name = self::PRO,
        array $options = ['temperature' => 1.0, 'max_tokens' => 1000],
    ): Model {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::TOOL_CALLING,
        ];

        if (self::MICRO !== $name) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        return new Model($name, $capabilities, $options);
    }
}
