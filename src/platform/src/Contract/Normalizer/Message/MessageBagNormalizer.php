<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageBagNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof MessageBag;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageBag::class => true,
        ];
    }

    /**
     * @param MessageBag $data
     *
     * @return array{
     *     messages: array<string, mixed>,
     *     model?: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = [
            'messages' => $this->normalizer->normalize($data->getMessages(), $format, $context),
        ];

        if (isset($context[Contract::CONTEXT_MODEL]) && $context[Contract::CONTEXT_MODEL] instanceof Model) {
            $array['model'] = $context[Contract::CONTEXT_MODEL]->getName();
        }

        return $array;
    }
}
