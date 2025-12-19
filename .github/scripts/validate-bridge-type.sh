#!/bin/bash
#
# Validates that all bridges have the correct "type" field in composer.json.
#
# Usage: validate-bridge-type.sh <bridge_type> [component]
#
# Arguments:
#   bridge_type     Type of bridge (e.g., "store", "tool", "platform", "message-store")
#                   This determines the expected composer.json type: symfony-ai-{bridge_type}
#   component       Name of the parent component (e.g., agent, platform, store, chat)
#                   If not provided, defaults to bridge_type
#
# Example:
#   validate-bridge-type.sh store
#   validate-bridge-type.sh tool agent
#   validate-bridge-type.sh message-store chat
#
# The script builds the bridge path internally as: src/${component}/src/Bridge/*

set -e

BRIDGE_TYPE="${1:?Bridge type is required (e.g., store, tool, platform, message-store)}"
COMPONENT="${2:-$BRIDGE_TYPE}"
BRIDGE_PATH="src/${COMPONENT}/src/Bridge/*"

EXPECTED_TYPE="symfony-ai-${BRIDGE_TYPE}"
ERRORS=0

echo "Validating ${BRIDGE_TYPE} bridges have correct type (${BRIDGE_PATH})..."
echo "Expected type: ${EXPECTED_TYPE}"
echo ""

for composer_file in ${BRIDGE_PATH}/composer.json; do
    if [[ ! -f "$composer_file" ]]; then
        continue
    fi

    bridge_dir=$(dirname "$composer_file")
    bridge_name=$(basename "$bridge_dir")

    actual_type=$(jq -r '.type // empty' "$composer_file")

    if [[ -z "$actual_type" ]]; then
        echo "::error file=$composer_file::${BRIDGE_TYPE} bridge '$bridge_name' is missing 'type' field in composer.json"
        ERRORS=$((ERRORS + 1))
    elif [[ "$actual_type" != "$EXPECTED_TYPE" ]]; then
        echo "::error file=$composer_file::${BRIDGE_TYPE} bridge '$bridge_name' has incorrect type '$actual_type', expected '$EXPECTED_TYPE'"
        ERRORS=$((ERRORS + 1))
    else
        echo "✓ $bridge_name: type '$actual_type' is correct"
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found $ERRORS type validation error(s) in ${BRIDGE_TYPE} bridges"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridges have the correct type!"
