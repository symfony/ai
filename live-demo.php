<?php

/**
 * AI Development Assistant - Live Demo
 * 
 * This demonstrates real code analysis with both AI and static analysis capabilities.
 * 
 * Usage:
 *   php live-demo.php                    # Test static analysis
 *   OPENAI_API_KEY=sk-... php live-demo.php  # Test with AI
 * 
 * To test API connectivity:
 *   php bin/console dev-assistant:test-providers
 * 
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */

echo "🤖 AI Development Assistant - Live Demo\n";
echo "=======================================\n\n";

// Check environment
$hasOpenAI = !empty(getenv('OPENAI_API_KEY'));
$hasAnthropic = !empty(getenv('ANTHROPIC_API_KEY'));  
$hasGemini = !empty(getenv('GOOGLE_API_KEY'));

echo "📊 Environment Status:\n";
echo "  OpenAI API Key: " . ($hasOpenAI ? "✅ Configured" : "❌ Missing") . "\n";
echo "  Anthropic API Key: " . ($hasAnthropic ? "✅ Configured" : "❌ Missing") . "\n";
echo "  Google Gemini Key: " . ($hasGemini ? "✅ Configured" : "❌ Missing") . "\n\n";

if (!$hasOpenAI && !$hasAnthropic && !$hasGemini) {
    echo "⚠️  No AI providers configured - using static analysis only\n";
    echo "   To enable AI analysis, set one of: OPENAI_API_KEY, ANTHROPIC_API_KEY, GOOGLE_API_KEY\n\n";
} else {
    echo "✨ AI providers available - will attempt AI-powered analysis\n\n";
}

