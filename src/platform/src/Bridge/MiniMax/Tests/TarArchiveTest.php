<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\TarArchive;

/**
 * The fixture is a real ustar archive produced by `tar --format ustar`, reproducing the layout
 * MiniMax delivers: the payload sits in a directory whose name is long enough to push the path into
 * the header's `prefix` field, and the wanted `.mp3` is not the first member.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TarArchiveTest extends TestCase
{
    public function testItFindsAMemberBehindOtherEntries()
    {
        $this->assertSame('ID3FAKE-MP3-PAYLOAD', TarArchive::findByExtension($this->archive(), 'mp3'));
        $this->assertSame('TITLES-PLACEHOLDER', TarArchive::findByExtension($this->archive(), 'titles'));
        $this->assertSame('EXTRA', TarArchive::findByExtension($this->archive(), 'extra'));
    }

    public function testTheExtensionMayBeGivenWithOrWithoutADot()
    {
        $this->assertSame('ID3FAKE-MP3-PAYLOAD', TarArchive::findByExtension($this->archive(), '.mp3'));
    }

    public function testItReturnsNullForAMemberThatIsNotThere()
    {
        $this->assertNull(TarArchive::findByExtension($this->archive(), 'wav'));
    }

    public function testItReturnsNullOnAnEmptyOrTruncatedArchive()
    {
        $this->assertNull(TarArchive::findByExtension('', 'mp3'));
        $this->assertNull(TarArchive::findByExtension(str_repeat("\0", 512), 'mp3'));
        $this->assertNull(TarArchive::findByExtension('not a tar at all', 'mp3'));
    }

    private function archive(): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/minimax-async-speech.tar');
    }
}
