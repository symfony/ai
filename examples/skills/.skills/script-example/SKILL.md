---
name: script-example
description: Example skill demonstrating script execution capabilities. Use when you need to demonstrate how to execute shell, Python, or PHP scripts from within a skill.
license: MIT
metadata:
  author: Guikingone
  version: "1.0"
---

# Script Example Skill

This skill demonstrates how to execute scripts from within a skill using the `executeScript` method.

## Available Scripts

- `hello.sh` - Simple bash script that prints a greeting
- `analyze.py` - Python script that performs basic data analysis
- `process.php` - PHP script that processes command-line arguments

## Usage

To execute a script from this skill:

```php
$tool = new SkillTool($loader, 'script-example');
$result = $tool->executeScript('hello.sh');
```

To execute a script with arguments:

```php
$result = $tool->executeScript('process.php', ['arg1', 'arg2']);
```

## Script Execution Features

- Automatic interpreter detection based on file extension
- Support for bash (.sh), Python (.py), PHP (.php), Node.js (.js), and Ruby (.rb)
- Capture of both stdout and stderr
- Configurable timeout (default: 60 seconds)
- Proper error handling and reporting
