<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Source;

use Symfony\AI\Store\Document\SourceInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RssFeed implements SourceInterface
{
    public function __construct(
        private readonly string $url,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
