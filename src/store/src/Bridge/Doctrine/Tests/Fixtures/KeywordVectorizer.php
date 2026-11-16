<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine\Tests\Fixtures;

use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\DocumentInterface;
use Symfony\AI\Store\Document\VectorizerInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * Counts how often each keyword occurs, one dimension per keyword.
 */
final class KeywordVectorizer implements VectorizerInterface
{
    /**
     * @var list<list<string>>
     */
    public array $calls = [];

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private readonly array $keywords = ['cat', 'dog', 'fish'],
        private readonly int $maxLength = \PHP_INT_MAX,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    /**
     * @return Vector|list<Vector>
     */
    public function vectorize(string|\Stringable|DocumentInterface|array $values, array $options = []): Vector|array
    {
        if (\is_array($values)) {
            $values = array_map(static fn (mixed $value): string => \is_string($value) ? $value : throw new InvalidArgumentException('Expected strings only.'), array_values($values));
            $this->calls[] = $values;

            if (null !== $this->failure) {
                throw $this->failure;
            }

            foreach ($values as $value) {
                if (\strlen($value) > $this->maxLength) {
                    throw new ExceedContextSizeException(\sprintf('The text exceeds %d characters.', $this->maxLength));
                }
            }

            return array_map($this->vectorFor(...), $values);
        }

        \assert(\is_string($values));
        $this->calls[] = [$values];

        return $this->vectorFor($values);
    }

    public function vectorFor(string $text): Vector
    {
        $text = strtolower($text);

        // A small constant keeps texts without any keyword away from the zero vector
        return new Vector(array_map(static fn (string $keyword): float => substr_count($text, $keyword) + 0.01, $this->keywords));
    }
}
