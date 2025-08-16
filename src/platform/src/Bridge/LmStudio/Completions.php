<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author André Lubian <lubiana123@gmail.com>
 */
final class Completions
{
    public const DEFAULT_CAPABILITIES = [
        Capability::INPUT_MESSAGES,
        Capability::OUTPUT_TEXT,
        Capability::OUTPUT_STREAMING,
    ];

    public static function create(
        string $name,
        array $options = ['temperature' => 0.7],
        array $capabilities = self::DEFAULT_CAPABILITIES,
    ): Model {
        return new Model($name, $capabilities, $options);
    }
}
