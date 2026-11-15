<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes the input of a decision into the input of the Gemini Decisions API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InputNormalizer implements NormalizerInterface
{
    /**
     * @return array{input: string|list<array<string, string>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (\is_string($data)) {
            return ['input' => $data];
        }

        if (\is_array($data)) {
            return ['input' => json_encode($data, \JSON_THROW_ON_ERROR)];
        }

        if ($data instanceof Text) {
            return ['input' => $data->getText()];
        }

        $model = $context[Contract::CONTEXT_MODEL];
        \assert($model instanceof Model);

        $input = [];
        foreach ($data instanceof Collection ? $data->getContent() : [$data] as $content) {
            $input[] = $this->normalizeContent($content, $model);
        }

        return ['input' => $input];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!($context[Contract::CONTEXT_MODEL] ?? null) instanceof Decision || !isset($context[Contract::CONTEXT_OPTIONS]['questions'])) {
            return false;
        }

        return \is_string($data) || \is_array($data) || $data instanceof Text || $data instanceof Image || $data instanceof Collection;
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable, the support depends on the options of the invocation
        return [
            'native-string' => false,
            'native-array' => false,
            Text::class => false,
            Image::class => false,
            Collection::class => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeContent(ContentInterface $content, Model $model): array
    {
        if ($content instanceof Text) {
            return ['type' => 'text', 'text' => $content->getText()];
        }

        if (!$content instanceof Image) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support "%s" inputs.', $model->getName(), $content::class));
        }

        if (!$model->supports(Capability::INPUT_IMAGE)) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
        }

        return ['type' => 'image', 'data' => $content->asBase64(), 'mime_type' => $content->getFormat()];
    }
}
