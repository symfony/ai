<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
     *      system_instruction?: array{parts: array{text: string}[]}
     *  }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = ['contents' => []];

        if (null !== $systemMessage = $data->getSystemMessage()) {
            $array['system_instruction'] = [
                'parts' => [['text' => $systemMessage->getContent()]],
            ];
        }

        foreach ($data->withoutSystemMessage()->getMessages() as $message) {
            $array['contents'][] = [
                'role' => $message->getRole()->equals(Role::Assistant) ? 'model' : 'user',
                'parts' => $this->normalizer->normalize($message, $format, $context),
            ];
        }

        return $array;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
