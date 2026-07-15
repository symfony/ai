#!/usr/bin/env bash

# Runs a command once for every package config file read from stdin, substituting "{}" in the
# arguments with that path. The command runs from the repository root, so PHPStan and PHPUnit
# report file paths relative to the root and GitHub can anchor their annotations.
#
# Packages run in parallel, but their output is buffered and printed in input order, each in its
# own log group. Every package runs even when an earlier one fails, and each failing package is
# reported separately, which preserves the failure attribution a per-package job matrix provided.
# This mirrors how symfony/phpunit-bridge's simple-phpunit runs the components of symfony/symfony.
#
# usage: <config paths on stdin> | .github/run-in-packages.sh <command> [args...] with {} placeholder

set -uo pipefail

if [ $# -eq 0 ]; then
    echo "::error::run-in-packages.sh needs a command to run" >&2
    exit 1
fi

configs=()
while read -r config; do
    [ -n "$config" ] && configs+=("$config")
done

if [ ${#configs[@]} -eq 0 ]; then
    echo "::error::run-in-packages.sh matched no packages" >&2
    exit 1
fi

parallel=$(nproc 2>/dev/null || echo 4)
output=$(mktemp -d)
trap 'rm -rf "$output"' EXIT

for index in "${!configs[@]}"; do
    # PHPStan compiles its DI container into $TMPDIR/phpstan, keyed by a hash of the config. Most
    # packages share a byte-identical phpstan.dist.neon, so without an isolated temporary directory
    # the parallel processes race on the very same container file.
    mkdir -p "$output/$index.tmp"

    (
        TMPDIR="$output/$index.tmp" "${@//\{\}/${configs[$index]}}" > "$output/$index.log" 2>&1
        echo $? > "$output/$index.exit"
    ) &

    while [ "$(jobs -rp | wc -l)" -ge "$parallel" ]; do
        wait -n
    done
done
wait

failed=()
for index in "${!configs[@]}"; do
    package=$(dirname "${configs[$index]}")

    echo "::group::$package"
    cat "$output/$index.log"
    echo "::endgroup::"

    if [ "$(cat "$output/$index.exit")" != '0' ]; then
        failed+=("$package")
    fi
done

echo "Ran in ${#configs[@]} package(s) with parallelism $parallel, ${#failed[@]} failed."

if [ ${#failed[@]} -gt 0 ]; then
    for package in "${failed[@]}"; do
        echo "::error title=$package::Failed in $package"
    done
    exit 1
fi
