<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DeepSeek;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

final class DeepSeek extends Model
{
    public const CHAT = 'deepseek-chat';
    public const REASONER = 'deepseek-reasoner';

    public function __construct(string $name = self::CHAT, array $options = ['temperature' => 1.0])
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
            Capability::TOOL_CALLING,
        ];

        parent::__construct($name, $capabilities, $options);
    }
}
