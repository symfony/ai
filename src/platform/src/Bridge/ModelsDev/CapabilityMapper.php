<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelsDev;

use Symfony\AI\Platform\Capability;

/**
 * Maps models.dev model metadata to Symfony AI Capability enums.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class CapabilityMapper
{
    /**
     * @param array{
     *     id: string,
     *     name: string,
     *     family?: string,
     *     attachment: bool,
     *     reasoning: bool,
     *     tool_call: bool,
     *     structured_output?: bool,
     *     temperature: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     *     cost: array<string, float>,
     *     limit: array<string, int>,
     *     status?: string,
     *     knowledge?: string,
     *     release_date?: string,
     *     last_updated?: string,
     *     open_weights?: bool,
     * } $modelData
     *
     * @return list<Capability>
     */
    public static function map(array $modelData): array
    {
        if (self::isEmbeddingModel($modelData)) {
            return self::mapEmbeddingCapabilities($modelData);
        }

        return self::mapCompletionCapabilities($modelData);
    }

    /**
     * @param array{
     *     id: string,
     *     family?: string,
     *     tool_call: bool,
     *     attachment: bool,
     *     reasoning: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     * } $modelData
     */
    public static function isEmbeddingModel(array $modelData): bool
    {
        $family = $modelData['family'] ?? '';
        if ('' !== $family && str_contains($family, 'embed')) {
            return true;
        }

        if (str_contains($modelData['id'], 'embed')) {
            return true;
        }

        return false;
    }

    /**
     * @param array{
     *     tool_call: bool,
     *     structured_output?: bool,
     *     reasoning: bool,
     *     modalities: array{input: list<string>, output: list<string>},
     * } $modelData
     *
     * @return list<Capability>
     */
    private static function mapCompletionCapabilities(array $modelData): array
    {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
        ];

        if ($modelData['tool_call']) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        if ($modelData['structured_output'] ?? false) {
            $capabilities[] = Capability::OUTPUT_STRUCTURED;
        }

        if ($modelData['reasoning']) {
            $capabilities[] = Capability::THINKING;
        }

        $inputModalities = $modelData['modalities']['input'] ?? [];
        if (\in_array('image', $inputModalities, true)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }
        if (\in_array('pdf', $inputModalities, true)) {
            $capabilities[] = Capability::INPUT_PDF;
        }
        if (\in_array('audio', $inputModalities, true)) {
            $capabilities[] = Capability::INPUT_AUDIO;
        }

        $outputModalities = $modelData['modalities']['output'] ?? [];
        if (\in_array('image', $outputModalities, true)) {
            $capabilities[] = Capability::OUTPUT_IMAGE;
        }
        if (\in_array('audio', $outputModalities, true)) {
            $capabilities[] = Capability::OUTPUT_AUDIO;
        }

        return $capabilities;
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @return list<Capability>
     */
    private static function mapEmbeddingCapabilities(array $modelData): array
    {
        return [
            Capability::INPUT_TEXT,
            Capability::EMBEDDINGS,
        ];
    }
}
