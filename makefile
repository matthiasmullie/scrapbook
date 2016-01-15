docs:
	wget http://apigen.org/apigen.phar
	chmod +x apigen.phar
	php apigen.phar generate --source=src,vendor/cache,vendor/psr --skip-doc-path="*/vendor/*" --destination=docs --template-theme=bootstrap
	rm apigen.phar
