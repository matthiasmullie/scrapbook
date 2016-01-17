# How to contribute

## Issues

When [filing bugs](https://github.com/matthiasmullie/scrapbook/issues/new),
try to be as thorough as possible:
* What version of Scrapbook did you use?
* What did you try to do? ***Please post the relevant parts of your code.***
* What went wrong? ***Please include error messages, if any.***
* What was the expected result?


## Pull requests

Bug fixes and general improvements to the existing codebase are always welcome.
New features are also welcome, but will be judged on an individual basis. If
you'd rather not risk wasting your time implementing a new feature only to see
it turned down, please start the discussion by
[opening an issue](https://github.com/matthiasmullie/scrapbook/issues/new).

Where applicable, new features should follow the existing model of wrapping them
around a KeyValueStore object, which is in turn also a KeyValueStore. Like how
an adapter can be wrapped inside a StampedeProtected, which can in turn be
wrapped inside a TransactionalStore. This keeps functionality nicely isolated
(single responsibility) while still offering a consistent API.

Don't forget to add your changes to the [changelog](CHANGELOG.md).


## Testing

### Running the tests

To run the complete test suite, for all adapters:

```sh
vendor/bin/phpunit
```

Or a specific adapter (in this case Memcached):

```sh
vendor/bin/phpunit --group "Memcached"
```

Or a couple of adapters (in this case the SQL group):

```sh
vendor/bin/phpunit --group "MySQL,PostgreSQL,SQLite"
```

Some adapter rely on external services (cache server) & libraries (client-side
APIs). If you need help to install these, take a look inside the
[tests/.travis directory](tests/.travis), where the installation scripts for
Travis CI are located.

A lot of them use Docker to launch the cache servers but you can always install
the servers natively, in which case you may need to alter the adapters
configuration (e.g. because you're running the server on a different port). This
configuration is located in [tests/Adapters](tests/Adapters), in the `::get`
method of every adapter. If you do need to alter these in order to run the tests
on your machine, make sure not to commit those changes!

Travis CI has been [configured](.travis.yml) to run a matrix of all supported
PHP versions & adapters individually. Upon submitting a new pull request, that
test suite will be run & report back on your pull request. Please make sure the
test suite passes.


### Writing tests

Please include tests for every change or addition to the code.


#### New adapter

To add a new adapter, just add a new *AdapterName*Test.php file in the
[tests/Adapters](tests/Adapters) directory, similar to the existing adapters.
That file should implement AdapterInterface & `::get` should return that
adapter's KeyValueStore implementation & throw a
`MatthiasMullie\Scrapbook\Exception\Exception` in case it fails to initialize.

These adapter tests can also include adapter-specific tests. Just look at
[MemoryStoreTest.php](tests/Adapters/MemoryStoreTest.php), for example.

Make sure to remember to also include the adapter in [.travis.yml](.travis.yml)
and create an [installation script](tests/.travis) for Travis CI.


#### Other new class

To create a new test that can be run for all adapters, make sure it extends from
[AdapterProviderTestInterface](tests/AdapterProviderTestInterface.php) and has a
static function `::suite` that calls
[AdapterProvider](tests/AdapterProvider.php). Or just extend
[AdapterTestCase](tests/AdapterTestCase.php) directly, which has that already
wired up.


## Coding standards

All code must follow [PSR-2](http://www.php-fig.org/psr/psr-2/). Just make sure
to run php-cs-fixer before submitting the code, it'll take care of the
formatting for you:

```sh
vendor/bin/php-cs-fixer fix src
vendor/bin/php-cs-fixer fix tests
```

Document the code thoroughly!


## License

Note that Scrapbook is MIT-licensed, which basically allows anyone to do
anything they like with it, without restriction.
