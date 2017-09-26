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
ifneq (,$(findstring Couchbase, $(ADAPTER)))
	docker-compose up -d couchbase
	# wait for couchbase to be up & bucket created
	$(eval cmd='docker-compose exec couchbase /bin/sh -c "curl -f http://localhost:8091/pools/default/buckets/default"')
	eval $(cmd) || while [ $$? -ne 0 ]; do sleep 1; eval $(cmd); done;
endif
ifneq (,$(findstring Memcached, $(ADAPTER)))
	docker-compose up -d memcached
	# not sure how to check memcached status from inside its container
endif
ifneq (,$(findstring MySQL, $(ADAPTER)))
	docker-compose up -d mysql
	# wait for mysql to be up & db created
	$(eval cmd='docker-compose exec mysql /bin/sh -c "mysql -hmysql -P3306 -uroot -e\"SELECT 1\" cache"')
	eval $(cmd) || while [ $$? -ne 0 ]; do sleep 1; eval $(cmd); done;
endif
ifneq (,$(findstring PostgreSQL, $(ADAPTER)))
	docker-compose up -d postgresql
	# postgresql doesn't ship with a client, so we can't check if a connection
	# can be established; but it loads fast enough that it shouldn't be an issue
endif
ifneq (,$(findstring Redis, $(ADAPTER)))
	docker-compose up -d redis
	$(eval cmd='docker-compose exec redis /bin/sh -c "redis-cli ping"')
	eval $(cmd) || while [ $$? -ne 0 ]; do sleep 1; eval $(cmd); done;
endif
	docker-compose up -d $(PHP)

down:
ifneq (,$(findstring Couchbase, $(ADAPTER)))
	docker-compose stop couchbase
endif
ifneq (,$(findstring Memcached, $(ADAPTER)))
	docker-compose stop memcached
endif
ifneq (,$(findstring MySQL, $(ADAPTER)))
	docker-compose stop mysql
endif
ifneq (,$(findstring PostgreSQL, $(ADAPTER)))
	docker-compose stop postgresql
endif
ifneq (,$(findstring Redis, $(ADAPTER)))
	docker-compose stop redis
endif
	docker-compose stop -t0 $(PHP)

test:
	# Usage:
	# make test - tests all adapters on latest PHP version
	# make test PHP=5.6 ADAPTER=Memcached - tests Memcached on PHP 5.6
	[ $(UP) -eq 1 ] && make up PHP=$(PHP) ADAPTER=$(ADAPTER) || true
	$(eval cmd='docker-compose run $(PHP) "vendor/bin/phpunit --group $(ADAPTER)"')
	eval $(cmd); status=$$?; [ $(DOWN) -eq 1 ] && make down PHP=$(PHP) ADAPTER=$(ADAPTER); exit $$status
