
up:
	docker-compose up -d

stop:
	docker-compose stop

start:
	docker-compose exec app bash php bin/console app:traffic-daemon

run:
	docker-compose exec app bash php bin/console app:main --datadir=./data

daemon:
	docker-compose exec app bash php bin/console app:main --daemon --datadir=/data/top-1m.csv --logfile=/var/log/traffic.log

shell:
	docker-compose exec app bash

composer-install:
	docker-compose run --rm composer install --ignore-platform-reqs

composer-update:
	docker-compose run --rm composer update --ignore-platform-reqs

composer-validate:
	docker-compose run --rm composer validate

# Code Quality Tools
deptrac:
	docker-compose run --rm composer exec deptrac

deptrac-debug:
	docker-compose run --rm composer exec deptrac -- --debug

ecs:
	docker-compose run --rm composer exec ecs check

ecs-fix:
	docker-compose run --rm composer exec ecs check -- --fix

rector:
	docker-compose run --rm composer exec rector --dry-run

rector-fix:
	docker-compose run --rm composer exec rector
