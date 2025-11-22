<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Voice\Bridge\ElevenLabs;

use Symfony\AI\Agent\Output;
use Symfony\AI\Voice\VoiceProviderInterface;
use Symfony\AI\Platform\Platform;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class VoiceProvider implements VoiceProviderInterface
{
    public function __construct(
        private Platform $platform,
        private string $model,
    ) {
    }

    public function addVoice(Output $output): void
    {
        $result = $output->getResult();

        $voice = $this->platform->invoke($this->model, $result->getContent());

        $output->setVoice(new Voice($voice->asBinary(), $this->getName()));
    }
}
