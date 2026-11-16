<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Model;

/**
 * A model served by a task pipeline of the Hugging Face inference service (hf-inference).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class Pipeline extends Model
{
}
