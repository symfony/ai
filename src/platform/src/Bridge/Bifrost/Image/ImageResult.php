<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Image;

use Symfony\AI\Platform\Result\BaseResult;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class ImageResult extends BaseResult
{
    /**
     * @var list<Base64Image|UrlImage>
     */
    private readonly array $images;

    /**
     * @param list<Base64Image|UrlImage> $images
     */
    public function __construct(
        private readonly ?string $revisedPrompt = null,
        array $images = [],
    ) {
        $this->images = $images;
    }

    public function getRevisedPrompt(): ?string
    {
        return $this->revisedPrompt;
    }

    /**
     * @return list<Base64Image|UrlImage>
     */
    public function getContent(): array
    {
        return $this->images;
    }
}
