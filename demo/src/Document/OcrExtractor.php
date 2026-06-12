<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Document;

use Symfony\AI\Platform\Bridge\Mistral\Ocr\Result\OcrResult;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class OcrExtractor
{
    public function __construct(
        #[Autowire(service: 'ai.platform.mistral')]
        private readonly PlatformInterface $platform,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * OCR is billed per page, so the result for a given document URL is cached and
     * the Mistral OCR endpoint is only ever called once per document.
     */
    public function extract(string $url): OcrResult
    {
        return $this->cache->get('mistral_ocr_'.hash('xxh128', $url), function (ItemInterface $item) use ($url): OcrResult {
            $result = $this->platform->invoke('mistral-ocr-latest', new DocumentUrl($url));

            $ocr = $result->asObject();
            \assert($ocr instanceof OcrResult);

            return $ocr;
        });
    }
}
