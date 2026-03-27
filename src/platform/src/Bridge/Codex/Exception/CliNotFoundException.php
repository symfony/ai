<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CliNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The "codex" CLI binary was not found. Please install it via "npm install -g @openai/codex" or provide the path to the binary.');
    }
}
