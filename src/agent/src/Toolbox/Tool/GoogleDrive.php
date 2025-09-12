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
#[AsTool('google_drive_list_files', 'Tool that lists files from Google Drive')]
#[AsTool('google_drive_upload_file', 'Tool that uploads files to Google Drive', method: 'uploadFile')]
#[AsTool('google_drive_download_file', 'Tool that downloads files from Google Drive', method: 'downloadFile')]
#[AsTool('google_drive_create_folder', 'Tool that creates folders in Google Drive', method: 'createFolder')]
#[AsTool('google_drive_share_file', 'Tool that shares files on Google Drive', method: 'shareFile')]
#[AsTool('google_drive_search_files', 'Tool that searches for files in Google Drive', method: 'searchFiles')]
final readonly class GoogleDrive
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
     * List files from Google Drive.
     *
     * @param string $folderId       Folder ID to list files from (use 'root' for root folder)
     * @param int    $maxResults     Maximum number of files to return
     * @param string $orderBy        Order by field (createdTime, folder, modifiedByMeTime, modifiedTime, name, name_natural, quotaBytesUsed, recency, sharedWithMeTime, starred, viewedByMeTime)
     * @param string $query          Search query to filter files
     * @param bool   $includeTrashed Whether to include trashed files
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     mimeType: string,
     *     size: string,
     *     createdTime: string,
     *     modifiedTime: string,
     *     parents: array<int, string>,
     *     webViewLink: string,
     *     webContentLink: string,
     *     thumbnailLink: string,
     *     ownedByMe: bool,
     *     shared: bool,
     *     starred: bool,
     *     trashed: bool,
     *     permissions: array<int, array{
     *         id: string,
     *         type: string,
     *         role: string,
     *         emailAddress: string,
     *     }>,
     * }>
     */
    public function __invoke(
        string $folderId = 'root',
        int $maxResults = 100,
        string $orderBy = 'modifiedTime desc',
        string $query = '',
        bool $includeTrashed = false,
    ): array {
        try {
            $params = [
                'pageSize' => min(max($maxResults, 1), 1000),
                'orderBy' => $orderBy,
                'fields' => 'nextPageToken, files(id,name,mimeType,size,createdTime,modifiedTime,parents,webViewLink,webContentLink,thumbnailLink,ownedByMe,shared,starred,trashed,permissions)',
            ];

            if ($query) {
                $params['q'] = $query;
            } elseif ('root' !== $folderId) {
                $params['q'] = "'{$folderId}' in parents";
            }

            if (!$includeTrashed) {
                $existingQuery = $params['q'] ?? '';
                $params['q'] = $existingQuery ? "{$existingQuery} and trashed=false" : 'trashed=false';
            }

            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/drive/v3/files', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (!isset($data['files'])) {
                return [];
            }

            $files = [];
            foreach ($data['files'] as $file) {
                $files[] = [
                    'id' => $file['id'],
                    'name' => $file['name'],
                    'mimeType' => $file['mimeType'],
                    'size' => $file['size'] ?? '0',
                    'createdTime' => $file['createdTime'],
                    'modifiedTime' => $file['modifiedTime'],
                    'parents' => $file['parents'] ?? [],
                    'webViewLink' => $file['webViewLink'] ?? '',
                    'webContentLink' => $file['webContentLink'] ?? '',
                    'thumbnailLink' => $file['thumbnailLink'] ?? '',
                    'ownedByMe' => $file['ownedByMe'] ?? false,
                    'shared' => $file['shared'] ?? false,
                    'starred' => $file['starred'] ?? false,
                    'trashed' => $file['trashed'] ?? false,
                    'permissions' => array_map(fn ($permission) => [
                        'id' => $permission['id'] ?? '',
                        'type' => $permission['type'],
                        'role' => $permission['role'],
                        'emailAddress' => $permission['emailAddress'] ?? '',
                    ], $file['permissions'] ?? []),
                ];
            }

            return $files;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Upload a file to Google Drive.
     *
     * @param string             $filePath    Path to the file to upload
     * @param string             $fileName    Name for the file in Google Drive
     * @param array<int, string> $parentIds   Parent folder IDs
     * @param string             $description File description
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     mimeType: string,
     *     size: string,
     *     createdTime: string,
     *     modifiedTime: string,
     *     webViewLink: string,
     *     webContentLink: string,
     * }|string
     */
    public function uploadFile(
        string $filePath,
        string $fileName = '',
        array $parentIds = [],
        string $description = '',
    ): array|string {
        try {
            if (!file_exists($filePath)) {
                return 'Error: File does not exist';
            }

            $fileName = $fileName ?: basename($filePath);
            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            // Create metadata
            $metadata = [
                'name' => $fileName,
                'description' => $description,
            ];

            if (!empty($parentIds)) {
                $metadata['parents'] = $parentIds;
            }

            $response = $this->httpClient->request('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'body' => [
                    'metadata' => json_encode($metadata),
                    'file' => $fileContent,
                ],
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'mimeType' => $data['mimeType'],
                'size' => $data['size'] ?? '0',
                'createdTime' => $data['createdTime'],
                'modifiedTime' => $data['modifiedTime'],
                'webViewLink' => $data['webViewLink'] ?? '',
                'webContentLink' => $data['webContentLink'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error uploading file: '.$e->getMessage();
        }
    }

    /**
     * Download a file from Google Drive.
     *
     * @param string $fileId   File ID to download
     * @param string $savePath Local path to save the file
     *
     * @return array{
     *     file_id: string,
     *     file_name: string,
     *     file_size: int,
     *     saved_path: string,
     * }|string
     */
    public function downloadFile(string $fileId, string $savePath): array|string
    {
        try {
            // First, get file metadata
            $metadataResponse = $this->httpClient->request('GET', "https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => 'id,name,mimeType,size',
                ],
            ]);

            $metadata = $metadataResponse->toArray();

            // Download the file
            $downloadResponse = $this->httpClient->request('GET', "https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'alt' => 'media',
                ],
                'buffer' => false,
            ]);

            $fileContent = $downloadResponse->getContent();

            // Save to local path
            if (is_dir($savePath)) {
                $savePath = rtrim($savePath, '/').'/'.$metadata['name'];
            }

            file_put_contents($savePath, $fileContent);

            return [
                'file_id' => $metadata['id'],
                'file_name' => $metadata['name'],
                'file_size' => \strlen($fileContent),
                'saved_path' => $savePath,
            ];
        } catch (\Exception $e) {
            return 'Error downloading file: '.$e->getMessage();
        }
    }

    /**
     * Create a folder in Google Drive.
     *
     * @param string             $folderName Name of the folder to create
     * @param array<int, string> $parentIds  Parent folder IDs
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     mimeType: string,
     *     createdTime: string,
     *     modifiedTime: string,
     *     webViewLink: string,
     * }|string
     */
    public function createFolder(string $folderName, array $parentIds = []): array|string
    {
        try {
            $folderData = [
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ];

            if (!empty($parentIds)) {
                $folderData['parents'] = $parentIds;
            }

            $response = $this->httpClient->request('POST', 'https://www.googleapis.com/drive/v3/files', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $folderData,
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'name' => $data['name'],
                'mimeType' => $data['mimeType'],
                'createdTime' => $data['createdTime'],
                'modifiedTime' => $data['modifiedTime'],
                'webViewLink' => $data['webViewLink'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error creating folder: '.$e->getMessage();
        }
    }

    /**
     * Share a file on Google Drive.
     *
     * @param string $fileId       File ID to share
     * @param string $emailAddress Email address to share with
     * @param string $role         Permission role (reader, writer, commenter, owner)
     * @param string $type         Permission type (user, group, domain, anyone)
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     role: string,
     *     emailAddress: string,
     * }|string
     */
    public function shareFile(
        string $fileId,
        string $emailAddress = '',
        string $role = 'reader',
        string $type = 'user',
    ): array|string {
        try {
            $permissionData = [
                'type' => $type,
                'role' => $role,
            ];

            if ($emailAddress) {
                $permissionData['emailAddress'] = $emailAddress;
            }

            $response = $this->httpClient->request('POST', "https://www.googleapis.com/drive/v3/files/{$fileId}/permissions", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $permissionData,
            ]);

            $data = $response->toArray();

            return [
                'id' => $data['id'],
                'type' => $data['type'],
                'role' => $data['role'],
                'emailAddress' => $data['emailAddress'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error sharing file: '.$e->getMessage();
        }
    }

    /**
     * Search for files in Google Drive.
     *
     * @param string $searchQuery    Search query
     * @param int    $maxResults     Maximum number of results to return
     * @param string $mimeType       MIME type filter
     * @param bool   $includeTrashed Whether to include trashed files
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     mimeType: string,
     *     size: string,
     *     createdTime: string,
     *     modifiedTime: string,
     *     webViewLink: string,
     *     thumbnailLink: string,
     *     starred: bool,
     *     trashed: bool,
     * }>
     */
    public function searchFiles(
        string $searchQuery,
        int $maxResults = 50,
        string $mimeType = '',
        bool $includeTrashed = false,
    ): array {
        try {
            $query = "name contains '{$searchQuery}'";

            if ($mimeType) {
                $query .= " and mimeType='{$mimeType}'";
            }

            if (!$includeTrashed) {
                $query .= ' and trashed=false';
            }

            return $this->__invoke(
                folderId: 'root',
                maxResults: $maxResults,
                query: $query,
                includeTrashed: $includeTrashed,
            );
        } catch (\Exception $e) {
            return [];
        }
    }
}
