<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Meta;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class LlamaResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model->supports(Capability::OUTPUT_TEXT);
    }

    public function convert(RawResultInterface $result, array $options = []): TextResult
    {
        $data = $result->getData();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('Response does not contain output.');
        }

        return new TextResult($data['choices'][0]['message']['content']);
    }
}
