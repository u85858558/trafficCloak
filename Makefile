init:
	make composer-install
	make up
	@echo "Project initialized! Use 'make loop' to start the service."

run:
	docker-compose exec app php bin/console app:main --loop --datadir=./data

up:
	docker-compose up -d

stop:
	docker-compose stop

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

psalm:
	docker-compose run --rm composer exec -- psalm -c psalm.xml

psalm-baseline:
	docker-compose run --rm composer exec -- psalm -c psalm.xml --set-baseline=psalm-baseline.xml
