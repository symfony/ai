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
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Guillermo Lengemann <guillermo.lengemann@gmail.com>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param AssistantMessage $data
     *
     * @return array{
     *     role: 'assistant',
     *     type: 'message',
     *     content: ?string
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data->hasToolCalls()) {
            return $this->normalizer->normalize($data->getToolCalls(), $format, $context);
        }

        return [
            'role' => $data->getRole()->value,
            'type' => 'message',
            'content' => $data->getContent(),
        ];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }
}
