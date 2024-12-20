SHELL := /bin/bash
# defaults for `make test`
PHP ?=
ADAPTER ?= Apc,Couchbase,Flysystem,Memcached,MemoryStore,MySQL,PostgreSQL,Redis,SQLite
GROUP ?= #adapter,buffered,collections,keyvaluestore,psr6,psr16,shard,transactional,stampede
VOLUME_BINDS ?= src,tests,build,.php-cs-fixer.php,phpunit.xml

install:
	wget -q -O - https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
	composer install
	cp composer.json composer.bak
	composer require league/flysystem
	composer require couchbase/couchbase
	mv composer.bak composer.json

docs:
	docker run --rm -v $$(pwd)/docs:/data/docs -w /data php:cli bash -c "\
		apt-get update && apt-get install -y wget git zip unzip;\
		docker-php-ext-install zip;\
		wget -q -O - https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer;\
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

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=8.3 ADAPTER=Memcached - tests Memcached on PHP 8.3
	VOLUMES="";\
	for VOLUME in $$(echo "$(VOLUME_BINDS)" | tr "," "\n"); do VOLUMES="$$VOLUMES -v $$(pwd)/$$VOLUME:/var/www/$$VOLUME"; done;\
	test "$(PHP)" && TEST_CONTAINER=php-$(PHP) || TEST_CONTAINER=php;\
	DEPENDENT_CONTAINERS="$(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr 'A-Z,' 'a-z '))";\
	RELEVANT_CONTAINERS="$$TEST_CONTAINER $(filter-out apc flysystem memorystore sqlite, $(shell echo $(ADAPTER) | tr 'A-Z,' 'a-z '))";\
	docker-compose up --no-deps --wait -d $$DEPENDENT_CONTAINERS;\
	GROUP_ARRAY=($$(echo "$(GROUP)" | tr "," "\n"));\
	docker-compose run --no-deps $$VOLUMES $$TEST_CONTAINER env XDEBUG_MODE=coverage vendor/bin/phpunit $${GROUP_ARRAY[@]/#/--group } --testsuite $(ADAPTER) --coverage-clover build/coverage-$(PHP)-$(ADAPTER).clover --configuration=phpunit.xml;\
	TEST_STATUS=$$?;\
	docker-compose stop -t0 $$RELEVANT_CONTAINERS;\
	exit $$TEST_STATUS

format:
	VOLUMES=""
	for VOLUME in $$(echo "$(VOLUME_BINDS)" | tr "," "\n"); do VOLUMES="$$VOLUMES -v $$(pwd)/$$VOLUME:/var/www/$$VOLUME"; done;\
	docker-compose run --no-deps $$VOLUMES php sh -c "vendor/bin/php-cs-fixer fix"

.PHONY: docs
