#!/bin/sh

# Exit on error
set -e

# Clear caches
php artisan config:clear
php artisan cache:clear

# Run migrations (force for production environments like Render)
# WARNING: Ensure your DB is connected
php artisan migrate --force

# Start Apache in the foreground
apache2-foreground
