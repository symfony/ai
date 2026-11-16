<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Doctrine;

/**
 * A Doctrine entity that builds the text of its embedding from the fields listed in #[Embedding].
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface IndexableEntityInterface
{
    public function getIndexableContent(): string;
}
