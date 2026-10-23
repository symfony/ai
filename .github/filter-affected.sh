#!/usr/bin/env bash

# Keeps the paths read from stdin that belong to an affected package. A path is either a package
# directory or a file directly inside one, like its phpunit.xml.dist.
#
# usage: <paths on stdin> | AFFECTED_PACKAGES='["src/agent", ...]' .github/filter-affected.sh

set -euo pipefail

affected=$(jq -r '.[]' <<< "$AFFECTED_PACKAGES")

while read -r path; do
    package=$path
    if [ -f "$path" ]; then
        package=$(dirname "$path")
    fi

    if grep -qxF "$package" <<< "$affected"; then
        echo "$path"
    fi
done
