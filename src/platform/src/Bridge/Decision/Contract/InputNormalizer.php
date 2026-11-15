<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Contract;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes the input of a decision into the state and images that TypeSafe, Ollama and OpenRouter share.
 *
 * The input is a string, structured data as an array, a {@see Text}, or a {@see Collection} of texts and images.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
 */
final class InputNormalizer implements NormalizerInterface
{
    /**
     * @return array{state: string|array<mixed>, images?: list<string>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (\is_string($data) || \is_array($data)) {
            return ['state' => $data];
        }

        $model = $context[Contract::CONTEXT_MODEL];
        \assert($model instanceof Model);

        $texts = [];
        $images = [];
        foreach ($data instanceof Collection ? $data->getContent() : [$data] as $content) {
            match (true) {
                $content instanceof Text => $texts[] = $content->getText(),
                $content instanceof Image => $images[] = $content->asBase64(),
                default => throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support "%s" inputs.', $model->getName(), $content::class)),
            };
        }

        if ([] === $texts) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires a text input.', $model->getName()));
        }

        $payload = ['state' => implode("\n\n", $texts)];

        if ([] !== $images) {
            if (!$model->supports(Capability::INPUT_IMAGE)) {
                throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
            }

            $payload['images'] = $images;
        }

        return $payload;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        $model = $context[Contract::CONTEXT_MODEL] ?? null;

        if (!$model instanceof Model || !$model->supports(Capability::DECISION) || !isset($context[Contract::CONTEXT_OPTIONS]['questions'])) {
            return false;
        }

        return \is_string($data) || \is_array($data) || $data instanceof Text || $data instanceof Collection;
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable, the support depends on the options of the invocation
        return [
            'native-string' => false,
            'native-array' => false,
            Text::class => false,
            Collection::class => false,
        ];
    }
}
