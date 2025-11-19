<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class UserMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param UserMessage $data
     *
     * @return list<array{inline_data?: array{mime_type: string, data: string}}>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parts = [];
        foreach ($data->getContent() as $content) {
            if ($content instanceof Text) {
                $parts[] = ['text' => $content->getText()];
            }
            if ($content instanceof File) {
                $parts[] = ['inline_data' => [
                    'mime_type' => $content->getFormat(),
                    'data' => $content->asBase64(),
                ]];
            }
        }

        return $parts;
    }

    protected function supportedDataClass(): string
    {
        return UserMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
