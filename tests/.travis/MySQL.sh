sudo kill -9 `sudo lsof -t -i:3307` # kill listeners on required port
docker run -d -p 3306:3307 -e MYSQL_ALLOW_EMPTY_PASSWORD=yes -e MYSQL_DATABASE=cache mysql
