<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Contract;

use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class MessageBagNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param MessageBag $data
     *
     * @return array{
     *      contents: list<array{
     *          role: 'model'|'user',
     *          parts: array<int, mixed>
     *      }>,
     *      systemInstruction?: array{parts: array{text: string}[]}
     *  }
     *
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $requestData = ['contents' => []];

        if (null !== $systemMessage = $data->getSystemMessage()) {
            $requestData['systemInstruction'] = [
                'parts' => [['text' => $systemMessage->getContent()]],
            ];
        }

        $previousMessage = null;
        foreach ($data->withoutSystemMessage()->getMessages() as $message) {
            $parts = $this->normalizer->normalize($message, $format, $context);

            // Vertex AI requires all responses to parallel function calls within a single content
            if ($message instanceof ToolCallMessage && $previousMessage instanceof ToolCallMessage) {
                $lastIndex = array_key_last($requestData['contents']);
                $requestData['contents'][$lastIndex]['parts'] = [...$requestData['contents'][$lastIndex]['parts'], ...$parts];
            } else {
                $requestData['contents'][] = [
                    'role' => $message->getRole()->equals(Role::Assistant) ? 'model' : 'user',
                    'parts' => $parts,
                ];
            }

            $previousMessage = $message;
        }

        return $requestData;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(BaseModel $model): bool
    {
        return $model instanceof Model;
    }
}
