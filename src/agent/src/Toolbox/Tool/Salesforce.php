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
#[AsTool('salesforce_query', 'Tool that queries Salesforce data')]
#[AsTool('salesforce_create_record', 'Tool that creates Salesforce records', method: 'createRecord')]
#[AsTool('salesforce_update_record', 'Tool that updates Salesforce records', method: 'updateRecord')]
#[AsTool('salesforce_delete_record', 'Tool that deletes Salesforce records', method: 'deleteRecord')]
#[AsTool('salesforce_get_record', 'Tool that gets Salesforce records', method: 'getRecord')]
#[AsTool('salesforce_search_records', 'Tool that searches Salesforce records', method: 'searchRecords')]
final readonly class Salesforce
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        #[\SensitiveParameter] private string $instanceUrl,
        private string $apiVersion = 'v58.0',
        private array $options = [],
    ) {
    }

    /**
     * Query Salesforce data using SOQL.
     *
     * @param string $query SOQL query string
     *
     * @return array{
     *     totalSize: int,
     *     done: bool,
     *     records: array<int, array<string, mixed>>,
     *     nextRecordsUrl: string|null,
     * }|string
     */
    public function __invoke(
        #[With(maximum: 10000)]
        string $query,
    ): array|string {
        try {
            $response = $this->httpClient->request('GET', "{$this->instanceUrl}/services/data/{$this->apiVersion}/query/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'q' => $query,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error querying Salesforce: '.($data[0]['message'] ?? 'Unknown error');
            }

            return [
                'totalSize' => $data['totalSize'],
                'done' => $data['done'],
                'records' => $data['records'],
                'nextRecordsUrl' => $data['nextRecordsUrl'] ?? null,
            ];
        } catch (\Exception $e) {
            return 'Error querying Salesforce: '.$e->getMessage();
        }
    }

    /**
     * Create a Salesforce record.
     *
     * @param string               $objectType Salesforce object type (e.g., 'Account', 'Contact', 'Lead')
     * @param array<string, mixed> $fields     Record fields
     *
     * @return array{
     *     id: string,
     *     success: bool,
     *     errors: array<int, array{statusCode: string, message: string, fields: array<int, string>}>,
     * }|string
     */
    public function createRecord(
        string $objectType,
        array $fields,
    ): array|string {
        try {
            $response = $this->httpClient->request('POST', "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/{$objectType}/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $fields,
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error creating record: '.($data[0]['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'success' => $data['success'] ?? true,
                'errors' => $data['errors'] ?? [],
            ];
        } catch (\Exception $e) {
            return 'Error creating record: '.$e->getMessage();
        }
    }

    /**
     * Update a Salesforce record.
     *
     * @param string               $objectType Salesforce object type
     * @param string               $recordId   Record ID
     * @param array<string, mixed> $fields     Fields to update
     */
    public function updateRecord(
        string $objectType,
        string $recordId,
        array $fields,
    ): string {
        try {
            $response = $this->httpClient->request('PATCH', "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/{$objectType}/{$recordId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $fields,
            ]);

            if (204 === $response->getStatusCode()) {
                return "Record {$recordId} updated successfully";
            } else {
                $data = $response->toArray();

                return 'Error updating record: '.($data[0]['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error updating record: '.$e->getMessage();
        }
    }

    /**
     * Delete a Salesforce record.
     *
     * @param string $objectType Salesforce object type
     * @param string $recordId   Record ID
     */
    public function deleteRecord(
        string $objectType,
        string $recordId,
    ): string {
        try {
            $response = $this->httpClient->request('DELETE', "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/{$objectType}/{$recordId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]);

            if (204 === $response->getStatusCode()) {
                return "Record {$recordId} deleted successfully";
            } else {
                $data = $response->toArray();

                return 'Error deleting record: '.($data[0]['message'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            return 'Error deleting record: '.$e->getMessage();
        }
    }

    /**
     * Get a Salesforce record.
     *
     * @param string $objectType Salesforce object type
     * @param string $recordId   Record ID
     * @param string $fields     Comma-separated list of fields to retrieve
     *
     * @return array<string, mixed>|string
     */
    public function getRecord(
        string $objectType,
        string $recordId,
        string $fields = '',
    ): array|string {
        try {
            $url = "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/{$objectType}/{$recordId}";

            $params = [];
            if ($fields) {
                $params['fields'] = $fields;
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error getting record: '.($data[0]['message'] ?? 'Unknown error');
            }

            return $data;
        } catch (\Exception $e) {
            return 'Error getting record: '.$e->getMessage();
        }
    }

    /**
     * Search Salesforce records using SOSL.
     *
     * @param string $searchQuery SOSL search query
     *
     * @return array<int, array{
     *     type: string,
     *     id: string,
     *     attributes: array{type: string, url: string},
     * }>|string
     */
    public function searchRecords(string $searchQuery): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->instanceUrl}/services/data/{$this->apiVersion}/search/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'q' => $searchQuery,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error searching records: '.($data[0]['message'] ?? 'Unknown error');
            }

            $results = [];
            foreach ($data['searchRecords'] as $record) {
                $results[] = [
                    'type' => $record['type'],
                    'id' => $record['Id'],
                    'attributes' => [
                        'type' => $record['attributes']['type'],
                        'url' => $record['attributes']['url'],
                    ],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            return 'Error searching records: '.$e->getMessage();
        }
    }

    /**
     * Get Salesforce object metadata.
     *
     * @param string $objectType Salesforce object type
     *
     * @return array{
     *     name: string,
     *     label: string,
     *     fields: array<int, array{
     *         name: string,
     *         type: string,
     *         label: string,
     *         length: int,
     *         required: bool,
     *         unique: bool,
     *         nillable: bool,
     *         creatable: bool,
     *         updateable: bool,
     *         queryable: bool,
     *         sortable: bool,
     *         filterable: bool,
     *         calculated: bool,
     *         cascadeDelete: bool,
     *         restrictedPicklist: bool,
     *         nameField: bool,
     *         autoNumber: bool,
     *         byteLength: int,
     *         digits: int,
     *         precision: int,
     *         scale: int,
     *         picklistValues: array<int, array{active: bool, defaultValue: bool, label: string, validFor: string, value: string}>,
     *         defaultValue: mixed,
     *         inlineHelpText: string,
     *         helpText: string,
     *     }>,
     * }|string
     */
    public function getObjectMetadata(string $objectType): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/{$objectType}/describe/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error getting object metadata: '.($data[0]['message'] ?? 'Unknown error');
            }

            return [
                'name' => $data['name'],
                'label' => $data['label'],
                'fields' => array_map(fn ($field) => [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'length' => $field['length'] ?? 0,
                    'required' => false === $field['nillable'] && false === $field['defaultedOnCreate'],
                    'unique' => $field['unique'] ?? false,
                    'nillable' => $field['nillable'],
                    'createable' => $field['createable'],
                    'updateable' => $field['updateable'],
                    'queryable' => $field['queryable'],
                    'sortable' => $field['sortable'],
                    'filterable' => $field['filterable'],
                    'calculated' => $field['calculated'] ?? false,
                    'cascadeDelete' => $field['cascadeDelete'] ?? false,
                    'restrictedPicklist' => $field['restrictedPicklist'] ?? false,
                    'nameField' => $field['nameField'] ?? false,
                    'autoNumber' => $field['autoNumber'] ?? false,
                    'byteLength' => $field['byteLength'] ?? 0,
                    'digits' => $field['digits'] ?? 0,
                    'precision' => $field['precision'] ?? 0,
                    'scale' => $field['scale'] ?? 0,
                    'picklistValues' => array_map(fn ($value) => [
                        'active' => $value['active'],
                        'defaultValue' => $value['defaultValue'],
                        'label' => $value['label'],
                        'validFor' => $value['validFor'] ?? '',
                        'value' => $value['value'],
                    ], $field['picklistValues'] ?? []),
                    'defaultValue' => $field['defaultValue'] ?? null,
                    'inlineHelpText' => $field['inlineHelpText'] ?? '',
                    'helpText' => $field['helpText'] ?? '',
                ], $data['fields']),
            ];
        } catch (\Exception $e) {
            return 'Error getting object metadata: '.$e->getMessage();
        }
    }

    /**
     * Create multiple Salesforce records in bulk.
     *
     * @param string                           $objectType Salesforce object type
     * @param array<int, array<string, mixed>> $records    Array of records to create
     *
     * @return array<int, array{
     *     id: string,
     *     success: bool,
     *     errors: array<int, array{statusCode: string, message: string, fields: array<int, string>}>,
     * }>|string
     */
    public function bulkCreateRecords(
        string $objectType,
        array $records,
    ): array|string {
        try {
            $payload = [
                'allOrNone' => false,
                'records' => $records,
            ];

            $response = $this->httpClient->request('POST', "{$this->instanceUrl}/services/data/{$this->apiVersion}/composite/sobjects", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error creating records: '.($data[0]['message'] ?? 'Unknown error');
            }

            return array_map(fn ($result) => [
                'id' => $result['id'] ?? '',
                'success' => $result['success'] ?? false,
                'errors' => $result['errors'] ?? [],
            ], $data);
        } catch (\Exception $e) {
            return 'Error creating records: '.$e->getMessage();
        }
    }

    /**
     * Get Salesforce organization information.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     country: string,
     *     currencyIsoCode: string,
     *     languageLocaleKey: string,
     *     timeZoneSidKey: string,
     *     organizationType: string,
     *     trialExpirationDate: string,
     *     instanceName: string,
     *     isSandbox: bool,
     *     complianceBccEmail: string,
     *     complianceEmail: string,
     *     defaultCurrencyIsoCode: string,
     *     defaultLocaleSidKey: string,
     *     defaultTimeZoneSidKey: string,
     *     division: string,
     *     fax: string,
     *     phone: string,
     *     street: string,
     *     city: string,
     *     state: string,
     *     postalCode: string,
     *     countryCode: string,
     *     features: array<string, mixed>,
     *     settings: array<string, mixed>,
     * }|string
     */
    public function getOrganizationInfo(): array|string
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->instanceUrl}/services/data/{$this->apiVersion}/sobjects/Organization/describe/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = $response->toArray();

            if (isset($data[0]['errorCode'])) {
                return 'Error getting organization info: '.($data[0]['message'] ?? 'Unknown error');
            }

            // Get organization details by querying the Organization object
            $orgQuery = $this->httpClient->request('GET', "{$this->instanceUrl}/services/data/{$this->apiVersion}/query/", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'q' => 'SELECT Id, Name, Country, CurrencyIsoCode, LanguageLocaleKey, TimeZoneSidKey, OrganizationType, TrialExpirationDate, InstanceName, IsSandbox, ComplianceBccEmail, ComplianceEmail, DefaultCurrencyIsoCode, DefaultLocaleSidKey, DefaultTimeZoneSidKey, Division, Fax, Phone, Street, City, State, PostalCode, CountryCode FROM Organization LIMIT 1',
                ],
            ]);

            $orgData = $orgQuery->toArray();
            $org = $orgData['records'][0] ?? [];

            return [
                'id' => $org['Id'] ?? '',
                'name' => $org['Name'] ?? '',
                'country' => $org['Country'] ?? '',
                'currencyIsoCode' => $org['CurrencyIsoCode'] ?? '',
                'languageLocaleKey' => $org['LanguageLocaleKey'] ?? '',
                'timeZoneSidKey' => $org['TimeZoneSidKey'] ?? '',
                'organizationType' => $org['OrganizationType'] ?? '',
                'trialExpirationDate' => $org['TrialExpirationDate'] ?? '',
                'instanceName' => $org['InstanceName'] ?? '',
                'isSandbox' => $org['IsSandbox'] ?? false,
                'complianceBccEmail' => $org['ComplianceBccEmail'] ?? '',
                'complianceEmail' => $org['ComplianceEmail'] ?? '',
                'defaultCurrencyIsoCode' => $org['DefaultCurrencyIsoCode'] ?? '',
                'defaultLocaleSidKey' => $org['DefaultLocaleSidKey'] ?? '',
                'defaultTimeZoneSidKey' => $org['DefaultTimeZoneSidKey'] ?? '',
                'division' => $org['Division'] ?? '',
                'fax' => $org['Fax'] ?? '',
                'phone' => $org['Phone'] ?? '',
                'street' => $org['Street'] ?? '',
                'city' => $org['City'] ?? '',
                'state' => $org['State'] ?? '',
                'postalCode' => $org['PostalCode'] ?? '',
                'countryCode' => $org['CountryCode'] ?? '',
                'features' => [],
                'settings' => [],
            ];
        } catch (\Exception $e) {
            return 'Error getting organization info: '.$e->getMessage();
        }
    }
}
