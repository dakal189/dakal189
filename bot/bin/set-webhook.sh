#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${TELEGRAM_BOT_TOKEN:-}" || -z "${WEBHOOK_URL:-}" ]]; then
	echo "Usage: TELEGRAM_BOT_TOKEN=... WEBHOOK_URL=... $0"
	exit 1
fi

curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook" \
	-d "url=${WEBHOOK_URL}" | jq .