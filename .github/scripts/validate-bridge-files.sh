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
CURRENT_YEAR=$(date +%Y)

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

    # Check newly added LICENSE files have correct first line
    license_file="${bridge_dir}LICENSE"
    if [[ -f "$license_file" ]]; then
        # Determine base ref for comparison (PR base branch or default branch)
        if [[ -n "${GITHUB_BASE_REF:-}" ]]; then
            BASE_REF="origin/${GITHUB_BASE_REF}"
        else
            BASE_REF="origin/main"
        fi

        # Check if LICENSE file is newly added (not in base branch)
        # Use git show which works with shallow clones if base is fetched
        if ! git show "${BASE_REF}:${license_file}" &>/dev/null; then
            expected_first_line="Copyright (c) ${CURRENT_YEAR}-present Fabien Potencier"
            actual_first_line=$(head -n 1 "$license_file")
            if [[ "$actual_first_line" != "$expected_first_line" ]]; then
                echo "::error file=${license_file}::${BRIDGE_TYPE} bridge '$bridge_name' LICENSE file first line must be '${expected_first_line}'"
                bridge_errors=$((bridge_errors + 1))
                ERRORS=$((ERRORS + 1))
            fi
        fi
    fi

    if [[ $bridge_errors -eq 0 ]]; then
        echo "✓ $bridge_name: all required files present and valid"
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS validation error(s) in ${BRIDGE_TYPE} bridges"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridges have required files and valid content!"
