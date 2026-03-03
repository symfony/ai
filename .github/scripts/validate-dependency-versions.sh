#!/bin/bash
#
# Validates that all components and bridges use the required version of a given package in require-dev.
#
# Usage: validate-dependency-versions.sh PACKAGE REQUIRED_VERSION
#
# Example: validate-dependency-versions.sh phpunit/phpunit "^11.5.53"
#
# Checks all composer.json files under src/ and ensures that if the given package is
# present in require-dev, it is set to exactly the required version.

set -e

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 PACKAGE REQUIRED_VERSION"
    exit 1
fi

PACKAGE="$1"
REQUIRED_VERSION="$2"
ERRORS=0

echo "Validating ${PACKAGE} version in all composer.json files..."
echo ""

while IFS= read -r -d '' composer_file; do
    package_version=$(jq -r --arg pkg "$PACKAGE" '.["require-dev"][$pkg] // empty' "$composer_file" 2>/dev/null)

    if [[ -n "$package_version" ]]; then
        if [[ "$package_version" != "$REQUIRED_VERSION" ]]; then
            echo "::error file=${composer_file}::${PACKAGE} version must be \"${REQUIRED_VERSION}\" but found \"${package_version}\" in ${composer_file}"
            ERRORS=$((ERRORS + 1))
        else
            echo "✓ ${composer_file}: ${PACKAGE} = \"${package_version}\""
        fi
    fi
done < <(find src/ -name "composer.json" -not -path "*/vendor/*" -print0 | sort -z)

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS composer.json file(s) with incorrect ${PACKAGE} version (expected \"${REQUIRED_VERSION}\")"
    exit 1
fi

echo ""
echo "All composer.json files use the required ${PACKAGE} version (${REQUIRED_VERSION})!"
