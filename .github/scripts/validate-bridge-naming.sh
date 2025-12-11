#!/bin/bash
#
# Validates bridge naming conventions for Symfony AI components.
#
# Usage: validate-bridge-naming.sh <bridge_type> [component] [options_file]
#
# Arguments:
#   bridge_type     Type of bridge (e.g., "store", "tool") - used in output messages and package suffix
#   component       Name of the parent component (e.g., agent, platform, store)
#                   If not provided, defaults to bridge_type
#   options_file    Optional: Path to options.php file for config key validation (only needed for stores)
#
# Example:
#   validate-bridge-naming.sh store
#   validate-bridge-naming.sh store store src/ai-bundle/config/options.php
#   validate-bridge-naming.sh tool agent
#
# The script builds the bridge path internally as: src/${component}/src/Bridge/*

set -e

BRIDGE_TYPE="${1:?Bridge type is required (e.g., store, tool)}"
COMPONENT="${2:-$BRIDGE_TYPE}"
BRIDGE_PATH="src/${COMPONENT}/src/Bridge/*"
OPTIONS_FILE="${3:-}"

ERRORS=0

# Find all bridges with composer.json
for composer_file in ${BRIDGE_PATH}/composer.json; do
    if [[ ! -f "$composer_file" ]]; then
        continue
    fi

    # Get the bridge directory name (e.g., ChromaDb)
    bridge_dir=$(dirname "$composer_file")
    bridge_name=$(basename "$bridge_dir")

    # Get the package name from composer.json
    package_name=$(jq -r '.name' "$composer_file")

    # Expected package name format: symfony/ai-{lowercase-with-dashes}-{type}
    # Convert PascalCase to kebab-case (e.g., ChromaDb -> chroma-db)
    expected_kebab=$(echo "$bridge_name" | sed 's/\([a-z]\)\([A-Z]\)/\1-\2/g' | tr '[:upper:]' '[:lower:]')
    expected_package="symfony/ai-${expected_kebab}-${BRIDGE_TYPE}"

    if [[ "$package_name" != "$expected_package" ]]; then
        echo "::error file=$composer_file::Package name '$package_name' does not match expected '$expected_package' for bridge '$bridge_name'"
        ERRORS=$((ERRORS + 1))
    else
        echo "✓ $bridge_name: package name '$package_name' is correct"
    fi

    # Check options.php for the config key if options file is provided
    if [[ -n "$OPTIONS_FILE" && -f "$OPTIONS_FILE" ]]; then
        # Expected config key should be lowercase without dashes/underscores
        expected_config_key=$(echo "$bridge_name" | tr '[:upper:]' '[:lower:]')

        # Look for ->arrayNode('configkey') in the options file
        if ! grep -q -- "->arrayNode('$expected_config_key')" "$OPTIONS_FILE"; then
            echo "::error file=$OPTIONS_FILE::Missing or incorrect config key for bridge '$bridge_name'. Expected '->arrayNode('$expected_config_key')' in ${BRIDGE_TYPE} configuration"
            ERRORS=$((ERRORS + 1))
        else
            echo "✓ $bridge_name: config key '$expected_config_key' found in options.php"
        fi
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS naming convention violation(s)"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridge naming conventions are valid!"
