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
#[AsTool('docker_list_images', 'Tool that lists Docker images')]
#[AsTool('docker_list_containers', 'Tool that lists Docker containers', method: 'listContainers')]
#[AsTool('docker_build_image', 'Tool that builds Docker images', method: 'buildImage')]
#[AsTool('docker_run_container', 'Tool that runs Docker containers', method: 'runContainer')]
#[AsTool('docker_stop_container', 'Tool that stops Docker containers', method: 'stopContainer')]
#[AsTool('docker_remove_container', 'Tool that removes Docker containers', method: 'removeContainer')]
final readonly class Docker
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $dockerHost = 'unix:///var/run/docker.sock',
        private string $apiVersion = 'v1.43',
        private array $options = [],
    ) {
    }

    /**
     * List Docker images.
     *
     * @param bool   $all     Show all images (including intermediate)
     * @param string $filters JSON string of filters
     * @param bool   $digests Show digests
     *
     * @return array<int, array{
     *     Id: string,
     *     ParentId: string,
     *     RepoTags: array<int, string>|null,
     *     RepoDigests: array<int, string>|null,
     *     Created: int,
     *     Size: int,
     *     SharedSize: int,
     *     VirtualSize: int,
     *     Labels: array<string, string>|null,
     *     Containers: int,
     * }>
     */
    public function __invoke(
        bool $all = false,
        string $filters = '',
        bool $digests = false,
    ): array {
        try {
            $params = [
                'all' => $all ? 'true' : 'false',
                'digests' => $digests ? 'true' : 'false',
            ];

            if ($filters) {
                $params['filters'] = $filters;
            }

            $response = $this->httpClient->request('GET', $this->buildUrl('/images/json'), [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return [];
            }

            return array_map(fn ($image) => [
                'Id' => $image['Id'],
                'ParentId' => $image['ParentId'],
                'RepoTags' => $image['RepoTags'],
                'RepoDigests' => $image['RepoDigests'],
                'Created' => $image['Created'],
                'Size' => $image['Size'],
                'SharedSize' => $image['SharedSize'],
                'VirtualSize' => $image['VirtualSize'],
                'Labels' => $image['Labels'],
                'Containers' => $image['Containers'],
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Docker containers.
     *
     * @param bool   $all     Show all containers (including stopped)
     * @param int    $limit   Limit number of containers
     * @param bool   $size    Show container sizes
     * @param string $filters JSON string of filters
     *
     * @return array<int, array{
     *     Id: string,
     *     Names: array<int, string>,
     *     Image: string,
     *     ImageID: string,
     *     Command: string,
     *     Created: int,
     *     Ports: array<int, array{
     *         IP: string,
     *         PrivatePort: int,
     *         PublicPort: int|null,
     *         Type: string,
     *     }>,
     *     SizeRw: int|null,
     *     SizeRootFs: int|null,
     *     Labels: array<string, string>,
     *     State: string,
     *     Status: string,
     *     HostConfig: array{
     *         NetworkMode: string,
     *     },
     *     NetworkSettings: array{
     *         Networks: array<string, mixed>,
     *     },
     *     Mounts: array<int, array{
     *         Type: string,
     *         Name: string,
     *         Source: string,
     *         Destination: string,
     *         Driver: string,
     *         Mode: string,
     *         RW: bool,
     *         Propagation: string,
     *     }>,
     * }>
     */
    public function listContainers(
        bool $all = false,
        int $limit = 0,
        bool $size = false,
        string $filters = '',
    ): array {
        try {
            $params = [
                'all' => $all ? 'true' : 'false',
                'size' => $size ? 'true' : 'false',
            ];

            if ($limit > 0) {
                $params['limit'] = $limit;
            }
            if ($filters) {
                $params['filters'] = $filters;
            }

            $response = $this->httpClient->request('GET', $this->buildUrl('/containers/json'), [
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return [];
            }

            return array_map(fn ($container) => [
                'Id' => $container['Id'],
                'Names' => $container['Names'],
                'Image' => $container['Image'],
                'ImageID' => $container['ImageID'],
                'Command' => $container['Command'],
                'Created' => $container['Created'],
                'Ports' => array_map(fn ($port) => [
                    'IP' => $port['IP'],
                    'PrivatePort' => $port['PrivatePort'],
                    'PublicPort' => $port['PublicPort'],
                    'Type' => $port['Type'],
                ], $container['Ports'] ?? []),
                'SizeRw' => $container['SizeRw'],
                'SizeRootFs' => $container['SizeRootFs'],
                'Labels' => $container['Labels'] ?? [],
                'State' => $container['State'],
                'Status' => $container['Status'],
                'HostConfig' => [
                    'NetworkMode' => $container['HostConfig']['NetworkMode'],
                ],
                'NetworkSettings' => [
                    'Networks' => $container['NetworkSettings']['Networks'] ?? [],
                ],
                'Mounts' => array_map(fn ($mount) => [
                    'Type' => $mount['Type'],
                    'Name' => $mount['Name'],
                    'Source' => $mount['Source'],
                    'Destination' => $mount['Destination'],
                    'Driver' => $mount['Driver'],
                    'Mode' => $mount['Mode'],
                    'RW' => $mount['RW'],
                    'Propagation' => $mount['Propagation'],
                ], $container['Mounts'] ?? []),
            ], $data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Build Docker image.
     *
     * @param string $dockerfile  Dockerfile content
     * @param string $context     Build context (directory path or tar stream)
     * @param string $tag         Image tag
     * @param bool   $noCache     Disable build cache
     * @param bool   $remove      Remove intermediate containers
     * @param bool   $forceRemove Force removal of intermediate containers
     * @param bool   $pull        Always pull base images
     *
     * @return array{
     *     stream: string,
     *     aux: array{ID: string}|null,
     *     error: string|null,
     *     errorDetail: array{message: string}|null,
     * }|string
     */
    public function buildImage(
        string $dockerfile,
        string $context,
        string $tag,
        bool $noCache = false,
        bool $remove = true,
        bool $forceRemove = false,
        bool $pull = false,
    ): array|string {
        try {
            $params = [
                'dockerfile' => $dockerfile,
                't' => $tag,
                'nocache' => $noCache ? 'true' : 'false',
                'rm' => $remove ? 'true' : 'false',
                'forcerm' => $forceRemove ? 'true' : 'false',
                'pull' => $pull ? 'true' : 'false',
            ];

            $response = $this->httpClient->request('POST', $this->buildUrl('/build'), [
                'headers' => [
                    'Content-Type' => 'application/tar',
                ],
                'query' => array_merge($this->options, $params),
                'body' => $context,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error building image: '.($data['error'] ?? 'Unknown error');
            }

            return [
                'stream' => $data['stream'] ?? '',
                'aux' => $data['aux'] ?? null,
                'error' => $data['error'] ?? null,
                'errorDetail' => $data['errorDetail'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error building image: '.$e->getMessage();
        }
    }

    /**
     * Run Docker container.
     *
     * @param string                $image       Image name
     * @param string                $name        Container name
     * @param array<int, string>    $cmd         Command to run
     * @param array<int, string>    $env         Environment variables
     * @param array<string, string> $ports       Port mappings (host_port:container_port)
     * @param array<string, string> $volumes     Volume mappings (host_path:container_path)
     * @param bool                  $detach      Run in detached mode
     * @param bool                  $interactive Keep STDIN open
     * @param bool                  $tty         Allocate a pseudo-TTY
     * @param string                $workingDir  Working directory
     * @param string                $user        Username or UID
     * @param string                $network     Network name
     *
     * @return array{
     *     Id: string,
     *     Warnings: array<int, string>|null,
     * }|string
     */
    public function runContainer(
        string $image,
        string $name = '',
        array $cmd = [],
        array $env = [],
        array $ports = [],
        array $volumes = [],
        bool $detach = true,
        bool $interactive = false,
        bool $tty = false,
        string $workingDir = '',
        string $user = '',
        string $network = '',
    ): array|string {
        try {
            $payload = [
                'Image' => $image,
                'Cmd' => $cmd,
                'Env' => $env,
                'AttachStdin' => $interactive,
                'AttachStdout' => true,
                'AttachStderr' => true,
                'Tty' => $tty,
                'OpenStdin' => $interactive,
                'StdinOnce' => false,
                'HostConfig' => [],
            ];

            if ($name) {
                $payload['name'] = $name;
            }
            if ($workingDir) {
                $payload['WorkingDir'] = $workingDir;
            }
            if ($user) {
                $payload['User'] = $user;
            }

            // Port bindings
            if (!empty($ports)) {
                $payload['HostConfig']['PortBindings'] = [];
                $payload['ExposedPorts'] = [];
                foreach ($ports as $hostPort => $containerPort) {
                    $payload['HostConfig']['PortBindings']["{$containerPort}/tcp"] = [['HostPort' => (string) $hostPort]];
                    $payload['ExposedPorts']["{$containerPort}/tcp"] = new \stdClass();
                }
            }

            // Volume bindings
            if (!empty($volumes)) {
                $payload['HostConfig']['Binds'] = [];
                foreach ($volumes as $hostPath => $containerPath) {
                    $payload['HostConfig']['Binds'][] = "{$hostPath}:{$containerPath}";
                }
            }

            // Network
            if ($network) {
                $payload['HostConfig']['NetworkMode'] = $network;
            }

            $response = $this->httpClient->request('POST', $this->buildUrl('/containers/create'), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['message'])) {
                return 'Error creating container: '.$data['message'];
            }

            $containerId = $data['Id'];

            // Start the container
            if ($detach) {
                $startResponse = $this->httpClient->request('POST', $this->buildUrl("/containers/{$containerId}/start"));
                if (204 !== $startResponse->getStatusCode()) {
                    return 'Error starting container: '.$startResponse->getContent();
                }
            }

            return [
                'Id' => $containerId,
                'Warnings' => $data['Warnings'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error running container: '.$e->getMessage();
        }
    }

    /**
     * Stop Docker container.
     *
     * @param string $containerId Container ID or name
     * @param int    $timeout     Timeout in seconds
     */
    public function stopContainer(
        string $containerId,
        int $timeout = 10,
    ): string {
        try {
            $params = [
                't' => $timeout,
            ];

            $response = $this->httpClient->request('POST', $this->buildUrl("/containers/{$containerId}/stop"), [
                'query' => array_merge($this->options, $params),
            ]);

            if (204 === $response->getStatusCode()) {
                return 'Container stopped successfully';
            }

            $data = $response->toArray();

            return 'Error stopping container: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error stopping container: '.$e->getMessage();
        }
    }

    /**
     * Remove Docker container.
     *
     * @param string $containerId   Container ID or name
     * @param bool   $force         Force removal
     * @param bool   $removeVolumes Remove associated volumes
     */
    public function removeContainer(
        string $containerId,
        bool $force = false,
        bool $removeVolumes = false,
    ): string {
        try {
            $params = [
                'force' => $force ? 'true' : 'false',
                'v' => $removeVolumes ? 'true' : 'false',
            ];

            $response = $this->httpClient->request('DELETE', $this->buildUrl("/containers/{$containerId}"), [
                'query' => array_merge($this->options, $params),
            ]);

            if (204 === $response->getStatusCode()) {
                return 'Container removed successfully';
            }

            $data = $response->toArray();

            return 'Error removing container: '.($data['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return 'Error removing container: '.$e->getMessage();
        }
    }

    /**
     * Build Docker API URL.
     */
    private function buildUrl(string $endpoint): string
    {
        $baseUrl = str_starts_with($this->dockerHost, 'unix://')
            ? 'http://localhost'
            : $this->dockerHost;

        return "{$baseUrl}/{$this->apiVersion}{$endpoint}";
    }
}
