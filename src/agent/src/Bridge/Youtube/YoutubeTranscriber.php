<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Youtube;

use MrMySQL\YoutubeTranscript\TranscriptListFetcher;
use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('youtube_transcript', 'Fetches the transcript of a YouTube video')]
final class YoutubeTranscriber
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
        if (!class_exists(TranscriptListFetcher::class)) {
            throw new LogicException('For using the YouTube transcription tool, the mrmysql/youtube-transcript package is required. Try running "composer require mrmysql/youtube-transcript".');
        }
    }

    /**
     * @param string $videoId The ID of the YouTube video
     */
    public function __invoke(string $videoId): string
    {
        $psr18Client = new Psr18Client($this->client);
        $fetcher = new TranscriptListFetcher($psr18Client, $psr18Client, $psr18Client);

        $list = $fetcher->fetch($videoId);
        $transcript = $list->findTranscript($list->getAvailableLanguageCodes());

        return array_reduce($transcript->fetch(), static function (string $carry, array $item): string {
            return $carry.\PHP_EOL.$item['text'];
        }, '');
    }
}
