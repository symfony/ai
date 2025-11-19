<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Contract\Normalizer\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\AudioNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\ImageUrlNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\Content\TextNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\MessageBagNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\SystemMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Message\UserMessageNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Contract\Normalizer\ToolNormalizer;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Contract
{
    public const CONTEXT_MODEL = 'model';

    final public function __construct(
        protected readonly NormalizerInterface $normalizer,
    ) {
    }

    public static function create(NormalizerInterface ...$normalizer): self
    {
        // Messages
        $normalizer[] = new MessageBagNormalizer();
        $normalizer[] = new AssistantMessageNormalizer();
        $normalizer[] = new SystemMessageNormalizer();
        $normalizer[] = new ToolCallMessageNormalizer();
        $normalizer[] = new UserMessageNormalizer();

        // Message Content
        $normalizer[] = new AudioNormalizer();
        $normalizer[] = new ImageNormalizer();
        $normalizer[] = new ImageUrlNormalizer();
        $normalizer[] = new TextNormalizer();

        // Options
        $normalizer[] = new ToolNormalizer();

        // Result
        $normalizer[] = new ToolCallNormalizer();

        // JsonSerializable objects as extension point to library interfaces
        $normalizer[] = new JsonSerializableNormalizer();

        return new self(
            new Serializer($normalizer),
        );
    }

    /**
     * @param object|array<string|int, mixed>|string $input
     *
     * @return array<string, mixed>|string
     */
    final public function createRequestPayload(Model $model, object|array|string $input): string|array
    {
        return $this->normalizer->normalize($input, context: [self::CONTEXT_MODEL => $model]);
    }

    /**
     * @param Tool[] $tools
     *
     * @return array<string, mixed>
     */
    final public function createToolOption(array $tools, Model $model): array
    {
        return $this->normalizer->normalize($tools, context: [
            self::CONTEXT_MODEL => $model,
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        ]);
    }
}
