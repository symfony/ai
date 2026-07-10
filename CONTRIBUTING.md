Contributing
------------

Symfony AI an open source, community-driven project, that is a standalone
monorepo, but adopts standards and conventions from the Symfony project.

The sections below describe what is specific to `symfony/ai`, and link to the
Symfony documentation for everything the two projects share. Read the linked
page for the general rule, and the paragraph above it for how it applies here.

Reporting a Bug
---------------

Report bugs on the [`symfony/ai` issue tracker][issues], not on
`symfony/symfony`. Say which package you are using and at which version, since
every component and bridge under `src/` is released as its own Composer
package.

The most useful reproducer is a short standalone script. The [`examples/`][ex]
directory is a good starting point: most of them are a few lines against a
single platform, and a modified example is something a maintainer can run
immediately. If the bug only shows up through the Symfony integration, base it
on the [`demo/`][demo] application instead.

When a bug involves a call to an AI provider, run the example with `-vvv`: the
examples wire a console logger into the HTTP client, so the calls made to the
provider are logged. Redact your API keys before pasting anything.

* [Reporting a Bug][1]

Reporting a Security Issue
--------------------------

Report security issues to **security@symfony.com**, the same address used for
the rest of the Symfony project, and never through the public issue tracker.

* [Security Issues][4]

Reviewing Issues and Pull Requests
----------------------------------

`symfony/ai` uses the same `Status: Needs Review`, `Status: Needs Work` and
`Status: Reviewed` labels as the rest of the Symfony project, set by leaving a
`Status: <status>` line in a comment.

Reviewing here often means running the change against a real provider, which
maintainers cannot always do for every platform. If you have credentials for
the provider a pull request touches, saying that its examples still work is a
genuinely useful review.

* [Reviewing issues/pull requests][0]

Submitting a Patch
------------------

`symfony/ai` has no maintenance branches. Every pull request — bug fix, feature
or deprecation — targets `main`, so the "oldest maintained branch" rule from
the Symfony documentation does not apply.

Prefix the pull request title with the components it touches, for example
`[Platform][Bedrock] Update model catalog`. Fill in the table from the pull
request template, and note that the `Docs?` column refers to the [`docs/`][docs]
directory in this repository, not to `symfony/symfony-docs`.

Changelog rules are checked automatically on every pull request:

* Each component keeps its own `CHANGELOG.md`. Add entries for features and
  deprecations only, and only under the upcoming, unreleased version.
* A bug fix must not touch any `CHANGELOG.md` or `UPGRADE.md`.
* A backward compatibility break needs an entry in the root `UPGRADE.md` **and**
  the `BC Break` label. Neither is valid without the other.

* [Submitting a Patch][2]

Running the Tests
-----------------

Every component, bundle and bridge under `src/` is its own Composer package
with its own test suite. They are published as read-only split repositories, so
an application installs only the packages it needs and Composer resolves the
interdependencies. That is convenient to consume and slightly awkward to
develop, which is what the tooling below is for.

### Installing Dependencies in the Root

First of all, make sure you have PHP >= 8.2 and Composer installed, and
install the dependencies in the root of the repository:

```bash
composer install
```

This enables you to use the `./link` script, which is used to link subpackages.

### Installing Dependencies in a Subpackage

Assuming you want to work on the `symfony/ai-bundle`, you need to install its
dependencies as well:

```bash
# install its dependencies
composer install --working-dir src/ai-bundle

# link the interdependencies
./link src/ai-bundle

# run its test suite
cd src/ai-bundle && vendor/bin/phpunit
```

Some packages require the `mongodb` or `redis` extension. If you don't have it
installed, add `--ignore-platform-req=ext-mongodb` to the `composer install`
call.

`./link` is the step that is easy to miss. It swaps every `symfony/ai-*`
package inside the subpackage's `vendor/` for a symlink to its directory in
`src/`, so the tests run against your working copy.

Adding a New Package or Bridge
------------------------------

