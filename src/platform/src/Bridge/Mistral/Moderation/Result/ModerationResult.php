<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Moderation\Result;

/**
 * Structured result of a Mistral moderation (/v1/moderations, /v1/chat/moderations) request.
 *
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class ModerationResult
{
    /**
     * @param list<Verdict> $verdicts One per moderated text or conversation
     */
    public function __construct(
        private readonly array $verdicts,
        private readonly string $id,
        private readonly string $model,
    ) {
    }

    /**
     * @return list<Verdict>
     */
    public function getVerdicts(): array
    {
        return $this->verdicts;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isFlagged(): bool
    {
        foreach ($this->verdicts as $verdict) {
            if ($verdict->isFlagged()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getFlaggedCategories(): array
    {
        $categories = [];
        foreach ($this->verdicts as $verdict) {
            $categories = array_merge($categories, $verdict->getFlaggedCategories());
        }

        return array_values(array_unique($categories));
    }
}
