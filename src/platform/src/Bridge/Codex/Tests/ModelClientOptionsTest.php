<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\ModelClient;
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
        $commandLine = (new ModelClient($binary))->request(new Codex('gpt-5-codex'), 'Hello', $options)->getObject()->getCommandLine();

        preg_match_all("/'--([a-z-]+)'/", $commandLine, $matches);

        return array_fill_keys(str_replace('-', '_', $matches[1]), true);
    }
}
