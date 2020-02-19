COMPOSE=docker-compose
PHP=$(COMPOSE) run --rm --no-deps dplr

sshkeygen:
	@ssh-keygen -P '' -f ./key && mv key docker/images/dplr && mv key.pub docker/images/remote

fixer:
	@$(PHP) vendor/bin/php-cs-fixer fix --verbose

phpunit:
	@$(PHP) vendor/bin/phpunit

check: fixer phpunit
