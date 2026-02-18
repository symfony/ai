#!/bin/bash
#
# Validates that all components and bridges use the required phpunit/phpunit version in require-dev.
#
# Usage: validate-phpunit-version.sh
#
# Checks all composer.json files under src/ and ensures that if phpunit/phpunit is
# present in require-dev, it is set to exactly "^11.5.53".

set -e

REQUIRED_VERSION="^11.5.53"
ERRORS=0

echo "Validating phpunit/phpunit version in all composer.json files..."
echo ""

while IFS= read -r -d '' composer_file; do
    phpunit_version=$(jq -r '."require-dev"."phpunit/phpunit" // empty' "$composer_file" 2>/dev/null)

    if [[ -n "$phpunit_version" ]]; then
        if [[ "$phpunit_version" != "$REQUIRED_VERSION" ]]; then
            echo "::error file=${composer_file}::phpunit/phpunit version must be \"${REQUIRED_VERSION}\" but found \"${phpunit_version}\" in ${composer_file}"
            ERRORS=$((ERRORS + 1))
        else
            echo "✓ ${composer_file}: phpunit/phpunit = \"${phpunit_version}\""
        fi
    fi
done < <(find src/ -name "composer.json" -not -path "*/vendor/*" -print0 | sort -z)

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS composer.json file(s) with incorrect phpunit/phpunit version (expected \"${REQUIRED_VERSION}\")"
    exit 1
fi

echo ""
echo "All composer.json files use the required phpunit/phpunit version (${REQUIRED_VERSION})!"
