docs:
	wget http://apigen.org/apigen.phar
	chmod +x apigen.phar
	php apigen.phar generate --source=src,vendor/cache,vendor/psr --skip-doc-path="*/vendor/*,*/psr-simplecache/*" --destination=docs --template-theme=bootstrap
	rm apigen.phar

# defaults for `make test`
PHP ?= '7.1'
ADAPTER ?= 'Apc,Couchbase,Flysystem,Memcached,MemoryStore,MySQL,PostgreSQL,Redis,SQLite'

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=5.6 ADAPTER=Memcached - tests Memcached on PHP 5.6
ifneq (,$(findstring Couchbase, $(ADAPTER)))
	docker-compose up -d couchbase
	# wait for couchbase to be up & bucket created
	$(eval cmd='docker-compose exec couchbase /bin/sh -c "curl -f http://localhost:8091/pools/default/buckets/default"')
	eval $(cmd) || while [ $$? -ne 0 ]; do sleep 1; eval $(cmd); done;
endif
ifneq (,$(findstring Memcached, $(ADAPTER)))
	docker-compose up -d memcached
endif
ifneq (,$(findstring MySQL, $(ADAPTER)))
	docker-compose up -d mysql
endif
ifneq (,$(findstring PostgreSQL, $(ADAPTER)))
	docker-compose up -d postgresql
endif
ifneq (,$(findstring Redis, $(ADAPTER)))
	docker-compose up -d redis
endif
	docker-compose up -d $(PHP)
	docker-compose run $(PHP) 'vendor/bin/phpunit --group "$(ADAPTER)"'; status=$$?; docker-compose stop; exit $$status
