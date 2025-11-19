<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

final class Collection implements ContentInterface
{
    /**
     * @var ContentInterface[]
     */
    private readonly array $content;

    public function __construct(ContentInterface ...$content)
    {
        $this->content = $content;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContent(): array
    {
        return $this->content;
    }
}
