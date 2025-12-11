#!/bin/bash
#
# Validates that all bridge directories are configured in splitsh.json.
#
# Usage: validate-bridge-splitsh.sh <bridge_type> [component]
#
# Arguments:
#   bridge_type     Type of bridge (e.g., "store", "tool") - used in output messages
#   component       Name of the parent component (e.g., agent, platform, store)
#                   If not provided, defaults to bridge_type
#
# Example:
#   validate-bridge-splitsh.sh store
#   validate-bridge-splitsh.sh tool agent
#
# The script builds the bridge path internally as: src/${component}/src/Bridge/*

set -e

BRIDGE_TYPE="${1:?Bridge type is required (e.g., store, tool)}"
COMPONENT="${2:-$BRIDGE_TYPE}"
BRIDGE_PATH="src/${COMPONENT}/src/Bridge/*"

SPLITSH_FILE="splitsh.json"

if [[ ! -f "$SPLITSH_FILE" ]]; then
    echo "::error::splitsh.json not found"
    exit 1
fi

ERRORS=0

echo "Validating ${BRIDGE_TYPE} bridges (${BRIDGE_PATH})..."
for bridge_dir in ${BRIDGE_PATH}/; do
    if [[ ! -d "$bridge_dir" ]]; then
        continue
    fi

    bridge_name=$(basename "$bridge_dir")
    # Remove trailing /* from bridge_path and append bridge_name
    base_path="${BRIDGE_PATH%/*}"
    expected_path="${base_path}/${bridge_name}"

    # Check if the path exists in splitsh.json
    if ! jq -e --arg path "$expected_path" 'any(.subtrees[]; . == $path or (type == "object" and .prefixes[0].from == $path))' "$SPLITSH_FILE" > /dev/null 2>&1; then
        echo "::error file=$SPLITSH_FILE::${BRIDGE_TYPE} bridge '$bridge_name' at '$expected_path' is not configured in splitsh.json"
        ERRORS=$((ERRORS + 1))
    else
        echo "✓ $bridge_name: configured in splitsh.json"
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS ${BRIDGE_TYPE} bridge(s) missing from splitsh.json"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridges are correctly configured in splitsh.json!"
