#!/bin/bash

echo "Waiting for MySQL..."
MAX_TRIES=30
COUNT=0

while [ $COUNT -lt $MAX_TRIES ]; do
    if mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; then
        echo "MySQL is ready!"
        break
    fi
    
    COUNT=$((COUNT+1))
    echo "Attempt $COUNT of $MAX_TRIES..."
    sleep 2
done

if [ $COUNT -eq $MAX_TRIES ]; then
    echo "Error: MySQL did not become ready in time"
    exit 1
fi

# Verificar si las tablas existen
php artisan migrate:status
if [ $? -ne 0 ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

echo "Database setup completed!"