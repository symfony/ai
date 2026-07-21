<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
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
     *     id: string,
     *     status: 'completed',
     *     content: list<array{type: 'output_text', text: string, annotations: array{}}>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data->hasToolCalls()) {
            return $this->normalizer->normalize($data->getToolCalls(), $format, $context);
        }

        $content = [];
        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $content[] = [
                    'type' => 'output_text',
                    'text' => $part->getText(),
                    'annotations' => [],
                ];
            }
        }

        return [
            'role' => $data->getRole()->value,
            'type' => 'message',
            'id' => 'msg_'.str_replace('-', '', $data->getId()->toRfc4122()),
            'status' => 'completed',
            'content' => $content,
        ];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }
}
