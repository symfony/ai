<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev\Output;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Selected option plus the full probability distribution.
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ChoiceAnswer
{
    /**
     * @param array<string, float> $probabilities
     */
    public function __construct(
        private readonly string $choice,
        private readonly array $probabilities,
        private readonly ?float $confidence = null,
    ) {
    }

    public function getChoice(): string
    {
        return $this->choice;
    }

    /**
     * @return array<string, float>
     */
    public function getProbabilities(): array
    {
        return $this->probabilities;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['choice']) || !\is_string($data['choice'])) {
            throw new RuntimeException('Choice answer is missing a string "choice" value.');
        }

        return new self(
            $data['choice'],
            self::floatMap($data['probabilities'] ?? null, 'Choice'),
            isset($data['confidence']) && is_numeric($data['confidence']) ? (float) $data['confidence'] : null,
        );
    }

    /**
     * @return array{type: 'choice', choice: string, probabilities: array<string, float>, confidence?: float}
     */
    public function toArray(): array
    {
        $data = [
            'type' => 'choice',
            'choice' => $this->choice,
            'probabilities' => $this->probabilities,
        ];

        if (null !== $this->confidence) {
            $data['confidence'] = $this->confidence;
        }

        return $data;
    }

    /**
     * @return array<string, float>
     */
    private static function floatMap(mixed $value, string $kind): array
    {
        if (!\is_array($value)) {
            throw new RuntimeException(\sprintf('"%s" answer is missing a "probabilities" object.', $kind));
        }

        $out = [];
        foreach ($value as $key => $probability) {
            $out[(string) $key] = (float) $probability;
        }

        return $out;
    }
}
