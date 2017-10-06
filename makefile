docs:
	wget http://apigen.org/apigen.phar
	chmod +x apigen.phar
	php apigen.phar generate --source=src,vendor/cache,vendor/psr --skip-doc-path="*/vendor/*,*/psr-simplecache/*" --destination=docs --template-theme=bootstrap
	rm apigen.phar

# defaults for `make test`
PHP ?= '7.1'
ADAPTER ?= 'Apc,Couchbase,Flysystem,Memcached,MemoryStore,MySQL,PostgreSQL,Redis,SQLite'
UP ?= 1
DOWN ?= 1

up:
	docker-compose -f docker-compose.yml -f docker-compose.$(PHP).yml up --no-deps -d $(shell echo $(ADAPTER) | tr "A-Z," "a-z ") php

down:
	docker-compose stop -t0 $(shell echo $(ADAPTER) | tr "A-Z," "a-z ") php

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=5.6 ADAPTER=Memcached - tests Memcached on PHP 5.6
	[ $(UP) -eq 1 ] && make up PHP=$(PHP) ADAPTER=$(ADAPTER) || true
	$(eval cmd='docker-compose run --no-deps php "vendor/bin/phpunit --group $(ADAPTER)"')
	eval $(cmd); status=$$?; [ $(DOWN) -eq 1 ] && make down PHP=$(PHP) ADAPTER=$(ADAPTER); exit $$status
