<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
interface InitializableStoreInterface extends StoreInterface
{
    /**
     * @param array<mixed> $options
     */
    public function initialize(array $options = []): void;
}
