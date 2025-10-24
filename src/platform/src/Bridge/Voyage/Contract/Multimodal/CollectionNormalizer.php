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
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

final class CollectionNormalizer extends Contract\Normalizer\ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const KEY_CONTENT = 'content';

    /**
     * @param Collection $data
     *
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = [];
        foreach ($data->getContent() as $item) {
            $normalized = $this->normalizer->normalize($item, $format, $context);
            $content = array_merge($content, array_pop($normalized)[self::KEY_CONTENT]);
        }

        return [['content' => $content]];
    }

    protected function supportedDataClass(): string
    {
        return Collection::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Voyage && $model->supports(Capability::INPUT_MULTIMODAL);
    }
}
