# Changelog


## [1.2.1] - 2016-04-05
### Fixed
- Made MySQL's value a LONGBLOB, which can store bigger values


## [1.2.0] - 2016-03-21
### Removed
- Removed Taggable, since it seems `cache/taggable-cache` now does the same
- Removed deprecated Filesystem adapter


## [1.1.0] - 2016-02-01
### Added
- Added sharding class

### Fixed
- MemoryStore no longer evicts everything if infinite memory is allocated to PHP


## [1.0.10] - 2016-01-22
### Fixed
- Don't let expired deferred items appear to have hit cache


## [1.0.9] - 2016-01-14
### Added
- Add cache/taggable-cache implementation that works for all PSR-6 libraries
- Include cache/integration-tests test suite

### Changed
- Revamped unit tests: no longer use dataProvider, but provide grouped suites

### Fixed
- Worked around APC's weird behavior of only clearing expires per page request
- Store `get` result in buffer if it wasn't in there already
- Encode certain characters in Memcached keys
- Serialize arrays & objects in Couchbase, because it forgets their data type


## [1.0.8] - 2016-01-12
### Fixed
- Deferred items now register as hit in psr/cache Item
- Non-string keys now fail in psr/cache Pool
- Deleting non-existing keys from cache also return true in psr/cache Pool
- Auto-commit deferred items on psr/cache Pool destruction
- If psr/cache Item::expiresAfter is passed null explicitly, default to forever
- SQL adapters now also return same data type as was stored for numerics
- Fixed psr/cache Pool::save return value when storing a non-existing Item


## [1.0.7] - 2015-12-23
### Added
- Properly support PHPUnit's --filter to narrow down adapters

### Fixed
- Distinguish between `false` & no value in cache, where $token should be null


## [1.0.6] - 2015-12-14
### Added
- composer.json now requires `psr/cache`

### Changed
- Travis scripts no longer install services but user Docker containers
- All `Psr6` methods accepting `$key` now throw InvalidArgumentException

### Removed
- Removed included `Psr\Cache` files

### Fixed
- Fixed Couchbase `flush` return value


## [1.0.5] - 2015-11-17
### Added
- Added stampede protection

### Fixed
- Made Redis `multi/exec` consistently return array


## [1.0.4] - 2015-11-04
### Added
- Added `Psr6\Pool::hasItem`, per PSR-6 spec
- Added `Psr6\Pool::deleteItem`, per PSR-6 spec

### Changed
- `Psr6\Pool::deleteItems` returns result instead of `static`, per PSR-6 spec
- `Psr6\Pool::save` returns result instead of `static`, per PSR-6 spec
- `Psr6\Pool::saveDeferred` returns result instead of `static`, per PSR-6 spec

### Removed
- Removed `Psr6\Item::exists`, per PSR-6 spec
- Removed `Psr6\Item::getExpiration`, per PSR-6 spec

### Fixed
- Make sure CAS tokens are null if value doesn't exist
- Normalized CAS token result on HHVM
- Restored original Filsystem adapter (for PHP5.3 B/C), but still deprecated


## [1.0.3] - 2015-10-21
### Added
- Added `league/flysystem` adapter

### Removed
- Deprecated Filesystem adapter


## [1.0.2] - 2015-10-17
### Changed
- Optimized transactions (e.g. multiple `set` can be combined into `setMulti`)
- Transaction rollback now restores original values, instead of clearing them

### Fixed
- When doing `set` on existing value in MemoryStore, don't doublecount the size
- Fixed SQL return values when replacement value is the same


## [1.0.1] - 2015-10-14
### Added
- Added `Psr6\Item::expiresAt`, per PSR-6 spec
- Added `Psr6\Item::expiresAfter`, per PSR-6 spec
- Added memory limit to MemoryStore & evict data, to prevent it from crashing
- Execute tests/Adapters/*, there can be adapter-specific tests too
- Implement nested transactions

### Changed
- Explicitly test file existence instead of using `@file_get_contents`

### Removed
- Removed `Psr6\Item::setExpiration`, per PSR-6 spec

### Fixed
- SQL adapter returns early if there's no data to be deleted


## [1.0.0] - 2015-09-04
### Added
- Apc adapter
- Couchbase adapter
- Filesystem adapter
- Memcached adapter
- MySQL adapter
- PostgreSQL adapter
- Redis adapter
- SQLite adapter
- MemoryStore adapter, for testing
- Buffered cache, to prevent multiple lookups for same value
- Transactional cache, to guarantee consistency of storing multiple values
- PSR-6 compatible interface for all of the above


[1.0.0]: https://github.com/matthiasmullie/scrapbook/compare/16fa802a3e72aee429e48378a724b11da9d4cada...1.0.0
[1.0.1]: https://github.com/matthiasmullie/scrapbook/compare/1.0.0...1.0.1
[1.0.2]: https://github.com/matthiasmullie/scrapbook/compare/1.0.1...1.0.2
[1.0.3]: https://github.com/matthiasmullie/scrapbook/compare/1.0.2...1.0.3
[1.0.4]: https://github.com/matthiasmullie/scrapbook/compare/1.0.3...1.0.4
[1.0.5]: https://github.com/matthiasmullie/scrapbook/compare/1.0.4...1.0.5
[1.0.6]: https://github.com/matthiasmullie/scrapbook/compare/1.0.5...1.0.6
[1.0.7]: https://github.com/matthiasmullie/scrapbook/compare/1.0.6...1.0.7
[1.0.8]: https://github.com/matthiasmullie/scrapbook/compare/1.0.7...1.0.8
[1.0.9]: https://github.com/matthiasmullie/scrapbook/compare/1.0.8...1.0.9
[1.0.10]: https://github.com/matthiasmullie/scrapbook/compare/1.0.9...1.0.10
[1.1.0]: https://github.com/matthiasmullie/scrapbook/compare/1.0.10...1.1.0
[1.2.0]: https://github.com/matthiasmullie/scrapbook/compare/1.1.0...1.2.0
[1.2.1]: https://github.com/matthiasmullie/scrapbook/compare/1.2.0...1.2.1
