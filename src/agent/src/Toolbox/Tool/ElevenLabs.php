<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @see https://elevenlabs.io/
 */
#[AsTool('text_to_speech', description: 'Convert text to speech / voice')]
final readonly class ElevenLabs
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        private string $path,
        private string $model,
        private string $voice,
    ) {
        if (!class_exists(Filesystem::class)) {
            throw new RuntimeException('For using the ElevenLabs TTS tool, the symfony/filesystem package is required. Try running "composer require symfony/filesystem".');
        }

        if (!class_exists(Uuid::class)) {
            throw new RuntimeException('For using the ElevenLabs TTS tool, the symfony/uid package is required. Try running "composer require symfony/uid".');
        }
    }

    /**
     * @return array{
     *     input: string,
     *     path: string,
     * }
     */
    public function __invoke(string $text): array
    {
        $response = $this->httpClient->request('POST', \sprintf('https://api.elevenlabs.io/v1/text-to-speech/%s?output_format=mp3_44100_128', $this->voice), [
            'headers' => [
                'xi-api-key' => $this->apiKey,
            ],
            'json' => [
                'text' => $text,
                'model_id' => $this->model,
            ],
        ]);

        $file = \sprintf('%s/%s.mp3', $this->path, Uuid::v4()->toRfc4122());

        $filesystem = new Filesystem();
        $filesystem->dumpFile($file, $response->getContent());

        return [
            'input' => $text,
            'path' => $file,
        ];
    }
}
