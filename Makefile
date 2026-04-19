.PHONY: help install test cs cs-fix stan check all

help: ## Show available targets
	@awk 'BEGIN{FS=":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\n"} /^[a-zA-Z_-]+:.*##/ {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install composer dependencies
	composer install

test: ## Run PHPUnit test suite
	vendor/bin/phpunit

cs: ## Check coding standard (dry run, diff output)
	vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Apply coding standard fixes in place
	vendor/bin/php-cs-fixer fix

stan: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse

check: cs stan test ## Run full CI check (CS dry-run + PHPStan + tests)

all: cs-fix stan test ## Auto-fix CS + PHPStan + tests (local dev shortcut)
