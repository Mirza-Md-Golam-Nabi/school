#!/bin/bash

# Makes `php` point to PHP 8.3 on cPanel so `php artisan ...` works.
#
# Usage (must be sourced, not run with sh/bash):
#     source setup-php-alias.sh
#   or
#     . setup-php-alias.sh
#
# - Activates the alias immediately in the current terminal session.
# - Also adds it to ~/.bashrc so future SSH logins get it automatically.
# - Safe to run again — it only adds lines that are missing.

PHP_PATH="/opt/cpanel/ea-php83/root/usr/bin/php"
BASHRC="$HOME/.bashrc"

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    echo "⚠️  Run it with source so the alias stays in this terminal:"
    echo "    source $0"
    exit 1
fi

if [ ! -x "$PHP_PATH" ]; then
    echo "❌ PHP 8.3 not found at $PHP_PATH"
    return 1
fi

alias php="$PHP_PATH"
alias art="$PHP_PATH artisan"

touch "$BASHRC"

for line in "alias php='$PHP_PATH'" "alias art='$PHP_PATH artisan'"; do
    if ! grep -qxF "$line" "$BASHRC"; then
        echo "$line" >> "$BASHRC"
    fi
done

unset line PHP_PATH BASHRC

echo "✅ php alias is active in this session."
echo "    php artisan migrate --force"
echo "    art migrate --force          (short form)"
