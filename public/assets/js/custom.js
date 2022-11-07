(function($){
	"use strict";

	/* 03 Mobile Navigation */

	var jPanelMenu = {};
	$( function () {
		jPanelMenu = $.jPanelMenu( {
			menu: '#responsive', animated: false, keyboardShortcuts: true
		} );
		jPanelMenu.on();

		$( document ).on( 'click', jPanelMenu.menu + ' li a', function ( e ) {
			if ( jPanelMenu.isOpen() && $( e.target ).attr( 'href' ).substring( 0, 1 ) == '#' ) {
				jPanelMenu.close();
			}
		} );

		$( document ).on( 'touchend', '.menu-trigger', function ( e ) {
			jPanelMenu.triggerMenu();
			e.preventDefault();
			return false;
		} );
	} );

	/* 10 ToolTip */

	$( ".tooltip.top" ).tipTip( {
		defaultPosition: "top"
	} );

	$( ".tooltip.bottom" ).tipTip( {
		defaultPosition: "bottom"
	} );

	$( ".tooltip.left" ).tipTip( {
		defaultPosition: "left"
	} );

	$( ".tooltip.right" ).tipTip( {
		defaultPosition: "right"
	} );

	/* "Build your own cache" - features */

	var adapters = {
		memcached: {
			src: "// create \\Memcached object pointing to your Memcached server\n" +
				"$client = new \\Memcached();\n" +
				"$client->addServer('localhost', 11211);\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\Memcached($client);\n",
			composer: 'pecl install memcached'
		},

		redis: {
			src: "// create \\Redis object pointing to your Redis server\n" +
				"$client = new \\Redis();\n" +
				"$client->connect('127.0.0.1');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\Redis($client);\n",
			composer: 'pecl install redis'
		},

		couchbase: {
			src: "// create \\Couchbase\\Bucket object pointing to your Couchbase server\n" +
				"$options = new \\Couchbase\\ClusterOptions();\n" +
				"$options->credentials('username', 'password');\n" +
				"$cluster = new \\Couchbase\\Cluster('couchbase://localhost', $options);\n" +
				"$bucket = $cluster->bucket('default');\n" +
				"$collection = $bucket->defaultCollection();\n" +
				"$bucketManager = $cluster->buckets();\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\Couchbase($collection, $bucketManager, $bucket);\n",
			composer: 'pecl install couchbase'
		},

		apc: {
			src: "// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\Apc();\n",
			composer: 'pecl install apcu'
		},

		mysql: {
			src: "// create \\PDO object pointing to your MySQL server\n" +
				"$client = new \\PDO('mysql:dbname=cache;host=127.0.0.1', 'root', '');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\MySQL($client);\n",
			composer: ''
		},


		sqlite: {
			src: "// create \\PDO object pointing to your SQLite database\n" +
				"$client = new \\PDO('sqlite:cache.db');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\SQLite($client);\n",
			composer: ''
		},

		pgsql: {
			src: "// create \\PDO object pointing to your PostgreSQL database\n" +
				"$client = new \\PDO('pgsql:user=postgres dbname=cache password=');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\PostgreSQL($client);\n",
			composer: ''
		},

		flysystem: {
			src: "// create Flysystem object\n" +
				"$adapter = new \\League\\Flysystem\\Adapter\\Local('/path/to/cache', LOCK_EX);\n" +
				"$filesystem = new \\League\\Flysystem\\Filesystem($adapter);\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\Flysystem($filesystem);\n",
			composer: 'composer require league/flysystem'
		},

		memory: {
			src: "// create Scrapbook cache object\n" +
				"$cache = new \\MatthiasMullie\\Scrapbook\\Adapters\\MemoryStore();\n",
			composer: ''
		}
	},

	types = {
		keyvaluestore: {
			src: "// set a value\n" +
				"$cache->set('key', 'value'); // returns true\n" +
				"\n" +
				"// get a value\n" +
				"$cache->get('key'); // returns 'value'\n",
			composer: ''
		},

		psr6: {
			src: "// create Pool object from cache engine\n" +
				"$pool = new \\MatthiasMullie\\Scrapbook\\Psr6\\Pool($cache);\n" +
				"\n" +
				"// get item from Pool\n" +
				"$item = $pool->getItem('key');\n" +
				"\n" +
				"// get item value\n" +
				"$value = $item->get();\n" +
				"\n" +
				"// ... or change the value & store it to cache\n" +
				"$item->set('updated-value');\n" +
				"$pool->save($item);\n",
			composer: ''
		},

		psr16: {
			src: "// create Simplecache object from cache engine\n" +
				"$simplecache = new \\MatthiasMullie\\Scrapbook\\Psr16\\SimpleCache($cache);\n" +
				"\n" +
				"// get value from cache\n" +
				"$value = $simplecache->get('key');\n" +
				"\n" +
				"// ... or store a new value to cache\n" +
				"$simplecache->set('key', 'updated-value');\n",
			composer: ''
		}
	},

	extras = {
		buffered: {
			'src-begin': "// create buffered cache layer over our real cache\n" +
						"$cache = new \\MatthiasMullie\\Scrapbook\\Buffered\\BufferedStore($cache);\n",
			'src-end': "",
			composer: ''
		},

		transactional: {
			'src-begin': "// create transactional cache layer over our real cache\n" +
						"$cache = new \\MatthiasMullie\\Scrapbook\\Buffered\\TransactionalStore($cache);\n" +
						"\n" +
						"// begin a transaction\n" +
						"$cache->begin();\n",
			'src-end': "// now commit all write operations\n" +
						"$cache->commit();\n",
			composer: ''
		},

		stampede: {
			'src-begin': "// create stampede protector layer over our real cache\n" +
			"$cache = new \\MatthiasMullie\\Scrapbook\\Scale\\StampedeProtector($cache);\n",
			'src-end': "",
			composer: ''
		},

		shard: {
			'src-begin': "// creating a second cache server\n" +
			"(MemoryStore for this example, but could be anything)\n" +
			"$cache2 = new \\MatthiasMullie\\Scrapbook\\Adapters\\MemoryStore();\n\n" +
			"// create shard layer over our real cache\n" +
			"// all data will now automatically be distributed between both caches\n" +
			"$cache = new \\MatthiasMullie\\Scrapbook\\Scale\\Shard($cache, $cache2);\n",
			'src-end': "",
			composer: ''
		}
	},

	generateCode = function() {
		var adapter = $('#features [name=adapter]:checked').val(),
			type = $('#features [name=type]:checked').val(),
			extra = [],
			composer = ['composer require matthiasmullie/scrapbook'], src, i;

		// gather selected extras in array
		$('#features [name=extra]:checked').each(function() {
			extra.push($(this).val())
		});

		// build source & composer data, based on selected values
		src = adapters[adapter].src + "\n";
		composer.push(adapters[adapter].composer);
		composer.push(types[type].composer);
		for (i in extra) {
			if (extras[extra[i]]['src-begin']) {
				src += extras[extra[i]]['src-begin'] + "\n";
			}
			composer.push(extras[extra[i]].composer);
		}
		src += types[type].src + "\n";

		for (i in extra) {
			if (extras[extra[i]]['src-end']) {
				src += extras[extra[i]]['src-end'] + "\n";
			}
		}

		$('#build_source').val(src.trim());

		// only display unique and non-"" composer requirements
		composer = $.grep(composer, function(v, k){
			return $.inArray(v ,composer) === k && v !== "";
		});
		$('#build_composer').val(composer.join("\n"));
	};

/*
	// transactional auto-selects buffered
	$('#features #extra_transactional').on('change', function() {
		if ($(this).is(':checked')) {
			$('#extra_buffered').prop('checked', true);
		}
	});

	// unselecting buffered auto-unselects transactional
	$('#features #extra_buffered').on('change', function() {
		if (!$(this).is(':checked')) {
			$('#extra_transactional').prop('checked', false);
		}
	});
*/
	// generate source right away, with initial defaults
	if ($('#features [name=adapter], #features [name=type], #features [name=extra]').length) {
		generateCode();
	}

	// and when users select different configurations
	$('#features [name=adapter], #features [name=type], #features [name=extra]').on('change', generateCode);

})(jQuery);
