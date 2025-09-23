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

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\OutputProcessorInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SearchResultProcessor implements OutputProcessorInterface
{
    public function processOutput(Output $output): void
    {
        $metadata = $output->result->getMetadata();

        if ($output->result instanceof StreamResult) {
            $generator = $output->result->getContent();
            // Makes $metadata accessible in the stream loop.
            $generator->send($metadata);

            return;
        }

        $rawResponse = $output->result->getRawResult()?->getObject();
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
