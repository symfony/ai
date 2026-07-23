<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Batch;

use Symfony\AI\Platform\ResultConverterInterface;

/**
 * Marks a converter as producing a {@see BatchJobResult} from a batch-submission response.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
interface BatchResultConverterInterface extends ResultConverterInterface
{
}
