<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class GptTest extends TestCase
{
    public function testItCreatesGptWithDefaultSettings()
    {
        $gpt = new Gpt('gpt-4o');

        $this->assertSame('gpt-4o', $gpt->getName());
        $this->assertSame([], $gpt->getOptions());
    }

    public function testItCreatesGptWithCustomSettings()
    {
        $gpt = new Gpt('gpt-4-turbo', [], ['temperature' => 0.5, 'max_tokens' => 1000]);

        $this->assertSame('gpt-4-turbo', $gpt->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $gpt->getOptions());
    }
}
