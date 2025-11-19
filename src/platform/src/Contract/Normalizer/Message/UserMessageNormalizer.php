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

use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class UserMessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UserMessage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            UserMessage::class => true,
        ];
    }

    /**
     * @param UserMessage $data
     *
     * @return array{role: 'assistant', content: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = ['role' => $data->getRole()->value];

        if (1 === \count($data->getContent()) && $data->getContent()[0] instanceof Text) {
            $array['content'] = $data->getContent()[0]->getText();

            return $array;
        }

        $array['content'] = $this->normalizer->normalize($data->getContent(), $format, $context);

        return $array;
    }
}
