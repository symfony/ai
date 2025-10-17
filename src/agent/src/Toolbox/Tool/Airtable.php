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
#[AsTool('airtable_get_records', 'Tool that gets Airtable records')]
#[AsTool('airtable_create_record', 'Tool that creates Airtable records', method: 'createRecord')]
#[AsTool('airtable_update_record', 'Tool that updates Airtable records', method: 'updateRecord')]
#[AsTool('airtable_delete_record', 'Tool that deletes Airtable records', method: 'deleteRecord')]
#[AsTool('airtable_search_records', 'Tool that searches Airtable records', method: 'searchRecords')]
#[AsTool('airtable_get_table_schema', 'Tool that gets Airtable table schema', method: 'getTableSchema')]
final readonly class Airtable
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
        #[\SensitiveParameter] private string $baseId,
        private array $options = [],
    ) {
    }

    /**
     * Get Airtable records.
     *
     * @param string $tableName     Airtable table name
     * @param int    $maxRecords    Maximum number of records to retrieve
     * @param string $view          View name to filter records
     * @param string $sortField     Field to sort by
     * @param string $sortDirection Sort direction (asc, desc)
     * @param string $offset        Pagination offset
     *
     * @return array{
     *     records: array<int, array{
     *         id: string,
     *         createdTime: string,
     *         fields: array<string, mixed>,
     *     }>,
     *     offset: string|null,
     * }|string
     */
    public function __invoke(
        string $tableName,
        int $maxRecords = 100,
        string $view = '',
        string $sortField = '',
        string $sortDirection = 'asc',
        string $offset = '',
    ): array|string {
        try {
            $params = [
                'maxRecords' => min(max($maxRecords, 1), 100),
            ];

            if ($view) {
                $params['view'] = $view;
            }

            if ($sortField) {
                $params['sort'] = [
                    [
                        'field' => $sortField,
                        'direction' => $sortDirection,
                    ],
                ];
            }

            if ($offset) {
                $params['offset'] = $offset;
            }

            $response = $this->httpClient->request('GET', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting records: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'records' => array_map(fn ($record) => [
                    'id' => $record['id'],
                    'createdTime' => $record['createdTime'],
                    'fields' => $record['fields'],
                ], $data['records']),
                'offset' => $data['offset'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error getting records: '.$e->getMessage();
        }
    }

    /**
     * Create an Airtable record.
     *
     * @param string               $tableName Airtable table name
     * @param array<string, mixed> $fields    Record fields
     * @param bool                 $typecast  Whether to perform automatic data conversion
     *
     * @return array{
     *     id: string,
     *     createdTime: string,
     *     fields: array<string, mixed>,
     * }|string
     */
    public function createRecord(
        string $tableName,
        array $fields,
        bool $typecast = false,
    ): array|string {
        try {
            $payload = [
                'records' => [
                    [
                        'fields' => $fields,
                    ],
                ],
            ];

            if ($typecast) {
                $payload['typecast'] = true;
            }

            $response = $this->httpClient->request('POST', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating record: '.($data['error']['message'] ?? 'Unknown error');
            }

            $record = $data['records'][0];

            return [
                'id' => $record['id'],
                'createdTime' => $record['createdTime'],
                'fields' => $record['fields'],
            ];
        } catch (\Exception $e) {
            return 'Error creating record: '.$e->getMessage();
        }
    }

    /**
     * Update an Airtable record.
     *
     * @param string               $tableName Airtable table name
     * @param string               $recordId  Record ID to update
     * @param array<string, mixed> $fields    Fields to update
     * @param bool                 $typecast  Whether to perform automatic data conversion
     *
     * @return array{
     *     id: string,
     *     createdTime: string,
     *     fields: array<string, mixed>,
     * }|string
     */
    public function updateRecord(
        string $tableName,
        string $recordId,
        array $fields,
        bool $typecast = false,
    ): array|string {
        try {
            $payload = [
                'records' => [
                    [
                        'id' => $recordId,
                        'fields' => $fields,
                    ],
                ],
            ];

            if ($typecast) {
                $payload['typecast'] = true;
            }

            $response = $this->httpClient->request('PATCH', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error updating record: '.($data['error']['message'] ?? 'Unknown error');
            }

            $record = $data['records'][0];

            return [
                'id' => $record['id'],
                'createdTime' => $record['createdTime'],
                'fields' => $record['fields'],
            ];
        } catch (\Exception $e) {
            return 'Error updating record: '.$e->getMessage();
        }
    }

    /**
     * Delete an Airtable record.
     *
     * @param string $tableName Airtable table name
     * @param string $recordId  Record ID to delete
     */
    public function deleteRecord(
        string $tableName,
        string $recordId,
    ): string {
        try {
            $response = $this->httpClient->request('DELETE', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}/{$recordId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error deleting record: '.($data['error']['message'] ?? 'Unknown error');
            }

            return "Record {$recordId} deleted successfully";
        } catch (\Exception $e) {
            return 'Error deleting record: '.$e->getMessage();
        }
    }

    /**
     * Search Airtable records.
     *
     * @param string $tableName     Airtable table name
     * @param string $filterFormula Filter formula
     * @param int    $maxRecords    Maximum number of records to retrieve
     * @param string $sortField     Field to sort by
     * @param string $sortDirection Sort direction (asc, desc)
     *
     * @return array{
     *     records: array<int, array{
     *         id: string,
     *         createdTime: string,
     *         fields: array<string, mixed>,
     *     }>,
     *     offset: string|null,
     * }|string
     */
    public function searchRecords(
        string $tableName,
        string $filterFormula,
        int $maxRecords = 100,
        string $sortField = '',
        string $sortDirection = 'asc',
    ): array|string {
        try {
            $params = [
                'filterByFormula' => $filterFormula,
                'maxRecords' => min(max($maxRecords, 1), 100),
            ];

            if ($sortField) {
                $params['sort'] = [
                    [
                        'field' => $sortField,
                        'direction' => $sortDirection,
                    ],
                ];
            }

            $response = $this->httpClient->request('GET', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
                'query' => array_merge($this->options, $params),
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error searching records: '.($data['error']['message'] ?? 'Unknown error');
            }

            return [
                'records' => array_map(fn ($record) => [
                    'id' => $record['id'],
                    'createdTime' => $record['createdTime'],
                    'fields' => $record['fields'],
                ], $data['records']),
                'offset' => $data['offset'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error searching records: '.$e->getMessage();
        }
    }

    /**
     * Get Airtable table schema.
     *
     * @param string $tableName Airtable table name
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     primaryFieldId: string,
     *     fields: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *         description: string,
     *         options: array<string, mixed>,
     *     }>,
     *     views: array<int, array{
     *         id: string,
     *         name: string,
     *         type: string,
     *     }>,
     * }|string
     */
    public function getTableSchema(string $tableName): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.airtable.com/v0/meta/bases/{$this->baseId}/tables", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting table schema: '.($data['error']['message'] ?? 'Unknown error');
            }

            // Find the table by name
            $table = null;
            foreach ($data['tables'] as $t) {
                if ($t['name'] === $tableName) {
                    $table = $t;
                    break;
                }
            }

            if (!$table) {
                return 'Table not found: '.$tableName;
            }

            return [
                'id' => $table['id'],
                'name' => $table['name'],
                'primaryFieldId' => $table['primaryFieldId'],
                'fields' => array_map(fn ($field) => [
                    'id' => $field['id'],
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'description' => $field['description'] ?? '',
                    'options' => $field['options'] ?? [],
                ], $table['fields']),
                'views' => array_map(fn ($view) => [
                    'id' => $view['id'],
                    'name' => $view['name'],
                    'type' => $view['type'],
                ], $table['views']),
            ];
        } catch (\Exception $e) {
            return 'Error getting table schema: '.$e->getMessage();
        }
    }

    /**
     * Bulk create Airtable records.
     *
     * @param string                           $tableName Airtable table name
     * @param array<int, array<string, mixed>> $records   Array of records to create
     * @param bool                             $typecast  Whether to perform automatic data conversion
     *
     * @return array<int, array{
     *     id: string,
     *     createdTime: string,
     *     fields: array<string, mixed>,
     * }>|string
     */
    public function bulkCreateRecords(
        string $tableName,
        array $records,
        bool $typecast = false,
    ): array|string {
        try {
            $payload = [
                'records' => array_map(fn ($fields) => [
                    'fields' => $fields,
                ], $records),
            ];

            if ($typecast) {
                $payload['typecast'] = true;
            }

            $response = $this->httpClient->request('POST', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error creating records: '.($data['error']['message'] ?? 'Unknown error');
            }

            return array_map(fn ($record) => [
                'id' => $record['id'],
                'createdTime' => $record['createdTime'],
                'fields' => $record['fields'],
            ], $data['records']);
        } catch (\Exception $e) {
            return 'Error creating records: '.$e->getMessage();
        }
    }

    /**
     * Bulk update Airtable records.
     *
     * @param string                                                      $tableName Airtable table name
     * @param array<int, array{id: string, fields: array<string, mixed>}> $records   Array of records to update
     * @param bool                                                        $typecast  Whether to perform automatic data conversion
     *
     * @return array<int, array{
     *     id: string,
     *     createdTime: string,
     *     fields: array<string, mixed>,
     * }>|string
     */
    public function bulkUpdateRecords(
        string $tableName,
        array $records,
        bool $typecast = false,
    ): array|string {
        try {
            $payload = [
                'records' => $records,
            ];

            if ($typecast) {
                $payload['typecast'] = true;
            }

            $response = $this->httpClient->request('PATCH', "https://api.airtable.com/v0/{$this->baseId}/{$tableName}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error updating records: '.($data['error']['message'] ?? 'Unknown error');
            }

            return array_map(fn ($record) => [
                'id' => $record['id'],
                'createdTime' => $record['createdTime'],
                'fields' => $record['fields'],
            ], $data['records']);
        } catch (\Exception $e) {
            return 'Error updating records: '.$e->getMessage();
        }
    }
}