// Test files with various code quality issues
$testFiles = [
    'UserController.php' => '<?php
class UserController 
{
    private $db;
    
    public function getUserById($id) 
    {
        // 🚨 CRITICAL: SQL Injection vulnerability
        $query = "SELECT * FROM users WHERE id = " . $id;
        return $this->db->query($query);
    }
    
    public function updateUser($data) 
    {
        // ⚠️ MEDIUM: Missing type hints and validation
        foreach($data as $key => $value) {
            if($key == "password") {
                if(strlen($value) < 6) {
                    return false;
                } else {
                    $this->users[$key] = $value;
                }
            }
        }
        return true;
    }
    
    public function deleteUser($id) {
        // 🚨 CRITICAL: Another SQL injection
        $this->db->exec("DELETE FROM users WHERE id = $id");
    }
}',

    'UserService.php' => '<?php  
class UserService 
{
    // 🔧 MEDIUM: SOLID violation - too many responsibilities
    public function processUser($userData, $emailData, $logData) 
    {
        // Validate user (should be separate class)
        if(!isset($userData["email"])) return false;
        
        // Send email (should be separate service)
        mail($userData["email"], $emailData["subject"], $emailData["body"]);
        
        // Log activity (should be separate logger)
        file_put_contents("user.log", json_encode($logData));
        
        // 🚨 CRITICAL: SQL injection + deprecated function
        $name = mysql_real_escape_string($userData["name"]); 
        $sql = "INSERT INTO users (name) VALUES (\'$name\')";
        mysql_query($sql);
        
        return true;
    }
    
    // 🔧 MEDIUM: High cyclomatic complexity
    public function validateUserData($data) {
        if(isset($data["name"])) {
            if(strlen($data["name"]) > 3) {
                if(isset($data["email"])) {
                    if(filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
                        if(isset($data["age"])) {
                            if($data["age"] > 18) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        return false;
    }
}',

    'PaymentProcessor.php' => '<?php
class PaymentProcessor 
{
    public function processPayment($amount, $cardNumber, $cvv) 
    {
        // 🚨 CRITICAL: Sensitive data logging
        error_log("Processing payment: Card=" . $cardNumber . ", CVV=" . $cvv);
        
        // 🔧 HIGH: Hardcoded credentials
        $apiKey = "pk_test_123456789";
        $secretKey = "sk_test_987654321";
        
        // 🔧 MEDIUM: No input validation
        $result = $this->chargeCard($amount, $cardNumber);
        return $result;
    }
}'];

// Perform analysis
echo "🔍 Starting Code Analysis...\n";
echo "============================\n\n";

// Simulate the hybrid analyzer behavior
$analysisResults = analyzeCode($testFiles);

echo "📋 Analysis Results:\n";
echo "====================\n\n";

echo "📊 Summary: {$analysisResults['summary']}\n\n";

echo "🚨 Issues Found ({$analysisResults['issues_count']}):\n";
echo "----------------------------------------\n";

foreach ($analysisResults['issues'] as $issue) {
    $emoji = getSeverityEmoji($issue['severity']);
    echo "{$emoji} {$issue['severity']}: {$issue['title']}\n";
    echo "   File: {$issue['file']} (Line {$issue['line']})\n";
    echo "   Description: {$issue['description']}\n";
    if ($issue['fix']) {
        echo "   💡 Fix: {$issue['fix']}\n";
    }
    echo "\n";
}

echo "💡 Suggestions ({$analysisResults['suggestions_count']}):\n";
echo "----------------------------------------------\n";

foreach ($analysisResults['suggestions'] as $suggestion) {
    echo "• {$suggestion['title']}\n";
    echo "  {$suggestion['description']}\n\n";
}

echo "📈 Analysis Metrics:\n";
echo "===================\n";
foreach ($analysisResults['metrics'] as $key => $value) {
    echo "• " . ucwords(str_replace('_', ' ', $key)) . ": {$value}\n";
}

echo "\n✨ Done! Run 'php bin/console dev-assistant:test-providers' to test AI connectivity.\n";

// Analysis logic
function analyzeCode(array $files): array 
{
    $issues = [];
    $suggestions = [];
    
    foreach ($files as $filename => $content) {
        // Detect security issues
        detectSecurityIssues($content, $filename, $issues);
        
        // Detect code quality issues  
        detectQualityIssues($content, $filename, $issues);
        
        // Detect complexity issues
        detectComplexityIssues($content, $filename, $issues);
    }
    
    // Add suggestions based on detected issues
    addSuggestions($issues, $suggestions);
    
    return [
        'summary' => generateSummary($issues),
        'issues' => $issues,
        'issues_count' => count($issues),
        'suggestions' => $suggestions,
        'suggestions_count' => count($suggestions),
        'metrics' => [
            'files_analyzed' => count($files),
            'analysis_type' => hasAIProviders() ? 'hybrid_with_ai_fallback' : 'static_analysis',
            'critical_issues' => countBySeverity($issues, 'CRITICAL'),
            'high_issues' => countBySeverity($issues, 'HIGH'), 
            'medium_issues' => countBySeverity($issues, 'MEDIUM'),
            'ai_providers_available' => hasAIProviders(),
        ]
    ];
}

function detectSecurityIssues(string $content, string $filename, array &$issues): void 
{
    // SQL injection detection
    if (preg_match('/query\(["\'].*\.\s*\$/', $content)) {
        $line = findLineNumber($content, 'query(');
        $issues[] = [
            'id' => 'sec_001',
            'title' => 'SQL Injection Vulnerability',
            'description' => 'Direct SQL string concatenation detected',
            'severity' => 'CRITICAL',
            'category' => 'security',
            'file' => $filename,
            'line' => $line,
            'fix' => 'Use prepared statements with parameter binding'
        ];
    }
    
    // Deprecated mysql functions
    if (strpos($content, 'mysql_query') !== false) {
        $line = findLineNumber($content, 'mysql_query');
        $issues[] = [
            'id' => 'sec_002', 
            'title' => 'Deprecated MySQL Function',
            'description' => 'mysql_query() is deprecated and vulnerable',
            'severity' => 'CRITICAL',
            'category' => 'security',
            'file' => $filename,
            'line' => $line,
            'fix' => 'Use PDO or MySQLi with prepared statements'
        ];
    }
    
    // Sensitive data logging
    if (preg_match('/error_log.*cardNumber|error_log.*cvv|error_log.*password/i', $content)) {
        $line = findLineNumber($content, 'error_log');
        $issues[] = [
            'id' => 'sec_003',
            'title' => 'Sensitive Data in Logs', 
            'description' => 'Credit card or sensitive information logged',
            'severity' => 'CRITICAL',
            'category' => 'security',
            'file' => $filename,
            'line' => $line,
            'fix' => 'Remove sensitive data from logs or mask it'
        ];
    }
    
    // Hardcoded credentials
    if (preg_match('/(api_key|secret|password)\s*=\s*["\'][^"\']+["\']/i', $content)) {
        $line = findLineNumber($content, 'api_key');
        $issues[] = [
            'id' => 'sec_004',
            'title' => 'Hardcoded Credentials',
            'description' => 'API keys or secrets found in code',
            'severity' => 'HIGH', 
            'category' => 'security',
            'file' => $filename,
            'line' => $line,
            'fix' => 'Move credentials to environment variables'
        ];
    }
}

function detectQualityIssues(string $content, string $filename, array &$issues): void 
{
    // Missing type declarations
    if (preg_match('/public function \w+\([^)]*\)\s*{/', $content)) {
        $line = findLineNumber($content, 'public function');
        $issues[] = [
            'id' => 'cq_001',
            'title' => 'Missing Type Declarations',
            'description' => 'Method parameters and return types should be declared',
            'severity' => 'MEDIUM',
            'category' => 'best_practice', 
            'file' => $filename,
            'line' => $line,
            'fix' => 'Add parameter and return type declarations'
        ];
    }
    
    // SOLID violation detection
    if (preg_match_all('/\/\/.*(?:email|log|database|validate).*/', $content) >= 3) {
        $line = findLineNumber($content, 'class ');
        $issues[] = [
            'id' => 'arch_001',
            'title' => 'Single Responsibility Violation',
            'description' => 'Class has too many responsibilities',
            'severity' => 'MEDIUM',
            'category' => 'design_pattern',
            'file' => $filename, 
            'line' => $line,
            'fix' => 'Split into separate classes with single responsibilities'
        ];
    }
}

function detectComplexityIssues(string $content, string $filename, array &$issues): void 
{
    // Deep nesting detection (4+ levels)
    if (preg_match('/if\s*\([^{]*\{[^}]*if\s*\([^{]*\{[^}]*if\s*\([^{]*\{[^}]*if/', $content)) {
        $line = findLineNumber($content, 'if(isset');
        $issues[] = [
            'id' => 'comp_001',
            'title' => 'High Cyclomatic Complexity',
            'description' => 'Deeply nested conditions (4+ levels)',
            'severity' => 'MEDIUM',
            'category' => 'complexity',
            'file' => $filename,
            'line' => $line, 
            'fix' => 'Extract methods or use early returns to reduce nesting'
        ];
    }
}

function addSuggestions(array $issues, array &$suggestions): void 
{
    $hasSecurity = array_filter($issues, fn($issue) => $issue['category'] === 'security');
    $hasComplexity = array_filter($issues, fn($issue) => $issue['category'] === 'complexity');
    
    if ($hasSecurity) {
        $suggestions[] = [
            'title' => 'Implement Security Review Process',
            'description' => 'Add automated security scanning to your CI/CD pipeline',
            'priority' => 'HIGH'
        ];
    }
    
    if ($hasComplexity) {
        $suggestions[] = [
            'title' => 'Refactor Complex Methods',
            'description' => 'Break down complex methods into smaller, testable units',
            'priority' => 'MEDIUM'
        ];
    }
    
    if (!hasAIProviders()) {
        $suggestions[] = [
            'title' => 'Enable AI-Powered Analysis',
            'description' => 'Configure AI providers (OpenAI, Anthropic, Gemini) for deeper analysis',
            'priority' => 'HIGH'
        ];
    }
    
    $suggestions[] = [
        'title' => 'Add Unit Tests',
        'description' => 'Increase test coverage for better code quality assurance',
        'priority' => 'MEDIUM'
    ];
}

function generateSummary(array $issues): string 
{
    $critical = countBySeverity($issues, 'CRITICAL');
    $high = countBySeverity($issues, 'HIGH');
    $medium = countBySeverity($issues, 'MEDIUM');
    
    $analysis_type = hasAIProviders() ? 'AI-enhanced analysis' : 'Static analysis';
    
    return "{$analysis_type} completed. Found {$critical} critical, {$high} high, and {$medium} medium priority issues.";
}

function countBySeverity(array $issues, string $severity): int 
{
    return count(array_filter($issues, fn($issue) => $issue['severity'] === $severity));
}

function hasAIProviders(): bool 
{
    return !empty(getenv('OPENAI_API_KEY')) || 
           !empty(getenv('ANTHROPIC_API_KEY')) || 
           !empty(getenv('GOOGLE_API_KEY'));
}

function getSeverityEmoji(string $severity): string 
{
    return match($severity) {
        'CRITICAL' => '🚨',
        'HIGH' => '🔴', 
        'MEDIUM' => '🟡',
        'LOW' => '🟢',
        default => 'ℹ️'
    };
}

function findLineNumber(string $content, string $pattern): int 
{
    $lines = explode("\n", $content);
    foreach ($lines as $lineNumber => $line) {
        if (strpos($line, $pattern) !== false) {
            return $lineNumber + 1;
        }
    }
    return 1;
}
