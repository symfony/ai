<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock\Recording;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * A JSON file of recorded provider interactions used by {@see RecordingProvider}.
 *
 * Each interaction stores the model, a request signature, and the serialized result (see
 * {@see ResultSerializer}). Interactions sharing a signature are replayed first-in-first-out,
 * mirroring the drop-in semantics of Symfony's MockHttpClient when given an array of responses.
 *
 * @phpstan-type Interaction array{model: string, signature: string, result: array<string, mixed>}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Cassette
{
    /**
     * @var list<Interaction>
     */
    private array $interactions = [];

    /**
     * @var list<int>
     */
    private array $consumed = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @param array<string, mixed> $result the serialized result produced by {@see ResultSerializer::toArray()}
     */
    public function record(string $model, string $signature, array $result): void
    {
        $this->load();

        $this->interactions[] = [
            'model' => $model,
            'signature' => $signature,
            'result' => $result,
        ];

        $this->save();
    }

    /**
     * Returns the next unused recorded result matching the given signature (FIFO).
     *
     * @return array<string, mixed> the serialized result for {@see ResultSerializer::fromArray()}
     */
    public function match(string $signature): array
    {
        $this->load();

        foreach ($this->interactions as $index => $interaction) {
            if ($interaction['signature'] !== $signature) {
                continue;
            }

            if (\in_array($index, $this->consumed, true)) {
                continue;
            }

            $this->consumed[] = $index;

            return $interaction['result'];
        }

        throw new RuntimeException(\sprintf('Cassette "%s" has no recorded interaction for signature "%s"; re-record it with AI_RECORD=1.', $this->path, $signature));
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_file($this->path)) {
            return;
        }

        $raw = file_get_contents($this->path);
        if (false === $raw || '' === trim($raw)) {
            return;
        }

        $data = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        $this->interactions = $data['interactions'] ?? [];
    }

    private function save(): void
    {
        $directory = \dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($this->path, json_encode(['interactions' => $this->interactions], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n");
    }
}
