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
#[AsTool('figma_get_file', 'Tool that gets Figma files')]
#[AsTool('figma_get_file_nodes', 'Tool that gets Figma file nodes', method: 'getFileNodes')]
#[AsTool('figma_get_images', 'Tool that gets Figma images', method: 'getImages')]
#[AsTool('figma_get_comments', 'Tool that gets Figma comments', method: 'getComments')]
#[AsTool('figma_get_team_projects', 'Tool that gets Figma team projects', method: 'getTeamProjects')]
#[AsTool('figma_get_team_files', 'Tool that gets Figma team files', method: 'getTeamFiles')]
final readonly class Figma
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v1',
        private array $options = [],
    ) {
    }

    /**
     * Get Figma file.
     *
     * @param string             $fileKey    Figma file key
     * @param string             $version    File version
     * @param array<int, string> $ids        Specific node IDs to retrieve
     * @param int                $depth      Depth of children to retrieve
     * @param string             $geometry   Geometry type (paths, bounds)
     * @param string             $pluginData Plugin data to include
     *
     * @return array{
     *     document: array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         visible: bool,
     *         children: array<int, array<string, mixed>>,
     *     },
     *     components: array<string, array{
     *         key: string,
     *         name: string,
     *         description: string,
     *         componentSetId: string|null,
     *         documentationLinks: array<int, mixed>,
     *     }>,
     *     componentSets: array<string, array{
     *         key: string,
     *         name: string,
     *         description: string,
     *         documentationLinks: array<int, mixed>,
     *     }>,
     *     styles: array<string, array{
     *         key: string,
     *         name: string,
     *         description: string,
     *         styleType: string,
     *     }>,
     *     name: string,
     *     lastModified: string,
     *     thumbnailUrl: string,
     *     version: string,
     *     role: string,
     *     editorType: string,
     *     linkAccess: string,
     * }|string
     */
    public function __invoke(
        string $fileKey,
        string $version = '',
        array $ids = [],
        int $depth = 1,
        string $geometry = 'paths',
        string $pluginData = '',
    ): array|string {
        try {
            $params = [
                'depth' => max($depth, 1),
                'geometry' => $geometry,
            ];

            if ($version) {
                $params['version'] = $version;
            }
            if (!empty($ids)) {
                $params['ids'] = implode(',', $ids);
            }
            if ($pluginData) {
                $params['plugin_data'] = $pluginData;
            }

            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/files/{$fileKey}", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting file: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'document' => $data['document'],
                'components' => $data['components'] ?? [],
                'componentSets' => $data['componentSets'] ?? [],
                'styles' => $data['styles'] ?? [],
                'name' => $data['name'],
                'lastModified' => $data['lastModified'],
                'thumbnailUrl' => $data['thumbnailUrl'],
                'version' => $data['version'],
                'role' => $data['role'],
                'editorType' => $data['editorType'],
                'linkAccess' => $data['linkAccess'],
            ];
        } catch (\Exception $e) {
            return 'Error getting file: '.$e->getMessage();
        }
    }

    /**
     * Get Figma file nodes.
     *
     * @param string             $fileKey    Figma file key
     * @param array<int, string> $ids        Node IDs to retrieve
     * @param int                $depth      Depth of children to retrieve
     * @param string             $geometry   Geometry type (paths, bounds)
     * @param string             $pluginData Plugin data to include
     *
     * @return array{
     *     nodes: array<string, array{
     *         document: array<string, mixed>,
     *         components: array<string, mixed>,
     *         componentSets: array<string, mixed>,
     *         styles: array<string, mixed>,
     *     }>,
     *     lastModified: string,
     *     name: string,
     *     role: string,
     *     thumbnailUrl: string,
     *     version: string,
     * }|string
     */
    public function getFileNodes(
        string $fileKey,
        array $ids,
        int $depth = 1,
        string $geometry = 'paths',
        string $pluginData = '',
    ): array|string {
        try {
            if (empty($ids)) {
                return 'Error: Node IDs are required';
            }

            $params = [
                'ids' => implode(',', $ids),
                'depth' => max($depth, 1),
                'geometry' => $geometry,
            ];

            if ($pluginData) {
                $params['plugin_data'] = $pluginData;
            }

            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/files/{$fileKey}/nodes", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting file nodes: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'nodes' => $data['nodes'],
                'lastModified' => $data['lastModified'],
                'name' => $data['name'],
                'role' => $data['role'],
                'thumbnailUrl' => $data['thumbnailUrl'],
                'version' => $data['version'],
            ];
        } catch (\Exception $e) {
            return 'Error getting file nodes: '.$e->getMessage();
        }
    }

    /**
     * Get Figma images.
     *
     * @param string             $fileKey           Figma file key
     * @param array<int, string> $ids               Node IDs to get images for
     * @param string             $format            Image format (jpg, png, svg, pdf)
     * @param string             $scale             Image scale (1, 2, 4, 8)
     * @param string             $svgOutline        SVG outline mode (full, simplified)
     * @param string             $svgId             SVG node ID
     * @param bool               $useAbsoluteBounds Use absolute bounds
     * @param string             $version           File version
     *
     * @return array{
     *     images: array<string, string>,
     *     status: int,
     *     error: bool,
     * }|string
     */
    public function getImages(
        string $fileKey,
        array $ids,
        string $format = 'png',
        string $scale = '1',
        string $svgOutline = 'full',
        string $svgId = '',
        bool $useAbsoluteBounds = false,
        string $version = '',
    ): array|string {
        try {
            if (empty($ids)) {
                return 'Error: Node IDs are required';
            }

            $params = [
                'ids' => implode(',', $ids),
                'format' => $format,
                'scale' => $scale,
                'svg_outline' => $svgOutline,
                'use_absolute_bounds' => $useAbsoluteBounds,
            ];

            if ($svgId) {
                $params['svg_id'] = $svgId;
            }
            if ($version) {
                $params['version'] = $version;
            }

            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/images/{$fileKey}", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting images: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'images' => $data['images'] ?? [],
                'status' => $data['status'] ?? 200,
                'error' => $data['error'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error getting images: '.$e->getMessage();
        }
    }

    /**
     * Get Figma comments.
     *
     * @param string $fileKey Figma file key
     *
     * @return array<int, array{
     *     id: string,
     *     file_key: string,
     *     parent_id: string|null,
     *     user: array{
     *         id: string,
     *         handle: string,
     *         img_url: string,
     *     },
     *     created_at: string,
     *     resolved_at: string|null,
     *     message: string,
     *     client_meta: array{
     *         x: float,
     *         y: float,
     *         node_id: string|null,
     *         node_offset: array{x: float, y: float}|null,
     *     },
     *     order_id: string,
     * }>|string
     */
    public function getComments(string $fileKey): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/files/{$fileKey}/comments", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting comments: '.($data['message'] ?? 'Unknown error');
            }

            return array_map(fn ($comment) => [
                'id' => $comment['id'],
                'file_key' => $comment['file_key'],
                'parent_id' => $comment['parent_id'],
                'user' => [
                    'id' => $comment['user']['id'],
                    'handle' => $comment['user']['handle'],
                    'img_url' => $comment['user']['img_url'],
                ],
                'created_at' => $comment['created_at'],
                'resolved_at' => $comment['resolved_at'],
                'message' => $comment['message'],
                'client_meta' => [
                    'x' => $comment['client_meta']['x'],
                    'y' => $comment['client_meta']['y'],
                    'node_id' => $comment['client_meta']['node_id'],
                    'node_offset' => $comment['client_meta']['node_offset'],
                ],
                'order_id' => $comment['order_id'],
            ], $data['comments'] ?? []);
        } catch (\Exception $e) {
            return 'Error getting comments: '.$e->getMessage();
        }
    }

    /**
     * Get Figma team projects.
     *
     * @param string $teamId Team ID
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     * }>|string
     */
    public function getTeamProjects(string $teamId): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/teams/{$teamId}/projects", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting team projects: '.($data['message'] ?? 'Unknown error');
            }

            return array_map(fn ($project) => [
                'id' => $project['id'],
                'name' => $project['name'],
            ], $data['projects'] ?? []);
        } catch (\Exception $e) {
            return 'Error getting team projects: '.$e->getMessage();
        }
    }

    /**
     * Get Figma team files.
     *
     * @param string $teamId     Team ID
     * @param string $projectId  Project ID (optional)
     * @param string $branchData Include branch data
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     last_modified: string,
     *     thumbnail_url: string,
     *     link_access: string,
     *     project: array{id: string, name: string}|null,
     *     branches: array<int, array{key: string, name: string, thumbnail_url: string, last_modified: string}>,
     * }>|string
     */
    public function getTeamFiles(
        string $teamId,
        string $projectId = '',
        string $branchData = '',
    ): array|string {
        try {
            $params = [];

            if ($projectId) {
                $params['project_ids'] = $projectId;
            }
            if ($branchData) {
                $params['branch_data'] = $branchData;
            }

            $response = $this->httpClient->request('GET', "https://api.figma.com/{$this->apiVersion}/teams/{$teamId}/files", [
                'headers' => [
                    'X-Figma-Token' => $this->accessToken,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 200 !== $data['status']) {
                return 'Error getting team files: '.($data['message'] ?? 'Unknown error');
            }

            return array_map(fn ($file) => [
                'key' => $file['key'],
                'name' => $file['name'],
                'last_modified' => $file['last_modified'],
                'thumbnail_url' => $file['thumbnail_url'],
                'link_access' => $file['link_access'],
                'project' => $file['project'] ? [
                    'id' => $file['project']['id'],
                    'name' => $file['project']['name'],
                ] : null,
                'branches' => array_map(fn ($branch) => [
                    'key' => $branch['key'],
                    'name' => $branch['name'],
                    'thumbnail_url' => $branch['thumbnail_url'],
                    'last_modified' => $branch['last_modified'],
                ], $file['branches'] ?? []),
            ], $data['files'] ?? []);
        } catch (\Exception $e) {
            return 'Error getting team files: '.$e->getMessage();
        }
    }
}
