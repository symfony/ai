<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\ModelClient;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Test\ModelClientOptionsTestCase;

final class ModelClientOptionsTest extends ModelClientOptionsTestCase
{
    public static function wireFields(): array
    {
        return [
            PlatformOptions::MAX_OUTPUT_TOKENS => null,
            PlatformOptions::TEMPERATURE => null,
        ];
    }

    protected function sendRequest(array $options): array
    {
        $binary = file_exists('/usr/bin/echo') ? '/usr/bin/echo' : '/bin/echo';
        $commandLine = (new ModelClient($binary))->request(new ClaudeCode('sonnet'), 'Hello', $options)->getObject()->getCommandLine();

        preg_match_all("/'--([a-z-]+)'/", $commandLine, $matches);

        return array_fill_keys(str_replace('-', '_', $matches[1]), true);
    }
}
