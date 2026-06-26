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

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class WebSearch implements ContentInterface
{
    public function __construct(
        private readonly ?string $query = null,
        private readonly ?string $id = null,
        private readonly ?string $status = null,
    ) {
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
}
