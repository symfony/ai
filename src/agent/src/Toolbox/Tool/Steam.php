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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('steam_game_search', 'Tool that searches for games on Steam')]
#[AsTool('steam_user_profile', 'Tool that gets Steam user profile information', method: 'getUserProfile')]
#[AsTool('steam_game_details', 'Tool that gets detailed information about a Steam game', method: 'getGameDetails')]
#[AsTool('steam_user_games', 'Tool that gets a user\'s game library', method: 'getUserGames')]
final readonly class Steam
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private ?string $apiKey = null,
        private array $options = [],
    ) {
    }

    /**
     * Search for games on Steam.
     *
     * @param string $query      Game name to search for
     * @param int    $maxResults Maximum number of results to return
     * @param string $category   Category filter (optional)
     *
     * @return array<int, array{
     *     app_id: int,
     *     name: string,
     *     short_description: string,
     *     header_image: string,
     *     capsule_image: string,
     *     price_overview: array{
     *         currency: string,
     *         initial: int,
     *         final: int,
     *         discount_percent: int,
     *     },
     *     release_date: array{
     *         coming_soon: bool,
     *         date: string,
     *     },
     *     categories: array<int, array{id: int, description: string}>,
     *     genres: array<int, array{id: int, description: string}>,
     *     steam_url: string,
     * }>
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        int $maxResults = 20,
        string $category = '',
    ): array {
        try {
            // Note: Steam Store API doesn't require API key for basic searches
            $params = [
                'term' => $query,
                'l' => 'english',
                'cc' => 'us',
            ];

            if ($category) {
                $params['category1'] = $category;
            }

            $response = $this->httpClient->request('GET', 'https://store.steampowered.com/api/storesearch', [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return [];
            }

            $results = [];
            $count = 0;

            foreach ($data['items'] as $item) {
                if ($count >= $maxResults) {
                    break;
                }

                $results[] = [
                    'app_id' => $item['id'],
                    'name' => $item['name'],
                    'short_description' => $item['tiny_description'] ?? '',
                    'header_image' => $item['header_image'] ?? '',
                    'capsule_image' => $item['capsule_image'] ?? '',
                    'price_overview' => [
                        'currency' => $item['currency'] ?? 'USD',
                        'initial' => $item['original_price'] ?? 0,
                        'final' => $item['final_price'] ?? 0,
                        'discount_percent' => $item['discount_percent'] ?? 0,
                    ],
                    'release_date' => [
                        'coming_soon' => $item['coming_soon'] ?? false,
                        'date' => $item['release_date'] ?? '',
                    ],
                    'categories' => $this->formatCategories($item['categories'] ?? []),
                    'genres' => $this->formatGenres($item['genres'] ?? []),
                    'steam_url' => $item['url'] ?? '',
                ];

                ++$count;
            }

            return $results;
        } catch (\Exception $e) {
            return [
                [
                    'app_id' => 0,
                    'name' => 'Search Error',
                    'short_description' => 'Unable to search Steam games: '.$e->getMessage(),
                    'header_image' => '',
                    'capsule_image' => '',
                    'price_overview' => ['currency' => 'USD', 'initial' => 0, 'final' => 0, 'discount_percent' => 0],
                    'release_date' => ['coming_soon' => false, 'date' => ''],
                    'categories' => [],
                    'genres' => [],
                    'steam_url' => '',
                ],
            ];
        }
    }

    /**
     * Get Steam user profile information.
     *
     * @param string $steamId Steam ID or vanity URL
     *
     * @return array{
     *     steam_id: string,
     *     person_name: string,
     *     real_name: string,
     *     profile_url: string,
     *     avatar: string,
     *     avatar_medium: string,
     *     avatar_full: string,
     *     person_state: int,
     *     community_visibility_state: int,
     *     profile_state: int,
     *     last_logoff: int,
     *     comment_permission: int,
     *     country_code: string,
     *     state_code: string,
     *     city_id: int,
     * }|string
     */
    public function getUserProfile(string $steamId): array|string
    {
        try {
            if (!$this->apiKey) {
                return 'Steam API key required for user profile access';
            }

            $response = $this->httpClient->request('GET', 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/', [
                'query' => [
                    'key' => $this->apiKey,
                    'steamids' => $steamId,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['response']['players'][0])) {
                return 'User not found or profile not public';
            }

            $player = $data['response']['players'][0];

            return [
                'steam_id' => $player['steamid'],
                'person_name' => $player['personaname'],
                'real_name' => $player['realname'] ?? '',
                'profile_url' => $player['profileurl'],
                'avatar' => $player['avatar'],
                'avatar_medium' => $player['avatarmedium'],
                'avatar_full' => $player['avatarfull'],
                'person_state' => $player['personastate'],
                'community_visibility_state' => $player['communityvisibilitystate'],
                'profile_state' => $player['profilestate'],
                'last_logoff' => $player['lastlogoff'],
                'comment_permission' => $player['commentpermission'],
                'country_code' => $player['loccountrycode'] ?? '',
                'state_code' => $player['locstatecode'] ?? '',
                'city_id' => $player['loccityid'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting user profile: '.$e->getMessage();
        }
    }

    /**
     * Get detailed information about a Steam game.
     *
     * @param int $appId Steam App ID
     *
     * @return array{
     *     app_id: int,
     *     name: string,
     *     type: string,
     *     description: string,
     *     short_description: string,
     *     header_image: string,
     *     capsule_image: string,
     *     capsule_image_v5: string,
     *     website: string,
     *     pc_requirements: array{minimum: string, recommended: string},
     *     mac_requirements: array{minimum: string, recommended: string},
     *     linux_requirements: array{minimum: string, recommended: string},
     *     developers: array<int, string>,
     *     publishers: array<int, string>,
     *     price_overview: array<string, mixed>,
     *     platforms: array{windows: bool, mac: bool, linux: bool},
     *     categories: array<int, array{id: int, description: string}>,
     *     genres: array<int, array{id: int, description: string}>,
     *     screenshots: array<int, array{id: int, path_thumbnail: string, path_full: string}>,
     *     movies: array<int, array{id: int, name: string, thumbnail: string, webm: array<string, string>}>,
     *     release_date: array{coming_soon: bool, date: string},
     *     background: string,
     *     background_raw: string,
     * }|string
     */
    public function getGameDetails(int $appId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://store.steampowered.com/api/appdetails', [
                'query' => [
                    'appids' => $appId,
                    'l' => 'english',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data[$appId]['data'])) {
                return 'Game not found or not available';
            }

            $gameData = $data[$appId]['data'];

            return [
                'app_id' => $appId,
                'name' => $gameData['name'],
                'type' => $gameData['type'],
                'description' => $gameData['detailed_description'] ?? '',
                'short_description' => $gameData['short_description'] ?? '',
                'header_image' => $gameData['header_image'] ?? '',
                'capsule_image' => $gameData['capsule_image'] ?? '',
                'capsule_image_v5' => $gameData['capsule_imagev5'] ?? '',
                'website' => $gameData['website'] ?? '',
                'pc_requirements' => [
                    'minimum' => $gameData['pc_requirements']['minimum'] ?? '',
                    'recommended' => $gameData['pc_requirements']['recommended'] ?? '',
                ],
                'mac_requirements' => [
                    'minimum' => $gameData['mac_requirements']['minimum'] ?? '',
                    'recommended' => $gameData['mac_requirements']['recommended'] ?? '',
                ],
                'linux_requirements' => [
                    'minimum' => $gameData['linux_requirements']['minimum'] ?? '',
                    'recommended' => $gameData['linux_requirements']['recommended'] ?? '',
                ],
                'developers' => $gameData['developers'] ?? [],
                'publishers' => $gameData['publishers'] ?? [],
                'price_overview' => $gameData['price_overview'] ?? [],
                'platforms' => $gameData['platforms'] ?? ['windows' => false, 'mac' => false, 'linux' => false],
                'categories' => $this->formatCategories($gameData['categories'] ?? []),
                'genres' => $this->formatGenres($gameData['genres'] ?? []),
                'screenshots' => $gameData['screenshots'] ?? [],
                'movies' => $gameData['movies'] ?? [],
                'release_date' => $gameData['release_date'] ?? ['coming_soon' => false, 'date' => ''],
                'background' => $gameData['background'] ?? '',
                'background_raw' => $gameData['background_raw'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting game details: '.$e->getMessage();
        }
    }

    /**
     * Get user's game library.
     *
     * @param string $steamId Steam ID
     *
     * @return array<int, array{
     *     app_id: int,
     *     name: string,
     *     playtime_forever: int,
     *     playtime_2weeks: int,
     *     playtime_windows_forever: int,
     *     playtime_mac_forever: int,
     *     playtime_linux_forever: int,
     *     rtime_last_played: int,
     *     playtime_disconnected: int,
     * }>|string
     */
    public function getUserGames(string $steamId): array|string
    {
        try {
            if (!$this->apiKey) {
                return 'Steam API key required for user game library access';
            }

            $response = $this->httpClient->request('GET', 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/', [
                'query' => [
                    'key' => $this->apiKey,
                    'steamid' => $steamId,
                    'include_appinfo' => true,
                    'include_played_free_games' => true,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['response']['games'])) {
                return 'No games found or library not public';
            }

            $results = [];
            foreach ($data['response']['games'] as $game) {
                $results[] = [
                    'app_id' => $game['appid'],
                    'name' => $game['name'],
                    'playtime_forever' => $game['playtime_forever'] ?? 0,
                    'playtime_2weeks' => $game['playtime_2weeks'] ?? 0,
                    'playtime_windows_forever' => $game['playtime_windows_forever'] ?? 0,
                    'playtime_mac_forever' => $game['playtime_mac_forever'] ?? 0,
                    'playtime_linux_forever' => $game['playtime_linux_forever'] ?? 0,
                    'rtime_last_played' => $game['rtime_last_played'] ?? 0,
                    'playtime_disconnected' => $game['playtime_disconnected'] ?? 0,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return 'Error getting user games: '.$e->getMessage();
        }
    }

    /**
     * Format categories data.
     *
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array<int, array{id: int, description: string}>
     */
    private function formatCategories(array $categories): array
    {
        $formatted = [];
        foreach ($categories as $category) {
            $formatted[] = [
                'id' => $category['id'],
                'description' => $category['description'],
            ];
        }

        return $formatted;
    }

    /**
     * Format genres data.
     *
     * @param array<int, array<string, mixed>> $genres
     *
     * @return array<int, array{id: int, description: string}>
     */
    private function formatGenres(array $genres): array
    {
        $formatted = [];
        foreach ($genres as $genre) {
            $formatted[] = [
                'id' => $genre['id'],
                'description' => $genre['description'],
            ];
        }

        return $formatted;
    }
}
