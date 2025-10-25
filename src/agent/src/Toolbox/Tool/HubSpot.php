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
#[AsTool('hubspot_create_contact', 'Tool that creates HubSpot contacts')]
#[AsTool('hubspot_get_contact', 'Tool that gets HubSpot contacts', method: 'getContact')]
#[AsTool('hubspot_update_contact', 'Tool that updates HubSpot contacts', method: 'updateContact')]
#[AsTool('hubspot_create_company', 'Tool that creates HubSpot companies', method: 'createCompany')]
#[AsTool('hubspot_create_deal', 'Tool that creates HubSpot deals', method: 'createDeal')]
#[AsTool('hubspot_search_objects', 'Tool that searches HubSpot objects', method: 'searchObjects')]
#[AsTool('hubspot_create_ticket', 'Tool that creates HubSpot tickets', method: 'createTicket')]
final readonly class HubSpot
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v3',
        private array $options = [],
    ) {
    }

    /**
     * Create a HubSpot contact.
     *
     * @param string               $email      Contact email address
     * @param string               $firstName  Contact first name
     * @param string               $lastName   Contact last name
     * @param string               $phone      Contact phone number
     * @param string               $company    Contact company
     * @param string               $jobTitle   Contact job title
     * @param string               $website    Contact website
     * @param string               $city       Contact city
     * @param string               $state      Contact state
     * @param string               $country    Contact country
     * @param string               $zipCode    Contact zip code
     * @param array<string, mixed> $properties Additional contact properties
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function __invoke(
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $phone = '',
        string $company = '',
        string $jobTitle = '',
        string $website = '',
        string $city = '',
        string $state = '',
        string $country = '',
        string $zipCode = '',
        array $properties = [],
    ): array|string {
        try {
            $contactProperties = array_merge($properties, [
                'email' => $email,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'phone' => $phone,
                'company' => $company,
                'jobtitle' => $jobTitle,
                'website' => $website,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'zip' => $zipCode,
            ]);

            // Remove empty values
            $contactProperties = array_filter($contactProperties, fn ($value) => '' !== $value);

            $payload = [
                'properties' => $contactProperties,
            ];

            $response = $this->httpClient->request('POST', "https://api.hubapi.com/{$this->apiVersion}/objects/contacts", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error creating contact: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error creating contact: '.$e->getMessage();
        }
    }

    /**
     * Get a HubSpot contact.
     *
     * @param string             $contactId  Contact ID
     * @param array<int, string> $properties Properties to retrieve
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function getContact(
        string $contactId,
        array $properties = [],
    ): array|string {
        try {
            $params = [];
            if (!empty($properties)) {
                $params['properties'] = implode(',', $properties);
            }

            $response = $this->httpClient->request('GET', "https://api.hubapi.com/{$this->apiVersion}/objects/contacts/{$contactId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error getting contact: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error getting contact: '.$e->getMessage();
        }
    }

    /**
     * Update a HubSpot contact.
     *
     * @param string               $contactId  Contact ID
     * @param array<string, mixed> $properties Properties to update
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function updateContact(
        string $contactId,
        array $properties,
    ): array|string {
        try {
            $payload = [
                'properties' => $properties,
            ];

            $response = $this->httpClient->request('PATCH', "https://api.hubapi.com/{$this->apiVersion}/objects/contacts/{$contactId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error updating contact: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error updating contact: '.$e->getMessage();
        }
    }

    /**
     * Create a HubSpot company.
     *
     * @param string               $name              Company name
     * @param string               $domain            Company domain
     * @param string               $industry          Company industry
     * @param string               $phone             Company phone
     * @param string               $city              Company city
     * @param string               $state             Company state
     * @param string               $country           Company country
     * @param string               $zipCode           Company zip code
     * @param int                  $numberOfEmployees Number of employees
     * @param string               $annualRevenue     Annual revenue
     * @param array<string, mixed> $properties        Additional company properties
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function createCompany(
        string $name,
        string $domain = '',
        string $industry = '',
        string $phone = '',
        string $city = '',
        string $state = '',
        string $country = '',
        string $zipCode = '',
        int $numberOfEmployees = 0,
        string $annualRevenue = '',
        array $properties = [],
    ): array|string {
        try {
            $companyProperties = array_merge($properties, [
                'name' => $name,
                'domain' => $domain,
                'industry' => $industry,
                'phone' => $phone,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'zip' => $zipCode,
                'numberofemployees' => $numberOfEmployees > 0 ? $numberOfEmployees : null,
                'annualrevenue' => $annualRevenue,
            ]);

            // Remove empty values
            $companyProperties = array_filter($companyProperties, fn ($value) => '' !== $value && null !== $value);

            $payload = [
                'properties' => $companyProperties,
            ];

            $response = $this->httpClient->request('POST', "https://api.hubapi.com/{$this->apiVersion}/objects/companies", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error creating company: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error creating company: '.$e->getMessage();
        }
    }

    /**
     * Create a HubSpot deal.
     *
     * @param string               $dealName    Deal name
     * @param string               $dealStage   Deal stage
     * @param string               $closeDate   Deal close date
     * @param string               $amount      Deal amount
     * @param string               $dealType    Deal type
     * @param string               $pipeline    Deal pipeline
     * @param string               $contactId   Associated contact ID
     * @param string               $companyId   Associated company ID
     * @param string               $description Deal description
     * @param array<string, mixed> $properties  Additional deal properties
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function createDeal(
        string $dealName,
        string $dealStage,
        string $closeDate = '',
        string $amount = '',
        string $dealType = '',
        string $pipeline = '',
        string $contactId = '',
        string $companyId = '',
        string $description = '',
        array $properties = [],
    ): array|string {
        try {
            $dealProperties = array_merge($properties, [
                'dealname' => $dealName,
                'dealstage' => $dealStage,
                'closedate' => $closeDate,
                'amount' => $amount,
                'dealtype' => $dealType,
                'pipeline' => $pipeline,
                'description' => $description,
            ]);

            // Remove empty values
            $dealProperties = array_filter($dealProperties, fn ($value) => '' !== $value);

            $payload = [
                'properties' => $dealProperties,
            ];

            // Add associations if provided
            $associations = [];
            if ($contactId) {
                $associations[] = [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 3, // Contact to Deal association
                        ],
                    ],
                ];
            }
            if ($companyId) {
                $associations[] = [
                    'to' => ['id' => $companyId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 5, // Company to Deal association
                        ],
                    ],
                ];
            }

            if (!empty($associations)) {
                $payload['associations'] = $associations;
            }

            $response = $this->httpClient->request('POST', "https://api.hubapi.com/{$this->apiVersion}/objects/deals", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error creating deal: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error creating deal: '.$e->getMessage();
        }
    }

    /**
     * Search HubSpot objects.
     *
     * @param string             $objectType Object type (contacts, companies, deals, tickets, etc.)
     * @param string             $query      Search query
     * @param array<int, string> $properties Properties to search in
     * @param int                $limit      Maximum number of results
     * @param string             $after      Pagination token
     *
     * @return array{
     *     total: int,
     *     results: array<int, array{
     *         id: string,
     *         properties: array<string, mixed>,
     *         createdAt: string,
     *         updatedAt: string,
     *         archived: bool,
     *     }>,
     *     paging: array{next: array{after: string}|null},
     * }|string
     */
    public function searchObjects(
        string $objectType,
        string $query,
        array $properties = [],
        int $limit = 100,
        string $after = '',
    ): array|string {
        try {
            $payload = [
                'query' => $query,
                'limit' => min(max($limit, 1), 100),
            ];

            if (!empty($properties)) {
                $payload['properties'] = $properties;
            }

            if ($after) {
                $payload['after'] = $after;
            }

            $response = $this->httpClient->request('POST', "https://api.hubapi.com/{$this->apiVersion}/objects/{$objectType}/search", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error searching objects: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'total' => $data['total'] ?? 0,
                'results' => array_map(fn ($result) => [
                    'id' => $result['id'],
                    'properties' => $result['properties'],
                    'createdAt' => $result['createdAt'],
                    'updatedAt' => $result['updatedAt'],
                    'archived' => $result['archived'] ?? false,
                ], $data['results']),
                'paging' => [
                    'next' => $data['paging']['next'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            return 'Error searching objects: '.$e->getMessage();
        }
    }

    /**
     * Create a HubSpot ticket.
     *
     * @param string               $subject    Ticket subject
     * @param string               $content    Ticket content
     * @param string               $priority   Ticket priority (LOW, MEDIUM, HIGH)
     * @param string               $category   Ticket category
     * @param string               $source     Ticket source
     * @param string               $contactId  Associated contact ID
     * @param string               $companyId  Associated company ID
     * @param array<string, mixed> $properties Additional ticket properties
     *
     * @return array{
     *     id: string,
     *     properties: array<string, mixed>,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     * }|string
     */
    public function createTicket(
        string $subject,
        string $content,
        string $priority = 'MEDIUM',
        string $category = '',
        string $source = '',
        string $contactId = '',
        string $companyId = '',
        array $properties = [],
    ): array|string {
        try {
            $ticketProperties = array_merge($properties, [
                'hs_ticket_priority' => $priority,
                'hs_pipeline_stage' => '1', // New ticket stage
                'subject' => $subject,
                'content' => $content,
                'hs_ticket_category' => $category,
                'hs_ticket_source' => $source,
            ]);

            // Remove empty values
            $ticketProperties = array_filter($ticketProperties, fn ($value) => '' !== $value);

            $payload = [
                'properties' => $ticketProperties,
            ];

            // Add associations if provided
            $associations = [];
            if ($contactId) {
                $associations[] = [
                    'to' => ['id' => $contactId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 16, // Contact to Ticket association
                        ],
                    ],
                ];
            }
            if ($companyId) {
                $associations[] = [
                    'to' => ['id' => $companyId],
                    'types' => [
                        [
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 18, // Company to Ticket association
                        ],
                    ],
                ];
            }

            if (!empty($associations)) {
                $payload['associations'] = $associations;
            }

            $response = $this->httpClient->request('POST', "https://api.hubapi.com/{$this->apiVersion}/objects/tickets", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = $response->toArray();

            if (isset($data['status']) && 'error' === $data['status']) {
                return 'Error creating ticket: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'properties' => $data['properties'],
                'createdAt' => $data['createdAt'],
                'updatedAt' => $data['updatedAt'],
                'archived' => $data['archived'] ?? false,
            ];
        } catch (\Exception $e) {
            return 'Error creating ticket: '.$e->getMessage();
        }
    }
}
