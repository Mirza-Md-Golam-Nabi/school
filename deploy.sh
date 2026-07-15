#!/bin/bash

echo "🚀 Deployment started..."

# Git থেকে latest code নামাও
git pull origin dev
echo "✅ Git pull done"

# Composer install
/opt/cpanel/ea-php83/root/usr/bin/php composer.phar install --no-dev --optimize-autoloader
echo "✅ Composer done"

# .env file না থাকলে copy করো
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
    echo "✅ .env created"
fi

# Cache clear
php artisan optimize:clear
echo "✅ Cache cleared"

# Migrate
php artisan migrate --force
echo "✅ Migration done"

# Cache rebuild
php artisan optimize
echo "✅ Cache rebuilt"


echo "🎉 Deployment finished!"
