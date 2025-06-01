<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface OutputProcessorInterface
{
    public function processOutput(Output $output): void;
}
