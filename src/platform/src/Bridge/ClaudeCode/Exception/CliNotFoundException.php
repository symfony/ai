<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class CliNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The "claude" CLI binary was not found. Please install it via "npm install -g @anthropic-ai/claude-code" or provide the path to the binary.');
    }
}