A new package has never been tagged, and until it is merged and split to its own
repository it is not on Packagist at all. So a released-style constraint on it
cannot resolve, and every package that requires it fails to install.

In practice two packages end up depending on a new bridge: `src/ai-bundle`,
which wires it into the Symfony integration, and `examples/`, which
demonstrates it. Both need the same treatment.

Wherever you make an existing package depend on your new one, point it at the
directory with a `path` repository and require `dev-main`:

```json
{
    "require-dev": {
        "symfony/ai-your-platform": "dev-main"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../platform/src/Bridge/YourPlatform"
        }
    ]
}
```

Both are temporary, and you do not have to clean them up yourself. Once the
package ships in a tagged release its constraint resolves from Packagist like
any other, so `./bump` rewrites the constraint and drops the `path` repository
as part of the release procedure. It only removes `path` repositories that point
inside the monorepo, and leaves any others alone.

A few expectations beyond the code:

* Every bridge ships at least one example in [`examples/`][ex]. They double as
  integration tests, so keep them runnable and small.
* The provider needs to be reachable. A paid subscription is fine, a waitlist or
  invite-only access is not.
* Stores should run from a Docker image and be added to the services in
  [`examples/compose.yaml`][compose], so their examples work without an account.

Backward Compatibility
----------------------

Symfony AI has not reached `1.0` yet and does not carry the Symfony backward
compatibility promise. A minor release may break backward compatibility where
it has to, which is why the root `UPGRADE.md` documents the upgrade path from
one minor to the next.

That is a licence to change an API, not to change it carelessly. Prefer
deprecating over breaking: mark the old API `@deprecated`, call
`trigger_deprecation()`, and keep it working. When you do break something, it
needs an `UPGRADE.md` entry and the `BC Break` label.

The promise linked below is what Symfony AI adopts at `1.0`, and reading it now
is the best guide to which changes will hurt later.

* [Our Backwards Compatibility Promise][6]

Coding Standards
----------------

Symfony AI follows the Symfony coding standards, including the license header
on every PHP file. Run PHP CS Fixer from the root of the repository, never from
inside a package:

```bash
vendor/bin/php-cs-fixer fix
```

Pull requests are additionally checked with PHPStan, which each package
configures for itself, and with Deptrac, which treats every component and
bridge as a layer and enforces which of them may depend on which.

* [Coding Standards][7]

Conventions
-----------

The Symfony conventions apply, in particular how to write a changelog entry and
how to deprecate code. Beyond them, `symfony/ai` keeps its own conventions in
[`AGENTS.md`][agents] — throwing package-specific exceptions rather than
`\RuntimeException`, declaring array shapes, and similar. They are written for
AI coding assistants, and they are an accurate summary for humans too.

Documentation lives in [`docs/`][docs] as reStructuredText. Validate it with
`./doctor-rst` from the root, which runs the validator in Docker.

* [Conventions][8]

Core Team
---------

Symfony AI is maintained inside the Symfony organization and follows the same
governance. The Mergers Team described below merges on `symfony/symfony`; this
repository has its own maintainers, but the rules for voting on and merging a
pull request are the ones in that document.

* [Symfony Core Team][3]

[0]: https://symfony.com/doc/current/contributing/community/reviews.html
[1]: https://symfony.com/doc/current/contributing/code/bugs.html
[2]: https://symfony.com/doc/current/contributing/code/patches.html
[3]: https://symfony.com/doc/current/contributing/code/core_team.html
[4]: https://symfony.com/doc/current/contributing/code/security.html
[6]: https://symfony.com/doc/current/contributing/code/bc.html
[7]: https://symfony.com/doc/current/contributing/code/standards.html
[8]: https://symfony.com/doc/current/contributing/code/conventions.html
[issues]: https://github.com/symfony/ai/issues
[ex]: examples/
[compose]: examples/compose.yaml
[demo]: demo/
[docs]: docs/
[agents]: AGENTS.md
