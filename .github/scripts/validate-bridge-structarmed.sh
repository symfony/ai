#!/bin/bash
#
# Validates that all bridges have a corresponding layer entry in structarmed.php.
#
# Usage: validate-bridge-structarmed.sh <bridge_type> [component]
#
# Arguments:
#   bridge_type     Type of bridge (e.g., "store", "tool") - used in output messages
#   component       Name of the parent component (e.g., agent, platform, store)
#                   If not provided, defaults to bridge_type
#
# Example:
#   validate-bridge-structarmed.sh store
#   validate-bridge-structarmed.sh tool agent
#
# The script builds the bridge path internally as: src/${component}/src/Bridge/*

set -e

BRIDGE_TYPE="${1:?Bridge type is required (e.g., store, tool)}"
COMPONENT="${2:-$BRIDGE_TYPE}"
BRIDGE_PATH="src/${COMPONENT}/src/Bridge/*"
STRUCTARMED_FILE="structarmed.php"

# Derive namespace from component name (capitalize first letter)
NAMESPACE="$(echo "${COMPONENT:0:1}" | tr '[:lower:]' '[:upper:]')${COMPONENT:1}"

# Separator used in structarmed.php layer patterns: a regex-escaped backslash
# inside a single-quoted PHP string (four backslashes)
SEP='\\\\'

ERRORS=0

echo "Validating ${BRIDGE_TYPE} bridges are covered in ${STRUCTARMED_FILE} (${BRIDGE_PATH})..."
echo ""

for bridge_dir in ${BRIDGE_PATH}/; do
    if [[ ! -d "$bridge_dir" ]]; then
        continue
    fi

    bridge_name=$(basename "$bridge_dir")
    pattern="Symfony${SEP}AI${SEP}${NAMESPACE}${SEP}Bridge${SEP}${bridge_name}${SEP}"

    if ! grep -qF "$pattern" "$STRUCTARMED_FILE"; then
        echo "::error::${BRIDGE_TYPE} bridge '${bridge_name}' is missing from ${STRUCTARMED_FILE} (expected pattern: ${pattern}.*)"
        ERRORS=$((ERRORS + 1))
    else
        echo "✓ ${bridge_name}: found in ${STRUCTARMED_FILE}"
    fi
done

if [[ $ERRORS -gt 0 ]]; then
    echo ""
    echo "::error::Found ${ERRORS} bridge(s) missing from ${STRUCTARMED_FILE}"
    exit 1
fi

echo ""
echo "All ${BRIDGE_TYPE} bridges are covered in ${STRUCTARMED_FILE}!"
