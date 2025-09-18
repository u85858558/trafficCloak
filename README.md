# TrafficCloak
Purpose: The app creates a natural web browsing experience, making it much more difficult for third parties to track your real online activities and create accurate profiles of your web browsing habits.

## Implemented modules:

**Google**: generates random search queries using sentence templates and word pools.

**Wikipedia**: starts from a random Wikipedia article and follows internal links naturally, simulating research and knowledge-seeking behavior with configurable crawl depth.

**DNS**: performs random DNS lookups from the Top 1 Million domains list using DNS-over-HTTPS for enhanced privacy and realistic network traffic patterns.

## Module ideas:

- News Sites: randomly visits major news websites and scrolls through articles
- Proxy Manager: rotates through different proxy servers and VPN endpoints to further obfuscate traffic origin  
- E-commerce: browses product listings on shopping sites.  
- Streaming Services: generates traffic to Streaming sites like  

## Installation & Setup

### Quick Start
```bash
# Initialize the project (installs dependencies and starts containers)
make init

# Start the TrafficCloak service in loop mode
make run

# Alternative: use the initialization script
./scripts/init.sh
```

### Manual Setup
```bash
# Install PHP dependencies
make composer-install

# Start Docker containers
make up

# Run a single cycle
docker-compose exec app php bin/console app:main

# Run in continuous loop mode
docker-compose exec app php bin/console app:main --loop --interval 60
```