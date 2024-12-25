
up:
	docker-compose up -d

stop:
	docker-compose stop

start:
	php bin/console app:traffic-daemon

run:
	php bin/console app:main --datadir=./data

daemon:
	php bin/console app:main --daemon --datadir=/data/top-1m.csv --logfile=/var/log/traffic.log


