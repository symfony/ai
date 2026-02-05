<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Contract;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Joshua Behrens <code@joshua-behrens.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param AssistantMessage $data
     *
     * @return array{
     *     role: Role::Assistant,
     *     content: string,
     *     tool_calls: list<array{
     *         type: 'function',
     *         function: array{
     *             name: string,
     *             arguments: array<string, mixed>
     *         }
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = $data->getContent();
        if (null === $content) {
            $content = '';
        } elseif (!\is_string($content)) {
            if ($content instanceof \Stringable) {
                $content = (string) $content;
            } else {
                $content = json_encode(
                    $this->normalizer->normalize($content, $format, $context),
                    \JSON_THROW_ON_ERROR
                );
            }
        }

        return [
            'role' => Role::Assistant,
            'content' => $content,
            'tool_calls' => array_values(array_map(static function (ToolCall $message): array {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $message->getName(),
                        // stdClass forces empty object
                        'arguments' => [] === $message->getArguments() ? new \stdClass() : $message->getArguments(),
                    ],
                ];
            }, $data->getToolCalls() ?? [])),
        ];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Ollama;
    }
}
