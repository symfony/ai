<?php

/**
 * Quick CS Fixer script for common Symfony coding standards issues
 */

$files = [
    'src/dev-assistant-bundle/src/Tool/ArchitectureAnalyzerTool.php',
    'src/dev-assistant-bundle/src/Model/AnalysisType.php',
    'src/dev-assistant-bundle/src/Model/AnalysisResult.php',
    'src/dev-assistant-bundle/src/Model/AnalysisRequest.php',
    'src/dev-assistant-bundle/src/Model/Severity.php',
    'src/dev-assistant-bundle/src/Command/TestProvidersCommand.php',
    'src/dev-assistant-bundle/tests/Integration/HybridAnalyzerIntegrationTest.php',
    'src/dev-assistant-bundle/src/Tool/CodeReviewTool.php',
    'src/dev-assistant-bundle/src/Tool/PerformanceAnalyzerTool.php',
    'src/dev-assistant-bundle/src/Analyzer/ArchitectureAnalyzer.php',
    'src/dev-assistant-bundle/src/Analyzer/PerformanceAnalyzer.php',
    'src/dev-assistant-bundle/src/Analyzer/CodeQualityAnalyzer.php',
    'src/dev-assistant-bundle/src/Service/AIProviderTester.php',
    'src/dev-assistant-bundle/src/DevAssistantBundle.php',
    'src/dev-assistant-bundle/src/Provider/StaticAnalysisProvider.php',
    'src/dev-assistant-bundle/src/Model/Issue.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Fix common CS issues
    
    // 1. Yoda conditions: $var === 'value' becomes 'value' === $var
    $content = preg_replace('/\$(\w+)\s*===\s*([A-Z_]+::[A-Z_]+)/', '$2 === $\1', $content);
    $content = preg_replace('/\$(\w+)\s*===\s*(self::[A-Z_]+)/', '$2 === $\1', $content);
    $content = preg_replace('/\$(\w+)\s*===\s*([\'"][^\'"]*)([\'"])/', '$2$3 === $\1', $content);
    
    // 2. String concatenation spacing: 'string' . $var becomes 'string'.$var
    $content = preg_replace('/([\'"])\s*\.\s*/', '$1.', $content);
    $content = preg_replace('/\s*\.\s*([\'"])/', '.$1', $content);
    $content = preg_replace('/\s*\.\s*(\$\w+)/', '.$1', $content);
    $content = preg_replace('/(\$\w+)\s*\.\s*/', '$1.', $content);
    
    // 3. Remove trailing whitespace
    $content = preg_replace('/[ \t]+$/m', '', $content);
    
    // 4. Fix blank lines before return statements (simple cases)
    $content = preg_replace('/\n(\s*)return\s/', "\n\n$1return ", $content);
    
    // 5. Fix parameter alignment issues (basic)
    $content = preg_replace('/,\s*\n\s*/', ",\n        ", $content);
    
    // 6. Fix missing docblock spacing
    $content = preg_replace('/\*\/\n(\s*)(public|private|protected)/', "*/\n$1\n$1$2", $content);
    
    // 7. Fix else/elseif formatting
    $content = preg_replace('/}\s*else\s*{/', '} else {', $content);
    $content = preg_replace('/}\s*elseif\s*\(/', '} elseif (', $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    } else {
        echo "No changes: $file\n";
    }
}

echo "CS fixes completed!\n";
