@echo off
REM Regenerate languages\oc-wolt-drive.pot from current source, and (by default)
REM merge into every existing .po file so translators see new strings without
REM losing their work.
REM
REM Usage:
REM   bin\make-pot.bat              POT + auto-merge .po files
REM   bin\make-pot.bat --no-merge   POT only, leave .po files alone

setlocal enabledelayedexpansion
pushd "%~dp0\.."

set "MERGE=1"
if /I "%~1"=="--no-merge" set "MERGE=0"

REM Locate WP-CLI.
where wp >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    set "WP=wp"
) else if exist "C:\wp-cli\wp-cli.phar" (
    set "WP=php C:\wp-cli\wp-cli.phar"
) else (
    echo ERROR: WP-CLI not found. Install from https://wp-cli.org/
    popd
    exit /b 1
)

if not exist languages mkdir languages

%WP% i18n make-pot . "languages\oc-wolt-drive.pot" ^
    --domain=oc-wolt-drive ^
    --exclude=node_modules,vendor,.git,bin,.claude ^
    --headers="{\"Report-Msgid-Bugs-To\":\"https://github.com/omerelias/shipping-wolt-plugin/issues\"}"

if %ERRORLEVEL% NEQ 0 (
    echo make-pot failed.
    popd
    exit /b %ERRORLEVEL%
)

echo POT regenerated -^> languages\oc-wolt-drive.pot

if "%MERGE%"=="1" (
    where msgmerge >nul 2>&1
    if !ERRORLEVEL! NEQ 0 (
        echo WARNING: msgmerge ^(from gettext^) not installed — skipping merge.
        echo Install GnuWin gettext or use Poedit to update existing .po files.
    ) else (
        for %%f in (languages\*.po) do (
            echo Merging into %%f...
            msgmerge --update --backup=none --quiet "%%f" "languages\oc-wolt-drive.pot"
        )
        echo Merge complete. Open the .po in Poedit to recompile .mo, or run: msgfmt languages\<file>.po -o languages\<file>.mo
    )
)

popd
endlocal
