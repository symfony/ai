<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class MessageBagNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param MessageBag $data
     *
     * @return array{
     *     input: array<string, mixed>,
     *     model?: string,
     *     system?: string,
     * }
     *
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $messages['input'] = [];

        foreach ($data->withoutSystemMessage()->getMessages() as $message) {
            $normalized = $this->normalizer->normalize($message, $format, $context);

            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                $messages['input'] = array_merge($messages['input'], $normalized);
                continue;
            }

            $messages['input'][] = $normalized;
        }

        if ($data->getSystemMessage()) {
            $messages['instructions'] = $data->getSystemMessage()->getContent();
        }

        return $messages;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
