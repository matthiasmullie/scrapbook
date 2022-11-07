# defaults for `make test`
PHP ?= '8.1'
ADAPTER ?= 'Apc,Couchbase,Flysystem,Memcached,MemoryStore,MySQL,PostgreSQL,Redis,SQLite'
UP ?= 1
DOWN ?= 1

install:
	wget -q -O - https://getcomposer.org/installer | php
	./composer.phar install
	cp composer.json composer.bak
	./composer.phar require league/flysystem
	./composer.phar require cache/integration-tests
	test `php-config --vernum` -ge 70400 && ./composer.phar require couchbase/couchbase
	mv composer.bak composer.json
	rm composer.phar

docs:
	make install
	wget http://apigen.org/apigen.phar
	chmod +x apigen.phar
	php apigen.phar generate --source=src,vendor/cache,vendor/psr --skip-doc-path="*/vendor/*,*/psr-simplecache/*" --destination=docs --template-theme=bootstrap
	rm apigen.phar

up:
	docker-compose up --no-deps -d $(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr "A-Z," "a-z ")) php

down:
	docker-compose stop -t0 $(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr "A-Z," "a-z ")) php

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=8.0 ADAPTER=Memcached - tests Memcached on PHP 8.0
	[ $(UP) -eq 1 ] && make up PHP=$(PHP) ADAPTER=$(ADAPTER) || true
	$(eval cmd='docker-compose run --no-deps php-$(PHP) env XDEBUG_MODE=coverage vendor/bin/phpunit --group $(ADAPTER)')
	eval $(cmd); status=$$?; [ $(DOWN) -eq 1 ] && make down PHP=$(PHP) ADAPTER=$(ADAPTER); exit $$status
