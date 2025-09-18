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
#[AsTool('dropbox_list_files', 'Tool that lists files and folders in Dropbox')]
#[AsTool('dropbox_upload_file', 'Tool that uploads files to Dropbox', method: 'uploadFile')]
#[AsTool('dropbox_download_file', 'Tool that downloads files from Dropbox', method: 'downloadFile')]
#[AsTool('dropbox_create_folder', 'Tool that creates folders in Dropbox', method: 'createFolder')]
#[AsTool('dropbox_share_file', 'Tool that shares files on Dropbox', method: 'shareFile')]
#[AsTool('dropbox_search_files', 'Tool that searches for files in Dropbox', method: 'searchFiles')]
final readonly class Dropbox
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private array $options = [],
    ) {
    }

    /**
     * List files and folders in Dropbox.
     *
     * @param string $path      Path to list (use '' for root)
     * @param bool   $recursive Whether to list recursively
     * @param int    $limit     Maximum number of items to return
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     path_display: string,
     *     path_lower: string,
     *     size: int,
     *     client_modified: string,
     *     server_modified: string,
     *     content_hash: string,
     *     tag: string,
     *     is_downloadable: bool,
     *     has_explicit_shared_members: bool,
     *     media_info: array<string, mixed>|null,
     * }>
     */
    public function __invoke(
        string $path = '',
        bool $recursive = false,
        int $limit = 100,
    ): array {
        try {
            $payload = [
                'path' => $path,
                'recursive' => $recursive,
                'limit' => min(max($limit, 1), 2000),
                'include_media_info' => true,
                'include_deleted' => false,
            ];

            $response = $this->httpClient->request('POST', 'https://api.dropboxapi.com/2/files/list_folder', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (!isset($data['entries'])) {
                return [];
            }

            $files = [];
            foreach ($data['entries'] as $entry) {
                $files[] = [
                    'id' => $entry['id'],
                    'name' => $entry['name'],
                    'path_display' => $entry['path_display'],
                    'path_lower' => $entry['path_lower'],
                    'size' => $entry['size'] ?? 0,
                    'client_modified' => $entry['client_modified'] ?? '',
                    'server_modified' => $entry['server_modified'] ?? '',
                    'content_hash' => $entry['content_hash'] ?? '',
                    'tag' => $entry['.tag'],
                    'is_downloadable' => $entry['is_downloadable'] ?? false,
                    'has_explicit_shared_members' => $entry['has_explicit_shared_members'] ?? false,
                    'media_info' => $entry['media_info'] ?? null,
                ];
            }

            return $files;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Upload a file to Dropbox.
     *
     * @param string $filePath    Path to the file to upload
     * @param string $dropboxPath Destination path in Dropbox
     * @param string $mode        Upload mode (add, overwrite, update)
     * @param bool   $autorename  Whether to autorename if file exists
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     path_display: string,
     *     path_lower: string,
     *     size: int,
     *     client_modified: string,
     *     server_modified: string,
     *     content_hash: string,
     * }|string
     */
    public function uploadFile(
        string $filePath,
        string $dropboxPath,
        string $mode = 'add',
        bool $autorename = true,
    ): array|string {
        try {
            if (!file_exists($filePath)) {
                return 'Error: File does not exist';
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);
            $fullPath = rtrim($dropboxPath, '/').'/'.$fileName;

            $response = $this->httpClient->request('POST', 'https://content.dropboxapi.com/2/files/upload', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/octet-stream',
                    'Dropbox-API-Arg' => json_encode([
                        'path' => $fullPath,
                        'mode' => $mode,
                        'autorename' => $autorename,
                        'mute' => false,
                    ]),
                ],
                'body' => $fileContent,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error uploading file: '.$data['error']['error_summary'];
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'path_display' => $data['path_display'],
                'path_lower' => $data['path_lower'],
                'size' => $data['size'],
                'client_modified' => $data['client_modified'],
                'server_modified' => $data['server_modified'],
                'content_hash' => $data['content_hash'],
            ];
        } catch (\Exception $e) {
            return 'Error uploading file: '.$e->getMessage();
        }
    }

    /**
     * Download a file from Dropbox.
     *
     * @param string $dropboxPath Path to the file in Dropbox
     * @param string $localPath   Local path to save the file
     *
     * @return array{
     *     file_path: string,
     *     file_size: int,
     *     saved_path: string,
     *     metadata: array{
     *         id: string,
     *         name: string,
     *         size: int,
     *         client_modified: string,
     *         server_modified: string,
     *     },
     * }|string
     */
    public function downloadFile(string $dropboxPath, string $localPath): array|string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://content.dropboxapi.com/2/files/download', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Dropbox-API-Arg' => json_encode(['path' => $dropboxPath]),
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                return 'Error downloading file: File not found or access denied';
            }

            $fileContent = $response->getContent();
            $metadataHeader = $response->getHeaders()['dropbox-api-result'][0] ?? '{}';
            $metadata = json_decode($metadataHeader, true);

            // Save to local path
            if (is_dir($localPath)) {
                $localPath = rtrim($localPath, '/').'/'.$metadata['name'];
            }

            file_put_contents($localPath, $fileContent);

            return [
                'file_path' => $dropboxPath,
                'file_size' => \strlen($fileContent),
                'saved_path' => $localPath,
                'metadata' => [
                    'id' => $metadata['id'] ?? '',
                    'name' => $metadata['name'] ?? '',
                    'size' => $metadata['size'] ?? 0,
                    'client_modified' => $metadata['client_modified'] ?? '',
                    'server_modified' => $metadata['server_modified'] ?? '',
                ],
            ];
        } catch (\Exception $e) {
            return 'Error downloading file: '.$e->getMessage();
        }
    }

    /**
     * Create a folder in Dropbox.
     *
     * @param string $path       Path where to create the folder
     * @param bool   $autorename Whether to autorename if folder exists
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     path_display: string,
     *     path_lower: string,
     *     client_modified: string,
     *     server_modified: string,
     * }|string
     */
    public function createFolder(string $path, bool $autorename = true): array|string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.dropboxapi.com/2/files/create_folder_v2', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'path' => $path,
                    'autorename' => $autorename,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating folder: '.$data['error']['error_summary'];
            }

            $metadata = $data['metadata'];

            return [
                'id' => $metadata['id'],
                'name' => $metadata['name'],
                'path_display' => $metadata['path_display'],
                'path_lower' => $metadata['path_lower'],
                'client_modified' => $metadata['client_modified'] ?? '',
                'server_modified' => $metadata['server_modified'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error creating folder: '.$e->getMessage();
        }
    }

    /**
     * Share a file on Dropbox.
     *
     * @param string $path          Path to the file to share
     * @param string $accessLevel   Access level (viewer, editor, owner)
     * @param bool   $allowDownload Whether to allow downloads
     * @param string $password      Optional password for the link
     * @param string $expires       Expiration date (YYYY-MM-DD)
     *
     * @return array{
     *     url: string,
     *     name: string,
     *     link_permissions: array{
     *         can_revoke: bool,
     *         can_remove_password: bool,
     *         can_update_password: bool,
     *         can_update_expiry: bool,
     *         can_update_audience: bool,
     *         can_set_password: bool,
     *         can_set_expiry: bool,
     *         can_set_audience: bool,
     *     },
     *     expires: string,
     *     path_lower: string,
     * }|string
     */
    public function shareFile(
        string $path,
        string $accessLevel = 'viewer',
        bool $allowDownload = true,
        string $password = '',
        string $expires = '',
    ): array|string {
        try {
            $settings = [
                'requested_visibility' => 'public',
                'access' => $accessLevel,
                'allow_download' => $allowDownload,
            ];

            if ($password) {
                $settings['password'] = $password;
            }

            if ($expires) {
                $settings['expires'] = $expires.'T23:59:59Z';
            }

            $response = $this->httpClient->request('POST', 'https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'path' => $path,
                    'settings' => $settings,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sharing file: '.$data['error']['error_summary'];
            }

            return [
                'url' => $data['url'],
                'name' => $data['name'],
                'link_permissions' => [
                    'can_revoke' => $data['link_permissions']['can_revoke'] ?? false,
                    'can_remove_password' => $data['link_permissions']['can_remove_password'] ?? false,
                    'can_update_password' => $data['link_permissions']['can_update_password'] ?? false,
                    'can_update_expiry' => $data['link_permissions']['can_update_expiry'] ?? false,
                    'can_update_audience' => $data['link_permissions']['can_update_audience'] ?? false,
                    'can_set_password' => $data['link_permissions']['can_set_password'] ?? false,
                    'can_set_expiry' => $data['link_permissions']['can_set_expiry'] ?? false,
                    'can_set_audience' => $data['link_permissions']['can_set_audience'] ?? false,
                ],
                'expires' => $data['expires'] ?? '',
                'path_lower' => $data['path_lower'],
            ];
        } catch (\Exception $e) {
            return 'Error sharing file: '.$e->getMessage();
        }
    }

    /**
     * Search for files in Dropbox.
     *
     * @param string $query        Search query
     * @param string $path         Path to search in (use '' for root)
     * @param int    $maxResults   Maximum number of results
     * @param string $fileCategory File category filter (image, document, video, audio, other)
     *
     * @return array<int, array{
     *     match_type: array{tag: string},
     *     metadata: array{
     *         id: string,
     *         name: string,
     *         path_display: string,
     *         path_lower: string,
     *         size: int,
     *         client_modified: string,
     *         server_modified: string,
     *         content_hash: string,
     *         tag: string,
     *     },
     * }>
     */
    public function searchFiles(
        #[With(maximum: 500)]
        string $query,
        string $path = '',
        int $maxResults = 20,
        string $fileCategory = '',
    ): array {
        try {
            $searchOptions = [
                'path' => $path,
                'max_results' => min(max($maxResults, 1), 100),
                'file_status' => 'active',
            ];

            if ($fileCategory) {
                $searchOptions['file_categories'] = [$fileCategory];
            }

            $response = $this->httpClient->request('POST', 'https://api.dropboxapi.com/2/files/search_v2', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'options' => $searchOptions,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['matches'])) {
                return [];
            }

            $results = [];
            foreach ($data['matches'] as $match) {
                $results[] = [
                    'match_type' => [
                        'tag' => $match['match_type']['tag'] ?? 'filename',
                    ],
                    'metadata' => [
                        'id' => $match['metadata']['id'] ?? '',
                        'name' => $match['metadata']['name'] ?? '',
                        'path_display' => $match['metadata']['path_display'] ?? '',
                        'path_lower' => $match['metadata']['path_lower'] ?? '',
                        'size' => $match['metadata']['size'] ?? 0,
                        'client_modified' => $match['metadata']['client_modified'] ?? '',
                        'server_modified' => $match['metadata']['server_modified'] ?? '',
                        'content_hash' => $match['metadata']['content_hash'] ?? '',
                        'tag' => $match['metadata']['.tag'] ?? 'file',
                    ],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get file metadata from Dropbox.
     *
     * @param string $path Path to the file
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     path_display: string,
     *     path_lower: string,
     *     size: int,
     *     client_modified: string,
     *     server_modified: string,
     *     content_hash: string,
     *     tag: string,
     *     is_downloadable: bool,
     *     has_explicit_shared_members: bool,
     * }|string
     */
    public function getFileMetadata(string $path): array|string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.dropboxapi.com/2/files/get_metadata', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'path' => $path,
                    'include_media_info' => true,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting file metadata: '.$data['error']['error_summary'];
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'path_display' => $data['path_display'],
                'path_lower' => $data['path_lower'],
                'size' => $data['size'] ?? 0,
                'client_modified' => $data['client_modified'] ?? '',
                'server_modified' => $data['server_modified'] ?? '',
                'content_hash' => $data['content_hash'] ?? '',
                'tag' => $data['.tag'],
                'is_downloadable' => $data['is_downloadable'] ?? false,
                'has_explicit_shared_members' => $data['has_explicit_shared_members'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error getting file metadata: '.$e->getMessage();
        }
    }
}
