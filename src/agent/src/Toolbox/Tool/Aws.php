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
#[AsTool('aws_s3_list_buckets', 'Tool that lists AWS S3 buckets')]
#[AsTool('aws_ec2_describe_instances', 'Tool that describes AWS EC2 instances', method: 'describeInstances')]
#[AsTool('aws_lambda_list_functions', 'Tool that lists AWS Lambda functions', method: 'listFunctions')]
#[AsTool('aws_rds_describe_instances', 'Tool that describes AWS RDS instances', method: 'describeRdsInstances')]
#[AsTool('aws_iam_list_users', 'Tool that lists AWS IAM users', method: 'listUsers')]
#[AsTool('aws_cloudwatch_get_metrics', 'Tool that gets AWS CloudWatch metrics', method: 'getMetrics')]
final readonly class Aws
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $accessKeyId = '',
        #[\SensitiveParameter] private string $secretAccessKey = '',
        private string $region = 'us-east-1',
        private array $options = [],
    ) {
    }

    /**
     * List AWS S3 buckets.
     *
     * @return array<int, array{
     *     name: string,
     *     creationDate: string,
     * }>
     */
    public function __invoke(): array
    {
        try {
            $headers = [
                'Content-Type' => 'application/x-amz-json-1.0',
                'X-Amz-Target' => 'AmazonS3.ListBuckets',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('GET', "https://s3.{$this->region}.amazonaws.com/", [
                'headers' => $headers,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($bucket) => [
                'name' => $bucket['Name'],
                'creationDate' => $bucket['CreationDate'],
            ], $data['Buckets'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Describe AWS EC2 instances.
     *
     * @param string $instanceIds Comma-separated instance IDs
     * @param string $filters     JSON filters
     *
     * @return array<int, array{
     *     instanceId: string,
     *     instanceType: string,
     *     state: string,
     *     publicIpAddress: string,
     *     privateIpAddress: string,
     *     launchTime: string,
     *     tags: array<string, string>,
     * }>
     */
    public function describeInstances(
        string $instanceIds = '',
        string $filters = '',
    ): array {
        try {
            $params = [];

            if ($instanceIds) {
                $params['InstanceIds'] = explode(',', $instanceIds);
            }
            if ($filters) {
                $params['Filters'] = json_decode($filters, true);
            }

            $headers = [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AmazonEC2.DescribeInstances',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('POST', "https://ec2.{$this->region}.amazonaws.com/", [
                'headers' => $headers,
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            $instances = [];
            foreach ($data['Reservations'] ?? [] as $reservation) {
                foreach ($reservation['Instances'] ?? [] as $instance) {
                    $instances[] = [
                        'instanceId' => $instance['InstanceId'],
                        'instanceType' => $instance['InstanceType'],
                        'state' => $instance['State']['Name'],
                        'publicIpAddress' => $instance['PublicIpAddress'] ?? '',
                        'privateIpAddress' => $instance['PrivateIpAddress'] ?? '',
                        'launchTime' => $instance['LaunchTime'],
                        'tags' => array_column($instance['Tags'] ?? [], 'Value', 'Key'),
                    ];
                }
            }

            return $instances;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List AWS Lambda functions.
     *
     * @param string $functionVersion Function version filter
     * @param string $masterRegion    Master region filter
     *
     * @return array<int, array{
     *     functionName: string,
     *     functionArn: string,
     *     runtime: string,
     *     role: string,
     *     handler: string,
     *     codeSize: int,
     *     description: string,
     *     timeout: int,
     *     memorySize: int,
     *     lastModified: string,
     *     codeSha256: string,
     *     version: string,
     *     vpcConfig: array<string, mixed>|null,
     *     environment: array<string, mixed>|null,
     * }>
     */
    public function listFunctions(
        string $functionVersion = '',
        string $masterRegion = '',
    ): array {
        try {
            $params = [];

            if ($functionVersion) {
                $params['FunctionVersion'] = $functionVersion;
            }
            if ($masterRegion) {
                $params['MasterRegion'] = $masterRegion;
            }

            $headers = [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AWSLambda.ListFunctions',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('POST', "https://lambda.{$this->region}.amazonaws.com/", [
                'headers' => $headers,
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($function) => [
                'functionName' => $function['FunctionName'],
                'functionArn' => $function['FunctionArn'],
                'runtime' => $function['Runtime'],
                'role' => $function['Role'],
                'handler' => $function['Handler'],
                'codeSize' => $function['CodeSize'],
                'description' => $function['Description'] ?? '',
                'timeout' => $function['Timeout'],
                'memorySize' => $function['MemorySize'],
                'lastModified' => $function['LastModified'],
                'codeSha256' => $function['CodeSha256'],
                'version' => $function['Version'],
                'vpcConfig' => $function['VpcConfig'] ?? null,
                'environment' => $function['Environment'] ?? null,
            ], $data['Functions'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Describe AWS RDS instances.
     *
     * @param string $dbInstanceIdentifier DB instance identifier
     * @param string $filters              JSON filters
     *
     * @return array<int, array{
     *     dbInstanceIdentifier: string,
     *     dbInstanceClass: string,
     *     engine: string,
     *     engineVersion: string,
     *     dbInstanceStatus: string,
     *     masterUsername: string,
     *     dbName: string,
     *     allocatedStorage: int,
     *     storageType: string,
     *     availabilityZone: string,
     *     multiAz: bool,
     *     publiclyAccessible: bool,
     *     endpoint: array{
     *         address: string,
     *         port: int,
     *     }|null,
     *     tags: array<string, string>,
     * }>
     */
    public function describeRdsInstances(
        string $dbInstanceIdentifier = '',
        string $filters = '',
    ): array {
        try {
            $params = [];

            if ($dbInstanceIdentifier) {
                $params['DBInstanceIdentifier'] = $dbInstanceIdentifier;
            }
            if ($filters) {
                $params['Filters'] = json_decode($filters, true);
            }

            $headers = [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AmazonRDS.DescribeDBInstances',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('POST', "https://rds.{$this->region}.amazonaws.com/", [
                'headers' => $headers,
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($instance) => [
                'dbInstanceIdentifier' => $instance['DBInstanceIdentifier'],
                'dbInstanceClass' => $instance['DBInstanceClass'],
                'engine' => $instance['Engine'],
                'engineVersion' => $instance['EngineVersion'],
                'dbInstanceStatus' => $instance['DBInstanceStatus'],
                'masterUsername' => $instance['MasterUsername'],
                'dbName' => $instance['DBName'] ?? '',
                'allocatedStorage' => $instance['AllocatedStorage'],
                'storageType' => $instance['StorageType'],
                'availabilityZone' => $instance['AvailabilityZone'],
                'multiAz' => $instance['MultiAZ'],
                'publiclyAccessible' => $instance['PubliclyAccessible'],
                'endpoint' => $instance['Endpoint'] ? [
                    'address' => $instance['Endpoint']['Address'],
                    'port' => $instance['Endpoint']['Port'],
                ] : null,
                'tags' => array_column($instance['TagList'] ?? [], 'Value', 'Key'),
            ], $data['DBInstances'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List AWS IAM users.
     *
     * @param string $pathPrefix Path prefix filter
     * @param string $marker     Pagination marker
     * @param int    $maxItems   Maximum items to return
     *
     * @return array<int, array{
     *     userName: string,
     *     userId: string,
     *     arn: string,
     *     path: string,
     *     createDate: string,
     *     passwordLastUsed: string,
     *     tags: array<string, string>,
     * }>
     */
    public function listUsers(
        string $pathPrefix = '',
        string $marker = '',
        int $maxItems = 100,
    ): array {
        try {
            $params = [
                'MaxItems' => min(max($maxItems, 1), 1000),
            ];

            if ($pathPrefix) {
                $params['PathPrefix'] = $pathPrefix;
            }
            if ($marker) {
                $params['Marker'] = $marker;
            }

            $headers = [
                'Content-Type' => 'application/x-amz-json-1.1',
                'X-Amz-Target' => 'AmazonIAM.ListUsers',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('POST', 'https://iam.amazonaws.com/', [
                'headers' => $headers,
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return [];
            }

            return array_map(fn ($user) => [
                'userName' => $user['UserName'],
                'userId' => $user['UserId'],
                'arn' => $user['Arn'],
                'path' => $user['Path'],
                'createDate' => $user['CreateDate'],
                'passwordLastUsed' => $user['PasswordLastUsed'] ?? '',
                'tags' => array_column($user['Tags'] ?? [], 'Value', 'Key'),
            ], $data['Users'] ?? []);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get AWS CloudWatch metrics.
     *
     * @param string $namespace  Metric namespace
     * @param string $metricName Metric name
     * @param string $dimensions JSON dimensions
     * @param string $startTime  Start time (ISO 8601)
     * @param string $endTime    End time (ISO 8601)
     * @param int    $period     Period in seconds
     * @param string $statistics Comma-separated statistics (Average, Sum, Maximum, Minimum, SampleCount)
     *
     * @return array{
     *     label: string,
     *     datapoints: array<int, array{
     *         timestamp: string,
     *         value: float,
     *         unit: string,
     *     }>,
     * }|string
     */
    public function getMetrics(
        string $namespace,
        string $metricName,
        string $dimensions = '',
        string $startTime = '',
        string $endTime = '',
        int $period = 300,
        string $statistics = 'Average',
    ): array|string {
        try {
            $params = [
                'Namespace' => $namespace,
                'MetricName' => $metricName,
                'Period' => $period,
                'Statistics' => explode(',', $statistics),
            ];

            if ($dimensions) {
                $params['Dimensions'] = json_decode($dimensions, true);
            }
            if ($startTime) {
                $params['StartTime'] = $startTime;
            }
            if ($endTime) {
                $params['EndTime'] = $endTime;
            }

            $headers = [
                'Content-Type' => 'application/x-amz-json-1.0',
                'X-Amz-Target' => 'CloudWatch.GetMetricStatistics',
            ];

            if ($this->accessKeyId && $this->secretAccessKey) {
                $headers['Authorization'] = 'AWS '.$this->accessKeyId.':'.$this->secretAccessKey;
            }

            $response = $this->httpClient->request('POST', "https://monitoring.{$this->region}.amazonaws.com/", [
                'headers' => $headers,
                'json' => $params,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                return 'Error getting CloudWatch metrics: '.($data['error']['Message'] ?? 'Unknown error');
            }

            return [
                'label' => $data['Label'],
                'datapoints' => array_map(fn ($datapoint) => [
                    'timestamp' => $datapoint['Timestamp'],
                    'value' => $datapoint['Average'] ?? $datapoint['Sum'] ?? $datapoint['Maximum'] ?? $datapoint['Minimum'] ?? $datapoint['SampleCount'] ?? 0.0,
                    'unit' => $datapoint['Unit'],
                ], $data['Datapoints'] ?? []),
            ];
        } catch (\Exception $e) {
            return 'Error getting CloudWatch metrics: '.$e->getMessage();
        }
    }
}
