<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author rglozman
 */
final readonly class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['choices'][0]['message'])) {
            throw new RuntimeException('Response does not contain message');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('Message does not contain content');
        }

        return new TextResult($data['choices'][0]['message']['content']);
    }
}
