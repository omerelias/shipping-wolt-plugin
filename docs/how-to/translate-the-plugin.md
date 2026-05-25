# Translate the plugin

The plugin ships with full Hebrew (`he_IL`) and uses the standard
gettext workflow. Adding more languages takes about 30 minutes per
language.

---

## Vocabulary

- **POT** (`.pot`) — master template; the catalogue of every
  translatable English string in the source. Never edited by hand.
- **PO** (`.po`) — per-language source file containing the
  translations. Edited in Poedit.
- **MO** (`.mo`) — compiled binary that WordPress actually loads.
  Poedit re-builds it whenever you save the `.po`.

Files live in `languages/`. Text domain is `oc-wolt-drive`. WP loads
the catalogue matching the site locale automatically.

---

## Workflow when source strings change

You'll hit this when new code lands that adds or modifies a translatable
string.

### 1. Regenerate the POT + merge into every .po

```bash
bash bin/make-pot.sh        # bash on Linux / macOS / Git Bash
bin\make-pot.bat            # Windows cmd equivalent
```

Add `--no-merge` to skip the merge into existing `.po` files (e.g. when
you want to bootstrap a fresh language).

Behind the scenes this runs WP-CLI's `wp i18n make-pot` to scan the
source, then `msgmerge --update --backup=none` against every
`languages/*.po` file.

> Both wrappers auto-fall-back to `wp-cli.phar` at `/c/wp-cli/` when
> `wp` isn't in your PATH (Windows default). On Linux/Mac install
> WP-CLI globally: https://wp-cli.org/.

### 2. msgmerge is non-destructive

When a `.po` is merged with a fresh POT:

| What | What happens |
|---|---|
| Existing translations | **Preserved as-is** |
| New strings | Added with empty `msgstr ""` for you to translate |
| Strings whose English changed slightly | Marked `#, fuzzy` for you to review |
| Strings deleted from the source | Marked `#~` "obsolete" but kept in the file |

So you never lose work. Worst case: a fuzzy translation needs a quick
review.

### 3. Open the .po in Poedit

[Poedit](https://poedit.net/) is free, runs on Mac / Windows / Linux.

1. Open `languages/oc-wolt-drive-{locale}.po`.
2. Untranslated strings appear at the top (orange highlight).
3. Translate them — Poedit suggests from translation memory.
4. Review any `#, fuzzy` markers.
5. **Save**. Poedit recompiles the `.mo` automatically.

### 4. Upload .po + .mo to the server

Both files matter:

- `.mo` — what WP actually reads at runtime
- `.po` — source for the next translator (and so `make-pot.sh` can
  merge into it later)

Don't commit `.po` without `.mo` or vice versa — they should stay in
lockstep.

---

## Starting a brand new language

Say you want German (`de_DE`):

```bash
# 1. Make sure the POT is current
bash bin/make-pot.sh --no-merge

# 2. Copy it to a new locale-specific .po
cp languages/oc-wolt-drive.pot languages/oc-wolt-drive-de_DE.po

# 3. Open it in Poedit
# Poedit asks for the language → pick German (Germany)
# Translate every string, save
```

Save in Poedit → `.mo` appears alongside automatically. Commit both.

WordPress will pick up your new locale automatically when a user runs
the site at `WPLANG=de_DE` or `WPLANG=de_DE_formal` (WP matches less
specific locales when an exact match isn't found).

---

## Conventions used in the source

Every translator-friendly string has a `/* translators: */` comment
above any `%s`/`%d` placeholders, telling the translator what each
placeholder represents:

```php
/* translators: %s: WC order number */
sprintf( __( 'Order #%s', 'oc-wolt-drive' ), $order->get_order_number() );
```

WP-CLI's `make-pot` warns when this is missing. If you add a new
translatable string with placeholders, add the comment too — `make-pot.sh`
should run with zero warnings.

---

## Translation choices we made for Hebrew

For consistency if you extend the Hebrew or use it as a template:

| English | Hebrew | Reasoning |
|---|---|---|
| `Wolt Drive`, `Webhook`, `JWT`, `API`, `merchant`, `venue` | Kept in Latin | Standard in Israeli technical UX; translating would just confuse |
| `Settings`, `Deliveries`, `Tools` | תרגום מלא | Top-level UI, everyone reads it |
| `Bearer token`, `secret`, `endpoint` | Latin | Developer-facing terms |
| Customer-facing copy (frontend card) | תרגום טבעי בעברית | End users won't recognise the English phrasing anyway |

If you adapt this for a non-Israeli locale, follow the same heuristic:
**translate user labels, keep developer/protocol terms in Latin**.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `make-pot.sh: WP-CLI not found` | WP-CLI not installed | Install from https://wp-cli.org/ or set `WP_CLI_PHAR=/path/to/wp-cli.phar` env var |
| `msgmerge: command not found` | gettext not installed | macOS: `brew install gettext` · Windows: install gettext-iconv · Ubuntu: `apt install gettext` |
| New strings show in English in WP admin | `.mo` wasn't uploaded, or has the wrong filename | Filename must be `oc-wolt-drive-{locale}.mo` exactly. Locale match is case-sensitive (`he_IL` ≠ `he_il`) |
| Some strings change but others don't | `.mo` is stale — Poedit didn't recompile, or the upload missed it | Open the `.po` in Poedit and save again to force a fresh `.mo` |
| WP-CLI warns about missing `translators:` comments | A new sprintf was added without a comment | Add `/* translators: %s: what %s is */` directly above the `__()` call |

---

## See also

- [Settings reference](../reference/settings.md) — every UI string the
  translator will encounter listed in one place.
- [Architecture](../reference/architecture.md) — file map showing where
  translatable strings live.
