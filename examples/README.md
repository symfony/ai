# Symfony AI Examples

This directory contains various examples of how to use the Symfony AI components. They are meant to provide a
reference implementation to help you get started.

On top, the examples are used as integration tests to ensure that the components work as expected.

## Running the examples

For setting up and running the examples, you can either run them standalone or via the example runner. You find the
commands for that in this section. Make sure to change into the `examples` directory before running the commands.

```bash
cd examples
```

### Setup

#### Dependencies

Before running the examples, you need to install the dependencies. You can do this by running:

```bash
composer install
```

If you want to run the examples together with local changes, for example while developing a feature, you need to link
the AI components into the vendor directory after `composer install`. You can use the `link` script in the root
directory for this:

```bash
../link
```

#### Configuration

Depending on the examples you want to run, you may need to configure the needed API keys. Therefore, you need to create a
`.env.local` file in the root of the examples' directory. This file should contain the environment variables for the
corresponding example you want to run.

_Now you can run examples standalone or via the example runner._

#### Store with Docker

Some of the store examples require locally running services, meaning that you need to have Docker installed and running
to test these examples.

```bash
docker compose up -d
```

### Running examples standalone

Every example script is a standalone PHP script that can be run from the command line.
You can run an example by executing the following command:

```bash
php openai/chat.php
```

To get more insights into what is happening at runtime, e.g. HTTP and tool calls, you can add `-vv` or `-vvv`:

```bash
php openai/toolcall-stream.php -vvv
```

#### GigaChat 4 via AI Studio

To try GigaChat 4 through GigaPlatform AI Studio, create an API key in AI Studio and
copy the model ID from its model list. Set `GIGACHAT_AI_STUDIO_API_KEY` and
`GIGACHAT_AI_STUDIO_MODEL` in `.env.local`, then run `php gigachat/chat.php` from
this directory. The example uses the Generic bridge with the AI Studio endpoint.

### Running examples via the example runner

You can also run the examples via the example runner, which takes care of running the examples parallel in
sub-processes. This is useful if you are contributing to the Symfony AI components and want to ensure that all examples
work as expected.

You can run the example runner by executing the following command:

```bash
./runner
```

If you only want to run examples of one or multiple specific subdirectories, you can pass the name as an argument:

```bash
./runner openai mistral
```

If you only want to run a specific subset of examples, you can use a filter option:

```bash
./runner --filter=toolcall
```

## Record & replay

Examples don't only run against the live provider APIs — every HTTP interaction can be recorded into a *cassette*
(a JSON file under `tests/fixtures/`, mirroring the example's path) and replayed later without any API keys. Replaying drives
the recorded bytes through the real bridge pipeline (`Platform → ModelClient → ResultConverter`), which turns the
example corpus into deterministic, credential-free integration tests: CI replays every recorded example via PHPUnit
and compares its output against a committed golden (`tests/fixtures/<path>.out`), so a result-converter regression fails
the build.

The switch is the `CASSETTE` environment variable (`record` or `replay`), but you usually don't set it yourself —
the runner and the test suite handle it:

```bash
# Record: run the examples live (API keys required), write cassettes with credentials
# redacted, then verify each recording replays and refresh its golden.
./runner --record openai

# Replay: re-run every recorded example offline and compare against its golden.
# No keys needed - this is also what CI runs.
vendor/bin/phpunit
```

Recording is a local maintainer task, since CI has no provider credentials — a good habit is recording alongside the
live example run before tagging a release: it costs no extra API calls, and the cassette diff shows exactly what the
providers changed since the last recording. Commit cassettes and goldens together.

Binary responses (generated images, audio, ...) are not stored byte-for-byte: the cassette keeps a metadata stub
(content type, byte size) and replay serves a small placeholder body instead.

Recordings never store your credentials or account specific values like an Azure endpoint: every variable of
`.env.test` whose value in `.env.local` differs is replaced by its placeholder, and replay loads `.env.test` instead of
`.env.local`. When an example reads a new environment variable, add a placeholder for it to `.env.test`.

To record a single example (or a subset), narrow the record run with a filter — the golden
refresh is included:

```bash
./runner --record --filter=chat openai
```
