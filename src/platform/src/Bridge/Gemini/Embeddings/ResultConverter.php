<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Embeddings;

use Symfony\AI\Platform\Contract\ResultConverter\VectorResultConverter;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final readonly class ResultConverter extends VectorResultConverter
{
    public function __construct()
    {
        parent::__construct('$.embeddings[*].values');
    }
}
