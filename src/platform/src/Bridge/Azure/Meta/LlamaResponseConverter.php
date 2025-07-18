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

use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\RawResponseInterface;
use Symfony\AI\Platform\Response\TextResponse;
use Symfony\AI\Platform\ResponseConverterInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class LlamaResponseConverter implements ResponseConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function convert(RawResponseInterface $response, array $options = []): TextResponse
    {
        $data = $response->getRawData();

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('Response does not contain output');
        }

        return new TextResponse($data['choices'][0]['message']['content']);
    }
}
