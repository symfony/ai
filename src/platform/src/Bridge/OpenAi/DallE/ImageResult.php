<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\DallE;

use Symfony\AI\Platform\Result\BaseResult;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
class ImageResult extends BaseResult
{
    /** @var list<Base64Image|UrlImage> */
    private readonly array $images;

    /**
     * @param list<Base64Image|UrlImage> $images
     */
    public function __construct(
        public ?string $revisedPrompt = null, // Only string on Dall-E 3 usage
        array $images = [],
    ) {
        $this->images = $images;
    }

    /**
     * @return list<Base64Image|UrlImage>
     */
    public function getContent(): array
    {
        return $this->images;
    }
}
