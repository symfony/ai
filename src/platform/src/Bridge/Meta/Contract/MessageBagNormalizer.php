<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Meta\Contract;

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Bridge\Meta\LlamaPromptConverter;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageBagNormalizer extends ModelContractNormalizer
{
    public function __construct(
        private readonly LlamaPromptConverter $promptConverter = new LlamaPromptConverter(),
    ) {
    }

    /**
     * @param MessageBag $data
     *
     * @return array{prompt: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'prompt' => $this->promptConverter->convertToPrompt($data),
        ];
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Llama;
    }
}
