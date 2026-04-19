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
    public const CONTEXT_OPTIONS = 'options';

    final public function __construct(
        protected readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @param NormalizerInterface[] $normalizers
     */
    public static function create(array $normalizers = []): self
    {
        // Messages
        $normalizers[] = new MessageBagNormalizer();
        $normalizers[] = new AssistantMessageNormalizer();
        $normalizers[] = new SystemMessageNormalizer();
        $normalizers[] = new ToolCallMessageNormalizer();
        $normalizers[] = new UserMessageNormalizer();

        // Message Content
        $normalizers[] = new AudioNormalizer();
        $normalizers[] = new ImageNormalizer();
        $normalizers[] = new ImageUrlNormalizer();
        $normalizers[] = new TextNormalizer();

        // Options
        $normalizers[] = new ToolNormalizer();

        // Result
        $normalizers[] = new ToolCallNormalizer();

        // JsonSerializable objects as extension point to library interfaces
        $normalizers[] = new JsonSerializableNormalizer();

        return new self(
            new Serializer($normalizers),
        );
    }

    /**
     * @param object|array<string|int, mixed>|string $input
     * @param array<string, mixed>                   $options Invocation options forwarded into the normalizer context as CONTEXT_OPTIONS
     *
     * @return array<string, mixed>|string
     */
    final public function createRequestPayload(Model $model, object|array|string $input, array $options = []): string|array
    {
        return $this->normalizer->normalize($input, context: [
            self::CONTEXT_MODEL => $model,
            self::CONTEXT_OPTIONS => $options,
        ]);
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
