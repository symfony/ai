<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation;

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface EvalSuiteLoaderInterface
{
    /**
     * @throws InvalidArgumentException When the eval file is missing or malformed
     */
    public function load(string $skillDirectory): EvalSuite;
}
