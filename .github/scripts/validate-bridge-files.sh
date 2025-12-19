#!/bin/bash
#
# Validates that all bridges contain required files.
#
# Usage: validate-bridge-files.sh <bridge_type> [component]
#
# Arguments:
#   bridge_type     Type of bridge (e.g., "store", "tool") - used in output messages
#   component       Name of the parent component (e.g., agent, platform, store)
#                   If not provided, defaults to bridge_type
#
# Example:
#   validate-bridge-files.sh store
#   validate-bridge-files.sh tool agent
#
# The script builds the bridge path internally as: src/${component}/src/Bridge/*

set -e

BRIDGE_TYPE="${1:?Bridge type is required (e.g., store, tool)}"
COMPONENT="${2:-$BRIDGE_TYPE}"
BRIDGE_PATH="src/${COMPONENT}/src/Bridge/*"

# Required files that must exist in every bridge
REQUIRED_FILES=(
    "LICENSE"
    "composer.json"
    "phpunit.xml.dist"
    "phpstan.dist.neon"
    "CHANGELOG.md"
    "README.md"
    ".gitignore"
    ".gitattributes"
    ".github/workflows/close-pull-request.yml"
    ".github/PULL_REQUEST_TEMPLATE.md"
)

ERRORS=0

echo "Validating ${BRIDGE_TYPE} bridges have required files (${BRIDGE_PATH})..."
echo ""

for bridge_dir in ${BRIDGE_PATH}/; do
    if [[ ! -d "$bridge_dir" ]]; then
        continue
    fi

    bridge_name=$(basename "$bridge_dir")
    bridge_errors=0

    for required_file in "${REQUIRED_FILES[@]}"; do
        file_path="${bridge_dir}${required_file}"
        if [[ ! -f "$file_path" ]]; then
            echo "::error file=${bridge_dir%/}::${BRIDGE_TYPE} bridge '$bridge_name' is missing required file: $required_file"
            bridge_errors=$((bridge_errors + 1))
            ERRORS=$((ERRORS + 1))
        fi
    done

    if [[ $bridge_errors -eq 0 ]]; then
        echo "✓ $bridge_name: all required files present"
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS missing required file(s) in ${BRIDGE_TYPE} bridges"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridges have required files!"
