<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

/**
 * Implement this in {@see ModelDescriberInterface} if you need to recursively build the schema.
 */
interface ModelDescriberAwareInterface
{
    public function setModelDescriber(ModelDescriberInterface $modelDescriber): void;
}
