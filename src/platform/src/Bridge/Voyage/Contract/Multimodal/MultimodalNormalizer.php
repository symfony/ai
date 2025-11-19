<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Contract\Multimodal;

use Symfony\AI\Platform\Bridge\Voyage\Voyage;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class MultimodalNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param ContentInterface[] $data
     *
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return array_map(
            function (ContentInterface $item) use ($format, $context) {
                $normalized = $this->normalizer->normalize($item, $format, $context);

                return array_pop($normalized);
            },
            $data
        );
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        $model = $context[Contract::CONTEXT_MODEL] ?? null;
        if (!$model instanceof Voyage || !$model->supports(Capability::INPUT_MULTIMODAL)) {
            return false;
        }

        return \is_array($data) && [] === array_filter($data, fn ($item) => !$item instanceof ContentInterface);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            'native-array' => true,
        ];
    }
}
