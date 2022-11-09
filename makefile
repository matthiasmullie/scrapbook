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
	test `php-config --vernum` -ge 70400 && ./composer.phar require couchbase/couchbase
	./composer.phar require cache/integration-tests --with-all-dependencies
	mv composer.bak composer.json
	rm composer.phar

docs:
	docker run --rm -v $$(pwd)/docs:/data/docs -w /data php:cli bash -c "\
		apt-get update && apt-get install -y wget git zip unzip;\
		docker-php-ext-install zip;\
		wget -q -O - https://getcomposer.org/installer | php;\
		mv composer.phar /usr/local/bin/composer;\
		wget -q https://phpdoc.org/phpDocumentor.phar;\
		TEMPLATE=\$$(grep -o "{{#TAGS}}.*{{/TAGS}}" docs/index.html);\
		HTML='';\
		git clone https://github.com/matthiasmullie/scrapbook.git code && cd code;\
		while read TAG; do\
			git clean -fx;\
			git checkout \$$TAG;\
			git reset --hard;\
			composer install --ignore-platform-reqs;\
			php ../phpDocumentor.phar --directory=src --directory=vendor/psr/cache --directory=vendor/psr/simple-cache --target=../docs/\$$TAG --visibility=public --defaultpackagename=Scrapbook --title=Scrapbook;\
			HTML=\$$HTML\$$(echo \$$TEMPLATE | sed -e \"s/{{[#/]TAGS}}//g\" | sed -e \"s/{{\.}}/\$$TAG/g\");\
		done <<< \$$(git rev-parse --abbrev-ref HEAD && git tag --sort=-v:refname);\
		sed -i \"s|\$$TEMPLATE|\$$HTML|g\" ../docs/index.html"

up:
	docker-compose up --no-deps -d $(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr "A-Z," "a-z ")) php-$(PHP)

down:
	docker-compose stop -t0 $(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr "A-Z," "a-z ")) php-$(PHP)

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=8.0 ADAPTER=Memcached - tests Memcached on PHP 8.0
	[ $(UP) -eq 1 ] && make up PHP=$(PHP) ADAPTER=$(ADAPTER) || true
	$(eval cmd='docker-compose run --no-deps php-$(PHP) env XDEBUG_MODE=coverage vendor/bin/phpunit --group $(ADAPTER)')
	eval $(cmd); status=$$?; [ $(DOWN) -eq 1 ] && make down PHP=$(PHP) ADAPTER=$(ADAPTER); exit $$status

.PHONY: docs
