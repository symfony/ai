<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\AudioResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class ElevenLabsResultConverter implements ResultConverterInterface
{
    public function __construct(
        private string $outputPath,
    ) {
        if (!class_exists(Filesystem::class)) {
            throw new RuntimeException('For using ElevenLabs as platform, the symfony/filesystem package is required. Try running "composer require symfony/filesystem".');
        }

        if (!class_exists(MimeTypes::class)) {
            throw new RuntimeException('For using ElevenLabs as platform, the symfony/mime package is required. Try running "composer require symfony/mime".');
        }
    }

    public function supports(Model $model): bool
    {
        return $model instanceof ElevenLabs;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        /** @var ResponseInterface $response */
        $response = $result->getObject();

        return match (true) {
            str_contains($response->getInfo('url'), 'speech-to-text') => new TextResult($result->getData()['text']),
            str_contains($response->getInfo('url'), 'text-to-speech') => $this->doConvertTextToSpeech($result),
            default => throw new RuntimeException('Unsupported ElevenLabs response.'),
        };
    }

    private function doConvertTextToSpeech(RawResultInterface $result): ResultInterface
    {
        $payload = $result->getObject()->getContent();

        $path = \sprintf('%s/%s.mp3', $this->outputPath, uniqid());

        $filesystem = new Filesystem();
        $filesystem->dumpFile($path, $payload);

        return new AudioResult($path, (new MimeTypes())->guessMimeType($path));
    }
}
