<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock\Recording;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Mock\Recording\Cassette;

final class CassetteTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/ai-recording-cassette-'.bin2hex(random_bytes(6)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testExistsReflectsFilePresence()
    {
        $cassette = new Cassette($this->path);
        $this->assertFalse($cassette->exists());

        $cassette->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'hi']);
        $this->assertTrue($cassette->exists());
    }

    public function testRecordPersistsInteraction()
    {
        $cassette = new Cassette($this->path);
        $cassette->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'hi']);

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame('gpt-4o', $data['interactions'][0]['model']);
        $this->assertSame('sig', $data['interactions'][0]['signature']);
        $this->assertSame(['type' => 'text', 'content' => 'hi'], $data['interactions'][0]['result']);
    }

    public function testMatchReturnsRecordedResult()
    {
        $recorder = new Cassette($this->path);
        $recorder->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'hi']);

        $cassette = new Cassette($this->path);

        $this->assertSame(['type' => 'text', 'content' => 'hi'], $cassette->match('sig'));
    }

    public function testMatchConsumesEqualSignaturesInOrder()
    {
        $recorder = new Cassette($this->path);
        $recorder->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'first']);
        $recorder->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'second']);

        $cassette = new Cassette($this->path);

        $this->assertSame('first', $cassette->match('sig')['content']);
        $this->assertSame('second', $cassette->match('sig')['content']);
    }

    public function testMatchThrowsWhenSignatureMissing()
    {
        $recorder = new Cassette($this->path);
        $recorder->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'hi']);

        $cassette = new Cassette($this->path);

        $this->expectException(RuntimeException::class);
        $cassette->match('unknown');
    }

    public function testMatchThrowsWhenEqualSignatureExhausted()
    {
        $recorder = new Cassette($this->path);
        $recorder->record('gpt-4o', 'sig', ['type' => 'text', 'content' => 'only']);

        $cassette = new Cassette($this->path);
        $cassette->match('sig');

        $this->expectException(RuntimeException::class);
        $cassette->match('sig');
    }
}
