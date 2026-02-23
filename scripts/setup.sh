#!/usr/bin/env bash
set -euo pipefail

echo "[1/4] Laravel setup"
cd laravel
cp -n .env.example .env || true
composer install
mkdir -p database
touch database/database.sqlite
php artisan key:generate
php artisan migrate:fresh --seed

echo "[2/4] Terminal gateway setup"
cd ../terminal-gw
cp -n .env.example .env || true
npm install

echo
echo "Done."
echo "Run Laravel:  cd laravel && php artisan serve --host 0.0.0.0 --port 8080"
echo "Run Gateway:  cd terminal-gw && npm start"
