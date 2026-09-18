#!/usr/bin/env bash
# Start the ADMS gateway with env vars sourced from the Laravel .env
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../.env"

# Parse key=value pairs from Laravel .env (handles CRLF and quoted values)
get_env() {
    grep -m1 "^${1}=" "$ENV_FILE" | sed 's/\r//' | cut -d'=' -f2- | sed "s/^['\"]//;s/['\"]$//"
}

export ADMS_MANAGEMENT_TOKEN="$(get_env DEVICE_GATEWAY_TOKEN)"
export LARAVEL_INTERNAL_URL="http://127.0.0.1:8000"
export LARAVEL_GATEWAY_TOKEN="$(get_env LARAVEL_GATEWAY_TOKEN)"
export ADMS_STORE_PATH="${ADMS_STORE_PATH:-$SCRIPT_DIR/../storage/gateway.db}"
export ADMS_DEVICE_ADDR="${ADMS_DEVICE_ADDR:-:8080}"
export ADMS_MANAGEMENT_ADDR="${ADMS_MANAGEMENT_ADDR:-:8081}"

echo "Starting ADMS gateway..."
echo "  Device:     $ADMS_DEVICE_ADDR"
echo "  Management: $ADMS_MANAGEMENT_ADDR"
echo "  Store:      $ADMS_STORE_PATH"
echo "  Laravel:    $LARAVEL_INTERNAL_URL"

exec "$SCRIPT_DIR/gateway"
