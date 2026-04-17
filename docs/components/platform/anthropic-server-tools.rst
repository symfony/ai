Anthropic Server Tools
======================

Anthropic provides built-in server-side tools that allow the model to perform specific actions like executing code or editing files in a sandboxed environment.

Overview
--------

Anthropic's server tools can be enabled when calling the model. These tools are executed on Anthropic's infrastructure.

Available Server Tools
----------------------

Code Execution
~~~~~~~~~~~~~~

Anthropic provides two main tools for code execution:

- **Bash** (``bash_code_execution``) - Allows the model to run bash commands.
- **Text Editor** (``text_editor_code_execution``) - Allows the model to create and edit files, often used in conjunction with Python execution.

To enable these tools you need to explicitly enable the code execution using the ``tools`` option::

    $result = $platform->invoke('claude-3-5-sonnet-latest', $messages, [
        'tools' => [[
            'type' => 'code_execution_20250825',
            'name' => 'code_execution',
        ]],
    ]);

Handling Results
----------------

When server tools are used, the model may return multiple content blocks. Symfony AI abstracts these into a :class:`Symfony\\AI\\Platform\\Result\\MultiPartResult`.

The individual parts can be any `ResultInterface` instances, but in practice they consist of the following result types:

- :class:`Symfony\\AI\\Platform\\Result\\TextResult` - Normal text response.
- :class:`Symfony\\AI\\Platform\\Result\\ExecutableCodeResult` - The code the model intended to run.
- :class:`Symfony\\AI\\Platform\\Result\\CodeExecutionResult` - The output of the executed code (stdout/stderr).

Example
-------

See `examples/anthropic/server-tools-code-execution.php`_ for a complete working example.

.. _`examples/anthropic/server-tools-code-execution.php`: https://github.com/symfony/ai/blob/main/examples/anthropic/server-tools-code-execution.php
