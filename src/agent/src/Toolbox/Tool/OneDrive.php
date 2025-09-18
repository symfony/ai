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
#[AsTool('onedrive_list_files', 'Tool that lists files and folders in OneDrive')]
#[AsTool('onedrive_upload_file', 'Tool that uploads files to OneDrive', method: 'uploadFile')]
#[AsTool('onedrive_download_file', 'Tool that downloads files from OneDrive', method: 'downloadFile')]
#[AsTool('onedrive_create_folder', 'Tool that creates folders in OneDrive', method: 'createFolder')]
#[AsTool('onedrive_share_file', 'Tool that shares files on OneDrive', method: 'shareFile')]
#[AsTool('onedrive_search_files', 'Tool that searches for files in OneDrive', method: 'searchFiles')]
final readonly class OneDrive
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v1.0',
        private array $options = [],
    ) {
    }

    /**
     * List files and folders in OneDrive.
     *
     * @param string $path    Path to list (use 'root' for root folder)
     * @param int    $top     Maximum number of items to return
     * @param string $orderBy Order by field (name, lastModifiedDateTime, size, etc.)
     * @param string $filter  Filter expression (e.g., "file ne null")
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     size: int,
     *     createdDateTime: string,
     *     lastModifiedDateTime: string,
     *     webUrl: string,
     *     downloadUrl: string,
     *     file: array{hashes: array{sha1Hash: string, quickXorHash: string}}|null,
     *     folder: array{childCount: int}|null,
     *     parentReference: array{driveId: string, driveType: string, id: string, path: string},
     *     createdBy: array{user: array{displayName: string, id: string}},
     *     lastModifiedBy: array{user: array{displayName: string, id: string}},
     * }>
     */
    public function __invoke(
        string $path = 'root',
        int $top = 100,
        string $orderBy = 'name',
        string $filter = '',
    ): array {
        try {
            $params = [
                '$top' => min(max($top, 1), 1000),
                '$orderby' => $orderBy,
                '$expand' => 'children',
            ];

            if ($filter) {
                $params['$filter'] = $filter;
            }

            $endpoint = 'root' === $path
                ? "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root/children"
                : "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root:/{$path}:/children";

            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['value'])) {
                return [];
            }

            $files = [];
            foreach ($data['value'] as $item) {
                $files[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => $item['size'] ?? 0,
                    'createdDateTime' => $item['createdDateTime'],
                    'lastModifiedDateTime' => $item['lastModifiedDateTime'],
                    'webUrl' => $item['webUrl'],
                    'downloadUrl' => $item['@microsoft.graph.downloadUrl'] ?? '',
                    'file' => $item['file'] ?? null,
                    'folder' => $item['folder'] ?? null,
                    'parentReference' => [
                        'driveId' => $item['parentReference']['driveId'],
                        'driveType' => $item['parentReference']['driveType'],
                        'id' => $item['parentReference']['id'],
                        'path' => $item['parentReference']['path'],
                    ],
                    'createdBy' => [
                        'user' => [
                            'displayName' => $item['createdBy']['user']['displayName'] ?? '',
                            'id' => $item['createdBy']['user']['id'] ?? '',
                        ],
                    ],
                    'lastModifiedBy' => [
                        'user' => [
                            'displayName' => $item['lastModifiedBy']['user']['displayName'] ?? '',
                            'id' => $item['lastModifiedBy']['user']['id'] ?? '',
                        ],
                    ],
                ];
            }

            return $files;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Upload a file to OneDrive.
     *
     * @param string $filePath     Path to the file to upload
     * @param string $onedrivePath Destination path in OneDrive
     * @param bool   $overwrite    Whether to overwrite if file exists
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     size: int,
     *     createdDateTime: string,
     *     lastModifiedDateTime: string,
     *     webUrl: string,
     *     downloadUrl: string,
     *     file: array{hashes: array{sha1Hash: string, quickXorHash: string}},
     * }|string
     */
    public function uploadFile(
        string $filePath,
        string $onedrivePath,
        bool $overwrite = true,
    ): array|string {
        try {
            if (!file_exists($filePath)) {
                return 'Error: File does not exist';
            }

            $fileContent = file_get_contents($filePath);
            $fileName = basename($filePath);
            $fullPath = rtrim($onedrivePath, '/').'/'.$fileName;

            $response = $this->httpClient->request('PUT', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root:/{$fullPath}:/content", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $fileContent,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error uploading file: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'size' => $data['size'],
                'createdDateTime' => $data['createdDateTime'],
                'lastModifiedDateTime' => $data['lastModifiedDateTime'],
                'webUrl' => $data['webUrl'],
                'downloadUrl' => $data['@microsoft.graph.downloadUrl'] ?? '',
                'file' => [
                    'hashes' => [
                        'sha1Hash' => $data['file']['hashes']['sha1Hash'] ?? '',
                        'quickXorHash' => $data['file']['hashes']['quickXorHash'] ?? '',
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error uploading file: '.$e->getMessage();
        }
    }

    /**
     * Download a file from OneDrive.
     *
     * @param string $fileId    OneDrive file ID
     * @param string $localPath Local path to save the file
     *
     * @return array{
     *     file_id: string,
     *     file_name: string,
     *     file_size: int,
     *     saved_path: string,
     *     metadata: array{
     *         id: string,
     *         name: string,
     *         size: int,
     *         createdDateTime: string,
     *         lastModifiedDateTime: string,
     *     },
     * }|string
     */
    public function downloadFile(string $fileId, string $localPath): array|string
    {
        try {
            // First, get file metadata
            $metadataResponse = $this->httpClient->request('GET', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/items/{$fileId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $metadata = $metadataResponse->toArray();

            if (isset($metadata['error'])) {
                return 'Error getting file metadata: '.($metadata['error']['message'] ?? 'Unknown error');
            }

            // Download the file
            $downloadResponse = $this->httpClient->request('GET', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/items/{$fileId}/content", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $fileContent = $downloadResponse->getContent();

            // Save to local path
            if (is_dir($localPath)) {
                $localPath = rtrim($localPath, '/').'/'.$metadata['name'];
            }

            file_put_contents($localPath, $fileContent);

            return [
                'file_id' => $fileId,
                'file_name' => $metadata['name'],
                'file_size' => \strlen($fileContent),
                'saved_path' => $localPath,
                'metadata' => [
                    'id' => $metadata['id'],
                    'name' => $metadata['name'],
                    'size' => $metadata['size'],
                    'createdDateTime' => $metadata['createdDateTime'],
                    'lastModifiedDateTime' => $metadata['lastModifiedDateTime'],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error downloading file: '.$e->getMessage();
        }
    }

    /**
     * Create a folder in OneDrive.
     *
     * @param string $path       Path where to create the folder
     * @param string $folderName Name of the folder to create
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     createdDateTime: string,
     *     lastModifiedDateTime: string,
     *     webUrl: string,
     *     folder: array{childCount: int},
     * }|string
     */
    public function createFolder(string $path, string $folderName): array|string
    {
        try {
            $endpoint = 'root' === $path
                ? "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root/children"
                : "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root:/{$path}:/children";

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'name' => $folderName,
                    'folder' => new \stdClass(),
                    '@microsoft.graph.conflictBehavior' => 'rename',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating folder: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'createdDateTime' => $data['createdDateTime'],
                'lastModifiedDateTime' => $data['lastModifiedDateTime'],
                'webUrl' => $data['webUrl'],
                'folder' => [
                    'childCount' => $data['folder']['childCount'] ?? 0,
                ],
            ];
        } catch (\Exception $e) {
            return 'Error creating folder: '.$e->getMessage();
        }
    }

    /**
     * Share a file on OneDrive.
     *
     * @param string $fileId             OneDrive file ID
     * @param string $type               Share type (view, edit)
     * @param string $scope              Share scope (anonymous, organization, users)
     * @param string $password           Optional password for the link
     * @param string $expirationDateTime Expiration date (ISO 8601 format)
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     webUrl: string,
     *     type: string,
     *     scope: string,
     *     hasPassword: bool,
     *     expirationDateTime: string,
     *     link: array{
     *         type: string,
     *         scope: string,
     *         webUrl: string,
     *         application: array{displayName: string, id: string},
     *     },
     * }|string
     */
    public function shareFile(
        string $fileId,
        string $type = 'view',
        string $scope = 'anonymous',
        string $password = '',
        string $expirationDateTime = '',
    ): array|string {
        try {
            $permission = [
                'type' => $type,
                'scope' => $scope,
            ];

            if ($password) {
                $permission['password'] = $password;
            }

            if ($expirationDateTime) {
                $permission['expirationDateTime'] = $expirationDateTime;
            }

            $response = $this->httpClient->request('POST', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/items/{$fileId}/createLink", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $permission,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error sharing file: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'webUrl' => $data['webUrl'],
                'type' => $data['type'],
                'scope' => $data['scope'],
                'hasPassword' => $data['hasPassword'] ?? false,
                'expirationDateTime' => $data['expirationDateTime'] ?? '',
                'link' => [
                    'type' => $data['link']['type'],
                    'scope' => $data['link']['scope'],
                    'webUrl' => $data['link']['webUrl'],
                    'application' => [
                        'displayName' => $data['link']['application']['displayName'],
                        'id' => $data['link']['application']['id'],
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error sharing file: '.$e->getMessage();
        }
    }

    /**
     * Search for files in OneDrive.
     *
     * @param string $query   Search query
     * @param int    $top     Maximum number of results
     * @param string $orderBy Order by field
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     size: int,
     *     createdDateTime: string,
     *     lastModifiedDateTime: string,
     *     webUrl: string,
     *     downloadUrl: string,
     *     file: array{hashes: array{sha1Hash: string, quickXorHash: string}}|null,
     *     folder: array{childCount: int}|null,
     *     parentReference: array{driveId: string, driveType: string, id: string, path: string},
     * }>
     */
    public function searchFiles(
        #[With(maximum: 500)]
        string $query,
        int $top = 20,
        string $orderBy = 'lastModifiedDateTime desc',
    ): array {
        try {
            $response = $this->httpClient->request('GET', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/root/search(q='{$query}')", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    '$top' => min(max($top, 1), 1000),
                    '$orderby' => $orderBy,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['value'])) {
                return [];
            }

            $results = [];
            foreach ($data['value'] as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'size' => $item['size'] ?? 0,
                    'createdDateTime' => $item['createdDateTime'],
                    'lastModifiedDateTime' => $item['lastModifiedDateTime'],
                    'webUrl' => $item['webUrl'],
                    'downloadUrl' => $item['@microsoft.graph.downloadUrl'] ?? '',
                    'file' => $item['file'] ?? null,
                    'folder' => $item['folder'] ?? null,
                    'parentReference' => [
                        'driveId' => $item['parentReference']['driveId'],
                        'driveType' => $item['parentReference']['driveType'],
                        'id' => $item['parentReference']['id'],
                        'path' => $item['parentReference']['path'],
                    ],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get file metadata from OneDrive.
     *
     * @param string $fileId OneDrive file ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     size: int,
     *     createdDateTime: string,
     *     lastModifiedDateTime: string,
     *     webUrl: string,
     *     downloadUrl: string,
     *     file: array{hashes: array{sha1Hash: string, quickXorHash: string}}|null,
     *     folder: array{childCount: int}|null,
     *     parentReference: array{driveId: string, driveType: string, id: string, path: string},
     *     createdBy: array{user: array{displayName: string, id: string}},
     *     lastModifiedBy: array{user: array{displayName: string, id: string}},
     * }|string
     */
    public function getFileMetadata(string $fileId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://graph.microsoft.com/{$this->apiVersion}/me/drive/items/{$fileId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting file metadata: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'size' => $data['size'] ?? 0,
                'createdDateTime' => $data['createdDateTime'],
                'lastModifiedDateTime' => $data['lastModifiedDateTime'],
                'webUrl' => $data['webUrl'],
                'downloadUrl' => $data['@microsoft.graph.downloadUrl'] ?? '',
                'file' => $data['file'] ?? null,
                'folder' => $data['folder'] ?? null,
                'parentReference' => [
                    'driveId' => $data['parentReference']['driveId'],
                    'driveType' => $data['parentReference']['driveType'],
                    'id' => $data['parentReference']['id'],
                    'path' => $data['parentReference']['path'],
                ],
                'createdBy' => [
                    'user' => [
                        'displayName' => $data['createdBy']['user']['displayName'] ?? '',
                        'id' => $data['createdBy']['user']['id'] ?? '',
                    ],
                ],
                'lastModifiedBy' => [
                    'user' => [
                        'displayName' => $data['lastModifiedBy']['user']['displayName'] ?? '',
                        'id' => $data['lastModifiedBy']['user']['id'] ?? '',
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return 'Error getting file metadata: '.$e->getMessage();
        }
    }
}
