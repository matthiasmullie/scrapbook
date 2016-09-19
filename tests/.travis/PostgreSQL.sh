INI_PATH=`php -r "echo php_ini_loaded_file();"`
PHP_VERSION=`php -r "echo phpversion();"`

sudo kill -9 `sudo lsof -t -i:5432` # kill listeners on required port
docker run -d -p 5432:5432 -e POSTGRES_PASSWORD= -e POSTGRES_DB=cache postgres
