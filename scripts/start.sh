#!/bin/bash
set -e

echo "Running"

if [ "$1" = "local" ]; then
    echo "Running locally..."
    if [ ! -f "vendor/autoload.php" ]; then
        echo "Installing dependencies first"
        composer install
    fi
    php bin/console app:main --loop --datadir=./data
else
    echo "Running in Docker"
    if ! docker-compose ps | grep -q "Up"; then
        echo "Starting Docker containers"
        make up
        sleep 3
    fi
    make loop
fi