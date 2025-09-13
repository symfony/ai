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
#[AsTool('k8s_get_pods', 'Tool that gets Kubernetes pods')]
#[AsTool('k8s_get_services', 'Tool that gets Kubernetes services', method: 'getServices')]
#[AsTool('k8s_get_deployments', 'Tool that gets Kubernetes deployments', method: 'getDeployments')]
#[AsTool('k8s_get_nodes', 'Tool that gets Kubernetes nodes', method: 'getNodes')]
#[AsTool('k8s_get_namespaces', 'Tool that gets Kubernetes namespaces', method: 'getNamespaces')]
#[AsTool('k8s_exec_command', 'Tool that executes commands in Kubernetes pods', method: 'execCommand')]
final readonly class Kubernetes
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $token,
        private string $baseUrl,
        private string $namespace = 'default',
        private array $options = [],
    ) {
    }

    /**
     * Get Kubernetes pods.
     *
     * @param string $namespace     Namespace (default: default)
     * @param string $labelSelector Label selector filter
     * @param string $fieldSelector Field selector filter
     *
     * @return array<int, array{
     *     name: string,
     *     namespace: string,
     *     status: string,
     *     phase: string,
     *     podIP: string,
     *     hostIP: string,
     *     startTime: string,
     *     containers: array<int, array{
     *         name: string,
     *         image: string,
     *         ready: bool,
     *         restartCount: int,
     *         state: array<string, mixed>,
     *     }>,
     *     labels: array<string, string>,
     *     annotations: array<string, string>,
     * }>
     */
    public function __invoke(
        string $namespace = '',
        string $labelSelector = '',
        string $fieldSelector = '',
    ): array {
        try {
            $ns = $namespace ?: $this->namespace;
            $params = [];

            if ($labelSelector) {
                $params['labelSelector'] = $labelSelector;
            }
            if ($fieldSelector) {
                $params['fieldSelector'] = $fieldSelector;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/v1/namespaces/{$ns}/pods", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($pod) => [
                'name' => $pod['metadata']['name'],
                'namespace' => $pod['metadata']['namespace'],
                'status' => $pod['status']['phase'],
                'phase' => $pod['status']['phase'],
                'podIP' => $pod['status']['podIP'] ?? '',
                'hostIP' => $pod['status']['hostIP'] ?? '',
                'startTime' => $pod['status']['startTime'] ?? '',
                'containers' => array_map(fn ($container) => [
                    'name' => $container['name'],
                    'image' => $container['image'],
                    'ready' => $container['ready'] ?? false,
                    'restartCount' => $container['restartCount'] ?? 0,
                    'state' => $container['state'] ?? [],
                ], $pod['status']['containerStatuses'] ?? []),
                'labels' => $pod['metadata']['labels'] ?? [],
                'annotations' => $pod['metadata']['annotations'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kubernetes services.
     *
     * @param string $namespace     Namespace (default: default)
     * @param string $labelSelector Label selector filter
     *
     * @return array<int, array{
     *     name: string,
     *     namespace: string,
     *     type: string,
     *     clusterIP: string,
     *     externalIPs: array<int, string>,
     *     ports: array<int, array{
     *         name: string,
     *         port: int,
     *         targetPort: int|string,
     *         protocol: string,
     *     }>,
     *     selector: array<string, string>,
     *     labels: array<string, string>,
     * }>
     */
    public function getServices(
        string $namespace = '',
        string $labelSelector = '',
    ): array {
        try {
            $ns = $namespace ?: $this->namespace;
            $params = [];

            if ($labelSelector) {
                $params['labelSelector'] = $labelSelector;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/v1/namespaces/{$ns}/services", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($service) => [
                'name' => $service['metadata']['name'],
                'namespace' => $service['metadata']['namespace'],
                'type' => $service['spec']['type'],
                'clusterIP' => $service['spec']['clusterIP'] ?? '',
                'externalIPs' => $service['spec']['externalIPs'] ?? [],
                'ports' => array_map(fn ($port) => [
                    'name' => $port['name'] ?? '',
                    'port' => $port['port'],
                    'targetPort' => $port['targetPort'],
                    'protocol' => $port['protocol'] ?? 'TCP',
                ], $service['spec']['ports'] ?? []),
                'selector' => $service['spec']['selector'] ?? [],
                'labels' => $service['metadata']['labels'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kubernetes deployments.
     *
     * @param string $namespace     Namespace (default: default)
     * @param string $labelSelector Label selector filter
     *
     * @return array<int, array{
     *     name: string,
     *     namespace: string,
     *     replicas: int,
     *     readyReplicas: int,
     *     availableReplicas: int,
     *     strategy: string,
     *     selector: array<string, string>,
     *     labels: array<string, string>,
     * }>
     */
    public function getDeployments(
        string $namespace = '',
        string $labelSelector = '',
    ): array {
        try {
            $ns = $namespace ?: $this->namespace;
            $params = [];

            if ($labelSelector) {
                $params['labelSelector'] = $labelSelector;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/apis/apps/v1/namespaces/{$ns}/deployments", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($deployment) => [
                'name' => $deployment['metadata']['name'],
                'namespace' => $deployment['metadata']['namespace'],
                'replicas' => $deployment['spec']['replicas'],
                'readyReplicas' => $deployment['status']['readyReplicas'] ?? 0,
                'availableReplicas' => $deployment['status']['availableReplicas'] ?? 0,
                'strategy' => $deployment['spec']['strategy']['type'] ?? 'RollingUpdate',
                'selector' => $deployment['spec']['selector']['matchLabels'] ?? [],
                'labels' => $deployment['metadata']['labels'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kubernetes nodes.
     *
     * @param string $labelSelector Label selector filter
     *
     * @return array<int, array{
     *     name: string,
     *     status: string,
     *     roles: array<int, string>,
     *     version: string,
     *     osImage: string,
     *     kernelVersion: string,
     *     containerRuntimeVersion: string,
     *     capacity: array<string, string>,
     *     allocatable: array<string, string>,
     *     labels: array<string, string>,
     * }>
     */
    public function getNodes(string $labelSelector = ''): array
    {
        try {
            $params = [];

            if ($labelSelector) {
                $params['labelSelector'] = $labelSelector;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/v1/nodes", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($node) => [
                'name' => $node['metadata']['name'],
                'status' => $node['status']['phase'],
                'roles' => array_keys(array_filter($node['metadata']['labels'] ?? [], fn ($key) => str_starts_with($key, 'node-role.kubernetes.io/'))),
                'version' => $node['status']['nodeInfo']['kubeletVersion'],
                'osImage' => $node['status']['nodeInfo']['osImage'],
                'kernelVersion' => $node['status']['nodeInfo']['kernelVersion'],
                'containerRuntimeVersion' => $node['status']['nodeInfo']['containerRuntimeVersion'],
                'capacity' => $node['status']['capacity'] ?? [],
                'allocatable' => $node['status']['allocatable'] ?? [],
                'labels' => $node['metadata']['labels'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get Kubernetes namespaces.
     *
     * @param string $labelSelector Label selector filter
     *
     * @return array<int, array{
     *     name: string,
     *     status: string,
     *     labels: array<string, string>,
     *     annotations: array<string, string>,
     * }>
     */
    public function getNamespaces(string $labelSelector = ''): array
    {
        try {
            $params = [];

            if ($labelSelector) {
                $params['labelSelector'] = $labelSelector;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('GET', "{$this->baseUrl}/api/v1/namespaces", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($namespace) => [
                'name' => $namespace['metadata']['name'],
                'status' => $namespace['status']['phase'],
                'labels' => $namespace['metadata']['labels'] ?? [],
                'annotations' => $namespace['metadata']['annotations'] ?? [],
            ], $data['items'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Execute command in Kubernetes pod.
     *
     * @param string $podName   Pod name
     * @param string $container Container name (optional)
     * @param string $command   Command to execute
     * @param string $namespace Namespace (default: default)
     *
     * @return array{
     *     output: string,
     *     error: string,
     *     exitCode: int,
     * }|string
     */
    public function execCommand(
        string $podName,
        string $container = '',
        string $command = '',
        string $namespace = '',
    ): array|string {
        try {
            $ns = $namespace ?: $this->namespace;
            $params = [
                'command' => explode(' ', $command),
                'stdin' => false,
                'stdout' => true,
                'stderr' => true,
                'tty' => false,
            ];

            if ($container) {
                $params['container'] = $container;
            }

            $headers = ['Content-Type' => 'application/json'];
            if ($this->token) {
                $headers['Authorization'] = 'Bearer '.$this->token;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/api/v1/namespaces/{$ns}/pods/{$podName}/exec", [
                'headers' => $headers,
                'query' => array_merge($this->options, $params),
            ]);

            if (200 === $response->getStatusCode()) {
                return [
                    'output' => $response->getContent(),
                    'error' => '',
                    'exitCode' => 0,
                ];
            }

            return 'Error executing command: HTTP '.$response->getStatusCode();
        } catch (\Exception $e) {
            return 'Error executing command: '.$e->getMessage();
        }
    }
}
