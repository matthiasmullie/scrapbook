(function($){
	"use strict";

	/* 01 Sticky Header */

	function stickyPosition( val, body, header ) {
		$( header ).css( {marginTop: val} );
		$( body ).css( {paddingTop: val} );
	}

	var logo = $( '#logo' ), header = $( '#header' ), menu = $( '.menu ul > li > a' );

	var smallHeight = 60, // set compact header height
		durationAnim = 150, // animation speed
		defaultHeight = parseInt( header.css( 'height' ) ),
		defLogoMarginTop = parseInt( logo.css( 'margin-top' ) ),
		defMenuPaddingTop = parseInt( menu.css( 'padding-top' ) ),
		defMenuPaddingBottom = parseInt( menu.css( 'padding-bottom' ) ),
		small_height = defaultHeight - smallHeight;

	$( "#header" ).css( {position: "fixed"} );

	var stickyValue = defaultHeight - 20;
	stickyPosition( -stickyValue, null, "#header" );
	stickyPosition( stickyValue, "body", null );

	var stickymenu = function () {
		var offset = $( window ).scrollTop(), // Get how much of the window is scrolled
			header = $( '#header' ), src = logo.find( 'img' ).attr( 'src' );

		var menuPaddingTop = defMenuPaddingTop - small_height / 4, menuPaddingBottom = defMenuPaddingBottom - small_height / 4, logoMarginTop = defLogoMarginTop - 1 - small_height / 4;

		if ( $( window ).width() > 767 ) {
			if ( offset > 60 ) { // if it is over 60px (the initial width)
				if ( !header.hasClass( 'compact' ) ) {
					header.animate( {
						height: defaultHeight - small_height
					}, {
						queue: false,
						duration: durationAnim,
						complete: function () {
							header.addClass( 'compact' ).css( "overflow", "visible" );
							$( "#header .topbar" ).css( {display: "none"} );
							$( "#header" ).css( {opacity: "0.95"} );
						}
					} );
					logo.animate( {
						marginTop: logoMarginTop
					}, {
						queue: false, duration: durationAnim
					} );
					menu.animate( {
						paddingTop: menuPaddingTop,
						paddingBottom: menuPaddingBottom,
						margin: 0
					}, {
						queue: false, duration: durationAnim
					} );
				}
			} else if ( offset > -1 && offset < 60 ) {
				header.animate( {
					height: defaultHeight
				}, {
					queue: false,
					duration: durationAnim,
					complete: function () {
						header.removeClass( 'compact' ).css( "overflow", "visible" );
						$( "#header .topbar" ).css( {display: "block"} );
						$( "#header" ).css( {opacity: "1"} );
					}
				} );
				logo.stop().animate( {
					marginTop: defLogoMarginTop
				}, {
					queue: false, duration: durationAnim
				} );
				menu.animate( {
					paddingTop: defMenuPaddingTop,
					paddingBottom: defMenuPaddingBottom
				}, {
					queue: false, duration: durationAnim
				} );
			}
		}
	};

	stickymenu();
	$( window ).scroll( function () {
		stickymenu();
	} );

	// sticky header reset for mobile
	$( window ).resize( function () {
		var winWidth = $( window ).width();
		if ( winWidth < 767 ) {
			$( '#logo' ).css( 'marginTop', '' );
			$( '#header' ).css( 'height', '' ).removeClass( 'compact' );
			$( "#header" ).css( {position: ""} );
			$( '.menu ul > li > a' ).css( {
				'paddingTop': '', 'paddingBottom': ''
			} );

			stickyPosition( null, null, "#header" );
			stickyPosition( null, "body", null );
		} else {
			stickymenu();
			stickyPosition( -stickyValue, null, "#header" );
			stickyPosition( stickyValue, "body", null );
			$( "#header" ).css( {position: "fixed"} );
		}
	} );

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
				"$cache = new \\Scrapbook\\Adapters\\Memcached($client);\n",
			composer: "composer require scrapbook/memcached"
		},

		redis: {
			src: "// create \\Redis object pointing to your Redis server\n" +
				"$client = new \\Redis();\n" +
				"$client->connect('127.0.0.1');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\Scrapbook\\Adapters\\Redis($client);\n",
			composer: "composer require scrapbook/redis"
		},

		mysql: {
			src: "// create \\PDO object pointing to your MySQL server\n" +
				"$client = new \\PDO('mysql:dbname=cache;host=127.0.0.1', 'root', '');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\Scrapbook\\Adapters\\MySQL($client);\n",
			composer: "composer require scrapbook/sql"
		},


		sqlite: {
			src: "// create \\PDO object pointing to your SQLite database\n" +
				"$client = new \\PDO('sqlite:cache.db');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\Scrapbook\\Adapters\\SQLite($client);\n",
			composer: "composer require scrapbook/sql"
		},

		pgsql: {
			src: "// create \\PDO object pointing to your PostgreSQL database\n" +
				"$client = new \\PDO('pgsql:user=postgres dbname=cache password=');\n" +
				"// create Scrapbook cache object\n" +
				"$cache = new \\Scrapbook\\Adapters\\PostgreSQL($client);\n",
			composer: "composer require scrapbook/sql"
		},

		memory: {
			src: "// create Scrapbook cache object\n" +
				"$cache = new \\Scrapbook\\Adapters\\MemoryStore();\n",
			composer: "composer require scrapbook/key-value-store"
		}
	},

	types = {
		keyvaluestore: {
			src: "// set a value\n" +
				"$cache->set('key', 'value'); // returns true\n" +
				"\n" +
				"// get a value\n" +
				"$cache->get('key'); // returns 'value'\n",
			composer: "" // don't really need key-value-store here; every adapter will require it anyhow
		},

		psr6: {
			src: "// create Pool object from cache engine\n" +
				"$pool = new \\Scrapbook\\Psr6\\Pool($cache);\n" +
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
			composer: "composer require scrapbook/psr-cache"
		}
	},

	extras = {
		buffered: {
			'src-begin': "// create buffered cache layer over our real cache\n" +
						"$cache = new \\Scrapbook\\Buffered\\BufferedStore($cache);\n",
			'src-end': "",
			composer: "composer require scrapbook/buffered-cache"
		},

		transactional: {
			'src-begin': "// create transactional cache layer over our real cache\n" +
						"$cache = new \\Scrapbook\\Buffered\\TransactionalStore($cache);\n" +
						"\n" +
						"// begin a transaction\n" +
						"$cache->begin();\n",
			'src-end': "// now commit all write operations\n" +
						"$cache->commit();\n",
			composer: "composer require scrapbook/buffered-cache"
		}
	},

	generateCode = function() {
		var adapter = $('#features [name=adapter]:checked').val(),
			type = $('#features [name=type]:checked').val(),
			extra = [],
			composer = [], src;

		// gather selected extras in array
		$('#features [name=extra]:checked').each(function() {
			extra.push($(this).val())
		});

		// build source & composer data, based on selected values
		src = adapters[adapter].src + "\n";
		composer.push(adapters[adapter].composer)
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
