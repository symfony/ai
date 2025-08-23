CHANGELOG
=========

0.1
---

 * Add Symfony bundle for integrating Platform, Agent, and Store components
 * Add service configuration:
   - Agent services with configurable platforms and system prompts
   - Tool registration via `#[AsTool]` attribute and `ai.tool` tag
   - Input/Output processor registration via `ai.agent.input_processor` and `ai.agent.output_processor` tags
   - Abstract service definitions for extensibility
 * Add Symfony Profiler integration for monitoring AI interactions
 * Add security integration:
   - `#[IsGrantedTool]` attribute for tool-level authorization
   - Security voter integration for runtime permission checks
 * Add configuration options:
   - Multiple agents configuration with different platforms
   - Platform credentials (API keys, endpoints)
   - Model configurations per agent
   - Vector store configurations
 * Add dependency injection integration:
   - Autoconfiguration for tools and processors
   - Service aliases for default agent and platform
   - Factory services for creating platforms
 * Add bundle configuration with semantic validation
 * Add support for fault-tolerant tool execution
 * Add structured output configuration support
 * Add composer script for PHPStan static analysis workflow
