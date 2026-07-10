#!/usr/bin/env bash

# Installs and tests each package read from stdin against its own --prefer-lowest resolution.
#
# A merged install cannot be shared here: composer resolves the intersection of all constraints,
# so a merged --prefer-lowest would pick the highest of every package's floor and never exercise
# the lowest bound a single package declares. Each package therefore gets its own install.
#
# Installs run sequentially. Parallel composer processes race while creating entries in the shared
# download cache, and sharing that cache is what keeps the lowest resolution fast, so the reliable
# combination is one install at a time against a warm cache.
#
# Components install in place. Bridges cannot: a bridge lives inside its component's
# path-repository tree, and installing it there makes composer chase a symlink cycle -- the same
# reason .github/actions/isolate-bridge relocates them. Each bridge is instead copied to a scratch
# tree that keeps it at its original depth under the repository root (".lowest/" replaces the
# leading "src/"), so relative paths to the shared fixtures/, .phpstan/ and sibling test suites
# still resolve. Every package runs even when an earlier one fails, and each failure is reported
# separately.
#
# Must run after .github/build-packages.php, from the repository root.
#
# usage: <package dirs on stdin> | .github/run-lowest.sh

set -uo pipefail

root="$PWD"
scratch="$root/.lowest"
trap 'rm -rf "$scratch"' EXIT

failed=()
total=0

while read -r package; do
    [ -n "$package" ] || continue
    total=$((total + 1))

    if [[ "$package" == */src/Bridge/* ]]; then
        # ".lowest/" stands in for the leading "src/", keeping the bridge at its original depth.
        dir="$scratch/${package#src/}"
        rm -rf "$scratch"
        mkdir -p "$(dirname "$dir")"
        cp -r "$root/$package" "$dir"
        rm -rf "$dir/vendor"
    else
        dir="$root/$package"
    fi

    echo "::group::$package"
    if ! (cd "$dir" && composer update --prefer-lowest --no-interaction --no-progress --ansi && vendor/bin/phpunit --exclude-group integration); then
        failed+=("$package")
    fi
    echo "::endgroup::"
done

if [ "$total" -eq 0 ]; then
    echo "::error::run-lowest.sh matched no packages" >&2
    exit 1
fi

echo "Ran lowest install + tests for $total package(s), ${#failed[@]} failed."

if [ ${#failed[@]} -gt 0 ]; then
    for package in "${failed[@]}"; do
        echo "::error title=$package::--prefer-lowest tests failed in $package"
    done
    exit 1
fi
