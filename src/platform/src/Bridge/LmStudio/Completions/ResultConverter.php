<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LmStudio\Completions;

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\LmStudio\Completions;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\ResultConverter as OpenAiResponseConverter;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author André Lubian <lubiana123@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly OpenAiResponseConverter $gptResponseConverter = new OpenAiResponseConverter(),
    ) {
    }

    public function supports(Model $model, Action $action): bool
    {
        if (Action::COMPLETE_CHAT !== $action) {
            return false;
        }

        return $model instanceof Completions;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->gptResponseConverter->convert($result, $options);
    }
}
