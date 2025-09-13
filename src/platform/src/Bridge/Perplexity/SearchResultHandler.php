<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Result\ResultHandlerInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SearchResultHandler implements ResultHandlerInterface
{
    public function handleResult(ResultInterface $result): void
    {
        $metadata = $result->getMetadata();

        if ($result instanceof StreamResult) {
            $generator = $result->getContent();
            // Makes $metadata accessible in the stream loop.
            $generator->send($metadata);

            return;
        }

        $rawResponse = $result->getRawResult()?->getObject();
        if (!$rawResponse instanceof ResponseInterface) {
            return;
        }

        $content = $rawResponse->toArray(false);

        if (\array_key_exists('search_results', $content)) {
            $metadata->add('search_results', $content['search_results']);
        }

        if (\array_key_exists('citations', $content)) {
            $metadata->add('citations', $content['citations']);
        }
    }
}
