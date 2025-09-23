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
class Completions extends Model
{
    public const DEFAULT_CAPABILITIES = [
        Capability::INPUT_MESSAGES,
        Capability::OUTPUT_TEXT,
        Capability::OUTPUT_STREAMING,
    ];

    public function __construct(
        string $name,
        array $options = [],
        array $capabilities = self::DEFAULT_CAPABILITIES,
    ) {
        parent::__construct($name, $capabilities, $options);
    }
}
