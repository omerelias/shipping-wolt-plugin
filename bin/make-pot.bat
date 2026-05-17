@echo off
REM Regenerate languages\oc-wolt-drive.pot from the current source.
REM Windows companion to make-pot.sh.
REM
REM Requirements: WP-CLI installed (wp.bat in PATH) OR wp-cli.phar reachable.

setlocal
pushd "%~dp0\.."

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
popd
endlocal
