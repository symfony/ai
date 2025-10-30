<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\ElevenLabs;

use Symfony\AI\Agent\Output;
use Symfony\AI\Agent\Voice;
use Symfony\AI\Agent\VoiceProviderInterface;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Platform;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class VoiceProvider implements VoiceProviderInterface
{
    public function __construct(
        private Platform $elevenLabsPlatform,
    ) {
    }

    public function addVoice(Output $output): void
    {
        $result = $output->getResult();

        $voice = $this->elevenLabsPlatform->invoke($this->getName(), $result->getContent());

        $output->setVoice(new Voice($voice->asBinary(), $this->getName()));
    }

    public function getName(): string
    {
        return ElevenLabs::ELEVEN_MULTILINGUAL_V2;
    }
}
