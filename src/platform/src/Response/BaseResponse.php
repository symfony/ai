<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response;

use Symfony\AI\Platform\Response\Metadata\MetadataAwareTrait;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
abstract class BaseResponse implements ResponseInterface
{
    use MetadataAwareTrait;
    use RawResponseAwareTrait;
}
