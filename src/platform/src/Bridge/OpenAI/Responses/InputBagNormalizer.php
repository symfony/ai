<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAI\Responses;

use Symfony\AI\Platform\Bridge\OpenAI\Responses;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\InputBagInterface;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InputBagNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportedDataClass(): string
    {
        return MessageBagInterface::class;
    }

    public function supportsModel(Model $model): bool
    {
        return $model instanceof Responses;
    }

    /**
     * @param InputBagInterface $data
     *
     * @return array{
     *     input: array<string, mixed>,
     *     model?: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = [
            'input' => $this->normalizer->normalize($data->getMessages(), $format, $context),
        ];

        if (isset($context[Contract::CONTEXT_MODEL]) && $context[Contract::CONTEXT_MODEL] instanceof Model) {
            $array['model'] = $context[Contract::CONTEXT_MODEL]->getName();
        }

        return $array;
    }
}
