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

use Symfony\AI\Platform\Model;

/**
 * Marker model class for Bifrost image-generation requests, routed through
 * `/v1/images/generations`. Bifrost typically expects model names such as
 * `openai/dall-e-3`, `openai/gpt-image-1` or `google/imagen-3`.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class ImageModel extends Model
{
}
