<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Messenger;

/**
 * A deserialization gadget: unserializing it writes a marker file, and so does destroying it.
 */
final class Gadget
{
    public string $marker = '';

    public function __destruct()
    {
        if ('' !== $this->marker) {
            file_put_contents($this->marker, 'destruct', \FILE_APPEND);
        }
    }

    /**
     * @param array{marker?: string} $data
     */
    public function __unserialize(array $data): void
    {
        $this->marker = $data['marker'] ?? '';
        file_put_contents($this->marker, 'unserialize', \FILE_APPEND);
    }
}
