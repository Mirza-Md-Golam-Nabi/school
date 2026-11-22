#!/bin/bash
set -euo pipefail

PHP="/opt/cpanel/ea-php83/root/usr/bin/php"

echo "🚀 Deployment started..."

# Pull the latest code from Git
git pull origin dev
echo "✅ Git pull done"

# Download composer.phar if it isn't here yet
if [ ! -f composer.phar ]; then
    curl -fsSL https://getcomposer.org/installer | "$PHP"
    echo "✅ Composer downloaded"
fi

# Run composer install before artisan down — if the new code doesn't
# match the old vendor/ folder, artisan down itself will crash, and
# the next deploy attempt will get stuck at this same spot (deadlock).
"$PHP" composer.phar install --no-dev --optimize-autoloader
echo "✅ Composer done"

# Write KEY="value" into .env (replaces the existing line, or appends it)
set_env() {
    local key="$1"
    local value="$2"
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    local line="${key}=\"${value}\""

    if grep -q "^${key}=" .env; then
        local escaped="${line//\\/\\\\}"
        escaped="${escaped//&/\\&}"
        escaped="${escaped//|/\\|}"
        sed -i "s|^${key}=.*|${escaped}|" .env
    else
        echo "$line" >> .env
    fi
}

# Create .env on the first deploy and fill in the database settings.
# If the script stops halfway (empty input, Ctrl+C, no terminal), the
# trap deletes the half-made .env so the next run asks again instead of
# skipping this block with a broken .env.
if [ ! -f .env ]; then
    trap 'rm -f .env' EXIT
    cp .env.example .env

    DEFAULT_APP_URL="https://$(basename "$PWD")"
    read -r -p "APP_URL [$DEFAULT_APP_URL]: " APP_URL
    APP_URL="${APP_URL:-$DEFAULT_APP_URL}"
    read -r -p "DB_DATABASE: " DB_DATABASE
    read -r -p "DB_USERNAME: " DB_USERNAME
    read -r -p "DB_PASSWORD: " DB_PASSWORD
    echo

    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
        echo "❌ DB_DATABASE and DB_USERNAME are required"
        exit 1
    fi

    set_env APP_ENV "production"
    set_env APP_DEBUG "false"
    set_env APP_URL "$APP_URL"
    set_env DB_DATABASE "$DB_DATABASE"
    set_env DB_USERNAME "nextfusi_mirza"
    set_env DB_PASSWORD "$DB_PASSWORD"

    "$PHP" artisan key:generate --force
    trap - EXIT
    echo "✅ .env created"
fi

# Send every request to public/ when the domain points at the project root
if [ ! -f .htaccess ]; then
    cat > .htaccess <<'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
EOF
    echo "✅ .htaccess created"
fi

# Link public/storage so uploaded files (photos, logos) can be served
if [ ! -L public/storage ]; then
    "$PHP" artisan storage:link
    echo "✅ Storage linked"
fi

"$PHP" artisan down
echo "✅ Maintenance mode ON"

# Migrate
"$PHP" artisan migrate --force
echo "✅ Migration done"

# Add any new permissions (only creates missing ones — never removes a
# permission or changes which role has it). --force is required in
# production, otherwise the confirmation prompt fails and stops the deploy
# while the site is still in maintenance mode.
"$PHP" artisan db:seed --class=PermissionSeeder --force
"$PHP" artisan permission:cache-reset
echo "✅ Permissions synced"

# Cache clear
"$PHP" artisan optimize:clear
"$PHP" artisan filament:optimize-clear
echo "✅ Cache cleared"

# Cache rebuild
"$PHP" artisan optimize
"$PHP" artisan filament:optimize
echo "✅ Cache rebuilt"

# Force the queue worker to load the new code
"$PHP" artisan queue:restart
echo "✅ Queue restarted"

"$PHP" artisan up
echo "✅ Maintenance mode OFF"

echo "🎉 Deployment finished!"
