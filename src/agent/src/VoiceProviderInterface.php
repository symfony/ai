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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface VoiceProviderInterface
{
    public function addVoice(Output $output): void;
}
