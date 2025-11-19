<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract;

use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class UserMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param UserMessage $data
     *
     * @return array{
     *     role: 'user',
     *     content: array<array{
     *         text?: string,
     *         image?: array{
     *             format: string,
     *             source: array{bytes: string}
     *         }
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = ['role' => $data->getRole()->value];

        foreach ($data->getContent() as $value) {
            $contentPart = [];
            if ($value instanceof Text) {
                $contentPart['text'] = $value->getText();
            } elseif ($value instanceof Image) {
                $contentPart['image']['format'] = u($value->getFormat())->replace('image/', '')->replace('jpg', 'jpeg')->toString();
                $contentPart['image']['source']['bytes'] = $value->asBase64();
            } else {
                throw new RuntimeException('Unsupported message type.');
            }
            $array['content'][] = $contentPart;
        }

        return $array;
    }

    protected function supportedDataClass(): string
    {
        return UserMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Nova;
    }
}
