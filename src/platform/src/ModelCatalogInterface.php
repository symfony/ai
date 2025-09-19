<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface ModelCatalogInterface
{
    public function supports(PlatformInterface $platform): bool;

    public function getModel(string $modelName): Model;
}
