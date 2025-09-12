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
#[AsTool('google_jobs_search', 'Tool that searches for jobs using Google Jobs API')]
#[AsTool('google_jobs_get_job_details', 'Tool that gets detailed job information', method: 'getJobDetails')]
#[AsTool('google_jobs_search_by_company', 'Tool that searches jobs by company', method: 'searchByCompany')]
#[AsTool('google_jobs_search_by_location', 'Tool that searches jobs by location', method: 'searchByLocation')]
#[AsTool('google_jobs_get_salary_info', 'Tool that gets salary information for jobs', method: 'getSalaryInfo')]
#[AsTool('google_jobs_get_company_info', 'Tool that gets company information', method: 'getCompanyInfo')]
#[AsTool('google_jobs_get_job_categories', 'Tool that gets job categories', method: 'getJobCategories')]
#[AsTool('google_jobs_get_trending_jobs', 'Tool that gets trending job searches', method: 'getTrendingJobs')]
final readonly class GoogleJobs
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://jobs.googleapis.com/v4',
        private array $options = [],
    ) {
    }

    /**
     * Search for jobs using Google Jobs API.
     *
     * @param string               $query           Job search query
     * @param string               $location        Job location
     * @param int                  $pageSize        Number of jobs per page
     * @param int                  $pageToken       Page token for pagination
     * @param array<string, mixed> $jobCategories   Job categories filter
     * @param array<string, mixed> $employmentTypes Employment types filter
     * @param array<string, mixed> $datePosted      Date posted filter
     * @param string               $sortBy          Sort results by (date, relevance)
     * @param array<string, mixed> $salaryRange     Salary range filter
     * @param bool                 $excludeSpam     Exclude spam jobs
     * @param string               $language        Language code
     *
     * @return array{
     *     success: bool,
     *     jobs: array<int, array{
     *         jobId: string,
     *         title: string,
     *         company: string,
     *         location: string,
     *         description: string,
     *         requirements: array<string, mixed>,
     *         responsibilities: array<string, mixed>,
     *         qualifications: array<string, mixed>,
     *         benefits: array<string, mixed>,
     *         employmentType: string,
     *         jobCategories: array<int, string>,
     *         postingPublishTime: string,
     *         postingExpireTime: string,
     *         applicationUrl: string,
     *         applicationEmail: string,
     *         salaryInfo: array{
     *             currency: string,
     *             minSalary: float,
     *             maxSalary: float,
     *             salaryPeriod: string,
     *         },
     *         companyInfo: array{
     *             name: string,
     *             displayName: string,
     *             website: string,
     *             description: string,
     *             size: string,
     *             industry: string,
     *             foundedYear: int,
     *             headquarters: string,
     *         },
     *         jobLocation: array{
     *             address: string,
     *             latLng: array{
     *                 latitude: float,
     *                 longitude: float,
     *             },
     *             region: string,
     *         },
     *     }>,
     *     totalJobs: int,
     *     nextPageToken: string,
     *     searchMetadata: array{
     *         searchId: string,
     *         totalResults: int,
     *         searchTime: float,
     *         query: string,
     *         location: string,
     *     },
     *     error: string,
     * }
     */
    public function __invoke(
        string $query,
        string $location = '',
        int $pageSize = 20,
        int $pageToken = 0,
        array $jobCategories = [],
        array $employmentTypes = [],
        array $datePosted = [],
        string $sortBy = 'relevance',
        array $salaryRange = [],
        bool $excludeSpam = true,
        string $language = 'en',
    ): array {
        try {
            $requestData = [
                'query' => $query,
                'pageSize' => max(1, min($pageSize, 100)),
                'pageToken' => $pageToken > 0 ? (string) $pageToken : '',
                'excludeSpamJobs' => $excludeSpam,
                'languageCode' => $language,
            ];

            if ($location) {
                $requestData['location'] = $location;
            }

            if (!empty($jobCategories)) {
                $requestData['jobCategories'] = $jobCategories;
            }

            if (!empty($employmentTypes)) {
                $requestData['employmentTypes'] = $employmentTypes;
            }

            if (!empty($datePosted)) {
                $requestData['datePosted'] = $datePosted;
            }

            if ($sortBy && 'relevance' !== $sortBy) {
                $requestData['sortBy'] = $sortBy;
            }

            if (!empty($salaryRange)) {
                $requestData['salaryRange'] = $salaryRange;
            }

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/jobs/search", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
                'json' => $requestData,
            ]);

            $data = $response->toArray();

            return [
                'success' => true,
                'jobs' => array_map(fn ($job) => [
                    'jobId' => $job['jobId'] ?? '',
                    'title' => $job['title'] ?? '',
                    'company' => $job['company'] ?? '',
                    'location' => $job['location'] ?? '',
                    'description' => $job['description'] ?? '',
                    'requirements' => $job['requirements'] ?? [],
                    'responsibilities' => $job['responsibilities'] ?? [],
                    'qualifications' => $job['qualifications'] ?? [],
                    'benefits' => $job['benefits'] ?? [],
                    'employmentType' => $job['employmentType'] ?? '',
                    'jobCategories' => $job['jobCategories'] ?? [],
                    'postingPublishTime' => $job['postingPublishTime'] ?? '',
                    'postingExpireTime' => $job['postingExpireTime'] ?? '',
                    'applicationUrl' => $job['applicationUrl'] ?? '',
                    'applicationEmail' => $job['applicationEmail'] ?? '',
                    'salaryInfo' => [
                        'currency' => $job['salaryInfo']['currency'] ?? 'USD',
                        'minSalary' => $job['salaryInfo']['minSalary'] ?? 0.0,
                        'maxSalary' => $job['salaryInfo']['maxSalary'] ?? 0.0,
                        'salaryPeriod' => $job['salaryInfo']['salaryPeriod'] ?? 'YEARLY',
                    ],
                    'companyInfo' => [
                        'name' => $job['companyInfo']['name'] ?? '',
                        'displayName' => $job['companyInfo']['displayName'] ?? '',
                        'website' => $job['companyInfo']['website'] ?? '',
                        'description' => $job['companyInfo']['description'] ?? '',
                        'size' => $job['companyInfo']['size'] ?? '',
                        'industry' => $job['companyInfo']['industry'] ?? '',
                        'foundedYear' => $job['companyInfo']['foundedYear'] ?? 0,
                        'headquarters' => $job['companyInfo']['headquarters'] ?? '',
                    ],
                    'jobLocation' => [
                        'address' => $job['jobLocation']['address'] ?? '',
                        'latLng' => [
                            'latitude' => $job['jobLocation']['latLng']['latitude'] ?? 0.0,
                            'longitude' => $job['jobLocation']['latLng']['longitude'] ?? 0.0,
                        ],
                        'region' => $job['jobLocation']['region'] ?? '',
                    ],
                ], $data['jobs'] ?? []),
                'totalJobs' => $data['totalJobs'] ?? 0,
                'nextPageToken' => $data['nextPageToken'] ?? '',
                'searchMetadata' => [
                    'searchId' => $data['searchMetadata']['searchId'] ?? '',
                    'totalResults' => $data['searchMetadata']['totalResults'] ?? 0,
                    'searchTime' => $data['searchMetadata']['searchTime'] ?? 0.0,
                    'query' => $query,
                    'location' => $location,
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'jobs' => [],
                'totalJobs' => 0,
                'nextPageToken' => '',
                'searchMetadata' => [
                    'searchId' => '',
                    'totalResults' => 0,
                    'searchTime' => 0.0,
                    'query' => $query,
                    'location' => $location,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get detailed job information.
     *
     * @param string $jobId Job ID
     *
     * @return array{
     *     success: bool,
     *     job: array{
     *         jobId: string,
     *         title: string,
     *         company: string,
     *         location: string,
     *         description: string,
     *         requirements: array<string, mixed>,
     *         responsibilities: array<string, mixed>,
     *         qualifications: array<string, mixed>,
     *         benefits: array<string, mixed>,
     *         employmentType: string,
     *         jobCategories: array<int, string>,
     *         postingPublishTime: string,
     *         postingExpireTime: string,
     *         applicationUrl: string,
     *         applicationEmail: string,
     *         salaryInfo: array{
     *             currency: string,
     *             minSalary: float,
     *             maxSalary: float,
     *             salaryPeriod: string,
     *         },
     *         companyInfo: array{
     *             name: string,
     *             displayName: string,
     *             website: string,
     *             description: string,
     *             size: string,
     *             industry: string,
     *             foundedYear: int,
     *             headquarters: string,
     *         },
     *         jobLocation: array{
     *             address: string,
     *             latLng: array{
     *                 latitude: float,
     *                 longitude: float,
     *             },
     *             region: string,
     *         },
     *         relatedJobs: array<int, string>,
     *         similarJobs: array<int, string>,
     *     },
     *     error: string,
     * }
     */
    public function getJobDetails(string $jobId): array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}/jobs/{$jobId}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => array_merge($this->options, ['key' => $this->apiKey]),
            ]);

            $data = $response->toArray();
            $job = $data['job'] ?? [];

            return [
                'success' => true,
                'job' => [
                    'jobId' => $job['jobId'] ?? $jobId,
                    'title' => $job['title'] ?? '',
                    'company' => $job['company'] ?? '',
                    'location' => $job['location'] ?? '',
                    'description' => $job['description'] ?? '',
                    'requirements' => $job['requirements'] ?? [],
                    'responsibilities' => $job['responsibilities'] ?? [],
                    'qualifications' => $job['qualifications'] ?? [],
                    'benefits' => $job['benefits'] ?? [],
                    'employmentType' => $job['employmentType'] ?? '',
                    'jobCategories' => $job['jobCategories'] ?? [],
                    'postingPublishTime' => $job['postingPublishTime'] ?? '',
                    'postingExpireTime' => $job['postingExpireTime'] ?? '',
                    'applicationUrl' => $job['applicationUrl'] ?? '',
                    'applicationEmail' => $job['applicationEmail'] ?? '',
                    'salaryInfo' => [
                        'currency' => $job['salaryInfo']['currency'] ?? 'USD',
                        'minSalary' => $job['salaryInfo']['minSalary'] ?? 0.0,
                        'maxSalary' => $job['salaryInfo']['maxSalary'] ?? 0.0,
                        'salaryPeriod' => $job['salaryInfo']['salaryPeriod'] ?? 'YEARLY',
                    ],
                    'companyInfo' => [
                        'name' => $job['companyInfo']['name'] ?? '',
                        'displayName' => $job['companyInfo']['displayName'] ?? '',
                        'website' => $job['companyInfo']['website'] ?? '',
                        'description' => $job['companyInfo']['description'] ?? '',
                        'size' => $job['companyInfo']['size'] ?? '',
                        'industry' => $job['companyInfo']['industry'] ?? '',
                        'foundedYear' => $job['companyInfo']['foundedYear'] ?? 0,
                        'headquarters' => $job['companyInfo']['headquarters'] ?? '',
                    ],
                    'jobLocation' => [
                        'address' => $job['jobLocation']['address'] ?? '',
                        'latLng' => [
                            'latitude' => $job['jobLocation']['latLng']['latitude'] ?? 0.0,
                            'longitude' => $job['jobLocation']['latLng']['longitude'] ?? 0.0,
                        ],
                        'region' => $job['jobLocation']['region'] ?? '',
                    ],
                    'relatedJobs' => $job['relatedJobs'] ?? [],
                    'similarJobs' => $job['similarJobs'] ?? [],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'job' => [
                    'jobId' => $jobId,
                    'title' => '',
                    'company' => '',
                    'location' => '',
                    'description' => '',
                    'requirements' => [],
                    'responsibilities' => [],
                    'qualifications' => [],
                    'benefits' => [],
                    'employmentType' => '',
                    'jobCategories' => [],
                    'postingPublishTime' => '',
                    'postingExpireTime' => '',
                    'applicationUrl' => '',
                    'applicationEmail' => '',
                    'salaryInfo' => ['currency' => 'USD', 'minSalary' => 0.0, 'maxSalary' => 0.0, 'salaryPeriod' => 'YEARLY'],
                    'companyInfo' => [
                        'name' => '', 'displayName' => '', 'website' => '', 'description' => '',
                        'size' => '', 'industry' => '', 'foundedYear' => 0, 'headquarters' => '',
                    ],
                    'jobLocation' => [
                        'address' => '', 'latLng' => ['latitude' => 0.0, 'longitude' => 0.0], 'region' => '',
                    ],
                    'relatedJobs' => [],
                    'similarJobs' => [],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search jobs by company.
     *
     * @param string $companyName Company name
     * @param string $location    Job location
     * @param int    $pageSize    Number of jobs per page
     * @param string $jobTitle    Specific job title filter
     *
     * @return array{
     *     success: bool,
     *     jobs: array<int, array{
     *         jobId: string,
     *         title: string,
     *         company: string,
     *         location: string,
     *         description: string,
     *         employmentType: string,
     *         postingPublishTime: string,
     *         applicationUrl: string,
     *     }>,
     *     company: string,
     *     totalJobs: int,
     *     error: string,
     * }
     */
    public function searchByCompany(
        string $companyName,
        string $location = '',
        int $pageSize = 20,
        string $jobTitle = '',
    ): array {
        try {
            $query = $jobTitle ? "{$jobTitle} at {$companyName}" : "jobs at {$companyName}";

            $result = $this->__invoke($query, $location, $pageSize);

            return [
                'success' => $result['success'],
                'jobs' => array_map(fn ($job) => [
                    'jobId' => $job['jobId'],
                    'title' => $job['title'],
                    'company' => $job['company'],
                    'location' => $job['location'],
                    'description' => $job['description'],
                    'employmentType' => $job['employmentType'],
                    'postingPublishTime' => $job['postingPublishTime'],
                    'applicationUrl' => $job['applicationUrl'],
                ], $result['jobs']),
                'company' => $companyName,
                'totalJobs' => $result['totalJobs'],
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'jobs' => [],
                'company' => $companyName,
                'totalJobs' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search jobs by location.
     *
     * @param string $location Job location
     * @param string $jobTitle Job title filter
     * @param int    $pageSize Number of jobs per page
     * @param int    $radius   Search radius in miles
     *
     * @return array{
     *     success: bool,
     *     jobs: array<int, array{
     *         jobId: string,
     *         title: string,
     *         company: string,
     *         location: string,
     *         description: string,
     *         employmentType: string,
     *         postingPublishTime: string,
     *         applicationUrl: string,
     *         distance: float,
     *     }>,
     *     location: string,
     *     totalJobs: int,
     *     error: string,
     * }
     */
    public function searchByLocation(
        string $location,
        string $jobTitle = '',
        int $pageSize = 20,
        int $radius = 25,
    ): array {
        try {
            $query = $jobTitle ?: 'jobs';

            $result = $this->__invoke($query, $location, $pageSize);

            return [
                'success' => $result['success'],
                'jobs' => array_map(fn ($job) => [
                    'jobId' => $job['jobId'],
                    'title' => $job['title'],
                    'company' => $job['company'],
                    'location' => $job['location'],
                    'description' => $job['description'],
                    'employmentType' => $job['employmentType'],
                    'postingPublishTime' => $job['postingPublishTime'],
                    'applicationUrl' => $job['applicationUrl'],
                    'distance' => 0.0, // Would need geocoding to calculate actual distance
                ], $result['jobs']),
                'location' => $location,
                'totalJobs' => $result['totalJobs'],
                'error' => $result['error'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'jobs' => [],
                'location' => $location,
                'totalJobs' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get salary information for jobs.
     *
     * @param string $jobTitle   Job title
     * @param string $location   Job location
     * @param string $experience Experience level
     *
     * @return array{
     *     success: bool,
     *     salaryData: array{
     *         jobTitle: string,
     *         location: string,
     *         experience: string,
     *         salaryRanges: array<int, array{
     *             percentile: string,
     *             minSalary: float,
     *             maxSalary: float,
     *             currency: string,
     *         }>,
     *         averageSalary: float,
     *         medianSalary: float,
     *         salaryPeriod: string,
     *         lastUpdated: string,
     *         dataSource: string,
     *     },
     *     error: string,
     * }
     */
    public function getSalaryInfo(
        string $jobTitle,
        string $location = '',
        string $experience = '',
    ): array {
        try {
            // This would typically use Google's salary API or similar service
            // For now, we'll search for jobs and extract salary information
            $result = $this->__invoke($jobTitle, $location, 50);

            $salaries = [];
            foreach ($result['jobs'] as $job) {
                if (!empty($job['salaryInfo']['minSalary']) && !empty($job['salaryInfo']['maxSalary'])) {
                    $salaries[] = [
                        'minSalary' => $job['salaryInfo']['minSalary'],
                        'maxSalary' => $job['salaryInfo']['maxSalary'],
                        'currency' => $job['salaryInfo']['currency'],
                    ];
                }
            }

            $averageSalary = 0.0;
            $medianSalary = 0.0;

            if (!empty($salaries)) {
                $allSalaries = array_merge(
                    array_column($salaries, 'minSalary'),
                    array_column($salaries, 'maxSalary')
                );
                $averageSalary = array_sum($allSalaries) / \count($allSalaries);
                sort($allSalaries);
                $medianSalary = $allSalaries[\count($allSalaries) / 2] ?? 0.0;
            }

            return [
                'success' => true,
                'salaryData' => [
                    'jobTitle' => $jobTitle,
                    'location' => $location,
                    'experience' => $experience,
                    'salaryRanges' => [
                        [
                            'percentile' => '25th',
                            'minSalary' => $salaries[0]['minSalary'] ?? 0.0,
                            'maxSalary' => $salaries[0]['maxSalary'] ?? 0.0,
                            'currency' => $salaries[0]['currency'] ?? 'USD',
                        ],
                        [
                            'percentile' => '50th',
                            'minSalary' => $medianSalary,
                            'maxSalary' => $medianSalary,
                            'currency' => 'USD',
                        ],
                        [
                            'percentile' => '75th',
                            'minSalary' => $salaries[\count($salaries) - 1]['minSalary'] ?? 0.0,
                            'maxSalary' => $salaries[\count($salaries) - 1]['maxSalary'] ?? 0.0,
                            'currency' => $salaries[\count($salaries) - 1]['currency'] ?? 'USD',
                        ],
                    ],
                    'averageSalary' => $averageSalary,
                    'medianSalary' => $medianSalary,
                    'salaryPeriod' => 'YEARLY',
                    'lastUpdated' => date('c'),
                    'dataSource' => 'Google Jobs API',
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'salaryData' => [
                    'jobTitle' => $jobTitle,
                    'location' => $location,
                    'experience' => $experience,
                    'salaryRanges' => [],
                    'averageSalary' => 0.0,
                    'medianSalary' => 0.0,
                    'salaryPeriod' => 'YEARLY',
                    'lastUpdated' => '',
                    'dataSource' => 'Google Jobs API',
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get company information.
     *
     * @param string $companyName Company name
     *
     * @return array{
     *     success: bool,
     *     company: array{
     *         name: string,
     *         displayName: string,
     *         website: string,
     *         description: string,
     *         size: string,
     *         industry: string,
     *         foundedYear: int,
     *         headquarters: string,
     *         employees: int,
     *         revenue: string,
     *         socialMedia: array<string, string>,
     *         benefits: array<int, string>,
     *         culture: array<string, mixed>,
     *         ratings: array{
     *             overall: float,
     *             workLifeBalance: float,
     *             compensation: float,
     *             careerOpportunities: float,
     *             management: float,
     *             culture: float,
     *         },
     *         recentNews: array<int, array{
     *             title: string,
     *             url: string,
     *             publishedAt: string,
     *         }>,
     *     },
     *     error: string,
     * }
     */
    public function getCompanyInfo(string $companyName): array
    {
        try {
            // This would typically use Google's company API or similar service
            // For now, we'll return basic structure
            return [
                'success' => true,
                'company' => [
                    'name' => $companyName,
                    'displayName' => $companyName,
                    'website' => '',
                    'description' => '',
                    'size' => '',
                    'industry' => '',
                    'foundedYear' => 0,
                    'headquarters' => '',
                    'employees' => 0,
                    'revenue' => '',
                    'socialMedia' => [],
                    'benefits' => [],
                    'culture' => [],
                    'ratings' => [
                        'overall' => 0.0,
                        'workLifeBalance' => 0.0,
                        'compensation' => 0.0,
                        'careerOpportunities' => 0.0,
                        'management' => 0.0,
                        'culture' => 0.0,
                    ],
                    'recentNews' => [],
                ],
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'company' => [
                    'name' => $companyName,
                    'displayName' => $companyName,
                    'website' => '',
                    'description' => '',
                    'size' => '',
                    'industry' => '',
                    'foundedYear' => 0,
                    'headquarters' => '',
                    'employees' => 0,
                    'revenue' => '',
                    'socialMedia' => [],
                    'benefits' => [],
                    'culture' => [],
                    'ratings' => [
                        'overall' => 0.0,
                        'workLifeBalance' => 0.0,
                        'compensation' => 0.0,
                        'careerOpportunities' => 0.0,
                        'management' => 0.0,
                        'culture' => 0.0,
                    ],
                    'recentNews' => [],
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get job categories.
     *
     * @param string $language Language code
     *
     * @return array{
     *     success: bool,
     *     categories: array<int, array{
     *         id: string,
     *         name: string,
     *         description: string,
     *         subcategories: array<int, array{
     *             id: string,
     *             name: string,
     *             description: string,
     *         }>,
     *     }>,
     *     totalCategories: int,
     *     language: string,
     *     error: string,
     * }
     */
    public function getJobCategories(string $language = 'en'): array
    {
        try {
            // Standard job categories
            $categories = [
                [
                    'id' => 'technology',
                    'name' => 'Technology',
                    'description' => 'Software development, IT, engineering, and tech-related positions',
                    'subcategories' => [
                        ['id' => 'software_engineer', 'name' => 'Software Engineer', 'description' => ''],
                        ['id' => 'data_scientist', 'name' => 'Data Scientist', 'description' => ''],
                        ['id' => 'product_manager', 'name' => 'Product Manager', 'description' => ''],
                        ['id' => 'devops', 'name' => 'DevOps Engineer', 'description' => ''],
                        ['id' => 'cybersecurity', 'name' => 'Cybersecurity', 'description' => ''],
                    ],
                ],
                [
                    'id' => 'healthcare',
                    'name' => 'Healthcare',
                    'description' => 'Medical, nursing, and healthcare-related positions',
                    'subcategories' => [
                        ['id' => 'physician', 'name' => 'Physician', 'description' => ''],
                        ['id' => 'nurse', 'name' => 'Nurse', 'description' => ''],
                        ['id' => 'pharmacist', 'name' => 'Pharmacist', 'description' => ''],
                        ['id' => 'therapist', 'name' => 'Therapist', 'description' => ''],
                    ],
                ],
                [
                    'id' => 'finance',
                    'name' => 'Finance',
                    'description' => 'Banking, accounting, and financial services',
                    'subcategories' => [
                        ['id' => 'accountant', 'name' => 'Accountant', 'description' => ''],
                        ['id' => 'financial_analyst', 'name' => 'Financial Analyst', 'description' => ''],
                        ['id' => 'investment_banker', 'name' => 'Investment Banker', 'description' => ''],
                        ['id' => 'financial_advisor', 'name' => 'Financial Advisor', 'description' => ''],
                    ],
                ],
                [
                    'id' => 'education',
                    'name' => 'Education',
                    'description' => 'Teaching, administration, and educational services',
                    'subcategories' => [
                        ['id' => 'teacher', 'name' => 'Teacher', 'description' => ''],
                        ['id' => 'professor', 'name' => 'Professor', 'description' => ''],
                        ['id' => 'principal', 'name' => 'Principal', 'description' => ''],
                        ['id' => 'counselor', 'name' => 'Counselor', 'description' => ''],
                    ],
                ],
                [
                    'id' => 'marketing',
                    'name' => 'Marketing',
                    'description' => 'Digital marketing, advertising, and communications',
                    'subcategories' => [
                        ['id' => 'digital_marketing', 'name' => 'Digital Marketing', 'description' => ''],
                        ['id' => 'content_marketing', 'name' => 'Content Marketing', 'description' => ''],
                        ['id' => 'social_media', 'name' => 'Social Media', 'description' => ''],
                        ['id' => 'brand_manager', 'name' => 'Brand Manager', 'description' => ''],
                    ],
                ],
            ];

            return [
                'success' => true,
                'categories' => $categories,
                'totalCategories' => \count($categories),
                'language' => $language,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'categories' => [],
                'totalCategories' => 0,
                'language' => $language,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get trending job searches.
     *
     * @param string $location  Location filter
     * @param string $timeframe Timeframe (day, week, month)
     * @param int    $limit     Number of trending searches
     *
     * @return array{
     *     success: bool,
     *     trendingJobs: array<int, array{
     *         query: string,
     *         location: string,
     *         searchCount: int,
     *         growthRate: float,
     *         relatedSearches: array<int, string>,
     *         topCompanies: array<int, string>,
     *         averageSalary: float,
     *         employmentType: string,
     *     }>,
     *     timeframe: string,
     *     location: string,
     *     totalTrending: int,
     *     error: string,
     * }
     */
    public function getTrendingJobs(
        string $location = '',
        string $timeframe = 'week',
        int $limit = 20,
    ): array {
        try {
            // This would typically use Google's trending searches API
            // For now, we'll return sample trending job data
            $trendingJobs = [
                [
                    'query' => 'remote software engineer',
                    'location' => $location ?: 'United States',
                    'searchCount' => 15000,
                    'growthRate' => 25.5,
                    'relatedSearches' => ['python developer', 'javascript developer', 'full stack developer'],
                    'topCompanies' => ['Google', 'Microsoft', 'Amazon', 'Meta'],
                    'averageSalary' => 120000.0,
                    'employmentType' => 'full_time',
                ],
                [
                    'query' => 'data scientist',
                    'location' => $location ?: 'United States',
                    'searchCount' => 12000,
                    'growthRate' => 18.2,
                    'relatedSearches' => ['machine learning engineer', 'data analyst', 'AI researcher'],
                    'topCompanies' => ['Tesla', 'Netflix', 'Uber', 'Airbnb'],
                    'averageSalary' => 110000.0,
                    'employmentType' => 'full_time',
                ],
                [
                    'query' => 'nurse',
                    'location' => $location ?: 'United States',
                    'searchCount' => 8000,
                    'growthRate' => 12.8,
                    'relatedSearches' => ['registered nurse', 'nurse practitioner', 'travel nurse'],
                    'topCompanies' => ['Mayo Clinic', 'Cleveland Clinic', 'Johns Hopkins', 'UCLA Health'],
                    'averageSalary' => 75000.0,
                    'employmentType' => 'full_time',
                ],
            ];

            return [
                'success' => true,
                'trendingJobs' => \array_slice($trendingJobs, 0, $limit),
                'timeframe' => $timeframe,
                'location' => $location ?: 'United States',
                'totalTrending' => \count($trendingJobs),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'trendingJobs' => [],
                'timeframe' => $timeframe,
                'location' => $location ?: 'United States',
                'totalTrending' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
