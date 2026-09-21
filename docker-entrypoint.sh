#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ] && [ "${3:-}" != "key:generate" ]; then
    echo "APP_KEY must be set before starting Bio-Frappe." >&2
    exit 1
fi

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

exec "$@"
