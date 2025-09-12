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
#[AsTool('spotify_search', 'Tool that searches for tracks, artists, albums, and playlists on Spotify')]
#[AsTool('spotify_get_track', 'Tool that gets detailed information about a Spotify track', method: 'getTrack')]
#[AsTool('spotify_get_artist', 'Tool that gets detailed information about a Spotify artist', method: 'getArtist')]
#[AsTool('spotify_get_album', 'Tool that gets detailed information about a Spotify album', method: 'getAlbum')]
#[AsTool('spotify_get_playlist', 'Tool that gets detailed information about a Spotify playlist', method: 'getPlaylist')]
#[AsTool('spotify_get_user_profile', 'Tool that gets Spotify user profile information', method: 'getUserProfile')]
final readonly class Spotify
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        private array $options = [],
    ) {
    }

    /**
     * Search for tracks, artists, albums, and playlists on Spotify.
     *
     * @param string $query  Search query
     * @param string $type   Comma-separated list of item types: album, artist, playlist, track
     * @param int    $limit  Maximum number of results to return (1-50)
     * @param int    $offset Index of the first result to return
     * @param string $market ISO 3166-1 alpha-2 country code
     *
     * @return array{
     *     tracks: array<int, array{
     *         id: string,
     *         name: string,
     *         artists: array<int, array{id: string, name: string}>,
     *         album: array{
     *             id: string,
     *             name: string,
     *             images: array<int, array{url: string, height: int, width: int}>,
     *             release_date: string,
     *         },
     *         duration_ms: int,
     *         explicit: bool,
     *         popularity: int,
     *         preview_url: string|null,
     *         external_urls: array{spotify: string},
     *     }>,
     *     artists: array<int, array{
     *         id: string,
     *         name: string,
     *         genres: array<int, string>,
     *         popularity: int,
     *         followers: array{total: int},
     *         images: array<int, array{url: string, height: int, width: int}>,
     *         external_urls: array{spotify: string},
     *     }>,
     *     albums: array<int, array{
     *         id: string,
     *         name: string,
     *         artists: array<int, array{id: string, name: string}>,
     *         images: array<int, array{url: string, height: int, width: int}>,
     *         release_date: string,
     *         total_tracks: int,
     *         album_type: string,
     *         external_urls: array{spotify: string},
     *     }>,
     *     playlists: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         owner: array{id: string, display_name: string},
     *         tracks: array{total: int},
     *         images: array<int, array{url: string, height: int, width: int}>,
     *         external_urls: array{spotify: string},
     *     }>,
     * }
     */
    public function __invoke(
        #[With(maximum: 500)]
        string $query,
        string $type = 'track,artist,album,playlist',
        int $limit = 20,
        int $offset = 0,
        string $market = 'US',
    ): array {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [
                    'tracks' => [],
                    'artists' => [],
                    'albums' => [],
                    'playlists' => [],
                ];
            }

            $response = $this->httpClient->request('GET', 'https://api.spotify.com/v1/search', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => array_merge($this->options, [
                    'q' => $query,
                    'type' => $type,
                    'limit' => min(max($limit, 1), 50),
                    'offset' => max($offset, 0),
                    'market' => $market,
                ]),
            ]);

            $data = $response->toArray();

            return [
                'tracks' => $this->formatTracks($data['tracks']['items'] ?? []),
                'artists' => $this->formatArtists($data['artists']['items'] ?? []),
                'albums' => $this->formatAlbums($data['albums']['items'] ?? []),
                'playlists' => $this->formatPlaylists($data['playlists']['items'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'tracks' => [],
                'artists' => [],
                'albums' => [],
                'playlists' => [],
            ];
        }
    }

    /**
     * Get detailed information about a Spotify track.
     *
     * @param string $trackId Track ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     artists: array<int, array{id: string, name: string, genres: array<int, string>, popularity: int}>,
     *     album: array{
     *         id: string,
     *         name: string,
     *         images: array<int, array{url: string, height: int, width: int}>,
     *         release_date: string,
     *         total_tracks: int,
     *         album_type: string,
     *     },
     *     duration_ms: int,
     *     explicit: bool,
     *     popularity: int,
     *     preview_url: string|null,
     *     external_urls: array{spotify: string},
     *     audio_features: array<string, mixed>|null,
     * }|string
     */
    public function getTrack(string $trackId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return 'Access token required';
            }

            $response = $this->httpClient->request('GET', "https://api.spotify.com/v1/tracks/{$trackId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);

            $trackData = $response->toArray();

            // Get audio features
            $audioFeaturesResponse = $this->httpClient->request('GET', "https://api.spotify.com/v1/audio-features/{$trackId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);

            $audioFeatures = $audioFeaturesResponse->toArray();

            return [
                'id' => $trackData['id'],
                'name' => $trackData['name'],
                'artists' => array_map(fn ($artist) => [
                    'id' => $artist['id'],
                    'name' => $artist['name'],
                    'genres' => $artist['genres'] ?? [],
                    'popularity' => $artist['popularity'] ?? 0,
                ], $trackData['artists']),
                'album' => [
                    'id' => $trackData['album']['id'],
                    'name' => $trackData['album']['name'],
                    'images' => $trackData['album']['images'],
                    'release_date' => $trackData['album']['release_date'],
                    'total_tracks' => $trackData['album']['total_tracks'],
                    'album_type' => $trackData['album']['album_type'],
                ],
                'duration_ms' => $trackData['duration_ms'],
                'explicit' => $trackData['explicit'],
                'popularity' => $trackData['popularity'],
                'preview_url' => $trackData['preview_url'],
                'external_urls' => $trackData['external_urls'],
                'audio_features' => $audioFeatures,
            ];
        } catch (\Exception $e) {
            return 'Error getting track: '.$e->getMessage();
        }
    }

    /**
     * Get detailed information about a Spotify artist.
     *
     * @param string $artistId Artist ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     genres: array<int, string>,
     *     popularity: int,
     *     followers: array{total: int},
     *     images: array<int, array{url: string, height: int, width: int}>,
     *     external_urls: array{spotify: string},
     *     top_tracks: array<int, array{id: string, name: string, popularity: int}>,
     *     albums: array<int, array{id: string, name: string, album_type: string}>,
     * }|string
     */
    public function getArtist(string $artistId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return 'Access token required';
            }

            $response = $this->httpClient->request('GET', "https://api.spotify.com/v1/artists/{$artistId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);

            $artistData = $response->toArray();

            // Get top tracks
            $topTracksResponse = $this->httpClient->request('GET', "https://api.spotify.com/v1/artists/{$artistId}/top-tracks", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => ['market' => 'US'],
            ]);

            $topTracks = $topTracksResponse->toArray();

            // Get albums
            $albumsResponse = $this->httpClient->request('GET', "https://api.spotify.com/v1/artists/{$artistId}/albums", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => ['limit' => 10],
            ]);

            $albums = $albumsResponse->toArray();

            return [
                'id' => $artistData['id'],
                'name' => $artistData['name'],
                'genres' => $artistData['genres'],
                'popularity' => $artistData['popularity'],
                'followers' => $artistData['followers'],
                'images' => $artistData['images'],
                'external_urls' => $artistData['external_urls'],
                'top_tracks' => array_map(fn ($track) => [
                    'id' => $track['id'],
                    'name' => $track['name'],
                    'popularity' => $track['popularity'],
                ], $topTracks['tracks'] ?? []),
                'albums' => array_map(fn ($album) => [
                    'id' => $album['id'],
                    'name' => $album['name'],
                    'album_type' => $album['album_type'],
                ], $albums['items'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting artist: '.$e->getMessage();
        }
    }

    /**
     * Get detailed information about a Spotify album.
     *
     * @param string $albumId Album ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     artists: array<int, array{id: string, name: string}>,
     *     images: array<int, array{url: string, height: int, width: int}>,
     *     release_date: string,
     *     total_tracks: int,
     *     album_type: string,
     *     popularity: int,
     *     external_urls: array{spotify: string},
     *     tracks: array<int, array{id: string, name: string, duration_ms: int, track_number: int}>,
     * }|string
     */
    public function getAlbum(string $albumId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return 'Access token required';
            }

            $response = $this->httpClient->request('GET', "https://api.spotify.com/v1/albums/{$albumId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);

            $albumData = $response->toArray();

            return [
                'id' => $albumData['id'],
                'name' => $albumData['name'],
                'artists' => array_map(fn ($artist) => [
                    'id' => $artist['id'],
                    'name' => $artist['name'],
                ], $albumData['artists']),
                'images' => $albumData['images'],
                'release_date' => $albumData['release_date'],
                'total_tracks' => $albumData['total_tracks'],
                'album_type' => $albumData['album_type'],
                'popularity' => $albumData['popularity'] ?? 0,
                'external_urls' => $albumData['external_urls'],
                'tracks' => array_map(fn ($track) => [
                    'id' => $track['id'],
                    'name' => $track['name'],
                    'duration_ms' => $track['duration_ms'],
                    'track_number' => $track['track_number'],
                ], $albumData['tracks']['items']),
            ];
        } catch (\Exception $e) {
            return 'Error getting album: '.$e->getMessage();
        }
    }

    /**
     * Get detailed information about a Spotify playlist.
     *
     * @param string $playlistId Playlist ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     owner: array{id: string, display_name: string},
     *     tracks: array{total: int, items: array<int, array{track: array<string, mixed>}>},
     *     images: array<int, array{url: string, height: int, width: int}>,
     *     external_urls: array{spotify: string},
     *     public: bool,
     *     collaborative: bool,
     *     followers: array{total: int},
     * }|string
     */
    public function getPlaylist(string $playlistId): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return 'Access token required';
            }

            $response = $this->httpClient->request('GET', "https://api.spotify.com/v1/playlists/{$playlistId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => [
                    'market' => 'US',
                ],
            ]);

            $playlistData = $response->toArray();

            return [
                'id' => $playlistData['id'],
                'name' => $playlistData['name'],
                'description' => $playlistData['description'] ?? '',
                'owner' => [
                    'id' => $playlistData['owner']['id'],
                    'display_name' => $playlistData['owner']['display_name'],
                ],
                'tracks' => [
                    'total' => $playlistData['tracks']['total'],
                    'items' => \array_slice($playlistData['tracks']['items'], 0, 10), // Limit to first 10 tracks
                ],
                'images' => $playlistData['images'],
                'external_urls' => $playlistData['external_urls'],
                'public' => $playlistData['public'],
                'collaborative' => $playlistData['collaborative'],
                'followers' => $playlistData['followers'],
            ];
        } catch (\Exception $e) {
            return 'Error getting playlist: '.$e->getMessage();
        }
    }

    /**
     * Get Spotify user profile information.
     *
     * @param string $userId User ID (optional, defaults to current user)
     *
     * @return array{
     *     id: string,
     *     display_name: string,
     *     email: string,
     *     country: string,
     *     followers: array{total: int},
     *     images: array<int, array{url: string, height: int, width: int}>,
     *     product: string,
     *     external_urls: array{spotify: string},
     * }|string
     */
    public function getUserProfile(string $userId = ''): array|string
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return 'Access token required';
            }

            $endpoint = $userId ? "https://api.spotify.com/v1/users/{$userId}" : 'https://api.spotify.com/v1/me';

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
            ]);

            $userData = $response->toArray();

            return [
                'id' => $userData['id'],
                'display_name' => $userData['display_name'] ?? '',
                'email' => $userData['email'] ?? '',
                'country' => $userData['country'] ?? '',
                'followers' => $userData['followers'] ?? ['total' => 0],
                'images' => $userData['images'] ?? [],
                'product' => $userData['product'] ?? '',
                'external_urls' => $userData['external_urls'],
            ];
        } catch (\Exception $e) {
            return 'Error getting user profile: '.$e->getMessage();
        }
    }

    /**
     * Get access token using Client Credentials flow.
     */
    private function getAccessToken(): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://accounts.spotify.com/api/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]),
            ]);

            $data = $response->toArray();

            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format tracks data.
     *
     * @param array<int, array<string, mixed>> $tracks
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatTracks(array $tracks): array
    {
        return array_map(fn ($track) => [
            'id' => $track['id'],
            'name' => $track['name'],
            'artists' => array_map(fn ($artist) => [
                'id' => $artist['id'],
                'name' => $artist['name'],
            ], $track['artists']),
            'album' => [
                'id' => $track['album']['id'],
                'name' => $track['album']['name'],
                'images' => $track['album']['images'],
                'release_date' => $track['album']['release_date'],
            ],
            'duration_ms' => $track['duration_ms'],
            'explicit' => $track['explicit'],
            'popularity' => $track['popularity'],
            'preview_url' => $track['preview_url'],
            'external_urls' => $track['external_urls'],
        ], $tracks);
    }

    /**
     * Format artists data.
     *
     * @param array<int, array<string, mixed>> $artists
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatArtists(array $artists): array
    {
        return array_map(fn ($artist) => [
            'id' => $artist['id'],
            'name' => $artist['name'],
            'genres' => $artist['genres'],
            'popularity' => $artist['popularity'],
            'followers' => $artist['followers'],
            'images' => $artist['images'],
            'external_urls' => $artist['external_urls'],
        ], $artists);
    }

    /**
     * Format albums data.
     *
     * @param array<int, array<string, mixed>> $albums
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatAlbums(array $albums): array
    {
        return array_map(fn ($album) => [
            'id' => $album['id'],
            'name' => $album['name'],
            'artists' => array_map(fn ($artist) => [
                'id' => $artist['id'],
                'name' => $artist['name'],
            ], $album['artists']),
            'images' => $album['images'],
            'release_date' => $album['release_date'],
            'total_tracks' => $album['total_tracks'],
            'album_type' => $album['album_type'],
            'external_urls' => $album['external_urls'],
        ], $albums);
    }

    /**
     * Format playlists data.
     *
     * @param array<int, array<string, mixed>> $playlists
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatPlaylists(array $playlists): array
    {
        return array_map(fn ($playlist) => [
            'id' => $playlist['id'],
            'name' => $playlist['name'],
            'description' => $playlist['description'] ?? '',
            'owner' => [
                'id' => $playlist['owner']['id'],
                'display_name' => $playlist['owner']['display_name'],
            ],
            'tracks' => ['total' => $playlist['tracks']['total']],
            'images' => $playlist['images'],
            'external_urls' => $playlist['external_urls'],
        ], $playlists);
    }
}
