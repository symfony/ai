# AI Development Assistant

A comprehensive AI-powered code analysis and architecture review system for PHP developers using Symfony AI framework.

## 🚀 Quick Start

### 1. Test the Demo (No setup required)
```bash
php live-demo.php
```
This runs a standalone demo that analyzes sample code and shows you exactly what the system can detect.

### 2. Test API Connectivity (Optional - for AI features)
```bash
# Test all configured providers
php bin/console dev-assistant:test-providers

# Test specific provider  
php bin/console dev-assistant:test-providers --provider=openai

# Test specific model
php bin/console dev-assistant:test-providers --provider=openai --model=gpt-4
```

### 3. Configure AI Providers (Optional)
Set environment variables for the AI providers you want to use:

```bash
# OpenAI
export OPENAI_API_KEY="sk-your-key-here"

# Anthropic Claude
export ANTHROPIC_API_KEY="sk-ant-your-key-here"  

# Google Gemini
export GOOGLE_API_KEY="your-google-api-key"
```

## 🔍 What It Detects

### Security Issues
- **SQL Injection** - Direct query concatenation
- **Sensitive Data Logging** - Credit cards, passwords in logs
- **Hardcoded Credentials** - API keys in source code
- **Deprecated Functions** - Unsafe legacy methods

### Code Quality Issues  
- **Missing Type Declarations** - Untyped parameters/returns
- **SOLID Violations** - Single Responsibility breaches
- **PSR Compliance** - Coding standard violations
- **Best Practices** - Framework-specific recommendations

### Architecture Issues
- **High Complexity** - Deeply nested conditions
- **Design Patterns** - Missing or misused patterns
- **Performance** - Inefficient algorithms or queries
- **Maintainability** - Hard-to-maintain code structures

## 📋 Example Output

```
🤖 AI Development Assistant - Live Demo
=======================================

📊 Environment Status:
  OpenAI API Key: ✅ Configured
  Anthropic API Key: ❌ Missing
  Google Gemini Key: ❌ Missing

🔍 Starting Code Analysis...

📋 Analysis Results:
====================

📊 Summary: AI-enhanced analysis completed. Found 4 critical, 1 high, and 3 medium priority issues.

🚨 Issues Found (8):
----------------------------------------
🚨 CRITICAL: SQL Injection Vulnerability
   File: UserController.php (Line 8)
   Description: Direct SQL string concatenation detected
   💡 Fix: Use prepared statements with parameter binding

🚨 CRITICAL: Sensitive Data in Logs
   File: PaymentProcessor.php (Line 7)
   Description: Credit card or sensitive information logged
   💡 Fix: Remove sensitive data from logs or mask it
```

## 🧪 Testing

### Run Integration Tests
```bash
cd src/dev-assistant-bundle
composer install
vendor/bin/phpunit tests/Integration/
```

### Manual Testing
```bash
# Test with real AI (requires API key)
OPENAI_API_KEY=sk-... php live-demo.php

# Test fallback mode (no API key)
php live-demo.php
```

## 🔧 Configuration

### Bundle Configuration
The system automatically configures when you include the bundle:

```php
// config/bundles.php
return [
    // ... other bundles
    Symfony\AI\DevAssistantBundle\DevAssistantBundle::class => ['all' => true],
];
```

### Service Configuration
```yaml
# config/packages/dev_assistant.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    Symfony\AI\DevAssistantBundle\:
        resource: '../src/dev-assistant-bundle/src/'
        exclude: '../src/dev-assistant-bundle/src/{Model,Contract}'
```

## 🔍 API Provider Testing

The system includes comprehensive API testing to help diagnose configuration issues:

### Common Issues and Solutions

| Error Type | Description | Solution |
|------------|-------------|----------|
| **Authentication** | Invalid API key | Check your API key format and permissions |
| **Rate Limiting** | Too many requests | Wait or upgrade your plan |
| **Model Not Found** | Invalid model name | Use supported models (gpt-4, claude-3, etc.) |
| **Billing** | Insufficient credits | Add billing info or top up credits |
| **Network** | Connection issues | Check firewall and proxy settings |

### Testing Commands
```bash
# Get detailed error diagnostics
php bin/console dev-assistant:test-providers --verbose

# Test with custom timeout
php bin/console dev-assistant:test-providers --timeout=30

# Export test results to file
php bin/console dev-assistant:test-providers > api-test-results.txt
```

## 🏗️ Architecture

### Core Components

- **HybridCodeQualityAnalyzer** - AI + static analysis hybrid
- **AIProviderTester** - API connectivity validation  
- **TestProvidersCommand** - CLI diagnostic tools
- **Analysis Models** - Type-safe data structures

### Analysis Flow

1. **Request** → Analysis request with code files
2. **AI Attempt** → Try configured AI providers in order
3. **Fallback** → Use static analysis if AI fails  
4. **Response** → Structured results with issues and suggestions

## 📊 Metrics and Monitoring

The system tracks:
- Analysis success/failure rates
- AI provider response times
- Issue detection accuracy  
- User interaction patterns

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🔗 Related Projects

- [Symfony AI](https://github.com/symfony/ai) - Core AI framework
- [Symfony](https://symfony.com) - PHP framework
- [PHPStan](https://phpstan.org) - Static analysis tool

---

**Need Help?** 
- Run `php live-demo.php` to see it in action
- Run `php bin/console dev-assistant:test-providers` to diagnose API issues
- Check the integration tests for usage examples
