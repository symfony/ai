# Panther Integration Tests

This directory contains Panther-based browser integration tests for the Symfony AI Demo application.

## Overview

The Panther tests verify the demo application scenarios by simulating real browser interactions. These tests cover:

1. **Demo Scenarios**: Tests for all 8 demo scenarios (YouTube, Recipe, Wikipedia, Blog, Speech, Video, Crop, Stream)
2. **Profiler Integration**: Tests verifying the Web Profiler integration in dev environment

## Requirements

- PHP 8.4+
- Chrome/Chromium browser
- OpenAI API Key (for most tests)
- HuggingFace API Token (for some scenarios)
- ChromaDB running (for Blog scenario)

## Running Tests Locally

### 1. Install Dependencies

```bash
composer install
vendor/bin/bdi driver:chromedriver --install
```

### 2. Set Up Environment Variables

```bash
export OPENAI_API_KEY='your-openai-api-key'
export HUGGINGFACE_API_TOKEN='your-huggingface-token'  # Optional
```

### 3. Start Required Services

```bash
# Start ChromaDB (required for Blog scenario)
docker compose up -d

# Initialize ChromaDB with blog data
bin/console ai:store:index blog -vv

# Start Symfony development server
symfony server:start -d --port=9080 --no-tls
```

### 4. Run Panther Tests

```bash
# Run all Panther tests
vendor/bin/phpunit --testsuite=panther

# Run specific test file
vendor/bin/phpunit tests/DemoScenariosTest.php
vendor/bin/phpunit tests/ProfilerIntegrationTest.php

# Run a specific test method
vendor/bin/phpunit tests/DemoScenariosTest.php --filter=testBlogScenarioInteraction
```

## Running in CI

The Panther tests are designed to run in CI when the `Run demo tests` label is added to a pull request, similar to the `Run examples` workflow.

The workflow will:
1. Set up PHP 8.4
2. Install dependencies
3. Start ChromaDB and initialize with blog data
4. Install Chrome driver
5. Start Symfony server
6. Run all Panther tests
7. Upload error screenshots on failure

## Test Structure

### Base Class

- `PantherTestCase`: Base class that extends Symfony's PantherTestCase with helper methods:
  - `requiresOpenAiKey()`: Skip test if API key is not available
  - `requiresHuggingFaceToken()`: Skip test if HF token is not available
  - `waitForElement()`: Wait for an element to appear
  - `waitForText()`: Wait for specific text to appear

### Test Files

- `DemoScenariosTest.php`: Tests all 8 demo scenarios
  - Page load tests: Verify each scenario page loads correctly
  - Interaction tests: Test actual AI interactions for select scenarios
  
- `ProfilerIntegrationTest.php`: Tests Web Profiler integration
  - Toolbar visibility in dev environment
  - Implementation links on cards
  - Profiler data collection
  - AI-specific profiler panels

## Test Configuration

### phpunit.xml

The test suite is configured in `phpunit.xml`:

```xml
<testsuite name="panther">
    <file>tests/DemoScenariosTest.php</file>
    <file>tests/ProfilerIntegrationTest.php</file>
</testsuite>
```

### Environment Variables

Panther-specific configuration in `.env.test`:

```
PANTHER_APP_ENV=panther
PANTHER_ERROR_SCREENSHOT_DIR=./var/error-screenshots
OPENAI_API_KEY=sk-proj-testing1234  # Override with real key for actual tests
```

## Troubleshooting

### Chrome Driver Issues

If you encounter issues with Chrome driver:

```bash
# Reinstall Chrome driver
vendor/bin/bdi driver:chromedriver --install --force
```

### Timeout Issues

The tests have extended timeouts for AI operations (60-90 seconds). If you experience timeouts:

1. Check your internet connection
2. Verify API keys are valid and have sufficient quota
3. Check if AI services (OpenAI, HuggingFace) are operational

### Screenshot on Failure

When tests fail, screenshots are automatically saved to `var/error-screenshots/`. These can help debug UI-related issues.

### Server Not Starting

If the Symfony server fails to start:

```bash
# Check if port 9080 is available
lsof -ti:9080 | xargs kill -9

# Start server manually
symfony server:start -d --port=9080 --no-tls
```

## Writing New Tests

When adding new Panther tests:

1. Extend `PantherTestCase` instead of Symfony's `PantherTestCase`
2. Use `requiresOpenAiKey()` or `requiresHuggingFaceToken()` when API keys are needed
3. Use `waitForElement()` or `waitForText()` for dynamic content
4. Keep tests focused and independent
5. Add descriptive docblocks explaining what the test verifies

Example:

```php
/**
 * Test a new scenario interaction.
 */
public function testNewScenarioInteraction()
{
    $this->requiresOpenAiKey();
    
    $client = static::createPantherClient();
    $crawler = $client->request('GET', '/new-scenario');

    // Your test code here
    
    $this->waitForElement('.expected-element', 60);
    $this->assertSelectorExists('.expected-element');
}
```
