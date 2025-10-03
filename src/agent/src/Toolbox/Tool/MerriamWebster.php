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

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('merriam_webster', 'Tool that searches the Merriam-Webster dictionary API')]
#[AsTool('merriam_webster_thesaurus', 'Tool that searches Merriam-Webster thesaurus', method: 'searchThesaurus')]
final readonly class MerriamWebster
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $dictionaryApiKey,
        #[\SensitiveParameter] private ?string $thesaurusApiKey = null,
        private array $options = [],
    ) {
    }

    /**
     * Search Merriam-Webster dictionary for word definitions.
     *
     * @param string $word The word to look up
     *
     * @return array{
     *     word: string,
     *     definitions: array<int, array{
     *         definition: string,
     *         part_of_speech: string,
     *         examples: array<int, string>,
     *         synonyms: array<int, string>,
     *         antonyms: array<int, string>,
     *     }>,
     *     pronunciation: array{
     *         phonetic: string,
     *         audio_url: string,
     *     },
     *     etymology: string,
     *     word_frequency: int,
     * }|string
     */
    public function __invoke(string $word): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://www.dictionaryapi.com/api/v3/references/collegiate/json/{$word}", [
                'query' => [
                    'key' => $this->dictionaryApiKey,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                return "No definition found for '{$word}'";
            }

            // Handle suggestions if the word is not found
            if (isset($data[0]) && \is_string($data[0])) {
                return 'Word not found. Did you mean: '.implode(', ', \array_slice($data, 0, 5)).'?';
            }

            $definitions = [];
            $pronunciation = ['phonetic' => '', 'audio_url' => ''];
            $etymology = '';
            $wordFrequency = 0;

            foreach ($data as $entry) {
                if (!isset($entry['shortdef'])) {
                    continue;
                }

                // Extract pronunciation information
                if (isset($entry['hwi']['prs'][0]['ipa'])) {
                    $pronunciation['phonetic'] = $entry['hwi']['prs'][0]['ipa'];
                }
                if (isset($entry['hwi']['prs'][0]['sound']['audio'])) {
                    $audioId = $entry['hwi']['prs'][0]['sound']['audio'];
                    $pronunciation['audio_url'] = "https://media.merriam-webster.com/audio/prons/en/us/mp3/{$audioId[0]}/{$audioId}.mp3";
                }

                // Extract etymology
                if (isset($entry['et'][0][1])) {
                    $etymology = $entry['et'][0][1];
                }

                // Extract word frequency
                if (isset($entry['meta']['stems'])) {
                    $wordFrequency = \count($entry['meta']['stems']);
                }

                // Process definitions
                foreach ($entry['shortdef'] as $index => $definition) {
                    $definitions[] = [
                        'definition' => $definition,
                        'part_of_speech' => $entry['fl'] ?? '',
                        'examples' => $this->extractExamples($entry, $index),
                        'synonyms' => $this->extractSynonyms($entry),
                        'antonyms' => $this->extractAntonyms($entry),
                    ];
                }
            }

            return [
                'word' => $word,
                'definitions' => $definitions,
                'pronunciation' => $pronunciation,
                'etymology' => $etymology,
                'word_frequency' => $wordFrequency,
            ];
        } catch (\Exception $e) {
            return 'Error searching dictionary: '.$e->getMessage();
        }
    }

    /**
     * Search Merriam-Webster thesaurus for synonyms and antonyms.
     *
     * @param string $word The word to look up in the thesaurus
     *
     * @return array{
     *     word: string,
     *     synonyms: array<int, array{
     *         word: string,
     *         part_of_speech: string,
     *         definition: string,
     *     }>,
     *     antonyms: array<int, array{
     *         word: string,
     *         part_of_speech: string,
     *         definition: string,
     *     }>,
     *     related_words: array<int, string>,
     * }|string
     */
    public function searchThesaurus(string $word): array|string
    {
        if (!$this->thesaurusApiKey) {
            return 'Thesaurus API key not provided';
        }

        try {
            $response = $this->httpClient->request('GET', "https://www.dictionaryapi.com/api/v3/references/thesaurus/json/{$word}", [
                'query' => [
                    'key' => $this->thesaurusApiKey,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data)) {
                return "No thesaurus entry found for '{$word}'";
            }

            // Handle suggestions if the word is not found
            if (isset($data[0]) && \is_string($data[0])) {
                return 'Word not found. Did you mean: '.implode(', ', \array_slice($data, 0, 5)).'?';
            }

            $synonyms = [];
            $antonyms = [];
            $relatedWords = [];

            foreach ($data as $entry) {
                if (!isset($entry['meta'])) {
                    continue;
                }

                $meta = $entry['meta'];

                // Extract synonyms
                if (isset($meta['syns'])) {
                    foreach ($meta['syns'] as $synonymGroup) {
                        foreach ($synonymGroup as $synonym) {
                            $synonyms[] = [
                                'word' => $synonym['wd'] ?? $synonym,
                                'part_of_speech' => $entry['fl'] ?? '',
                                'definition' => $this->getShortDefinition($entry),
                            ];
                        }
                    }
                }

                // Extract antonyms
                if (isset($meta['ants'])) {
                    foreach ($meta['ants'] as $antonymGroup) {
                        foreach ($antonymGroup as $antonym) {
                            $antonyms[] = [
                                'word' => $antonym['wd'] ?? $antonym,
                                'part_of_speech' => $entry['fl'] ?? '',
                                'definition' => $this->getShortDefinition($entry),
                            ];
                        }
                    }
                }

                // Extract related words
                if (isset($meta['rels'])) {
                    foreach ($meta['rels'] as $relatedWord) {
                        if (isset($relatedWord['wd'])) {
                            $relatedWords[] = $relatedWord['wd'];
                        }
                    }
                }
            }

            return [
                'word' => $word,
                'synonyms' => array_unique($synonyms, \SORT_REGULAR),
                'antonyms' => array_unique($antonyms, \SORT_REGULAR),
                'related_words' => array_unique($relatedWords),
            ];
        } catch (\Exception $e) {
            return 'Error searching thesaurus: '.$e->getMessage();
        }
    }

    /**
     * Get word of the day.
     *
     * @return array{
     *     word: string,
     *     definition: string,
     *     pronunciation: string,
     *     example: string,
     *     date: string,
     * }|string
     */
    public function getWordOfTheDay(): array|string
    {
        try {
            // Note: This would require a different API endpoint or web scraping
            // For now, we'll return a placeholder
            return 'Word of the day feature requires additional API access';
        } catch (\Exception $e) {
            return 'Error getting word of the day: '.$e->getMessage();
        }
    }

    /**
     * Extract examples from dictionary entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<int, string>
     */
    private function extractExamples(array $entry, int $definitionIndex): array
    {
        $examples = [];

        if (isset($entry['def'][0]['sseq'][$definitionIndex][0][1]['dt'][1][1])) {
            $exampleData = $entry['def'][0]['sseq'][$definitionIndex][0][1]['dt'][1][1];
            if (\is_array($exampleData)) {
                foreach ($exampleData as $example) {
                    if (isset($example['t'])) {
                        $examples[] = strip_tags($example['t']);
                    }
                }
            }
        }

        return $examples;
    }

    /**
     * Extract synonyms from dictionary entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<int, string>
     */
    private function extractSynonyms(array $entry): array
    {
        $synonyms = [];

        if (isset($entry['meta']['syns'])) {
            foreach ($entry['meta']['syns'] as $synonymGroup) {
                foreach ($synonymGroup as $synonym) {
                    $synonyms[] = \is_array($synonym) ? ($synonym['wd'] ?? '') : $synonym;
                }
            }
        }

        return array_filter($synonyms);
    }

    /**
     * Extract antonyms from dictionary entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<int, string>
     */
    private function extractAntonyms(array $entry): array
    {
        $antonyms = [];

        if (isset($entry['meta']['ants'])) {
            foreach ($entry['meta']['ants'] as $antonymGroup) {
                foreach ($antonymGroup as $antonym) {
                    $antonyms[] = \is_array($antonym) ? ($antonym['wd'] ?? '') : $antonym;
                }
            }
        }

        return array_filter($antonyms);
    }

    /**
     * Get short definition from entry.
     *
     * @param array<string, mixed> $entry
     */
    private function getShortDefinition(array $entry): string
    {
        if (isset($entry['shortdef'][0])) {
            return $entry['shortdef'][0];
        }

        return '';
    }
}
