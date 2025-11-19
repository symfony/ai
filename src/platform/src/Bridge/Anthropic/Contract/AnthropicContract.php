<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Contract;

use Symfony\AI\Platform\Contract;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class AnthropicContract extends Contract
{
    public static function create(NormalizerInterface ...$normalizer): Contract
    {
        return parent::create(
            new AssistantMessageNormalizer(),
            new DocumentNormalizer(),
            new DocumentUrlNormalizer(),
            new ImageNormalizer(),
            new ImageUrlNormalizer(),
            new MessageBagNormalizer(),
            new ToolCallMessageNormalizer(),
            new ToolNormalizer(),
            ...$normalizer,
        );
    }
}
