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
#[AsTool('linkedin_post_content', 'Tool that posts content to LinkedIn')]
#[AsTool('linkedin_get_profile', 'Tool that gets LinkedIn profile information', method: 'getProfile')]
#[AsTool('linkedin_search_people', 'Tool that searches for people on LinkedIn', method: 'searchPeople')]
#[AsTool('linkedin_search_companies', 'Tool that searches for companies on LinkedIn', method: 'searchCompanies')]
#[AsTool('linkedin_get_company_info', 'Tool that gets LinkedIn company information', method: 'getCompanyInfo')]
#[AsTool('linkedin_get_feed', 'Tool that gets LinkedIn feed posts', method: 'getFeed')]
final readonly class LinkedIn
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessToken,
        private string $apiVersion = 'v2',
        private array $options = [],
    ) {
    }

    /**
     * Post content to LinkedIn.
     *
     * @param string               $text       Post text content
     * @param string               $visibility Post visibility (PUBLIC, CONNECTIONS)
     * @param array<string, mixed> $media      Optional media attachments
     *
     * @return array{
     *     id: string,
     *     activity: string,
     * }|string
     */
    public function __invoke(
        #[With(maximum: 3000)]
        string $text,
        string $visibility = 'PUBLIC',
        array $media = [],
    ): array|string {
        try {
            $postData = [
                'author' => 'urn:li:person:'.$this->getPersonUrn(),
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
                ],
            ];

            if (!empty($media)) {
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
                $postData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $media;
            }

            $response = $this->httpClient->request('POST', "https://api.linkedin.com/{$this->apiVersion}/ugcPosts", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => $postData,
            ]);

            $data = $response->toArray();

            if (isset($data['serviceErrorCode'])) {
                return 'Error posting to LinkedIn: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'activity' => $data['activity'] ?? $data['id'],
            ];
        } catch (\Exception $e) {
            return 'Error posting to LinkedIn: '.$e->getMessage();
        }
    }

    /**
     * Get LinkedIn profile information.
     *
     * @param string $personId LinkedIn person ID (optional, defaults to current user)
     *
     * @return array{
     *     id: string,
     *     firstName: array{localized: array<string, string>, preferredLocale: array{country: string, language: string}},
     *     lastName: array{localized: array<string, string>, preferredLocale: array{country: string, language: string}},
     *     headline: array{localized: array<string, string>, preferredLocale: array{country: string, language: string}},
     *     summary: array{localized: array<string, string>, preferredLocale: array{country: string, language: string}},
     *     location: array{country: string, geographicArea: string, city: string, postalCode: string},
     *     industryName: array{localized: array<string, string>, preferredLocale: array{country: string, language: string}},
     *     profilePicture: array{displayImage: string},
     *     publicProfileUrl: string,
     *     numConnections: int,
     * }|string
     */
    public function getProfile(string $personId = ''): array|string
    {
        try {
            $profileId = $personId ?: $this->getPersonUrn();

            $fields = 'id,firstName,lastName,headline,summary,location,industryName,profilePicture(displayImage~:playableStreams),publicProfileUrl,numConnections';

            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/people/{$profileId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => $fields,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['serviceErrorCode'])) {
                return 'Error getting profile: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'headline' => $data['headline'],
                'summary' => $data['summary'] ?? ['localized' => [], 'preferredLocale' => ['country' => 'US', 'language' => 'en']],
                'location' => $data['location'] ?? ['country' => '', 'geographicArea' => '', 'city' => '', 'postalCode' => ''],
                'industryName' => $data['industryName'],
                'profilePicture' => $data['profilePicture'] ?? ['displayImage' => ''],
                'publicProfileUrl' => $data['publicProfileUrl'] ?? '',
                'numConnections' => $data['numConnections'] ?? 0,
            ];
        } catch (\Exception $e) {
            return 'Error getting profile: '.$e->getMessage();
        }
    }

    /**
     * Search for people on LinkedIn.
     *
     * @param string $keywords Search keywords
     * @param string $industry Industry filter
     * @param string $location Location filter
     * @param int    $count    Number of results (1-100)
     *
     * @return array<int, array{
     *     id: string,
     *     firstName: string,
     *     lastName: string,
     *     headline: string,
     *     location: array{country: string, geographicArea: string, city: string},
     *     industryName: string,
     *     publicProfileUrl: string,
     * }>
     */
    public function searchPeople(
        #[With(maximum: 500)]
        string $keywords,
        string $industry = '',
        string $location = '',
        int $count = 10,
    ): array {
        try {
            $params = [
                'keywords' => $keywords,
                'count' => min(max($count, 1), 100),
            ];

            if ($industry) {
                $params['industry'] = $industry;
            }
            if ($location) {
                $params['location'] = $location;
            }

            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/peopleSearch", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['elements'])) {
                return [];
            }

            $people = [];
            foreach ($data['elements'] as $person) {
                $people[] = [
                    'id' => $person['id'],
                    'firstName' => $person['firstName']['localized']['en_US'] ?? '',
                    'lastName' => $person['lastName']['localized']['en_US'] ?? '',
                    'headline' => $person['headline']['localized']['en_US'] ?? '',
                    'location' => [
                        'country' => $person['location']['country'] ?? '',
                        'geographicArea' => $person['location']['geographicArea'] ?? '',
                        'city' => $person['location']['city'] ?? '',
                    ],
                    'industryName' => $person['industryName']['localized']['en_US'] ?? '',
                    'publicProfileUrl' => $person['publicProfileUrl'] ?? '',
                ];
            }

            return $people;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Search for companies on LinkedIn.
     *
     * @param string $keywords Search keywords
     * @param string $industry Industry filter
     * @param string $location Location filter
     * @param int    $count    Number of results (1-100)
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     industry: string,
     *     location: array{country: string, geographicArea: string, city: string},
     *     employeeCountRange: array{start: int, end: int},
     *     website: string,
     *     logoV2: array{original: string},
     * }>
     */
    public function searchCompanies(
        #[With(maximum: 500)]
        string $keywords,
        string $industry = '',
        string $location = '',
        int $count = 10,
    ): array {
        try {
            $params = [
                'keywords' => $keywords,
                'count' => min(max($count, 1), 100),
            ];

            if ($industry) {
                $params['industry'] = $industry;
            }
            if ($location) {
                $params['location'] = $location;
            }

            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/companySearch", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['elements'])) {
                return [];
            }

            $companies = [];
            foreach ($data['elements'] as $company) {
                $companies[] = [
                    'id' => $company['id'],
                    'name' => $company['name']['localized']['en_US'] ?? '',
                    'description' => $company['description']['localized']['en_US'] ?? '',
                    'industry' => $company['industry'] ?? '',
                    'location' => [
                        'country' => $company['location']['country'] ?? '',
                        'geographicArea' => $company['location']['geographicArea'] ?? '',
                        'city' => $company['location']['city'] ?? '',
                    ],
                    'employeeCountRange' => [
                        'start' => $company['employeeCountRange']['start'] ?? 0,
                        'end' => $company['employeeCountRange']['end'] ?? 0,
                    ],
                    'website' => $company['website'] ?? '',
                    'logoV2' => [
                        'original' => $company['logoV2']['original'] ?? '',
                    ],
                ];
            }

            return $companies;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get LinkedIn company information.
     *
     * @param string $companyId LinkedIn company ID
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     industry: string,
     *     location: array{country: string, geographicArea: string, city: string},
     *     employeeCountRange: array{start: int, end: int},
     *     website: string,
     *     logoV2: array{original: string},
     *     foundedOn: array{year: int, month: int, day: int},
     *     specialities: array<int, string>,
     *     companySize: string,
     * }|string
     */
    public function getCompanyInfo(string $companyId): array|string
    {
        try {
            $fields = 'id,name,description,industry,location,employeeCountRange,website,logoV2(original),foundedOn,specialities,companySize';

            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/companies/{$companyId}", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => $fields,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['serviceErrorCode'])) {
                return 'Error getting company info: '.($data['message'] ?? 'Unknown error');
            }

            return [
                'id' => $data['id'],
                'name' => $data['name']['localized']['en_US'] ?? '',
                'description' => $data['description']['localized']['en_US'] ?? '',
                'industry' => $data['industry'] ?? '',
                'location' => [
                    'country' => $data['location']['country'] ?? '',
                    'geographicArea' => $data['location']['geographicArea'] ?? '',
                    'city' => $data['location']['city'] ?? '',
                ],
                'employeeCountRange' => [
                    'start' => $data['employeeCountRange']['start'] ?? 0,
                    'end' => $data['employeeCountRange']['end'] ?? 0,
                ],
                'website' => $data['website'] ?? '',
                'logoV2' => [
                    'original' => $data['logoV2']['original'] ?? '',
                ],
                'foundedOn' => [
                    'year' => $data['foundedOn']['year'] ?? 0,
                    'month' => $data['foundedOn']['month'] ?? 0,
                    'day' => $data['foundedOn']['day'] ?? 0,
                ],
                'specialities' => $data['specialities'] ?? [],
                'companySize' => $data['companySize'] ?? '',
            ];
        } catch (\Exception $e) {
            return 'Error getting company info: '.$e->getMessage();
        }
    }

    /**
     * Get LinkedIn feed posts.
     *
     * @param int    $count Number of posts (1-100)
     * @param string $since Get posts since this date (YYYY-MM-DD)
     *
     * @return array<int, array{
     *     id: string,
     *     author: string,
     *     text: string,
     *     created: int,
     *     type: string,
     *     visibility: string,
     *     numLikes: int,
     *     numComments: int,
     *     numShares: int,
     * }>
     */
    public function getFeed(int $count = 20, string $since = ''): array
    {
        try {
            $params = [
                'count' => min(max($count, 1), 100),
            ];

            if ($since) {
                $params['since'] = strtotime($since) * 1000; // Convert to milliseconds
            }

            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/feedUpdates", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => $params,
            ]);

            $data = $response->toArray();

            if (!isset($data['elements'])) {
                return [];
            }

            $posts = [];
            foreach ($data['elements'] as $post) {
                $posts[] = [
                    'id' => $post['id'],
                    'author' => $post['actor'] ?? '',
                    'text' => $post['updateContent']['companyStatusUpdate']['share']['comment'] ?? '',
                    'created' => $post['createdTime'] ?? 0,
                    'type' => $post['updateType'] ?? 'unknown',
                    'visibility' => $post['isCommentable'] ? 'public' : 'private',
                    'numLikes' => $post['numLikes'] ?? 0,
                    'numComments' => $post['numComments'] ?? 0,
                    'numShares' => $post['numShares'] ?? 0,
                ];
            }

            return $posts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get person URN for current user.
     */
    private function getPersonUrn(): string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.linkedin.com/{$this->apiVersion}/people/~", [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'query' => [
                    'fields' => 'id',
                ],
            ]);

            $data = $response->toArray();

            return $data['id'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
