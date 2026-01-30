<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return array<string, array<mixed>|bool|string|int|float|\UnitEnum|null>
     */
    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();

        // Disable generation of config/reference.php - not needed in this app
        unset($parameters['.kernel.bundles_definition']);

        return $parameters;
    }
}
