<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use OskarStark\Enum\Trait\Comparable;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum Role: string
{
    use Comparable;

    case System = 'system';
    case Assistant = 'assistant';
    case User = 'user';
    case ToolCall = 'tool';
}
