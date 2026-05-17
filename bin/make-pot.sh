#!/usr/bin/env bash
#
# Regenerate languages/oc-wolt-drive.pot from the current source.
# Run from anywhere — script changes into the plugin root itself.
#
# Requirements:
#   - WP-CLI (https://wp-cli.org/) callable as `wp`, OR
#     wp-cli.phar reachable as `php $WP_CLI_PHAR` with WP_CLI_PHAR set.
#
# Usage:
#   bash bin/make-pot.sh
#   WP_CLI_PHAR=/c/wp-cli/wp-cli.phar bash bin/make-pot.sh

set -euo pipefail

cd "$(dirname "$0")/.."

if command -v wp >/dev/null 2>&1; then
    WP="wp"
elif [ -n "${WP_CLI_PHAR:-}" ] && [ -f "${WP_CLI_PHAR}" ]; then
    WP="php ${WP_CLI_PHAR}"
elif [ -f "/c/wp-cli/wp-cli.phar" ]; then
    WP="php /c/wp-cli/wp-cli.phar"
else
    echo "ERROR: WP-CLI not found. Install from https://wp-cli.org/ or set WP_CLI_PHAR." >&2
    exit 1
fi

OUT="languages/oc-wolt-drive.pot"
mkdir -p languages

$WP i18n make-pot . "$OUT" \
    --domain=oc-wolt-drive \
    --exclude=node_modules,vendor,.git,bin,.claude \
    --headers='{"Report-Msgid-Bugs-To":"https://github.com/omerelias/shipping-wolt-plugin/issues"}'

echo "POT regenerated → $OUT"

# If existing .po files exist, hint at the next step.
if ls languages/*.po >/dev/null 2>&1; then
    echo "Next: merge into existing translations with:"
    for po in languages/*.po; do
        echo "    msgmerge --update --backup=none $po $OUT"
    done
fi
