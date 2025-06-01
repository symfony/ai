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

use Symfony\AI\Agent\Exception\LogicException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsTool('youtube_transcript', 'Fetches the transcript of a YouTube video')]
final readonly class YouTubeTranscriber
{
    public function __construct(
        private HttpClientInterface $client,
    ) {
        if (!class_exists(Crawler::class)) {
            throw new LogicException('The Symfony DomCrawler component is required to use this tool. Try running "composer require symfony/dom-crawler".');
        }
        if (!class_exists(CssSelectorConverter::class)) {
            throw new LogicException('The Symfony CSS Selector component is required to use this tool. Try running "composer require symfony/css-selector".');
        }
    }

    /**
     * @param string $videoId The ID of the YouTube video
     */
    public function __invoke(string $videoId): string
    {
        // Fetch the HTML content of the YouTube video page
        $htmlResponse = $this->client->request('GET', 'https://youtube.com/watch?v='.$videoId);
        $html = $htmlResponse->getContent();

        // Use DomCrawler to parse the HTML
        $crawler = new Crawler($html);

        // Extract the script containing the ytInitialPlayerResponse
        $scriptContent = $crawler->filter('script')->reduce(function (Crawler $node) {
            return str_contains($node->text(), 'var ytInitialPlayerResponse = {');
        })->text();

        // Extract and parse the JSON data from the script
        $start = strpos($scriptContent, 'var ytInitialPlayerResponse = ') + \strlen('var ytInitialPlayerResponse = ');
        $dataString = substr($scriptContent, $start);
        $dataString = substr($dataString, 0, strrpos($dataString, ';') ?: null);
        $data = json_decode(trim($dataString), true);

        // Extract the URL for the captions
        if (!isset($data['captions']['playerCaptionsTracklistRenderer']['captionTracks'][0]['baseUrl'])) {
            throw new RuntimeException('Captions are not available for this video.');
        }
        $captionsUrl = $data['captions']['playerCaptionsTracklistRenderer']['captionTracks'][0]['baseUrl'];

        // Fetch and parse the captions XML
        $xmlResponse = $this->client->request('GET', $captionsUrl);
        $xmlContent = $xmlResponse->getContent();
        $xmlCrawler = new Crawler($xmlContent);

        // Collect all text elements from the captions
        $transcript = $xmlCrawler->filter('text')->each(function (Crawler $node) {
            return $node->text().' ';
        });

        return implode(\PHP_EOL, $transcript);
    }
}
