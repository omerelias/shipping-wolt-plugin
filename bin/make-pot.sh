#!/usr/bin/env bash
#
# Regenerate languages/oc-wolt-drive.pot from the current source, and
# (by default) merge the new template into every existing .po file so
# translators see new strings without losing their work.
#
# Usage:
#   bash bin/make-pot.sh              # POT + auto-merge .po files
#   bash bin/make-pot.sh --no-merge   # POT only, leave .po files alone
#
# Requirements:
#   - WP-CLI (https://wp-cli.org/) callable as `wp`, OR
#     wp-cli.phar at /c/wp-cli/wp-cli.phar (Windows default), OR
#     WP_CLI_PHAR env var pointing to wp-cli.phar.
#   - msgmerge (from gettext) for the merge step. Skipped with a warning
#     if not installed.

set -euo pipefail

MERGE=1
for arg in "$@"; do
    case "$arg" in
        --no-merge) MERGE=0 ;;
        -h|--help)
            sed -n '2,18p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
    esac
done

cd "$(dirname "$0")/.."

# Locate WP-CLI.
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

# Regenerate the POT.
$WP i18n make-pot . "$OUT" \
    --domain=oc-wolt-drive \
    --exclude=node_modules,vendor,.git,bin,.claude \
    --headers='{"Report-Msgid-Bugs-To":"https://github.com/omerelias/shipping-wolt-plugin/issues"}'

echo "POT regenerated → $OUT"

# Merge into existing per-language .po files.
if [ "$MERGE" -eq 1 ]; then
    shopt -s nullglob
    po_files=( languages/*.po )
    shopt -u nullglob

    if [ ${#po_files[@]} -eq 0 ]; then
        echo "No .po files to merge yet — start a new language by copying the POT to languages/oc-wolt-drive-<locale>.po"
    elif ! command -v msgmerge >/dev/null 2>&1; then
        echo "WARNING: msgmerge (gettext) not installed — skipping merge." >&2
        echo "To merge manually later:" >&2
        for po in "${po_files[@]}"; do
            echo "    msgmerge --update --backup=none $po $OUT" >&2
        done
    else
        for po in "${po_files[@]}"; do
            echo "Merging into $po…"
            msgmerge --update --backup=none --quiet "$po" "$OUT"
        done
        echo "Merge complete. Existing translations preserved; new strings added with empty msgstr; removed strings marked obsolete."
        echo "Open the .po files in Poedit (or run msgfmt) to recompile the .mo binaries WP actually loads."
    fi
fi
