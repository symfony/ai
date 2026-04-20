---
name: docs-check
description: Check Documentation for Outdated Code References
---

# Check Documentation for Outdated Code References

Scan RST and Markdown documentation files in this repository for code references that no longer match the actual
codebase. Report findings grouped by file with actionable details.

## What to Check

### 1. PHP Class and Interface References
- RST `:class:` roles (e.g., `:class:\`Symfony\\AI\\Platform\\Platform\``)
- RST `:method:` roles (e.g., `:method:\`Symfony\\AI\\Platform\\PlatformInterface::invoke\``)
- Fully qualified class names in code blocks and inline code
- `use` statements in PHP code blocks

For each reference, verify the class/interface/method actually exists at that namespace path by searching `src/`.

### 2. Composer Package Names
- `composer require` commands in code blocks
- Verify the package exists in a `composer.json` within the repository

### 3. Configuration Keys
- YAML configuration examples (especially `config/packages/ai.yaml` patterns)
- Cross-reference with the bundle's actual configuration classes (look for `Configuration.php` or `*Extension.php` files)

### 4. PHP Attributes
- `#[AsTool]`, `#[AsAgent]`, `#[AsMemory]`, and similar attribute references
- Verify the attribute class exists at the referenced namespace

### 5. Method and Constructor Signatures
- Code examples showing constructor calls or method invocations
- Check that parameter names and order roughly match the current signatures

### 6. Example File References
- Links to example files (e.g., `examples/rag/in-memory.php`)
- Verify the referenced file paths exist

## Where to Look

Scan these locations for documentation files:
- `docs/**/*.rst` - Main Sphinx documentation
- `README.md` - Root repository README
- `CONTRIBUTING.md` - Contributing guide
- `src/*/README.md` - Component READMEs (platform, agent, store, chat, mate, ai-bundle, mcp-bundle)
- `src/*/src/Bridge/*/README.md` - Bridge READMEs (e.g. `src/platform/src/Bridge/HuggingFace/README.md`, `src/store/src/Bridge/Qdrant/README.md`, `src/agent/src/Bridge/Tavily/README.md`, `src/chat/src/Bridge/Doctrine/README.md`)
- `src/mate/INSTRUCTIONS.md` and `src/mate/src/Bridge/*/INSTRUCTIONS.md` - Mate extension instructions
- `examples/README.md` and `examples/*/README.md` - Example-suite READMEs
- `demo/README.md` - Demo application README

Explicitly exclude the following from scans:
- All `CHANGELOG.md` and `UPGRADE.md` files — these intentionally document historical state (old class names, removed APIs, before/after diffs) so references that no longer exist in the code are expected, not bugs
- Anything under `vendor/`, `node_modules/`, `.git/`, `.symfony-docs/`, or `**/cache/`
- `fixtures/**` and `**/tests/**/Fixtures/**` (test data, not documentation)
- `.github/**` templates and `.claude/**` (harness configuration, not project docs)
- `CLAUDE.md`, `AGENTS.md`, `AGENT_INSTRUCTIONS.md` (assistant instructions, not user-facing docs)
- `ai.symfony.com/public/**` (generated artifacts)

## How to Work

1. Use the Explore agent to gather all RST and MD documentation files
2. Process files in parallel where possible using multiple agents
3. For each file, extract code references and verify them against the codebase using Grep and Glob
4. Collect all findings into a single report

## Output Format

Group findings by documentation file. For each issue found, report:
- **File and line**: path and approximate line number
- **Reference**: the outdated reference as written in the docs
- **Issue**: what is wrong (class not found, method renamed, file missing, etc.)
- **Suggestion**: if possible, suggest the correct current reference

If no issues are found in a file, skip it from the report.

At the end, provide a summary count: `X issues found across Y files (Z files checked)`.

## Addressing Findings

If any issues are found, proceed with the following steps:

### 5. Fix the Documentation
- For each finding, update the documentation file to match the current codebase
- Apply fixes carefully: use the correct class names, method signatures, file paths, and configuration keys as they exist in `src/`
- Do not change the intent or structure of the documentation — only correct the outdated references

### 6. Verify Fixes
- Re-check the corrected references against the codebase to ensure accuracy
- Run `vendor/bin/php-cs-fixer fix` if any PHP files were modified

### 7. Commit, Push, and Open a Pull Request
- Create a new branch from `main` named `docs/fix-outdated-references`
- Commit all changes with a descriptive message summarizing the fixes
- Push the branch to GitHub
- Open a pull request against `main` using the repository's PR template, filling in the table with `Docs?: yes` and describing the fixed references in the body
