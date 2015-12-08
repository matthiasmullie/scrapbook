mkdir /tmp/cache

# not adding to composer.json immediately, as that also makes tests for other
# adapters fail on 5.3, even though we don't need flysystem there
composer require league/flysystem
