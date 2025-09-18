#!/bin/bash
set -e

echo "Initializing Project..."

if ! docker info > /dev/null 2>&1; then
    echo "Docker is not running."
    exit 1
fi

make composer-install
make up
sleep 5