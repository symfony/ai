<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * A mock response for testing purposes.
 *
 * This class provides a simple way to create predictable responses
 * for the MockAgent.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MockResponse
{
    public function __construct(
        private readonly string $content = '',
    ) {
    }

    public function toResult(): ResultInterface
    {
        return new TextResult($this->content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Create a MockResponse from a string.
     */
    public static function create(string $content): self
    {
        return new self($content);
    }
}
