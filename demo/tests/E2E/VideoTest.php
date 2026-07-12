<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

/**
 * The webcam of the browser is faked by Chrome, so the model captions its test pattern, see
 * E2ETestCase.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VideoTest extends E2ETestCase
{
    private const string PLACEHOLDER = 'Please define an instruction and hit submit.';

    public function testFrameOfTheWebcamIsCaptioned()
    {
        $this->visit('/video');

        // The webcam feed needs to run, otherwise there is no frame to grab for the stimulus
        // controller, which pushes it into the live component as data URL.
        $this->client->waitForVisibility('#videoFeed');

        $this->chat('What do you see?');

        $caption = $this->waitForTextChange('#welcome i', self::PLACEHOLDER);

        $this->assertNotSame('', $caption);
        $this->assertStringNotContainsString('Please provide both an instruction', $caption);

        $panel = $this->openAiPanel();

        // This use case has no agent, but calls the platform directly - hence no tools either.
        $panel->assertMetrics(platformCalls: 1, toolCalls: 0);
        $panel->assertPlatformCall('gpt-5.2');

        // The frame is captioned in a single, one-shot call.
        $calls = $panel->platformCalls();
        $this->assertCount(1, $calls);
        $this->assertStringContainsString('User:', $calls[0]['input']);
        $this->assertStringContainsString('max_output_tokens', $calls[0]['options']);
    }

    protected function requiredApiKeys(): array
    {
        return ['OPENAI_API_KEY'];
    }
}
