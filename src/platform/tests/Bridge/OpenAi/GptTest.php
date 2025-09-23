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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(Gpt::class)]
#[Small]
final class GptTest extends TestCase
{
    public function testItCreatesGptWithDefaultSettings()
    {
        $gpt = new Gpt(Gpt::GPT_4O);

        $this->assertSame(Gpt::GPT_4O, $gpt->getName());
        $this->assertSame([], $gpt->getOptions());
    }

    public function testItCreatesGptWithCustomSettings()
    {
        $gpt = new Gpt(Gpt::GPT_4_TURBO, ['temperature' => 0.5, 'max_tokens' => 1000]);

        $this->assertSame(Gpt::GPT_4_TURBO, $gpt->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $gpt->getOptions());
    }
}
