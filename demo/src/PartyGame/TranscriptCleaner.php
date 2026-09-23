<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PartyGame;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Turns a raw speech-to-text transcript into a clean sentence before it is shown to the player.
 *
 * Filler words and false starts are the norm for spoken input, but the joke or the point the
 * player is making must survive untouched, so the instruction below only allows tidying, not
 * rewriting.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TranscriptCleaner
{
    private const INSTRUCTIONS = <<<'PROMPT'
        You clean up spoken transcripts. Fix grammar and punctuation, remove filler words
        (um, uh, like, you know), keep the speaker's meaning, tone, jokes and personality
        exactly as intended. Output ONLY the cleaned sentence, nothing else, no quotes, no
        commentary.
        PROMPT;

    /**
     * @param non-empty-string $model
     */
    public function __construct(
        #[Autowire(service: 'ai.platform.openai')]
        private readonly PlatformInterface $platform,
        private readonly string $model = 'gpt-5-mini',
    ) {
    }

    public function cleanup(string $transcript): string
    {
        $messages = new MessageBag(
            Message::forSystem(self::INSTRUCTIONS),
            Message::ofUser($transcript),
        );

        $result = $this->platform->invoke($this->model, $messages, [
            'max_output_tokens' => 300,
        ]);

        $cleaned = trim($result->asText());

        // the instruction says "no quotes", but models occasionally wrap the answer anyway
        if (2 <= \strlen($cleaned) && str_starts_with($cleaned, '"') && str_ends_with($cleaned, '"')) {
            $cleaned = trim(substr($cleaned, 1, -1));
        }

        return $cleaned;
    }
}
