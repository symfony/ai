#!/bin/bash

DIR="$(cd "$(dirname "$0")" && pwd)"
curl https://models.dev/api.json | jq -S . > "$DIR/../config/models-dev.json"
