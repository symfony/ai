<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Contract;

use Symfony\AI\Platform\Bridge\Ollama\Ollama;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

/**
 * Ollama expects the content as a string and images as a separate base64 list.
 *
 * @see https://docs.ollama.com/api/chat
 *
 * @author Julian Sawicki <julek23@gmail.com>
 */
final class UserMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param UserMessage $data
     *
     * @return array{role: 'user', content: string, images?: list<string>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $texts = [];
        $images = [];

        foreach ($data->getContent() as $content) {
            if ($content instanceof Text) {
                $texts[] = $content->getText();

                continue;
            }

            if ($content instanceof Image) {
                $images[] = $content->asBase64();

                continue;
            }

            throw new InvalidArgumentException(\sprintf('Content of type "%s" is not supported by Ollama user messages, only "%s" and "%s" are.', $content::class, Text::class, Image::class));
        }

        $message = [
            'role' => $data->getRole()->value,
            'content' => implode("\n", $texts),
        ];

        if ([] !== $images) {
            $message['images'] = $images;
        }

        return $message;
    }

    protected function supportedDataClass(): string
    {
        return UserMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Ollama;
    }
}
