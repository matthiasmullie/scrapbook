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

Because Scrapbook supports a wide range of PHP versions and has adapters for a
good amount of services, testing could be laborious.

Docker images has been created to set up the entire environment, or just a
specific combination of PHP version + adapters. These can be launched from the
command line, as configured in the makefile. Just make sure you have installed
[Docker](https://docs.docker.com/engine/installation/) &
[Docker-compose](https://docs.docker.com/compose/install/).

To run the complete test suite, for all adapters, on the latest PHP release:

```sh
make test
```

Or a specific adapter (in this case Memcached):

```sh
make test ADAPTER=Memcached
```

Or a couple of adapters (in this case the SQL group):

```sh
make test ADAPTER=MySQL,PostgreSQL,SQLite
```

Or with a specific PHP version:

```sh
make test PHP=8.0 ADAPTER=MySQL,PostgreSQL,SQLite
```

GitHub Actions have been [configured](.github/workflows/test.yml) to run supported
PHP versions & adapters. Upon submitting a new pull request, that test suite will
be run & report back on your pull request. Please make sure the test suite passes.


### Writing tests

Please include tests for every change or addition to the code.


#### New adapter

To add a new adapter, just add a new *AdapterName*Provider.php file in the
[tests/Providers](tests/Providers) directory, similar to the existing adapters.
That file should extend from AdapterProvider, `::getAdapter` should return that
adapter's KeyValueStore implementation & `__construct` should throw a
`MatthiasMullie\Scrapbook\Exception\Exception` in case it fails to initialize.

There are also adapter-specific tests. Just look at
[MemoryStoreTest.php](tests/Adapters/MemoryStoreTest.php), for example.

Make sure to remember to also include the adapter in [test.yml](.github/workflows/test.yml).


#### Other new class

To create a new test that can be run for all adapters, make sure it extends from
[AdapterProviderTestInterface](tests/AdapterProviderTestInterface.php) and has a
static function `::suite` that calls
[AdapterProvider](tests/AdapterTestProvider.php). Or just extend
[AdapterTestCase](tests/AdapterTestCase.php) directly, which has that already
wired up.


## Coding standards

All code must follow [PSR-12](http://www.php-fig.org/psr/psr-12/). Just make sure
to run php-cs-fixer before submitting the code, it'll take care of the
formatting for you:

```sh
make format
```

Document the code thoroughly!


## License

Note that Scrapbook is MIT-licensed, which basically allows anyone to do
anything they like with it, without restriction.
