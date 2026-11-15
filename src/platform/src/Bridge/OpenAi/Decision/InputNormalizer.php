<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes the input of a decision into the input of the OpenAI Decisions API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InputNormalizer implements NormalizerInterface
{
    /**
     * @return array{input: string|list<array{role: 'user', content: list<array<string, mixed>>}>}
     *
     * @throws InvalidArgumentException When the input is a message bag
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if ($data instanceof MessageBag) {
            // Taken over from the Responses API normalizer, which would put the system message aside
            $model = $context[Contract::CONTEXT_MODEL];
            \assert($model instanceof Model);

            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires a string, an array, a "Text", an "Image" or a "Collection" input.', $model->getName()));
        }

        if (\is_string($data)) {
            return ['input' => $data];
        }

        if ($data instanceof Text) {
            return ['input' => $data->getText()];
        }

        if (\is_array($data)) {
            $content = [['type' => 'input_json', 'data' => $data]];
        } else {
            $model = $context[Contract::CONTEXT_MODEL];
            \assert($model instanceof Model);

            $content = [];
            foreach ($data instanceof Collection ? $data->getContent() : [$data] as $item) {
                $content[] = $this->normalizeContent($item, $model);
            }
        }

        return ['input' => [['role' => 'user', 'content' => $content]]];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        $model = $context[Contract::CONTEXT_MODEL] ?? null;

        if (!$model instanceof Gpt || !$model->supports(Capability::DECISION) || !isset($context[Contract::CONTEXT_OPTIONS]['questions'])) {
            return false;
        }

        return \is_string($data) || \is_array($data) || $data instanceof Text || $data instanceof Image || $data instanceof Collection || $data instanceof MessageBag;
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
            MessageBag::class => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeContent(ContentInterface $content, Model $model): array
    {
        if ($content instanceof Text) {
            return ['type' => 'input_text', 'text' => $content->getText()];
        }

        if (!$content instanceof Image) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support "%s" inputs.', $model->getName(), $content::class));
        }

        if (!$model->supports(Capability::INPUT_IMAGE)) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" does not support image inputs.', $model->getName()));
        }

        return ['type' => 'input_image', 'image_url' => $content->asDataUrl()];
    }
}
