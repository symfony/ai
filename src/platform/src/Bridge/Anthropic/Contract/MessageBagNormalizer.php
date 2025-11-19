<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
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
     *     messages: array<string, mixed>,
     *     model?: string,
     *     system?: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = [
            'messages' => $this->normalizer->normalize($data->withoutSystemMessage()->getMessages(), $format, $context),
        ];

        if (null !== $system = $data->getSystemMessage()) {
            $array['system'] = $system->getContent();
        }

        if (isset($context[Contract::CONTEXT_MODEL]) && $context[Contract::CONTEXT_MODEL] instanceof Model) {
            $array['model'] = $context[Contract::CONTEXT_MODEL]->getName();
        }

        return $array;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Claude;
    }
}
