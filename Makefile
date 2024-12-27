
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


