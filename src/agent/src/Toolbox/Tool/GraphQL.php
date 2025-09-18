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
#[AsTool('graphql_query', 'Tool that executes GraphQL queries')]
#[AsTool('graphql_mutation', 'Tool that executes GraphQL mutations', method: 'mutation')]
#[AsTool('graphql_introspect', 'Tool that introspects GraphQL schema', method: 'introspect')]
#[AsTool('graphql_validate', 'Tool that validates GraphQL queries', method: 'validate')]
final readonly class GraphQL
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpoint,
        private array $headers = [],
        private array $options = [],
    ) {
    }

    /**
     * Execute GraphQL query.
     *
     * @param string               $query         GraphQL query string
     * @param array<string, mixed> $variables     Query variables
     * @param string               $operationName Operation name
     *
     * @return array{
     *     data: array<string, mixed>|null,
     *     errors: array<int, array{
     *         message: string,
     *         locations: array<int, array{
     *             line: int,
     *             column: int,
     *         }>,
     *         path: array<int, string|int>,
     *         extensions: array<string, mixed>,
     *     }>,
     *     extensions: array<string, mixed>,
     * }
     */
    public function __invoke(
        string $query,
        array $variables = [],
        string $operationName = '',
    ): array {
        try {
            $body = [
                'query' => $query,
            ];

            if (!empty($variables)) {
                $body['variables'] = $variables;
            }

            if ($operationName) {
                $body['operationName'] = $operationName;
            }

            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => array_merge([
                    'Content-Type' => 'application/json',
                ], $this->headers),
                'json' => $body,
            ]);

            $data = $response->toArray();

            return [
                'data' => $data['data'] ?? null,
                'errors' => array_map(fn ($error) => [
                    'message' => $error['message'],
                    'locations' => array_map(fn ($location) => [
                        'line' => $location['line'],
                        'column' => $location['column'],
                    ], $error['locations'] ?? []),
                    'path' => $error['path'] ?? [],
                    'extensions' => $error['extensions'] ?? [],
                ], $data['errors'] ?? []),
                'extensions' => $data['extensions'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'data' => null,
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'locations' => [],
                        'path' => [],
                        'extensions' => [],
                    ],
                ],
                'extensions' => [],
            ];
        }
    }

    /**
     * Execute GraphQL mutation.
     *
     * @param string               $mutation      GraphQL mutation string
     * @param array<string, mixed> $variables     Mutation variables
     * @param string               $operationName Operation name
     *
     * @return array{
     *     data: array<string, mixed>|null,
     *     errors: array<int, array{
     *         message: string,
     *         locations: array<int, array{
     *             line: int,
     *             column: int,
     *         }>,
     *         path: array<int, string|int>,
     *         extensions: array<string, mixed>,
     *     }>,
     *     extensions: array<string, mixed>,
     * }
     */
    public function mutation(
        string $mutation,
        array $variables = [],
        string $operationName = '',
    ): array {
        // Mutations are essentially the same as queries in GraphQL
        return $this->__invoke($mutation, $variables, $operationName);
    }

    /**
     * Introspect GraphQL schema.
     *
     * @param string $queryType Type to introspect (query, mutation, subscription, or specific type)
     *
     * @return array{
     *     data: array<string, mixed>|null,
     *     errors: array<int, array{
     *         message: string,
     *         locations: array<int, array{
     *             line: int,
     *             column: int,
     *         }>,
     *         path: array<int, string|int>,
     *         extensions: array<string, mixed>,
     *     }>,
     *     schema: array{
     *         queryType: array{
     *             name: string,
     *             fields: array<int, array{
     *                 name: string,
     *                 type: array<string, mixed>,
     *                 args: array<int, array{
     *                     name: string,
     *                     type: array<string, mixed>,
     *                 }>,
     *             }>,
     *         },
     *         mutationType: array{
     *             name: string,
     *             fields: array<int, array{
     *                 name: string,
     *                 type: array<string, mixed>,
     *                 args: array<int, array{
     *                     name: string,
     *                     type: array<string, mixed>,
     *                 }>,
     *             }>,
     *         }|null,
     *         subscriptionType: array{
     *             name: string,
     *             fields: array<int, array{
     *                 name: string,
     *                 type: array<string, mixed>,
     *                 args: array<int, array{
     *                     name: string,
     *                     type: array<string, mixed>,
     *                 }>,
     *             }>,
     *         }|null,
     *         types: array<int, array{
     *             name: string,
     *             kind: string,
     *             fields: array<int, array{
     *                 name: string,
     *                 type: array<string, mixed>,
     *             }>,
     *         }>,
     *     },
     * }
     */
    public function introspect(string $queryType = 'full'): array
    {
        $introspectionQuery = match ($queryType) {
            'query' => '
                query IntrospectionQuery {
                    __schema {
                        queryType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                    }
                }
            ',
            'mutation' => '
                query IntrospectionQuery {
                    __schema {
                        mutationType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                    }
                }
            ',
            'subscription' => '
                query IntrospectionQuery {
                    __schema {
                        subscriptionType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                    }
                }
            ',
            default => '
                query IntrospectionQuery {
                    __schema {
                        queryType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                        mutationType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                        subscriptionType {
                            name
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                                args {
                                    name
                                    type {
                                        name
                                        kind
                                    }
                                }
                            }
                        }
                        types {
                            name
                            kind
                            fields {
                                name
                                type {
                                    name
                                    kind
                                }
                            }
                        }
                    }
                }
            ',
        };

        $result = $this->__invoke($introspectionQuery);

        // Parse schema information
        $schema = [
            'queryType' => [
                'name' => '',
                'fields' => [],
            ],
            'mutationType' => null,
            'subscriptionType' => null,
            'types' => [],
        ];

        if ($result['data'] && isset($result['data']['__schema'])) {
            $schemaData = $result['data']['__schema'];

            if (isset($schemaData['queryType'])) {
                $schema['queryType'] = [
                    'name' => $schemaData['queryType']['name'],
                    'fields' => array_map(fn ($field) => [
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'args' => array_map(fn ($arg) => [
                            'name' => $arg['name'],
                            'type' => $arg['type'],
                        ], $field['args'] ?? []),
                    ], $schemaData['queryType']['fields'] ?? []),
                ];
            }

            if (isset($schemaData['mutationType'])) {
                $schema['mutationType'] = [
                    'name' => $schemaData['mutationType']['name'],
                    'fields' => array_map(fn ($field) => [
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'args' => array_map(fn ($arg) => [
                            'name' => $arg['name'],
                            'type' => $arg['type'],
                        ], $field['args'] ?? []),
                    ], $schemaData['mutationType']['fields'] ?? []),
                ];
            }

            if (isset($schemaData['subscriptionType'])) {
                $schema['subscriptionType'] = [
                    'name' => $schemaData['subscriptionType']['name'],
                    'fields' => array_map(fn ($field) => [
                        'name' => $field['name'],
                        'type' => $field['type'],
                        'args' => array_map(fn ($arg) => [
                            'name' => $arg['name'],
                            'type' => $arg['type'],
                        ], $field['args'] ?? []),
                    ], $schemaData['subscriptionType']['fields'] ?? []),
                ];
            }

            if (isset($schemaData['types'])) {
                $schema['types'] = array_map(fn ($type) => [
                    'name' => $type['name'],
                    'kind' => $type['kind'],
                    'fields' => array_map(fn ($field) => [
                        'name' => $field['name'],
                        'type' => $field['type'],
                    ], $type['fields'] ?? []),
                ], $schemaData['types']);
            }
        }

        return [
            'data' => $result['data'],
            'errors' => $result['errors'],
            'schema' => $schema,
        ];
    }

    /**
     * Validate GraphQL query.
     *
     * @param string $query GraphQL query to validate
     *
     * @return array{
     *     valid: bool,
     *     errors: array<int, array{
     *         message: string,
     *         locations: array<int, array{
     *             line: int,
     *             column: int,
     *         }>,
     *         path: array<int, string|int>,
     *     }>,
     * }
     */
    public function validate(string $query): array
    {
        // Use a simple introspection query to test if the query is valid
        $testQuery = 'query { __typename }';
        $result = $this->__invoke($query);

        return [
            'valid' => empty($result['errors']),
            'errors' => array_map(fn ($error) => [
                'message' => $error['message'],
                'locations' => $error['locations'],
                'path' => $error['path'],
            ], $result['errors']),
        ];
    }
}
