
start:
	php bin/console app:traffic-daemon

run:
	php bin/console app:main --datadir=/data/top-1m.csv

daemon:
	php bin/console app:main --daemon --datadir=/data/top-1m.csv --logfile=/var/log/traffic.log


