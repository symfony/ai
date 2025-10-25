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
#[AsTool('zenguard_scan', 'Tool that performs security scanning using ZenGuard')]
#[AsTool('zenguard_vulnerability_check', 'Tool that checks for vulnerabilities', method: 'vulnerabilityCheck')]
#[AsTool('zenguard_malware_detection', 'Tool that detects malware', method: 'malwareDetection')]
#[AsTool('zenguard_code_analysis', 'Tool that analyzes code security', method: 'codeAnalysis')]
#[AsTool('zenguard_network_scan', 'Tool that performs network scanning', method: 'networkScan')]
#[AsTool('zenguard_threat_intelligence', 'Tool that provides threat intelligence', method: 'threatIntelligence')]
#[AsTool('zenguard_compliance_check', 'Tool that checks compliance', method: 'complianceCheck')]
#[AsTool('zenguard_security_report', 'Tool that generates security reports', method: 'securityReport')]
final readonly class ZenGuard
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.zenguard.com/v1',
        private array $options = [],
    ) {
    }

    /**
     * Perform security scanning using ZenGuard.
     *
     * @param string               $target      Target to scan (URL, IP, domain)
     * @param string               $scanType    Type of scan (web, network, code, comprehensive)
     * @param array<string, mixed> $scanOptions Additional scan options
     * @param string               $priority    Scan priority (low, medium, high, critical)
     *
     * @return array{
     *     success: bool,
     *     scan: array{
     *         target: string,
     *         scan_id: string,
     *         scan_type: string,
     *         status: string,
     *         start_time: string,
     *         end_time: string,
     *         vulnerabilities: array<int, array{
     *             id: string,
     *             title: string,
     *             severity: string,
     *             cvss_score: float,
     *             description: string,
     *             remediation: string,
     *             affected_components: array<int, string>,
     *         }>,
     *         threats: array<int, array{
     *             type: string,
     *             level: string,
     *             description: string,
     *             indicators: array<int, string>,
     *         }>,
     *         recommendations: array<int, string>,
     *         risk_score: float,
     *     },
     *     priority: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function __invoke(
        string $target,
        string $scanType = 'comprehensive',
        array $scanOptions = [],
        string $priority = 'medium',
    ): array {
        try {
            $requestData = [
                'target' => $target,
                'scan_type' => $scanType,
                'scan_options' => $scanOptions,
                'priority' => $priority,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/scan", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $scan = $data['scan'] ?? [];

            return [
                'success' => true,
                'scan' => [
                    'target' => $target,
                    'scan_id' => $scan['scan_id'] ?? '',
                    'scan_type' => $scanType,
                    'status' => $scan['status'] ?? 'pending',
                    'start_time' => $scan['start_time'] ?? '',
                    'end_time' => $scan['end_time'] ?? '',
                    'vulnerabilities' => array_map(fn ($vuln) => [
                        'id' => $vuln['id'] ?? '',
                        'title' => $vuln['title'] ?? '',
                        'severity' => $vuln['severity'] ?? 'low',
                        'cvss_score' => $vuln['cvss_score'] ?? 0.0,
                        'description' => $vuln['description'] ?? '',
                        'remediation' => $vuln['remediation'] ?? '',
                        'affected_components' => $vuln['affected_components'] ?? [],
                    ], $scan['vulnerabilities'] ?? []),
                    'threats' => array_map(fn ($threat) => [
                        'type' => $threat['type'] ?? '',
                        'level' => $threat['level'] ?? 'low',
                        'description' => $threat['description'] ?? '',
                        'indicators' => $threat['indicators'] ?? [],
                    ], $scan['threats'] ?? []),
                    'recommendations' => $scan['recommendations'] ?? [],
                    'risk_score' => $scan['risk_score'] ?? 0.0,
                ],
                'priority' => $priority,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'scan' => [
                    'target' => $target,
                    'scan_id' => '',
                    'scan_type' => $scanType,
                    'status' => 'failed',
                    'start_time' => '',
                    'end_time' => '',
                    'vulnerabilities' => [],
                    'threats' => [],
                    'recommendations' => [],
                    'risk_score' => 0.0,
                ],
                'priority' => $priority,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check for vulnerabilities.
     *
     * @param string               $target             Target to check
     * @param array<string, mixed> $vulnerabilityTypes Types of vulnerabilities to check
     * @param string               $severity           Minimum severity level
     *
     * @return array{
     *     success: bool,
     *     vulnerabilities: array<int, array{
     *         id: string,
     *         title: string,
     *         severity: string,
     *         cvss_score: float,
     *         cve_id: string,
     *         description: string,
     *         remediation: string,
     *         affected_components: array<int, string>,
     *         exploit_available: bool,
     *         patch_available: bool,
     *     }>,
     *     total_vulnerabilities: int,
     *     critical_count: int,
     *     high_count: int,
     *     medium_count: int,
     *     low_count: int,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function vulnerabilityCheck(
        string $target,
        array $vulnerabilityTypes = ['web', 'network', 'code'],
        string $severity = 'low',
    ): array {
        try {
            $requestData = [
                'target' => $target,
                'vulnerability_types' => $vulnerabilityTypes,
                'severity' => $severity,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/vulnerability/check", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $vulnerabilities = $data['vulnerabilities'] ?? [];

            $criticalCount = 0;
            $highCount = 0;
            $mediumCount = 0;
            $lowCount = 0;

            foreach ($vulnerabilities as $vuln) {
                switch ($vuln['severity'] ?? 'low') {
                    case 'critical':
                        $criticalCount++;
                        break;
                    case 'high':
                        $highCount++;
                        break;
                    case 'medium':
                        $mediumCount++;
                        break;
                    case 'low':
                    default:
                        $lowCount++;
                        break;
                }
            }

            return [
                'success' => true,
                'vulnerabilities' => array_map(fn ($vuln) => [
                    'id' => $vuln['id'] ?? '',
                    'title' => $vuln['title'] ?? '',
                    'severity' => $vuln['severity'] ?? 'low',
                    'cvss_score' => $vuln['cvss_score'] ?? 0.0,
                    'cve_id' => $vuln['cve_id'] ?? '',
                    'description' => $vuln['description'] ?? '',
                    'remediation' => $vuln['remediation'] ?? '',
                    'affected_components' => $vuln['affected_components'] ?? [],
                    'exploit_available' => $vuln['exploit_available'] ?? false,
                    'patch_available' => $vuln['patch_available'] ?? false,
                ], $vulnerabilities),
                'total_vulnerabilities' => \count($vulnerabilities),
                'critical_count' => $criticalCount,
                'high_count' => $highCount,
                'medium_count' => $mediumCount,
                'low_count' => $lowCount,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'vulnerabilities' => [],
                'total_vulnerabilities' => 0,
                'critical_count' => 0,
                'high_count' => 0,
                'medium_count' => 0,
                'low_count' => 0,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect malware.
     *
     * @param string $filePath Path to file to scan
     * @param string $fileType Type of file (binary, script, document)
     * @param bool   $deepScan Whether to perform deep scanning
     *
     * @return array{
     *     success: bool,
     *     malware_detection: array{
     *         file_path: string,
     *         file_type: string,
     *         is_malicious: bool,
     *         threat_level: string,
     *         malware_family: string,
     *         detection_engine: string,
     *         signatures: array<int, string>,
     *         behavior_analysis: array{
     *             suspicious_actions: array<int, string>,
     *             network_activity: array<int, string>,
     *             file_operations: array<int, string>,
     *         },
     *         recommendations: array<int, string>,
     *     },
     *     deep_scan: bool,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function malwareDetection(
        string $filePath,
        string $fileType = 'binary',
        bool $deepScan = false,
    ): array {
        try {
            $requestData = [
                'file_path' => $filePath,
                'file_type' => $fileType,
                'deep_scan' => $deepScan,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/malware/detect", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $detection = $data['malware_detection'] ?? [];

            return [
                'success' => true,
                'malware_detection' => [
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'is_malicious' => $detection['is_malicious'] ?? false,
                    'threat_level' => $detection['threat_level'] ?? 'clean',
                    'malware_family' => $detection['malware_family'] ?? '',
                    'detection_engine' => $detection['detection_engine'] ?? '',
                    'signatures' => $detection['signatures'] ?? [],
                    'behavior_analysis' => [
                        'suspicious_actions' => $detection['behavior_analysis']['suspicious_actions'] ?? [],
                        'network_activity' => $detection['behavior_analysis']['network_activity'] ?? [],
                        'file_operations' => $detection['behavior_analysis']['file_operations'] ?? [],
                    ],
                    'recommendations' => $detection['recommendations'] ?? [],
                ],
                'deep_scan' => $deepScan,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'malware_detection' => [
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'is_malicious' => false,
                    'threat_level' => 'clean',
                    'malware_family' => '',
                    'detection_engine' => '',
                    'signatures' => [],
                    'behavior_analysis' => [
                        'suspicious_actions' => [],
                        'network_activity' => [],
                        'file_operations' => [],
                    ],
                    'recommendations' => [],
                ],
                'deep_scan' => $deepScan,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analyze code security.
     *
     * @param string               $code          Code to analyze
     * @param string               $language      Programming language
     * @param array<string, mixed> $analysisTypes Types of analysis to perform
     *
     * @return array{
     *     success: bool,
     *     code_analysis: array{
     *         code: string,
     *         language: string,
     *         security_issues: array<int, array{
     *             type: string,
     *             severity: string,
     *             line: int,
     *             column: int,
     *             description: string,
     *             remediation: string,
     *             cwe_id: string,
     *         }>,
     *         code_smells: array<int, array{
     *             type: string,
     *             line: int,
     *             description: string,
     *             suggestion: string,
     *         }>,
     *         dependencies: array<int, array{
     *             name: string,
     *             version: string,
     *             vulnerabilities: array<int, string>,
     *             license: string,
     *         }>,
     *         metrics: array{
     *             lines_of_code: int,
     *             cyclomatic_complexity: float,
     *             security_score: float,
     *         },
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function codeAnalysis(
        string $code,
        string $language,
        array $analysisTypes = ['security', 'quality', 'dependencies'],
    ): array {
        try {
            $requestData = [
                'code' => $code,
                'language' => $language,
                'analysis_types' => $analysisTypes,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/code/analyze", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $analysis = $data['code_analysis'] ?? [];

            return [
                'success' => true,
                'code_analysis' => [
                    'code' => $code,
                    'language' => $language,
                    'security_issues' => array_map(fn ($issue) => [
                        'type' => $issue['type'] ?? '',
                        'severity' => $issue['severity'] ?? 'low',
                        'line' => $issue['line'] ?? 0,
                        'column' => $issue['column'] ?? 0,
                        'description' => $issue['description'] ?? '',
                        'remediation' => $issue['remediation'] ?? '',
                        'cwe_id' => $issue['cwe_id'] ?? '',
                    ], $analysis['security_issues'] ?? []),
                    'code_smells' => array_map(fn ($smell) => [
                        'type' => $smell['type'] ?? '',
                        'line' => $smell['line'] ?? 0,
                        'description' => $smell['description'] ?? '',
                        'suggestion' => $smell['suggestion'] ?? '',
                    ], $analysis['code_smells'] ?? []),
                    'dependencies' => array_map(fn ($dep) => [
                        'name' => $dep['name'] ?? '',
                        'version' => $dep['version'] ?? '',
                        'vulnerabilities' => $dep['vulnerabilities'] ?? [],
                        'license' => $dep['license'] ?? '',
                    ], $analysis['dependencies'] ?? []),
                    'metrics' => [
                        'lines_of_code' => $analysis['metrics']['lines_of_code'] ?? 0,
                        'cyclomatic_complexity' => $analysis['metrics']['cyclomatic_complexity'] ?? 0.0,
                        'security_score' => $analysis['metrics']['security_score'] ?? 0.0,
                    ],
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code_analysis' => [
                    'code' => $code,
                    'language' => $language,
                    'security_issues' => [],
                    'code_smells' => [],
                    'dependencies' => [],
                    'metrics' => [
                        'lines_of_code' => 0,
                        'cyclomatic_complexity' => 0.0,
                        'security_score' => 0.0,
                    ],
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform network scanning.
     *
     * @param string          $target   Network target (IP range, domain)
     * @param array<int, int> $ports    Ports to scan
     * @param string          $scanType Type of scan (tcp, udp, syn)
     * @param int             $timeout  Scan timeout in seconds
     *
     * @return array{
     *     success: bool,
     *     network_scan: array{
     *         target: string,
     *         open_ports: array<int, array{
     *             port: int,
     *             protocol: string,
     *             service: string,
     *             version: string,
     *             banner: string,
     *         }>,
     *         closed_ports: array<int, int>,
     *         filtered_ports: array<int, int>,
     *         services: array<int, array{
     *             name: string,
     *             port: int,
     *             protocol: string,
     *             state: string,
     *             version: string,
     *         }>,
     *         vulnerabilities: array<int, array{
     *             port: int,
     *             service: string,
     *             vulnerability: string,
     *             severity: string,
     *         }>,
     *         scan_duration: float,
     *         total_ports_scanned: int,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function networkScan(
        string $target,
        array $ports = [22, 23, 25, 53, 80, 110, 143, 443, 993, 995],
        string $scanType = 'tcp',
        int $timeout = 30,
    ): array {
        try {
            $requestData = [
                'target' => $target,
                'ports' => $ports,
                'scan_type' => $scanType,
                'timeout' => $timeout,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/network/scan", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $scan = $data['network_scan'] ?? [];

            return [
                'success' => true,
                'network_scan' => [
                    'target' => $target,
                    'open_ports' => array_map(fn ($port) => [
                        'port' => $port['port'] ?? 0,
                        'protocol' => $port['protocol'] ?? 'tcp',
                        'service' => $port['service'] ?? '',
                        'version' => $port['version'] ?? '',
                        'banner' => $port['banner'] ?? '',
                    ], $scan['open_ports'] ?? []),
                    'closed_ports' => $scan['closed_ports'] ?? [],
                    'filtered_ports' => $scan['filtered_ports'] ?? [],
                    'services' => array_map(fn ($service) => [
                        'name' => $service['name'] ?? '',
                        'port' => $service['port'] ?? 0,
                        'protocol' => $service['protocol'] ?? 'tcp',
                        'state' => $service['state'] ?? 'unknown',
                        'version' => $service['version'] ?? '',
                    ], $scan['services'] ?? []),
                    'vulnerabilities' => array_map(fn ($vuln) => [
                        'port' => $vuln['port'] ?? 0,
                        'service' => $vuln['service'] ?? '',
                        'vulnerability' => $vuln['vulnerability'] ?? '',
                        'severity' => $vuln['severity'] ?? 'low',
                    ], $scan['vulnerabilities'] ?? []),
                    'scan_duration' => $scan['scan_duration'] ?? 0.0,
                    'total_ports_scanned' => \count($ports),
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'network_scan' => [
                    'target' => $target,
                    'open_ports' => [],
                    'closed_ports' => [],
                    'filtered_ports' => [],
                    'services' => [],
                    'vulnerabilities' => [],
                    'scan_duration' => 0.0,
                    'total_ports_scanned' => \count($ports),
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Provide threat intelligence.
     *
     * @param string $indicator     Threat indicator (IP, domain, hash, URL)
     * @param string $indicatorType Type of indicator
     * @param string $timeframe     Timeframe for intelligence
     *
     * @return array{
     *     success: bool,
     *     threat_intelligence: array{
     *         indicator: string,
     *         indicator_type: string,
     *         reputation: string,
     *         confidence: float,
     *         threat_actors: array<int, string>,
     *         malware_families: array<int, string>,
     *         attack_vectors: array<int, string>,
     *         iocs: array<int, array{
     *             type: string,
     *             value: string,
     *             confidence: float,
     *         }>,
     *         timeline: array<int, array{
     *             timestamp: string,
     *             event: string,
     *             source: string,
     *         }>,
     *         recommendations: array<int, string>,
     *     },
     *     timeframe: string,
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function threatIntelligence(
        string $indicator,
        string $indicatorType = 'ip',
        string $timeframe = '30d',
    ): array {
        try {
            $requestData = [
                'indicator' => $indicator,
                'indicator_type' => $indicatorType,
                'timeframe' => $timeframe,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/threat/intelligence", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $intelligence = $data['threat_intelligence'] ?? [];

            return [
                'success' => true,
                'threat_intelligence' => [
                    'indicator' => $indicator,
                    'indicator_type' => $indicatorType,
                    'reputation' => $intelligence['reputation'] ?? 'unknown',
                    'confidence' => $intelligence['confidence'] ?? 0.0,
                    'threat_actors' => $intelligence['threat_actors'] ?? [],
                    'malware_families' => $intelligence['malware_families'] ?? [],
                    'attack_vectors' => $intelligence['attack_vectors'] ?? [],
                    'iocs' => array_map(fn ($ioc) => [
                        'type' => $ioc['type'] ?? '',
                        'value' => $ioc['value'] ?? '',
                        'confidence' => $ioc['confidence'] ?? 0.0,
                    ], $intelligence['iocs'] ?? []),
                    'timeline' => array_map(fn ($event) => [
                        'timestamp' => $event['timestamp'] ?? '',
                        'event' => $event['event'] ?? '',
                        'source' => $event['source'] ?? '',
                    ], $intelligence['timeline'] ?? []),
                    'recommendations' => $intelligence['recommendations'] ?? [],
                ],
                'timeframe' => $timeframe,
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'threat_intelligence' => [
                    'indicator' => $indicator,
                    'indicator_type' => $indicatorType,
                    'reputation' => 'unknown',
                    'confidence' => 0.0,
                    'threat_actors' => [],
                    'malware_families' => [],
                    'attack_vectors' => [],
                    'iocs' => [],
                    'timeline' => [],
                    'recommendations' => [],
                ],
                'timeframe' => $timeframe,
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check compliance.
     *
     * @param string               $target       Target to check compliance for
     * @param string               $standard     Compliance standard (ISO27001, SOC2, GDPR, HIPAA)
     * @param array<string, mixed> $requirements Specific requirements to check
     *
     * @return array{
     *     success: bool,
     *     compliance: array{
     *         target: string,
     *         standard: string,
     *         compliance_score: float,
     *         status: string,
     *         requirements: array<int, array{
     *             id: string,
     *             title: string,
     *             status: string,
     *             description: string,
     *             evidence: array<int, string>,
     *             recommendations: array<int, string>,
     *         }>,
     *         gaps: array<int, array{
     *             requirement_id: string,
     *             gap_description: string,
     *             severity: string,
     *             remediation: string,
     *         }>,
     *         recommendations: array<int, string>,
     *         next_assessment: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function complianceCheck(
        string $target,
        string $standard = 'ISO27001',
        array $requirements = [],
    ): array {
        try {
            $requestData = [
                'target' => $target,
                'standard' => $standard,
                'requirements' => $requirements,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/compliance/check", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $compliance = $data['compliance'] ?? [];

            return [
                'success' => true,
                'compliance' => [
                    'target' => $target,
                    'standard' => $standard,
                    'compliance_score' => $compliance['compliance_score'] ?? 0.0,
                    'status' => $compliance['status'] ?? 'non-compliant',
                    'requirements' => array_map(fn ($req) => [
                        'id' => $req['id'] ?? '',
                        'title' => $req['title'] ?? '',
                        'status' => $req['status'] ?? 'not_met',
                        'description' => $req['description'] ?? '',
                        'evidence' => $req['evidence'] ?? [],
                        'recommendations' => $req['recommendations'] ?? [],
                    ], $compliance['requirements'] ?? []),
                    'gaps' => array_map(fn ($gap) => [
                        'requirement_id' => $gap['requirement_id'] ?? '',
                        'gap_description' => $gap['gap_description'] ?? '',
                        'severity' => $gap['severity'] ?? 'low',
                        'remediation' => $gap['remediation'] ?? '',
                    ], $compliance['gaps'] ?? []),
                    'recommendations' => $compliance['recommendations'] ?? [],
                    'next_assessment' => $compliance['next_assessment'] ?? '',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'compliance' => [
                    'target' => $target,
                    'standard' => $standard,
                    'compliance_score' => 0.0,
                    'status' => 'non-compliant',
                    'requirements' => [],
                    'gaps' => [],
                    'recommendations' => [],
                    'next_assessment' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate security report.
     *
     * @param string               $scanId     Scan ID to generate report for
     * @param string               $reportType Type of report (executive, technical, detailed)
     * @param string               $format     Report format (pdf, html, json)
     * @param array<string, mixed> $sections   Sections to include in report
     *
     * @return array{
     *     success: bool,
     *     report: array{
     *         scan_id: string,
     *         report_type: string,
     *         format: string,
     *         report_url: string,
     *         summary: array{
     *             total_vulnerabilities: int,
     *             critical_count: int,
     *             high_count: int,
     *             medium_count: int,
     *             low_count: int,
     *             risk_score: float,
     *             compliance_score: float,
     *         },
     *         sections: array<int, string>,
     *         generated_at: string,
     *         expires_at: string,
     *     },
     *     processingTime: float,
     *     error: string,
     * }
     */
    public function securityReport(
        string $scanId,
        string $reportType = 'technical',
        string $format = 'pdf',
        array $sections = ['summary', 'vulnerabilities', 'recommendations'],
    ): array {
        try {
            $requestData = [
                'scan_id' => $scanId,
                'report_type' => $reportType,
                'format' => $format,
                'sections' => $sections,
            ];

            $response = $this->httpClient->request('POST', "{$this->baseUrl}/reports/generate", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ] + $this->options);

            $data = $response->toArray();
            $report = $data['report'] ?? [];

            return [
                'success' => true,
                'report' => [
                    'scan_id' => $scanId,
                    'report_type' => $reportType,
                    'format' => $format,
                    'report_url' => $report['report_url'] ?? '',
                    'summary' => [
                        'total_vulnerabilities' => $report['summary']['total_vulnerabilities'] ?? 0,
                        'critical_count' => $report['summary']['critical_count'] ?? 0,
                        'high_count' => $report['summary']['high_count'] ?? 0,
                        'medium_count' => $report['summary']['medium_count'] ?? 0,
                        'low_count' => $report['summary']['low_count'] ?? 0,
                        'risk_score' => $report['summary']['risk_score'] ?? 0.0,
                        'compliance_score' => $report['summary']['compliance_score'] ?? 0.0,
                    ],
                    'sections' => $sections,
                    'generated_at' => $report['generated_at'] ?? '',
                    'expires_at' => $report['expires_at'] ?? '',
                ],
                'processingTime' => $data['processing_time'] ?? 0.0,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'report' => [
                    'scan_id' => $scanId,
                    'report_type' => $reportType,
                    'format' => $format,
                    'report_url' => '',
                    'summary' => [
                        'total_vulnerabilities' => 0,
                        'critical_count' => 0,
                        'high_count' => 0,
                        'medium_count' => 0,
                        'low_count' => 0,
                        'risk_score' => 0.0,
                        'compliance_score' => 0.0,
                    ],
                    'sections' => $sections,
                    'generated_at' => '',
                    'expires_at' => '',
                ],
                'processingTime' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
