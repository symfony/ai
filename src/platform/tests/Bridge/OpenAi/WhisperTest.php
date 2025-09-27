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
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class WhisperTest extends TestCase
{
    public function testItCreatesWhisperWithDefaultSettings()
    {
        $whisper = new Whisper('whisper-1');

        $this->assertSame('whisper-1', $whisper->getName());
        $this->assertSame([], $whisper->getOptions());
    }

    public function testItCreatesWhisperWithCustomSettings()
    {
        $whisper = new Whisper('whisper-1', options: ['language' => 'en', 'response_format' => 'json']);

        $this->assertSame('whisper-1', $whisper->getName());
        $this->assertSame(['language' => 'en', 'response_format' => 'json'], $whisper->getOptions());
    }
}
