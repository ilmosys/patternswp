/**
 * PatternsWP — Block Editor Patterns Library
 *
 * Ready-to-use script using WordPress / Gutenberg packages (no build step).
 * Text domain: patternswp
 */
( function ( wp, lodash ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var useCallback = wp.element.useCallback;
	var useMemo = wp.element.useMemo;
	var createRoot = wp.element.createRoot;
	var render = wp.element.render;

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var _n = wp.i18n._n;

	var useDispatch = wp.data.useDispatch;
	var useSelect = wp.data.useSelect;
	var subscribe = wp.data.subscribe;

	var useDebounce = wp.compose.useDebounce;

	var parse = wp.blocks.parse;
	var cloneBlock = wp.blocks.cloneBlock;
	var isUnmodifiedDefaultBlock = wp.blocks.isUnmodifiedDefaultBlock;
	var registerBlockType = wp.blocks.registerBlockType;

	var BlockPreview = wp.blockEditor.BlockPreview;
	var storeBlockEditor = wp.blockEditor.store;

	var speak = wp.a11y.speak;

	var castArray = lodash.castArray;
	var isEmpty = lodash.isEmpty;

	var registerPlugin = wp.plugins && wp.plugins.registerPlugin;
	var PluginMoreMenuItem =
		( wp.editor && wp.editor.PluginMoreMenuItem ) ||
		( wp.editPost && wp.editPost.PluginMoreMenuItem );

	var c = wp.components;
	var Modal = c.Modal;
	var Button = c.Button;
	var SearchControl = c.SearchControl;
	var Spinner = c.Spinner;
	var DropdownMenu = c.DropdownMenu;
	var MenuGroup = c.MenuGroup;
	var MenuItem = c.MenuItem;
	var Tooltip = c.Tooltip;
	var Notice = c.Notice;
	var Icon = c.Icon;
	var CheckboxControl = c.CheckboxControl;
	var Dropdown = c.Dropdown;
	var SelectControl = c.SelectControl;
	var ButtonGroup = c.ButtonGroup;
	var Fill = c.Fill;

	var SVG = wp.primitives && wp.primitives.SVG;
	var Path = wp.primitives && wp.primitives.Path;
	var Circle = wp.primitives && wp.primitives.Circle;

	/* IconScout Unicons (thinline + line) — https://iconscout.com/unicons */
	var icons = ( function () {
		function svgIcon( children ) {
			if ( ! SVG ) {
				return null;
			}
			return el(
				SVG,
				{
					xmlns: 'http://www.w3.org/2000/svg',
					viewBox: '0 0 24 24',
					width: 18,
					height: 18,
					fill: 'currentColor',
					'aria-hidden': true,
					focusable: false,
				},
				children
			);
		}

		function uicon( pathD ) {
			return svgIcon(
				el( Path, {
					d: pathD,
					fill: 'currentColor',
				} )
			);
		}

		function uiconPaths( paths ) {
			return svgIcon(
				el(
					Fragment,
					null,
					paths.map( function ( pathD, index ) {
						return el( Path, {
							key: index,
							d: pathD,
							fill: 'currentColor',
						} );
					} )
				)
			);
		}

		return {
			heart: uicon(
				'M17.5.917a6.4,6.4,0,0,0-5.5,3.3A6.4,6.4,0,0,0,6.5.917,6.8,6.8,0,0,0,0,7.967c0,6.775,10.956,14.6,11.422,14.932l.578.409.578-.409C13.044,22.569,24,14.742,24,7.967A6.8,6.8,0,0,0,17.5.917ZM12,20.846c-3.253-2.43-10-8.4-10-12.879a4.8,4.8,0,0,1,4.5-5.05A4.8,4.8,0,0,1,11,7.967h2a4.8,4.8,0,0,1,4.5-5.05A4.8,4.8,0,0,1,22,7.967C22,12.448,15.253,18.416,12,20.846Z'
			),
			heartFilled: uicon(
				'M17.5.917a6.4,6.4,0,0,0-5.5,3.3A6.4,6.4,0,0,0,6.5.917,6.8,6.8,0,0,0,0,7.967c0,6.775,10.956,14.6,11.422,14.932l.578.409.578-.409C13.044,22.569,24,14.742,24,7.967A6.8,6.8,0,0,0,17.5.917Z'
			),
			moreVertical: svgIcon(
				el(
					Fragment,
					null,
					Circle
						? el( Circle, { cx: 12, cy: 2, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 12, cy: 2, r: 2, fill: 'currentColor' } ),
					Circle
						? el( Circle, { cx: 12, cy: 12, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 12, cy: 12, r: 2, fill: 'currentColor' } ),
					Circle
						? el( Circle, { cx: 12, cy: 22, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 12, cy: 22, r: 2, fill: 'currentColor' } )
				)
			),
			edit: uiconPaths( [
				'M5,19H9.414L23.057,5.357a3.125,3.125,0,0,0,0-4.414,3.194,3.194,0,0,0-4.414,0L5,14.586Zm2-3.586L20.057,2.357a1.148,1.148,0,0,1,1.586,0,1.123,1.123,0,0,1,0,1.586L8.586,17H7Z',
				'M23.621,7.622,22,9.243V16H16v6H2V3A1,1,0,0,1,3,2H14.758L16.379.379A5.013,5.013,0,0,1,16.84,0H3A3,3,0,0,0,0,3V24H18.414L24,18.414V7.161A5.15,5.15,0,0,1,23.621,7.622ZM18,21.586V18h3.586Z',
			] ),
			sort: uicon(
				'M16.29,14.29,12,18.59l-4.29-4.3a1,1,0,0,0-1.42,1.42l5,5a1,1,0,0,0,1.42,0l5-5a1,1,0,0,0-1.42-1.42ZM7.71,9.71,12,5.41l4.29,4.3a1,1,0,0,0,1.42,0,1,1,0,0,0,0-1.42l-5-5a1,1,0,0,0-1.42,0l-5,5A1,1,0,0,0,7.71,9.71Z'
			),
			filter: uicon(
				'm15 24-6-4.5v-5.12l-8-9v-2.38a3 3 0 0 1 3-3h16a3 3 0 0 1 3 3v2.38l-8 9zm-4-5.5 2 1.5v-6.38l8-9v-1.62a1 1 0 0 0 -1-1h-16a1 1 0 0 0 -1 1v1.62l8 9z'
			),
			grid: uiconPaths( [
				'M11,11H0V3A3,3,0,0,1,3,0h8ZM2,9H9V2H3A1,1,0,0,0,2,3Z',
				'M24,11H13V0h8a3,3,0,0,1,3,3ZM15,9h7V3a1,1,0,0,0-1-1H15Z',
				'M11,24H3a3,3,0,0,1-3-3V13H11ZM2,15v6a1,1,0,0,0,1,1H9V15Z',
				'M21,24H13V13H24v8A3,3,0,0,1,21,24Zm-6-2h6a1,1,0,0,0,1-1V15H15Z',
			] ),
			list: svgIcon(
				el(
					Fragment,
					null,
					el( 'rect', { x: 7, y: 4, width: 17, height: 2, fill: 'currentColor' } ),
					el( 'rect', { x: 7, y: 11, width: 17, height: 2, fill: 'currentColor' } ),
					el( 'rect', { x: 7, y: 18, width: 17, height: 2, fill: 'currentColor' } ),
					Circle
						? el( Circle, { cx: 2, cy: 5, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 2, cy: 5, r: 2, fill: 'currentColor' } ),
					Circle
						? el( Circle, { cx: 2, cy: 12, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 2, cy: 12, r: 2, fill: 'currentColor' } ),
					Circle
						? el( Circle, { cx: 2, cy: 19, r: 2, fill: 'currentColor' } )
						: el( 'circle', { cx: 2, cy: 19, r: 2, fill: 'currentColor' } )
				)
			),
			plus: svgIcon(
				el( 'polygon', {
					points:
						'24 11 13 11 13 0 11 0 11 11 0 11 0 13 11 13 11 24 13 24 13 13 24 13 24 11',
					fill: 'currentColor',
				} )
			),
			lock: svgIcon(
				el(
					Fragment,
					null,
					el( Path, {
						d: 'M19,8V7A7,7,0,0,0,5,7V8H2V21a3,3,0,0,0,3,3H19a3,3,0,0,0,3-3V8ZM7,7A5,5,0,0,1,17,7V8H7ZM20,21a1,1,0,0,1-1,1H5a1,1,0,0,1-1-1V10H20Z',
						fill: 'currentColor',
					} ),
					el( 'rect', {
						x: 11,
						y: 14,
						width: 2,
						height: 4,
						fill: 'currentColor',
					} )
				)
			),
			check: uicon(
				'M18.3534546,7.5735474c-0.1932983-0.1972046-0.5098267-0.2003784-0.7070312-0.0070801l-7.8066406,7.8066406l-3.4863281-3.4863281c-0.194397-0.1905518-0.5054321-0.1905518-0.6998291,0c-0.1972046,0.1932373-0.2003784,0.5097656-0.0071411,0.7069702l3.8398438,3.8398438c0.0936279,0.0939331,0.2208862,0.1466675,0.3535156,0.1464844c0.1326294,0.0001221,0.2598267-0.0526123,0.3534546-0.1464844l8.1601562-8.1601562C18.5440063,8.0790405,18.5440063,7.7679443,18.3534546,7.5735474z'
			),
			refresh: uicon(
				'M12,2A10,10,0,0,0,4.93,4.93,1,1,0,0,0,6.34,6.34,8,8,0,1,1,4,12H7L3,8,0,12H2A10,10,0,1,0,12,2Z'
			),
			plusBox: uicon(
				'M9,13h2v2a1,1,0,0,0,2,0V13h2a1,1,0,0,0,0-2H13V9a1,1,0,0,0-2,0v2H9a1,1,0,0,0,0,2ZM21,2H3A1,1,0,0,0,2,3V21a1,1,0,0,0,1,1H21a1,1,0,0,0,1-1V3A1,1,0,0,0,21,2ZM20,20H4V4H20Z'
			),
			close: uicon(
				'M19.8534546,19.1465454L12.7069092,12l7.1465454-7.1465454c0.1871948-0.1937256,0.1871948-0.5009155,0-0.6947021c-0.1918335-0.1986084-0.5083618-0.2041016-0.7069702-0.0122681l-7.1465454,7.1465454L4.8534546,4.1465454c-0.1937256-0.1871338-0.5009155-0.1871338-0.6947021,0C3.960144,4.3383789,3.9546509,4.6549072,4.1464844,4.8535156L11.2929688,12l-7.1464844,7.1464844c-0.09375,0.09375-0.1464233,0.2208862-0.1464233,0.3534546C4,19.776062,4.223877,19.999939,4.5,20c0.1326294,0.0001221,0.2598267-0.0526123,0.3534546-0.1465454l7.1464844-7.1464844l7.1465454,7.1465454C19.2401123,19.9474487,19.3673706,20.0001831,19.5,20c0.1325073-0.000061,0.2595825-0.0526733,0.3533325-0.1463623C20.048645,19.6583862,20.0487061,19.3417969,19.8534546,19.1465454z'
			),
			brand: svgIcon(
				el(
					Fragment,
					null,
					el( Path, { d: 'M9.34 24L4.45 21.16L4.03 20.92V15.3L9.34 12V24Z', fill: 'currentColor' } ),
					el( Path, {
						fillRule: 'evenodd',
						clipRule: 'evenodd',
						d: 'M14.66 9.2L20.97 6L14.66 2.8V9.2Z',
						fill: 'currentColor',
					} ),
					el( Path, { d: 'M14.66 2.8L9.34 6L4.03 9.2V2.8L9.34 0L14.66 2.8Z', fill: 'currentColor' } ),
					el( Path, {
						fillRule: 'evenodd',
						clipRule: 'evenodd',
						d: 'M10.79 11.44V17.84L14.66 15.3L20.97 12V6L14.66 9.2L10.79 11.44Z',
						fill: 'currentColor',
					} )
				)
			),
			settings: uicon(
				'M15,24H9V20.487a9,9,0,0,1-2.849-1.646L3.107,20.6l-3-5.2L3.15,13.645a9.1,9.1,0,0,1,0-3.29L.107,8.6l3-5.2L6.151,5.159A9,9,0,0,1,9,3.513V0h6V3.513a9,9,0,0,1,2.849,1.646L20.893,3.4l3,5.2L20.85,10.355a9.1,9.1,0,0,1,0,3.29L23.893,15.4l-3,5.2-3.044-1.758A9,9,0,0,1,15,20.487Zm-4-2h2V18.973l.751-.194A6.984,6.984,0,0,0,16.994,16.9l.543-.553,2.623,1.515,1-1.732-2.62-1.513.206-.746a7.048,7.048,0,0,0,0-3.75l-.206-.746,2.62-1.513-1-1.732L17.537,7.649,16.994,7.1a6.984,6.984,0,0,0-3.243-1.875L13,5.027V2H11V5.027l-.751.194A6.984,6.984,0,0,0,7.006,7.1l-.543.553L3.84,6.134l-1,1.732L5.46,9.379l-.206.746a7.048,7.048,0,0,0,0,3.75l.206.746L2.84,16.134l1,1.732,2.623-1.515.543.553a6.984,6.984,0,0,0,3.243,1.875l.751.194Zm1-6a4,4,0,1,1,4-4A4,4,0,0,1,12,16Zm0-6a2,2,0,1,0,2,2A2,2,0,0,0,12,10Z'
			),
			arrowUp: uicon(
				'M17.71,11.29l-5-5a1,1,0,0,0-.33-.21,1,1,0,0,0-.76,0,1,1,0,0,0-.33.21l-5,5a1,1,0,0,0,1.42,1.42L11,9.41V17a1,1,0,0,0,2,0V9.41l3.29,3.3a1,1,0,0,0,1.42,0A1,1,0,0,0,17.71,11.29Z'
			),
			arrowDown: uicon(
				'M17.71,11.29a1,1,0,0,0-1.42,0L13,14.59V7a1,1,0,0,0-2,0v7.59l-3.29-3.3a1,1,0,0,0-1.42,1.42l5,5a1,1,0,0,0,.33.21.94.94,0,0,0,.76,0,1,1,0,0,0,.33-.21l5-5A1,1,0,0,0,17.71,11.29Z'
			),
			chevronDown: uicon(
				'M17,9.17a1,1,0,0,0-1.41,0L12,12.71,8.46,9.17a1,1,0,0,0-1.41,0,1,1,0,0,0,0,1.42l4.24,4.24a1,1,0,0,0,1.42,0L17,10.59A1,1,0,0,0,17,9.17Z'
			),
			chevronUp: uicon(
				'M16.8535156,13.6465454l-4.5-4.5c-0.000061-0.000061-0.000061-0.0001221-0.0001221-0.0001831c-0.1952515-0.1951294-0.5117188-0.1950684-0.7068481,0.0001831l-4.5,4.5c-0.1871948,0.1937256-0.1871948,0.5009155,0,0.6947021c0.1918335,0.1986084,0.5083618,0.2041016,0.7069702,0.0122681L12,10.2069702l4.1464844,4.1465454C16.2401123,14.4474487,16.3673706,14.5001831,16.5,14.5c0.1325073,0,0.2596436-0.0526733,0.3533936-0.1463623C17.048645,14.1583862,17.0487671,13.8417969,16.8535156,13.6465454z'
			),
			view: uiconPaths( [
				'M23.821,11.181v0C22.943,9.261,19.5,3,12,3S1.057,9.261.179,11.181a1.969,1.969,0,0,0,0,1.64C1.057,14.739,4.5,21,12,21s10.943-6.261,11.821-8.181A1.968,1.968,0,0,0,23.821,11.181ZM12,19c-6.307,0-9.25-5.366-10-6.989C2.75,10.366,5.693,5,12,5c6.292,0,9.236,5.343,10,7C21.236,13.657,18.292,19,12,19Z',
				'M12,7a5,5,0,1,0,5,5A5.006,5.006,0,0,0,12,7Zm0,8a3,3,0,1,1,3-3A3,3,0,0,1,12,15Z',
			] ),
			arrowLeft: uicon(
				'M24,13l0-2-21.445.031L6.877,6.707,5.463,5.293.877,9.879a3,3,0,0,0,0,4.242l4.586,4.586,1.414-1.414L2.615,13.031Z'
			),
		};
	} )();

	function iconOrDash( modernIcon, dashicon ) {
		return modernIcon || dashicon;
	}

	var FAVORITES_KEY = 'patternswp_favorites_v1';
	var RECENT_KEY = 'patternswp_recent_v1';
	var PREFS_KEY = 'patternswp_prefs_v2';
	var MAX_FAVORITES = 40;
	var MAX_RECENT = 24;

	var PAGE_SIZE = 20;

	var DEFAULT_PREFS = {
		layout: 'grid',
		viewport: 'desktop',
		sortField: 'default',
		sortOrder: 'asc',
		typeFilter: 'all',
		previewSize: 2,
		showTitle: true,
		showPreview: true,
	};

	var PREVIEW_SIZE_HEIGHTS = {
		1: 160,
		2: 200,
		3: 240,
		4: 300,
	};

	var SPECIAL = {
		ALL: '',
		FAVORITES: '__favorites__',
		RECENT: '__recent__',
	};

	var VIEWPORTS = {
		desktop: 1300,
		tablet: 778,
		mobile: 358,
	};

	var pageCache = new Map();
	var openModalExternal = null;
	var insertAnchorClientId = null;

	function setInsertAnchor( clientId ) {
		insertAnchorClientId = clientId || null;
	}

	function openPatternsLibrary( options ) {
		options = options || {};
		setInsertAnchor( options.anchorClientId || null );
		if ( openModalExternal ) {
			openModalExternal();
			return true;
		}
		return false;
	}

	window.patternswpOpenLibrary = openPatternsLibrary;

	/* ------------------------------------------------------------------ */
	/* Helpers                                                            */
	/* ------------------------------------------------------------------ */

	function getBoot() {
		return window.patternsWpData || {
			isLicenseActive: false,
			patternCategories: [],
			patternsNonce: '',
			libraryComplete: false,
			ajaxUrl: window.ajaxurl || '',
		};
	}

	function ajaxUrl() {
		var boot = getBoot();
		return boot.ajaxUrl || window.ajaxurl || '';
	}

	function isLicenseUnlocked() {
		return !! getBoot().isLicenseActive;
	}

	function isPatternLocked( pattern ) {
		if ( ! pattern || isLicenseUnlocked() ) {
			return false;
		}
		return pattern.locked === true || pattern.type === 'pro';
	}

	function parseAjaxJson( response ) {
		return response.text().then( function ( text ) {
			var json = null;
			try {
				json = text ? JSON.parse( text ) : null;
			} catch ( e ) {
				json = null;
			}
			if ( ! json ) {
				throw new Error( 'invalid' );
			}
			return json;
		} );
	}

	function normalizeLibraryPayload( json ) {
		if ( ! json || ! json.success ) {
			throw new Error( 'invalid' );
		}
		var data = json.data;
		if ( Array.isArray( data ) ) {
			return {
				patterns: data,
				total: data.length,
				complete: true,
				categories: null,
			};
		}
		if ( data && Array.isArray( data.patterns ) ) {
			if ( typeof data.licenseActive === 'boolean' ) {
				window.patternsWpData = window.patternsWpData || {};
				window.patternsWpData.isLicenseActive = data.licenseActive;
			}
			return {
				patterns: data.patterns,
				total: typeof data.total === 'number' ? data.total : data.patterns.length,
				complete: !! data.complete,
				categories: Array.isArray( data.categories ) ? data.categories : null,
				licenseActive: data.licenseActive === true,
			};
		}
		throw new Error( 'invalid' );
	}

	function patternKey( pattern ) {
		return ( pattern.title || '' ) + '::' + ( ( pattern.categories || [] ).join( ',' ) );
	}

	function formatCategoryLabel( slug ) {
		if ( ! slug ) {
			return __( 'All patterns', 'patternswp' );
		}
		var map = {
			cta: 'CTA',
			faq: 'FAQ',
		};
		return String( slug )
			.replace( /^patternswp-/, '' )
			.split( '-' )
			.map( function ( part ) {
				if ( map[ part ] ) {
					return map[ part ];
				}
				return part.charAt( 0 ).toUpperCase() + part.slice( 1 );
			} )
			.join( ' ' );
	}

	function readStore( key, fallback ) {
		try {
			var raw = window.localStorage.getItem( key );
			if ( ! raw ) {
				return fallback;
			}
			var parsed = JSON.parse( raw );
			return parsed != null ? parsed : fallback;
		} catch ( e ) {
			return fallback;
		}
	}

	function writeStore( key, value ) {
		try {
			window.localStorage.setItem( key, JSON.stringify( value ) );
		} catch ( e ) {
			/* quota / private mode */
		}
	}

	function decodePatternContent( content ) {
		if ( ! content ) {
			return '';
		}
		try {
			/* API returns base64-encoded block markup */
			return window.atob( content );
		} catch ( e ) {
			return content;
		}
	}

	function cacheKey( category, search, page, perPage ) {
		return category + '|' + search + '|' + page + '|' + perPage;
	}

	function sortPatterns( list, sortField, sortOrder ) {
		var items = list.slice();
		var dir = sortOrder === 'desc' ? -1 : 1;

		if ( sortField === 'title' ) {
			items.sort( function ( a, b ) {
				return dir * ( a.title || '' ).localeCompare( b.title || '' );
			} );
			return items;
		}

		if ( sortField === 'type' ) {
			items.sort( function ( a, b ) {
				var av = a.type === 'pro' ? 1 : 0;
				var bv = b.type === 'pro' ? 1 : 0;
				return dir * ( av - bv ) || ( a.title || '' ).localeCompare( b.title || '' );
			} );
			return items;
		}

		/* default: keep API order; desc reverses loaded list */
		if ( sortOrder === 'desc' ) {
			items.reverse();
		}
		return items;
	}

	function filterByType( list, typeFilter ) {
		if ( typeFilter === 'free' ) {
			return list.filter( function ( p ) {
				return p.type !== 'pro';
			} );
		}
		if ( typeFilter === 'pro' ) {
			return list.filter( function ( p ) {
				return p.type === 'pro';
			} );
		}
		return list;
	}

	function upsertByKey( list, items ) {
		var seen = new Set( list.map( patternKey ) );
		var next = list.slice();
		items.forEach( function ( item ) {
			var key = patternKey( item );
			if ( ! seen.has( key ) ) {
				seen.add( key );
				next.push( item );
			}
		} );
		return next;
	}

	/* ------------------------------------------------------------------ */
	/* Data fetching                                                      */
	/* ------------------------------------------------------------------ */

	function fetchPatternsPage( page, search, category, perPage, signal, retries ) {
		var pageSize = perPage || PAGE_SIZE;
		var key = cacheKey( category, search, page, pageSize );
		if ( pageCache.has( key ) ) {
			return Promise.resolve( pageCache.get( key ) );
		}
		if ( typeof retries !== 'number' ) {
			retries = 1;
		}

		var boot = getBoot();
		var body = new window.URLSearchParams( {
			action: 'fetch_patterns',
			page: String( page ),
			patternsPerPage: String( pageSize ),
			search: search || '',
			category: category || '',
			nonce: boot.patternsNonce || '',
		} );

		return window
			.fetch( ajaxUrl(), {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
				signal: signal,
			} )
			.then( parseAjaxJson )
			.then( function ( json ) {
				var payload = normalizeLibraryPayload( json );
				if ( payload.patterns.length || payload.complete ) {
					pageCache.set( key, payload );
				}
				return payload;
			} )
			.catch( function ( err ) {
				if ( err && err.name === 'AbortError' ) {
					throw err;
				}
				if ( retries > 0 ) {
					return new Promise( function ( resolve ) {
						window.setTimeout( resolve, 1200 );
					} ).then( function () {
						return fetchPatternsPage( page, search, category, perPage, signal, retries - 1 );
					} );
				}
				throw err;
			} );
	}

	function warmLibrary( signal, mode, force ) {
		var boot = getBoot();
		var body = new window.URLSearchParams( {
			action: 'patternswp_warm_library',
			nonce: boot.patternsNonce || '',
			mode: mode || 'sync',
			force: force ? '1' : '0',
		} );

		return window
			.fetch( ajaxUrl(), {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
				signal: signal,
			} )
			.then( parseAjaxJson )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data || typeof json.data !== 'object' ) {
					throw new Error( 'invalid' );
				}
				return {
					total: typeof json.data.total === 'number' ? json.data.total : 0,
					complete: !! json.data.complete,
					categories: Array.isArray( json.data.categories ) ? json.data.categories : null,
					hasUpdates: !! json.data.has_updates,
				};
			} );
	}

	/* ------------------------------------------------------------------ */
	/* Insert hook (mirrors core inserter destination logic)              */
	/* ------------------------------------------------------------------ */

	function useInsertPattern( onInserted ) {
		var getSelectedBlock = useSelect( function ( select ) {
			return select( storeBlockEditor ).getSelectedBlock;
		}, [] );

		var destination = useSelect(
			function ( select ) {
				var s = select( storeBlockEditor );
				var selectedId = s.getSelectedBlockClientId();
				var rootClientId = '';
				var index = s.getBlockOrder( rootClientId ).length;

				if ( selectedId ) {
					rootClientId = s.getBlockRootClientId( selectedId );
					index = s.getBlockIndex( selectedId ) + 1;
				}

				return {
					rootClientId: rootClientId,
					index: index,
				};
			},
			[]
		);

		var dispatch = useDispatch( storeBlockEditor );
		var notices = useDispatch( 'core/notices' );

		return useCallback(
			function ( patternOrList, options ) {
				options = options || {};
				var list = Array.isArray( patternOrList ) ? patternOrList : [ patternOrList ];
				var blocks = [];
				list.forEach( function ( pattern ) {
					if ( isPatternLocked( pattern ) ) {
						return;
					}
					try {
						var markup = decodePatternContent( pattern.content );
						if ( ! markup ) {
							return;
						}
						parse( markup ).forEach( function ( block ) {
							blocks.push( cloneBlock( block ) );
						} );
					} catch ( e ) {
						/* skip broken pattern */
					}
				} );

				if ( ! blocks.length ) {
					var blocked = list.some( isPatternLocked );
					notices.createErrorNotice(
						blocked
							? __( 'Activate your PatternsWP license to insert Pro patterns.', 'patternswp' )
							: __( 'Could not insert this pattern.', 'patternswp' ),
						{ type: 'snackbar' }
					);
					if ( blocked ) {
						window.open( 'https://thepatternswp.com/pricing/', '_blank', 'noopener,noreferrer' );
					}
					return;
				}

				var anchorId = options.anchorClientId || insertAnchorClientId;
				var selected = getSelectedBlock();
				var editorSelect = wp.data.select( storeBlockEditor );

				if ( anchorId && editorSelect.getBlock( anchorId ) ) {
					var rootClientId = editorSelect.getBlockRootClientId( anchorId );
					var index = editorSelect.getBlockIndex( anchorId ) + 1;
					dispatch.insertBlocks( blocks, index, rootClientId, true );
				} else if ( selected && isUnmodifiedDefaultBlock( selected ) ) {
					dispatch.replaceBlocks( selected.clientId, blocks );
				} else {
					dispatch.insertBlocks(
						blocks,
						destination.index,
						destination.rootClientId,
						true
					);
				}

				var count = castArray( blocks ).length;
				speak(
					sprintf(
						_n( '%d block added.', '%d blocks added.', count, 'patternswp' ),
						count
					)
				);

				if ( list.length === 1 ) {
					notices.createSuccessNotice(
						sprintf(
							/* translators: %s: pattern title */
							__( '“%s” inserted.', 'patternswp' ),
							list[ 0 ].title || __( 'Pattern', 'patternswp' )
						),
						{ type: 'snackbar' }
					);
				} else {
					notices.createSuccessNotice(
						sprintf(
							/* translators: %d: number of patterns */
							_n( '%d pattern inserted.', '%d patterns inserted.', list.length, 'patternswp' ),
							list.length
						),
						{ type: 'snackbar' }
					);
				}

				if ( onInserted ) {
					onInserted( list );
				}
			},
			[ destination.index, destination.rootClientId, dispatch, getSelectedBlock, notices, onInserted ]
		);
	}

	/* ------------------------------------------------------------------ */
	/* Lazy BlockPreview — mount only while in view                       */
	/* ------------------------------------------------------------------ */

	function LazyPreviewInner( props ) {
		var ref = useRef( null );
		var visibleState = useState( false );
		var visible = visibleState[ 0 ];
		var setVisible = visibleState[ 1 ];

		useEffect(
			function () {
				var node = ref.current;
				if ( ! node || ! window.IntersectionObserver ) {
					setVisible( true );
					return;
				}
				var observer = new window.IntersectionObserver(
					function ( entries ) {
						entries.forEach( function ( entry ) {
							setVisible( entry.isIntersecting );
						} );
					},
					{ root: props.root || null, rootMargin: '300px 0px', threshold: 0.01 }
				);
				observer.observe( node );
				return function () {
					observer.disconnect();
				};
			},
			[ props.root ]
		);

		var minHeight = props.minHeight || 200;

		return el(
			'div',
			{ ref: ref, style: { width: '100%', height: '100%', minHeight: minHeight } },
			visible
				? el( BlockPreview, {
						blocks: props.blocks,
						viewportWidth: props.viewportWidth,
						minHeight: minHeight,
				  } )
				: el( 'div', { style: { display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100%' } }, el( Spinner ) )
		);
	}

	/**
	 * Detail preview that shows the pattern at its real height.
	 * Gutenberg's ScaledBlockPreview hard-caps iframe/content at 2000px, which
	 * clips page templates. CSS removes the cap; this re-applies it after React
	 * writes inline max-height, and sizes the wrapper so the modal can scroll.
	 */
	function FullPatternPreview( props ) {
		var viewportWidth = props.viewportWidth || VIEWPORTS.desktop;
		var wrapRef = useRef( null );
		var heightState = useState( 0 );
		var height = heightState[ 0 ];
		var setHeight = heightState[ 1 ];

		useEffect(
			function () {
				var wrap = wrapRef.current;
				if ( ! wrap ) {
					return;
				}

				var timer = 0;
				var tries = 0;
				var boundIframe = null;

				function unlockEl( el, propsMap ) {
					if ( ! el ) {
						return;
					}
					Object.keys( propsMap ).forEach( function ( key ) {
						el.style.setProperty( key, propsMap[ key ], 'important' );
					} );
				}

				function contentHeight( iframe ) {
					var doc = iframe.contentDocument;
					if ( ! doc ) {
						return 0;
					}
					var html = doc.documentElement;
					var body = doc.body;
					if ( html ) {
						html.style.setProperty( 'max-height', 'none', 'important' );
						html.style.setProperty( 'overflow', 'visible', 'important' );
					}
					if ( body ) {
						body.style.setProperty( 'max-height', 'none', 'important' );
						body.style.setProperty( 'overflow', 'visible', 'important' );
					}
					return Math.max(
						body ? body.scrollHeight : 0,
						html ? html.scrollHeight : 0,
						body ? body.offsetHeight : 0,
						html ? html.offsetHeight : 0
					);
				}

				function measure() {
					var iframe = wrap.querySelector( 'iframe' );
					var containerEl = wrap.querySelector( '.block-editor-block-preview__container' );
					var contentEl = wrap.querySelector( '.block-editor-block-preview__content' );

					unlockEl( containerEl, {
						overflow: 'visible',
						'max-height': 'none',
					} );
					unlockEl( contentEl, {
						overflow: 'visible',
						'max-height': 'none',
					} );
					unlockEl( wrap.querySelector( '.block-editor-iframe__container' ), {
						overflow: 'visible',
						'max-height': 'none',
					} );
					unlockEl( wrap.querySelector( '.block-editor-iframe__scale-container' ), {
						overflow: 'visible',
						'max-height': 'none',
					} );

					if ( ! iframe ) {
						return;
					}

					if ( boundIframe !== iframe ) {
						boundIframe = iframe;
						iframe.addEventListener( 'load', measure );
					}

					var full = contentHeight( iframe );
					unlockEl( iframe, {
						'max-height': 'none',
						overflow: 'visible',
					} );
					if ( full > 40 ) {
						unlockEl( iframe, { height: full + 'px' } );
					}

					var next = 0;
					if ( contentEl ) {
						next = Math.ceil( contentEl.getBoundingClientRect().height );
					}
					if ( ( ! next || next < 40 ) && full > 40 ) {
						var containerWidth = wrap.getBoundingClientRect().width || viewportWidth;
						var scale = viewportWidth ? containerWidth / viewportWidth : 1;
						next = Math.ceil( full * scale );
					}
					if ( next > 40 ) {
						unlockEl( wrap, { 'min-height': next + 'px' } );
						setHeight( function ( prev ) {
							return Math.abs( prev - next ) > 8 ? next : prev;
						} );
					}
				}

				function tick() {
					measure();
					tries += 1;
					if ( tries < 40 ) {
						timer = window.setTimeout( tick, tries < 12 ? 100 : 300 );
					}
				}

				var observer = window.MutationObserver
					? new window.MutationObserver( function () {
							measure();
					  } )
					: null;
				if ( observer ) {
					observer.observe( wrap, { childList: true, subtree: true } );
				}
				tick();

				return function () {
					if ( observer ) {
						observer.disconnect();
					}
					window.clearTimeout( timer );
					if ( boundIframe ) {
						boundIframe.removeEventListener( 'load', measure );
					}
				};
			},
			[ props.blocks, viewportWidth ]
		);

		return el(
			'div',
			{
				ref: wrapRef,
				className: 'patternswp-detail__preview',
				style: height ? { minHeight: height } : undefined,
			},
			el( BlockPreview, {
				blocks: props.blocks,
				viewportWidth: viewportWidth,
				minHeight: 120,
			} )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Sidebar nav item                                                   */
	/* ------------------------------------------------------------------ */

	function SidebarNavItem( props ) {
		return el(
			'li',
			null,
			el(
				'button',
				{
					type: 'button',
					className: 'patternswp-sidebar__nav-item' + ( props.isActive ? ' is-active' : '' ),
					onClick: props.onClick,
					'aria-current': props.isActive ? 'page' : undefined,
				},
				props.label
			)
		);
	}

	function SidebarCollapsibleSection( props ) {
		var isExpanded = !! props.isExpanded;
		var panelId = props.panelId;

		return el(
			'div',
			{
				className:
					'patternswp-sidebar__section' +
					( isExpanded ? ' is-expanded' : ' is-collapsed' ),
			},
			el(
				'button',
				{
					type: 'button',
					className: 'patternswp-sidebar__section-toggle',
					onClick: props.onToggle,
					'aria-expanded': isExpanded,
					'aria-controls': panelId,
					'aria-label': isExpanded
						? sprintf(
								/* translators: %s: sidebar section title */
								__( 'Minimize %s', 'patternswp' ),
								props.title
						  )
						: sprintf(
								/* translators: %s: sidebar section title */
								__( 'Maximize %s', 'patternswp' ),
								props.title
						  ),
				},
				el( 'span', { className: 'patternswp-sidebar__section-title' }, props.title ),
				el( Icon, {
					className: 'patternswp-sidebar__section-toggle-icon',
					icon: iconOrDash(
						isExpanded ? icons.chevronUp : icons.chevronDown,
						isExpanded ? 'arrow-up-alt2' : 'arrow-down-alt2'
					),
					size: 16,
				} )
			),
			isExpanded
				? el(
						'div',
						{ id: panelId, className: 'patternswp-sidebar__section-panel' },
						props.children
				  )
				: null
		);
	}

	/* ------------------------------------------------------------------ */
	/* Appearance settings panel                                          */
	/* ------------------------------------------------------------------ */

	function PreviewSizeControl( props ) {
		var value = props.value || 2;
		var onChange = props.onChange;
		var steps = [ 1, 2, 3, 4 ];
		/* Fill covers completed segments (value 1 = 25%, … value 4 = 100%). */
		var fillPercent = ( value / 4 ) * 100;

		function setSize( next, event ) {
			if ( event ) {
				event.preventDefault();
				event.stopPropagation();
			}
			if ( next >= 1 && next <= 4 && next !== value ) {
				onChange( next );
			}
		}

		return el(
			'div',
			{
				className: 'patternswp-preview-size',
				role: 'slider',
				'aria-label': __( 'Preview size', 'patternswp' ),
				'aria-valuemin': 1,
				'aria-valuemax': 4,
				'aria-valuenow': value,
				tabIndex: 0,
				onKeyDown: function ( event ) {
					if ( event.key === 'ArrowRight' || event.key === 'ArrowUp' ) {
						setSize( Math.min( 4, value + 1 ), event );
					}
					if ( event.key === 'ArrowLeft' || event.key === 'ArrowDown' ) {
						setSize( Math.max( 1, value - 1 ), event );
					}
				},
				onMouseDown: function ( event ) {
					/* Keep Appearance dropdown open while interacting. */
					event.stopPropagation();
				},
			},
			el(
				'div',
				{ className: 'patternswp-preview-size__track' },
				el( 'div', {
					className: 'patternswp-preview-size__fill',
					style: { width: fillPercent + '%' },
				} ),
				el(
					'div',
					{ className: 'patternswp-preview-size__breaks', 'aria-hidden': true },
					el( 'span' ),
					el( 'span' ),
					el( 'span' )
				),
				el( 'span', {
					className: 'patternswp-preview-size__thumb',
					style: { left: fillPercent + '%' },
				} ),
				el(
					'div',
					{ className: 'patternswp-preview-size__hits' },
					steps.map( function ( step ) {
						return el( 'button', {
							key: step,
							type: 'button',
							className:
								'patternswp-preview-size__hit' +
								( step === value ? ' is-active' : '' ),
							'aria-label': sprintf(
								/* translators: %d: preview size level */
								__( 'Size %d', 'patternswp' ),
								step
							),
							onMouseDown: function ( event ) {
								setSize( step, event );
							},
							onClick: function ( event ) {
								setSize( step, event );
							},
						} );
					} )
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Appearance settings panel                                          */
	/* ------------------------------------------------------------------ */

	function AppearancePanel( props ) {
		var sortField = props.sortField;
		var sortOrder = props.sortOrder;
		var previewSize = props.previewSize;
		var showTitle = props.showTitle;
		var showPreview = props.showPreview;

		function propertyRow( key, label, checked, onToggle ) {
			return el(
				'button',
				{
					type: 'button',
					key: key,
					className: 'patternswp-appearance__property' + ( checked ? ' is-active' : '' ),
					onClick: onToggle,
				},
				el( 'span', { className: 'patternswp-appearance__property-check' }, checked ? el( Icon, { icon: iconOrDash( icons.check, 'yes' ) } ) : null ),
				el( 'span', null, label )
			);
		}

		return el(
			'div',
			{ className: 'patternswp-appearance' },
			el(
				'div',
				{ className: 'patternswp-appearance__header' },
				el( 'h2', { className: 'patternswp-appearance__title' }, __( 'Appearance', 'patternswp' ) ),
				el(
					Button,
					{
						variant: 'tertiary',
						className: 'patternswp-appearance__reset',
						onClick: props.onReset,
					},
					__( 'Reset view', 'patternswp' )
				)
			),
			el(
				'div',
				{ className: 'patternswp-appearance__row patternswp-appearance__row--sort' },
				el(
					'div',
					{ className: 'patternswp-appearance__field' },
					el( 'span', { className: 'patternswp-appearance__label' }, __( 'Sort by', 'patternswp' ) ),
					SelectControl
						? el( SelectControl, {
								hideLabelFromVision: true,
								label: __( 'Sort by', 'patternswp' ),
								value: sortField,
								options: [
									{ label: __( 'Default', 'patternswp' ), value: 'default' },
									{ label: __( 'Title', 'patternswp' ), value: 'title' },
									{ label: __( 'Type', 'patternswp' ), value: 'type' },
								],
								onChange: props.onSortFieldChange,
								__nextHasNoMarginBottom: true,
								__next40pxDefaultSize: true,
						  } )
						: null
				),
				el(
					'div',
					{ className: 'patternswp-appearance__field patternswp-appearance__field--order' },
					el( 'span', { className: 'patternswp-appearance__label' }, __( 'Order', 'patternswp' ) ),
					el(
						ButtonGroup,
						{ className: 'patternswp-appearance__order' },
						el( Button, {
							icon: iconOrDash( icons.arrowUp, 'arrow-up-alt' ),
							label: __( 'Ascending', 'patternswp' ),
							isPressed: sortOrder === 'asc',
							onClick: function () {
								props.onSortOrderChange( 'asc' );
							},
						} ),
						el( Button, {
							icon: iconOrDash( icons.arrowDown, 'arrow-down-alt' ),
							label: __( 'Descending', 'patternswp' ),
							isPressed: sortOrder === 'desc',
							onClick: function () {
								props.onSortOrderChange( 'desc' );
							},
						} )
					)
				)
			),
			el(
				'div',
				{ className: 'patternswp-appearance__section' },
				el( 'span', { className: 'patternswp-appearance__label' }, __( 'Preview size', 'patternswp' ) ),
				el( PreviewSizeControl, {
					value: previewSize,
					onChange: props.onPreviewSizeChange,
				} )
			),
			el(
				'div',
				{ className: 'patternswp-appearance__section' },
				el( 'span', { className: 'patternswp-appearance__label' }, __( 'Properties', 'patternswp' ) ),
				el(
					'div',
					{ className: 'patternswp-appearance__properties' },
					propertyRow( 'title', __( 'Title', 'patternswp' ), showTitle, function () {
						props.onShowTitleChange( ! showTitle );
					} ),
					propertyRow( 'preview', __( 'Preview', 'patternswp' ), showPreview, function () {
						props.onShowPreviewChange( ! showPreview );
					} )
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Pattern card                                                       */
	/* ------------------------------------------------------------------ */

	function PatternCard( props ) {
		var pattern = props.pattern;
		var isLicenseActive = props.isLicenseActive;
		var isPro = pattern.type === 'pro' || pattern.locked === true;
		var locked = isPatternLocked( pattern );
		var isFavorite = props.isFavorite;
		var isSelected = props.isSelected;
		var bulkMode = props.bulkMode;
		var viewportWidth = props.viewportWidth;
		var scrollRoot = props.scrollRoot;
		var showTitle = props.showTitle !== false;
		var showPreview = props.showPreview !== false;
		var previewHeight = props.previewHeight || 200;

		var blocks = useMemo(
			function () {
				try {
					return parse( decodePatternContent( pattern.content ) );
				} catch ( e ) {
					return [];
				}
			},
			[ pattern.content ]
		);

		function handlePreviewClick() {
			if ( bulkMode ) {
				props.onToggleSelect( pattern );
				return;
			}
			if ( locked ) {
				props.onView( pattern );
				return;
			}
			props.onInsert( pattern );
		}

		return el(
			'article',
			{
				className:
					'patternswp-card' +
					( locked ? ' is-locked' : '' ) +
					( isSelected ? ' is-selected' : '' ) +
					( ! showPreview ? ' no-preview' : '' ),
			},
			showPreview
				? el(
						'div',
						{
							className: 'patternswp-card__preview',
							style: { height: previewHeight, minHeight: previewHeight, maxHeight: previewHeight },
							onClick: handlePreviewClick,
							onKeyDown: function ( event ) {
								if ( event.key === 'Enter' || event.key === ' ' ) {
									event.preventDefault();
									handlePreviewClick();
								}
							},
							role: 'button',
							tabIndex: 0,
							'aria-label': sprintf(
								/* translators: %s: pattern title */
								__( 'Insert pattern: %s', 'patternswp' ),
								pattern.title || ''
							),
						},
						isPro
							? el( 'span', { className: 'patternswp-card__badge' }, __( 'Pro', 'patternswp' ) )
							: null,
						el( LazyPreviewInner, {
							blocks: blocks,
							viewportWidth: viewportWidth,
							root: scrollRoot,
							minHeight: previewHeight,
						} )
				  )
				: null,
			el(
				'div',
				{ className: 'patternswp-card__footer' },
				el(
					'div',
					{ className: 'patternswp-card__select' },
					CheckboxControl
						? el( CheckboxControl, {
								checked: !! isSelected,
								onChange: function () {
									props.onToggleSelect( pattern );
								},
								__nextHasNoMarginBottom: true,
						  } )
						: el( 'input', {
								type: 'checkbox',
								checked: !! isSelected,
								onChange: function () {
									props.onToggleSelect( pattern );
								},
								'aria-label': __( 'Select pattern', 'patternswp' ),
						  } )
				),
				showTitle
					? el( 'div', { className: 'patternswp-card__title', title: pattern.title }, pattern.title )
					: el( 'div', { className: 'patternswp-card__title is-empty' } ),
				el(
					'div',
					{ className: 'patternswp-card__icons' },
					el(
						Tooltip,
						{ text: __( 'View pattern', 'patternswp' ) },
						el( Button, {
							icon: iconOrDash( icons.view, 'visibility' ),
							label: __( 'View', 'patternswp' ),
							size: 'compact',
							onClick: function ( event ) {
								event.stopPropagation();
								props.onView( pattern );
							},
						} )
					),
					el(
						Tooltip,
						{
							text: isFavorite
								? __( 'Remove from favorites', 'patternswp' )
								: __( 'Add to favorites', 'patternswp' ),
						},
						el( Button, {
							icon: iconOrDash(
								isFavorite ? icons.heartFilled : icons.heart,
								'heart'
							),
							label: __( 'Favorite', 'patternswp' ),
							size: 'compact',
							isPressed: !! isFavorite,
							onClick: function ( event ) {
								event.stopPropagation();
								props.onToggleFavorite( pattern );
							},
						} )
					),
					el(
						DropdownMenu,
						{
							icon: iconOrDash( icons.moreVertical, 'ellipsis' ),
							label: __( 'Pattern actions', 'patternswp' ),
							toggleProps: { size: 'compact' },
						},
						function ( { onClose } ) {
							return el(
								MenuGroup,
								null,
								locked
									? el( MenuItem, {
											icon: iconOrDash( icons.lock, 'lock' ),
											onClick: function () {
												window.open(
													'https://thepatternswp.com/pricing/',
													'_blank',
													'noopener,noreferrer'
												);
												onClose();
											},
									  }, __( 'Upgrade to insert', 'patternswp' ) )
									: el( MenuItem, {
											icon: iconOrDash( icons.plus, 'plus' ),
											onClick: function () {
												props.onInsert( pattern );
												onClose();
											},
									  }, __( 'Add pattern', 'patternswp' ) ),
								el( MenuItem, {
									icon: iconOrDash(
										isFavorite ? icons.heartFilled : icons.heart,
										'heart'
									),
									onClick: function () {
										props.onToggleFavorite( pattern );
										onClose();
									},
								}, isFavorite
									? __( 'Remove favorite', 'patternswp' )
									: __( 'Add to favorites', 'patternswp' ) )
							);
						}
					)
				)
			)
		);
	}

	function PatternDetailView( props ) {
		var pattern = props.pattern;
		var isLicenseActive = props.isLicenseActive;
		var isPro = pattern.type === 'pro' || pattern.locked === true;
		var locked = isPatternLocked( pattern );
		var isFavorite = props.isFavorite;
		var viewportWidth = props.viewportWidth;

		var blocks = useMemo(
			function () {
				try {
					return parse( decodePatternContent( pattern.content ) );
				} catch ( e ) {
					return [];
				}
			},
			[ pattern.content ]
		);

		return el(
			'div',
			{ className: 'patternswp-detail' },
			el(
				'div',
				{ className: 'patternswp-detail__header' },
				el( Button, {
					className: 'patternswp-detail__back',
					icon: iconOrDash( icons.arrowLeft, 'arrow-left-alt2' ),
					label: __( 'Back to patterns', 'patternswp' ),
					showTooltip: true,
					onClick: props.onClose,
				} ),
				el(
					'h2',
					{ className: 'patternswp-detail__title' },
					pattern.title || __( 'Untitled pattern', 'patternswp' )
				),
				isPro
					? el( 'span', { className: 'patternswp-detail__badge' }, __( 'Pro', 'patternswp' ) )
					: null,
				el(
					'div',
					{ className: 'patternswp-detail__actions' },
					el( Button, {
						icon: iconOrDash(
							isFavorite ? icons.heartFilled : icons.heart,
							'heart'
						),
						label: isFavorite
							? __( 'Remove from favorites', 'patternswp' )
							: __( 'Add to favorites', 'patternswp' ),
						isPressed: !! isFavorite,
						onClick: function () {
							props.onToggleFavorite( pattern );
						},
					} ),
					locked
						? el( Button, {
								variant: 'primary',
								icon: iconOrDash( icons.lock, 'lock' ),
								onClick: function () {
									window.open(
										'https://thepatternswp.com/pricing/',
										'_blank',
										'noopener,noreferrer'
									);
								},
						  }, __( 'Upgrade to insert', 'patternswp' ) )
						: el( Button, {
								className: 'patternswp-detail__insert',
								variant: 'primary',
								icon: iconOrDash( icons.plus, 'plus' ),
								onClick: function () {
									props.onInsert( pattern );
								},
						  }, __( 'Add pattern', 'patternswp' ) )
				)
			),
			el(
				'div',
				{ className: 'patternswp-detail__body' },
				el(
					'div',
					{
						className: 'patternswp-detail__canvas',
						style: { maxWidth: viewportWidth + 'px' },
					},
					el( FullPatternPreview, {
						blocks: blocks,
						viewportWidth: viewportWidth,
					} )
				)
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Library modal                                                      */
	/* ------------------------------------------------------------------ */

	function PatternsLibrary( props ) {
		var onRequestClose = props.onRequestClose;
		var boot = getBoot();
		var isLicenseActive = !! boot.isLicenseActive;

		var remoteCategoriesState = useState( function () {
			var list = Array.isArray( boot.patternCategories ) ? boot.patternCategories : [];
			return list
				.filter( function ( cat ) {
					return cat && cat.name;
				} )
				.slice()
				.sort( function ( a, b ) {
					return formatCategoryLabel( a.name ).localeCompare( formatCategoryLabel( b.name ) );
				} );
		} );
		var categories = remoteCategoriesState[ 0 ];
		var setRemoteCategories = remoteCategoriesState[ 1 ];

		var libraryCompleteState = useState( !! boot.libraryComplete );
		var libraryComplete = libraryCompleteState[ 0 ];
		var setLibraryComplete = libraryCompleteState[ 1 ];

		var libraryTotalState = useState( 0 );
		var libraryTotal = libraryTotalState[ 0 ];
		var setLibraryTotal = libraryTotalState[ 1 ];

		var syncingState = useState( false );
		var syncing = syncingState[ 0 ];
		var setSyncing = syncingState[ 1 ];

		var gridReadyState = useState( false );
		var gridReady = gridReadyState[ 0 ];
		var setGridReady = gridReadyState[ 1 ];

		var syncRequestRef = useRef( 0 );

		var prefs = Object.assign( {}, DEFAULT_PREFS, readStore( PREFS_KEY, {} ) );

		var categoryState = useState( SPECIAL.ALL );
		var category = categoryState[ 0 ];
		var setCategory = categoryState[ 1 ];

		var searchState = useState( '' );
		var search = searchState[ 0 ];
		var setSearch = searchState[ 1 ];

		var debouncedSearchState = useState( '' );
		var debouncedSearch = debouncedSearchState[ 0 ];
		var setDebouncedSearch = debouncedSearchState[ 1 ];
		var debounceSearch = useDebounce( setDebouncedSearch, 250 );

		var sortFieldState = useState( prefs.sortField || 'default' );
		var sortField = sortFieldState[ 0 ];
		var setSortField = sortFieldState[ 1 ];

		var sortOrderState = useState( prefs.sortOrder || 'asc' );
		var sortOrder = sortOrderState[ 0 ];
		var setSortOrder = sortOrderState[ 1 ];

		var typeState = useState( prefs.typeFilter || 'all' );
		var typeFilter = typeState[ 0 ];
		var setTypeFilter = typeState[ 1 ];

		var layoutState = useState( prefs.layout || 'grid' );
		var layout = layoutState[ 0 ];
		var setLayout = layoutState[ 1 ];

		var viewportState = useState( prefs.viewport || 'desktop' );
		var viewport = viewportState[ 0 ];
		var setViewport = viewportState[ 1 ];

		var previewSizeState = useState( prefs.previewSize || 2 );
		var previewSize = previewSizeState[ 0 ];
		var setPreviewSize = previewSizeState[ 1 ];

		var showTitleState = useState( prefs.showTitle !== false );
		var showTitle = showTitleState[ 0 ];
		var setShowTitle = showTitleState[ 1 ];

		var showPreviewState = useState( prefs.showPreview !== false );
		var showPreview = showPreviewState[ 0 ];
		var setShowPreview = showPreviewState[ 1 ];

		var patternsState = useState( [] );
		var patterns = patternsState[ 0 ];
		var setPatterns = patternsState[ 1 ];

		var pageState = useState( 1 );
		var page = pageState[ 0 ];
		var setPage = pageState[ 1 ];

		var hasMoreState = useState( true );
		var hasMore = hasMoreState[ 0 ];
		var setHasMore = hasMoreState[ 1 ];

		var loadingState = useState( true );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];

		var loadingMoreState = useState( false );
		var loadingMore = loadingMoreState[ 0 ];
		var setLoadingMore = loadingMoreState[ 1 ];

		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];

		var favoritesState = useState( function () {
			return readStore( FAVORITES_KEY, [] );
		} );
		var favorites = favoritesState[ 0 ];
		var setFavorites = favoritesState[ 1 ];

		var recentState = useState( function () {
			return readStore( RECENT_KEY, [] );
		} );
		var recent = recentState[ 0 ];
		var setRecent = recentState[ 1 ];

		var bulkModeState = useState( false );
		var bulkMode = bulkModeState[ 0 ];
		var setBulkMode = bulkModeState[ 1 ];

		var collectionsExpandedState = useState( false );
		var collectionsExpanded = collectionsExpandedState[ 0 ];
		var setCollectionsExpanded = collectionsExpandedState[ 1 ];

		var previewPatternState = useState( null );
		var previewPattern = previewPatternState[ 0 ];
		var setPreviewPattern = previewPatternState[ 1 ];

		var selectedState = useState( {} );
		var selectedMap = selectedState[ 0 ];
		var setSelectedMap = selectedState[ 1 ];

		var scrollRef = useRef( null );
		var sentinelRef = useRef( null );
		var abortRef = useRef( null );
		var requestIdRef = useRef( 0 );
		var firstPageReadyRef = useRef( false );

		useEffect(
			function () {
				debounceSearch( search );
			},
			[ search, debounceSearch ]
		);

		useEffect(
			function () {
				writeStore( PREFS_KEY, {
					layout: layout,
					viewport: viewport,
					sortField: sortField,
					sortOrder: sortOrder,
					typeFilter: typeFilter,
					previewSize: previewSize,
					showTitle: showTitle,
					showPreview: showPreview,
				} );
			},
			[
				layout,
				viewport,
				sortField,
				sortOrder,
				typeFilter,
				previewSize,
				showTitle,
				showPreview,
			]
		);

		var isLocalCollection =
			category === SPECIAL.FAVORITES || category === SPECIAL.RECENT;

		useEffect(
			function () {
				setPreviewPattern( null );
			},
			[ category, debouncedSearch ]
		);

		useEffect(
			function () {
				if ( ! previewPattern ) {
					return;
				}
				function onKeyDown( event ) {
					if ( event.key === 'Escape' ) {
						event.preventDefault();
						event.stopPropagation();
						setPreviewPattern( null );
					}
				}
				document.addEventListener( 'keydown', onKeyDown, true );
				return function () {
					document.removeEventListener( 'keydown', onKeyDown, true );
				};
			},
			[ previewPattern ]
		);

		var loadPage = useCallback(
			function ( nextPage, replace ) {
				if ( isLocalCollection ) {
					return;
				}

				if ( abortRef.current ) {
					abortRef.current.abort();
				}
				var controller = window.AbortController ? new window.AbortController() : null;
				abortRef.current = controller;
				var requestId = ++requestIdRef.current;

				if ( replace ) {
					setLoading( true );
					setError( '' );
				} else {
					setLoadingMore( true );
				}

				fetchPatternsPage(
					nextPage,
					debouncedSearch,
					category === SPECIAL.ALL ? '' : category,
					PAGE_SIZE,
					controller && controller.signal
				)
					.then( function ( payload ) {
						if ( requestId !== requestIdRef.current ) {
							return;
						}
						var data = payload && Array.isArray( payload.patterns ) ? payload.patterns : [];
						var complete = !!( payload && payload.complete );
						var total = payload && typeof payload.total === 'number' ? payload.total : 0;
						if ( payload && payload.categories && payload.categories.length ) {
							setRemoteCategories(
								payload.categories
									.filter( function ( cat ) {
										return cat && cat.name;
									} )
									.slice()
									.sort( function ( a, b ) {
										return formatCategoryLabel( a.name ).localeCompare(
											formatCategoryLabel( b.name )
										);
									} )
							);
						}
						setLibraryComplete( complete );
						if ( total ) {
							setLibraryTotal( total );
						}
						setPage( nextPage );
						setPatterns( function ( prev ) {
							var next = replace ? data : upsertByKey( prev, data );
							setHasMore( next.length < total || ( ! complete && data.length >= PAGE_SIZE ) );
							return next;
						} );
					} )
					.catch( function ( err ) {
						if ( err && err.name === 'AbortError' ) {
							return;
						}
						if ( requestId !== requestIdRef.current ) {
							return;
						}
						setError(
							__( 'Failed to load patterns. Please try again.', 'patternswp' )
						);
						if ( replace ) {
							setPatterns( [] );
							setHasMore( false );
						}
					} )
					.finally( function () {
						if ( requestId !== requestIdRef.current ) {
							return;
						}
						setLoading( false );
						setLoadingMore( false );
						if ( ! firstPageReadyRef.current ) {
							firstPageReadyRef.current = true;
							setGridReady( true );
						}
					} );
			},
			[ category, debouncedSearch, isLocalCollection ]
		);

		/* Local collections (favorites / recent) — no AJAX */
		useEffect(
			function () {
				if ( ! isLocalCollection ) {
					return;
				}
				var source = category === SPECIAL.FAVORITES ? favorites : recent;
				var filtered = source;
				if ( debouncedSearch ) {
					var q = debouncedSearch.toLowerCase();
					filtered = source.filter( function ( p ) {
						return ( p.title || '' ).toLowerCase().indexOf( q ) !== -1;
					} );
				}
				setPatterns( filtered );
				setHasMore( false );
				setLoading( false );
				setError( '' );
				setPage( 1 );
			},
			[ category, debouncedSearch, isLocalCollection, favorites, recent ]
		);

		/* Remote patterns — reset + fetch when category / search changes */
		useEffect(
			function () {
				if ( isLocalCollection ) {
					return;
				}

				setPatterns( [] );
				setPage( 1 );
				setHasMore( true );
				loadPage( 1, true );

				return function () {
					if ( abortRef.current ) {
						abortRef.current.abort();
					}
				};
			},
			[ category, debouncedSearch, isLocalCollection, loadPage ]
		);

		/* Fill the rest of the catalog after the first page is on screen. */
		useEffect(
			function () {
				if ( ! gridReady ) {
					return;
				}

				var stopped = false;
				var timer = 0;
				var tries = 0;
				var requestId = ++syncRequestRef.current;

				function applyStatus( status ) {
					if ( status.categories && status.categories.length ) {
						setRemoteCategories(
							status.categories
								.filter( function ( cat ) {
									return cat && cat.name;
								} )
								.slice()
								.sort( function ( a, b ) {
									return formatCategoryLabel( a.name ).localeCompare(
										formatCategoryLabel( b.name )
									);
								} )
						);
					}
					if ( typeof status.total === 'number' ) {
						setLibraryTotal( status.total );
					}
					setLibraryComplete( !! status.complete );
					if ( ! status.complete ) {
						setHasMore( true );
					}
				}

				function tick( mode ) {
					if ( stopped || requestId !== syncRequestRef.current || tries > 40 ) {
						setSyncing( false );
						return;
					}
					tries += 1;
					setSyncing( true );
					warmLibrary( null, mode )
						.then( function ( status ) {
							if ( stopped || requestId !== syncRequestRef.current ) {
								return;
							}
							applyStatus( status );
							if ( status.complete && ( mode === 'check' ? ! status.hasUpdates : true ) ) {
								setSyncing( false );
								return;
							}
							timer = window.setTimeout( function () {
								tick( 'sync' );
							}, 600 );
						} )
						.catch( function ( err ) {
							if ( err && err.name === 'AbortError' ) {
								return;
							}
							if ( ! stopped && requestId === syncRequestRef.current ) {
								timer = window.setTimeout( function () {
									tick( 'sync' );
								}, 2000 );
							}
						} );
				}

				tick( boot.libraryComplete ? 'check' : 'sync' );

				return function () {
					stopped = true;
					window.clearTimeout( timer );
				};
			},
			[ gridReady ]
		);

		function onSyncClick() {
			syncRequestRef.current += 1;
			var requestId = syncRequestRef.current;
			setSyncing( true );

			function applyStatus( status ) {
				if ( status.categories && status.categories.length ) {
					setRemoteCategories(
						status.categories
							.filter( function ( cat ) {
								return cat && cat.name;
							} )
							.slice()
							.sort( function ( a, b ) {
								return formatCategoryLabel( a.name ).localeCompare(
									formatCategoryLabel( b.name )
								);
							} )
					);
				}
				if ( typeof status.total === 'number' ) {
					setLibraryTotal( status.total );
				}
				setLibraryComplete( !! status.complete );
				if ( ! status.complete || status.hasUpdates ) {
					setHasMore( true );
				}
			}

			function tick( mode ) {
				if ( requestId !== syncRequestRef.current ) {
					return;
				}
				warmLibrary( null, mode, true )
					.then( function ( status ) {
						if ( requestId !== syncRequestRef.current ) {
							return;
						}
						applyStatus( status );
						if ( status.complete && ! status.hasUpdates ) {
							setSyncing( false );
							return;
						}
						window.setTimeout( function () {
							tick( 'sync' );
						}, 500 );
					} )
					.catch( function () {
						if ( requestId === syncRequestRef.current ) {
							setSyncing( false );
						}
					} );
			}

			tick( 'check' );
		}
		useEffect(
			function () {
				var sentinel = sentinelRef.current;
				var root = scrollRef.current;
				if ( ! sentinel || ! root || ! window.IntersectionObserver ) {
					return;
				}
				if ( isLocalCollection || ! hasMore || loading || loadingMore ) {
					return;
				}

				var observer = new window.IntersectionObserver(
					function ( entries ) {
						if ( entries[ 0 ] && entries[ 0 ].isIntersecting ) {
							loadPage( page + 1, false );
						}
					},
					{ root: root, rootMargin: '600px 0px', threshold: 0 }
				);
				observer.observe( sentinel );
				return function () {
					observer.disconnect();
				};
			},
			[ hasMore, loading, loadingMore, page, loadPage, isLocalCollection, patterns.length ]
		);

		var favoriteKeys = useMemo(
			function () {
				return new Set( favorites.map( patternKey ) );
			},
			[ favorites ]
		);

		var visiblePatterns = useMemo(
			function () {
				return sortPatterns( filterByType( patterns, typeFilter ), sortField, sortOrder );
			},
			[ patterns, typeFilter, sortField, sortOrder ]
		);

		var previewHeight = PREVIEW_SIZE_HEIGHTS[ previewSize ] || PREVIEW_SIZE_HEIGHTS[ 2 ];
		if ( layout === 'list' ) {
			previewHeight = Math.max( previewHeight, 300 );
		}

		function resetAppearance() {
			setSortField( DEFAULT_PREFS.sortField );
			setSortOrder( DEFAULT_PREFS.sortOrder );
			setPreviewSize( DEFAULT_PREFS.previewSize );
			setShowTitle( DEFAULT_PREFS.showTitle );
			setShowPreview( DEFAULT_PREFS.showPreview );
			setLayout( DEFAULT_PREFS.layout );
			setViewport( DEFAULT_PREFS.viewport );
		}

		var insertPattern = useInsertPattern( function ( patterns ) {
			var list = Array.isArray( patterns ) ? patterns : [ patterns ];
			setRecent( function ( prev ) {
				var next = prev.slice();
				list.forEach( function ( pattern ) {
					var key = patternKey( pattern );
					next = [ pattern ].concat(
						next.filter( function ( p ) {
							return patternKey( p ) !== key;
						} )
					);
				} );
				next = next.slice( 0, MAX_RECENT );
				writeStore( RECENT_KEY, next );
				return next;
			} );
			onRequestClose();
		} );

		var toggleFavorite = useCallback( function ( pattern ) {
			setFavorites( function ( prev ) {
				var key = patternKey( pattern );
				var exists = prev.some( function ( p ) {
					return patternKey( p ) === key;
				} );
				var next;
				if ( exists ) {
					next = prev.filter( function ( p ) {
						return patternKey( p ) !== key;
					} );
				} else {
					next = [ pattern ].concat( prev ).slice( 0, MAX_FAVORITES );
				}
				writeStore( FAVORITES_KEY, next );
				return next;
			} );
		}, [] );

		var toggleSelect = useCallback( function ( pattern ) {
			var key = patternKey( pattern );
			setSelectedMap( function ( prev ) {
				var next = Object.assign( {}, prev );
				if ( next[ key ] ) {
					delete next[ key ];
				} else {
					next[ key ] = pattern;
				}
				return next;
			} );
		}, [] );

		var selectedList = useMemo(
			function () {
				return Object.keys( selectedMap ).map( function ( key ) {
					return selectedMap[ key ];
				} );
			},
			[ selectedMap ]
		);

		var insertSelected = useCallback(
			function () {
				var allowed = selectedList.filter( function ( pattern ) {
					return ! isPatternLocked( pattern );
				} );
				if ( ! allowed.length ) {
					return;
				}
				insertPattern( allowed );
			},
			[ selectedList, insertPattern, isLicenseActive ]
		);

		var collectionItems = useMemo(
			function () {
				return [
					{ value: SPECIAL.ALL, label: __( 'All patterns', 'patternswp' ) },
					{
						value: SPECIAL.FAVORITES,
						label: sprintf(
							/* translators: %d: favorites count */
							__( 'Favorites (%d)', 'patternswp' ),
							favorites.length
						),
					},
					{
						value: SPECIAL.RECENT,
						label: sprintf(
							/* translators: %d: recent count */
							__( 'Recently used (%d)', 'patternswp' ),
							recent.length
						),
					},
				];
			},
			[ favorites.length, recent.length ]
		);

		var categoryItems = useMemo(
			function () {
				return categories.map( function ( cat ) {
					return {
						value: cat.name,
						label: formatCategoryLabel( cat.name ),
					};
				} );
			},
			[ categories ]
		);

		var viewportWidth = VIEWPORTS[ viewport ] || VIEWPORTS.desktop;

		var gridClass =
			'patternswp-grid' +
			( layout === 'list' ? ' is-list' : '' ) +
			( viewport === 'tablet' ? ' is-tablet' : '' ) +
			( viewport === 'mobile' ? ' is-mobile' : '' ) +
			' preview-size-' +
			previewSize;

		return el(
			Modal,
			{
				title: __( 'PatternsWP Patterns', 'patternswp' ),
				className: 'patternswp-modal',
				onRequestClose: onRequestClose,
				isFullScreen: true,
				shouldCloseOnClickOutside: false,
				headerActions: el(
					Button,
					{
						className: 'patternswp-library-sync' + ( syncing ? ' is-busy' : '' ),
						icon: syncing
							? el( Spinner )
							: iconOrDash( icons.refresh, 'update' ),
						label: syncing
							? __( 'Updating the pattern library', 'patternswp' )
							: libraryComplete
								? __( 'Check for new patterns', 'patternswp' )
								: __( 'Load remaining patterns', 'patternswp' ),
						showTooltip: true,
						onClick: onSyncClick,
						disabled: syncing,
					},
					syncing
						? ( libraryTotal
							? sprintf(
									/* translators: %d: number of cached patterns */
									__( '%d', 'patternswp' ),
									libraryTotal
							  )
							: __( 'Syncing', 'patternswp' ) )
						: __( 'Sync', 'patternswp' )
				),
			},
			el(
				'div',
				{ className: 'patternswp-layout' },
				el(
					'aside',
					{ className: 'patternswp-sidebar', 'aria-label': __( 'Pattern categories', 'patternswp' ) },
					el( SidebarCollapsibleSection, {
						title: __( 'Collections', 'patternswp' ),
						panelId: 'patternswp-collections-panel',
						isExpanded: collectionsExpanded,
						onToggle: function () {
							setCollectionsExpanded( ! collectionsExpanded );
						},
					},
						el(
							'ul',
							{ className: 'patternswp-sidebar__nav' },
							collectionItems.map( function ( item ) {
								return el( SidebarNavItem, {
									key: item.value || 'all',
									label: item.label,
									isActive: category === item.value,
									onClick: function () {
										setCategory( item.value );
									},
								} );
							} )
						)
					),
					el(
						'div',
						{ className: 'patternswp-sidebar__section' },
						el( 'h2', { className: 'patternswp-sidebar__section-title' }, __( 'Categories', 'patternswp' ) ),
						el(
							'ul',
							{ className: 'patternswp-sidebar__nav' },
							categoryItems.map( function ( item ) {
								return el( SidebarNavItem, {
									key: item.value,
									label: item.label,
									isActive: category === item.value,
									onClick: function () {
										setCategory( item.value );
									},
								} );
							} )
						)
					),
					el(
						'div',
						{ className: 'patternswp-sidebar__section patternswp-sidebar__footer' },
						el( 'h2', { className: 'patternswp-sidebar__section-title' }, __( 'Resources', 'patternswp' ) ),
						el(
							'a',
							{
								className: 'patternswp-sidebar__link',
								href: 'https://thepatternswp.com/docs/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__( 'Pattern guide', 'patternswp' )
						),
						el(
							'a',
							{
								className: 'patternswp-sidebar__link',
								href: isLicenseActive
									? 'https://thepatternswp.com/account/'
									: 'https://thepatternswp.com/pricing/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							isLicenseActive
								? __( 'PatternsWP account', 'patternswp' )
								: __( 'Upgrade to Pro', 'patternswp' )
						),
						el(
							'a',
							{
								className: 'patternswp-sidebar__link',
								href: 'https://thepatternswp.com/contact/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__( 'Support', 'patternswp' )
						)
					)
				),
				el(
					'section',
					{
						className:
							'patternswp-main' + ( previewPattern ? ' patternswp-main--detail' : '' ),
					},
					previewPattern
						? el( PatternDetailView, {
								pattern: previewPattern,
								isLicenseActive: isLicenseActive,
								isFavorite: favoriteKeys.has( patternKey( previewPattern ) ),
								viewportWidth: viewportWidth,
								onClose: function () {
									setPreviewPattern( null );
								},
								onInsert: insertPattern,
								onToggleFavorite: toggleFavorite,
						  } )
						: el(
								Fragment,
								null,
					el(
						'div',
						{ className: 'patternswp-toolbar' },
						el(
							'div',
							{ className: 'patternswp-toolbar__search' },
							el( SearchControl, {
								label: __( 'Search patterns', 'patternswp' ),
								placeholder: __( 'Search', 'patternswp' ),
								value: search,
								onChange: setSearch,
								__nextHasNoMarginBottom: true,
							} )
						),
						el(
							'div',
							{ className: 'patternswp-toolbar__actions' },
							el(
								'span',
								{ className: 'patternswp-toolbar__meta' },
								loading
									? el( Spinner )
									: sprintf(
											/* translators: %d: number of patterns */
											_n( '%d pattern', '%d patterns', visiblePatterns.length, 'patternswp' ),
											visiblePatterns.length
									  ) + ( hasMore && ! isLocalCollection ? '+' : '' )
							),
							el( Button, {
								className: 'patternswp-toolbar__bulk-edit',
								icon: iconOrDash( icons.edit, 'edit' ),
								onClick: function () {
									setBulkMode( ! bulkMode );
									if ( bulkMode ) {
										setSelectedMap( {} );
									}
								},
								isPressed: bulkMode,
							}, __( 'Bulk edit', 'patternswp' ) ),
							el(
								DropdownMenu,
								{
									icon: iconOrDash( icons.filter, 'filter' ),
									label: __( 'Filter', 'patternswp' ),
									toggleProps: {
										children: __( 'Filter', 'patternswp' ),
									},
								},
								function ( { onClose } ) {
									return el(
										Fragment,
										null,
										el(
											MenuGroup,
											{ label: __( 'Type', 'patternswp' ) },
											[
												{ value: 'all', label: __( 'All', 'patternswp' ) },
												{ value: 'free', label: __( 'Free', 'patternswp' ) },
												{ value: 'pro', label: __( 'Pro', 'patternswp' ) },
											].map( function ( opt ) {
												return el( MenuItem, {
													key: opt.value,
													icon: typeFilter === opt.value ? iconOrDash( icons.check, 'yes' ) : null,
													onClick: function () {
														setTypeFilter( opt.value );
														onClose();
													},
												}, opt.label );
											} )
										),
										el(
											MenuGroup,
											{ label: __( 'Preview width', 'patternswp' ) },
											[
												{ value: 'desktop', label: __( 'Desktop', 'patternswp' ) },
												{ value: 'tablet', label: __( 'Tablet', 'patternswp' ) },
												{ value: 'mobile', label: __( 'Mobile', 'patternswp' ) },
											].map( function ( opt ) {
												return el( MenuItem, {
													key: opt.value,
													icon: viewport === opt.value ? iconOrDash( icons.check, 'yes' ) : null,
													onClick: function () {
														setViewport( opt.value );
														onClose();
													},
												}, opt.label );
											} )
										)
									);
								}
							),
							el( Button, {
								icon: iconOrDash(
									layout === 'grid' ? icons.grid : icons.list,
									layout === 'grid' ? 'grid-view' : 'list-view'
								),
								label:
									layout === 'grid'
										? __( 'Grid view', 'patternswp' )
										: __( 'List view', 'patternswp' ),
								onClick: function () {
									setLayout( layout === 'grid' ? 'list' : 'grid' );
								},
							} ),
							Dropdown
								? el( Dropdown, {
										className: 'patternswp-appearance-dropdown',
										popoverProps: {
											placement: 'bottom-end',
											offset: 8,
											focusOnMount: false,
										},
										renderToggle: function ( toggleProps ) {
											return el( Button, {
												className: 'patternswp-appearance-toggle',
												icon: iconOrDash( icons.settings, 'admin-generic' ),
												label: __( 'Appearance', 'patternswp' ),
												onClick: function ( event ) {
													event.preventDefault();
													toggleProps.onToggle();
												},
												'aria-expanded': toggleProps.isOpen,
												isPressed: !! toggleProps.isOpen,
											} );
										},
										renderContent: function () {
											return el( AppearancePanel, {
												sortField: sortField,
												sortOrder: sortOrder,
												previewSize: previewSize,
												showTitle: showTitle,
												showPreview: showPreview,
												onSortFieldChange: setSortField,
												onSortOrderChange: setSortOrder,
												onPreviewSizeChange: setPreviewSize,
												onShowTitleChange: setShowTitle,
												onShowPreviewChange: setShowPreview,
												onReset: resetAppearance,
											} );
										},
								  } )
								: null
						)
					),
					bulkMode
						? el(
								'div',
								{ className: 'patternswp-bulk-bar' },
								el(
									'span',
									null,
									sprintf(
										/* translators: %d: selected count */
										_n( '%d selected', '%d selected', selectedList.length, 'patternswp' ),
										selectedList.length
									)
								),
								el(
									'div',
									{ className: 'patternswp-bulk-bar__actions' },
									el( Button, {
										variant: 'tertiary',
										onClick: function () {
											setSelectedMap( {} );
										},
										disabled: ! selectedList.length,
									}, __( 'Clear', 'patternswp' ) ),
									el( Button, {
										variant: 'primary',
										onClick: insertSelected,
										disabled: ! selectedList.length,
									}, __( 'Insert selected', 'patternswp' ) )
								)
						  )
						: null,
					error
						? el(
								Notice,
								{
									status: 'error',
									isDismissible: true,
									onRemove: function () {
										setError( '' );
									},
								},
								error
						  )
						: null,
					el(
						'div',
						{
							className: 'patternswp-grid-scroll',
							ref: scrollRef,
						},
						loading && isEmpty( visiblePatterns )
							? el( 'div', { className: 'patternswp-loading' }, el( Spinner ), __( 'Loading patterns…', 'patternswp' ) )
							: null,
						! loading && isEmpty( visiblePatterns )
							? el(
									'div',
									{ className: 'patternswp-empty' },
									el( Icon, { icon: iconOrDash( icons.search, 'search' ), size: 36 } ),
									el(
										'p',
										null,
										debouncedSearch
											? __( 'No search results found.', 'patternswp' )
											: libraryComplete
												? __( 'No patterns were found.', 'patternswp' )
												: __( 'Loading the pattern library…', 'patternswp' )
									)
							  )
							: null,
						! isEmpty( visiblePatterns )
							? el(
									'div',
									{ className: gridClass, role: 'list' },
									visiblePatterns.map( function ( pattern ) {
										var key = patternKey( pattern );
										return el( PatternCard, {
											key: key,
											pattern: pattern,
											isLicenseActive: isLicenseActive,
											isFavorite: favoriteKeys.has( key ),
											isSelected: !! selectedMap[ key ],
											bulkMode: bulkMode,
											viewportWidth: viewportWidth,
											scrollRoot: scrollRef.current,
											showTitle: showTitle,
											showPreview: showPreview,
											previewHeight: previewHeight,
											onInsert: insertPattern,
											onToggleFavorite: toggleFavorite,
											onToggleSelect: toggleSelect,
											onView: setPreviewPattern,
										} );
									} ),
									hasMore && ! isLocalCollection
										? el(
												'div',
												{ className: 'patternswp-sentinel', ref: sentinelRef },
												loadingMore ? el( Spinner ) : null
										  )
										: null
							  )
							: null
					)
						  )
				)
			)
		);
	}

	function PatternsModal( props ) {
		if ( ! props.isOpen ) {
			return null;
		}
		return el( PatternsLibrary, { onRequestClose: props.onRequestClose } );
	}

	/* ------------------------------------------------------------------ */
	/* Pattern Library block (inserter / /patternswp)                     */
	/* ------------------------------------------------------------------ */

	function InstantSearch( props ) {
		var clientId = props.clientId;
		var isLicenseActive = !! getBoot().isLicenseActive;

		var queryState = useState( '' );
		var query = queryState[ 0 ];
		var setQuery = queryState[ 1 ];

		var resultsState = useState( [] );
		var results = resultsState[ 0 ];
		var setResults = resultsState[ 1 ];

		var loadingState = useState( false );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];

		var openState = useState( false );
		var isOpen = openState[ 0 ];
		var setOpen = openState[ 1 ];

		var wrapRef = useRef( null );
		var abortRef = useRef( null );

		var insertPattern = useInsertPattern( function ( patterns ) {
			var list = Array.isArray( patterns ) ? patterns : [ patterns ];
			var prev = readStore( RECENT_KEY, [] );
			var next = prev.slice();
			list.forEach( function ( pattern ) {
				var key = patternKey( pattern );
				next = [ pattern ].concat(
					next.filter( function ( p ) {
						return patternKey( p ) !== key;
					} )
				);
			} );
			next = next.slice( 0, MAX_RECENT );
			writeStore( RECENT_KEY, next );
			setQuery( '' );
			setResults( [] );
			setOpen( false );
		} );

		var runSearch = useCallback(
			function ( value ) {
				if ( abortRef.current ) {
					abortRef.current.abort();
				}
				var trimmed = ( value || '' ).trim();
				if ( ! trimmed ) {
					setResults( [] );
					setLoading( false );
					setOpen( false );
					return;
				}

				var controller =
					typeof window.AbortController !== 'undefined'
						? new window.AbortController()
						: null;
				abortRef.current = controller;
				setLoading( true );
				setOpen( true );

				fetchPatternsPage(
					1,
					trimmed,
					'',
					undefined,
					controller ? controller.signal : undefined
				)
					.then( function ( payload ) {
						var data = payload && Array.isArray( payload.patterns ) ? payload.patterns : [];
						setResults( data.slice( 0, 8 ) );
						setLoading( false );
					} )
					.catch( function ( err ) {
						if ( err && err.name === 'AbortError' ) {
							return;
						}
						setResults( [] );
						setLoading( false );
					} );
			},
			[]
		);

		var debouncedSearch = useDebounce( runSearch, 300 );

		useEffect(
			function () {
				return function () {
					if ( abortRef.current ) {
						abortRef.current.abort();
					}
				};
			},
			[]
		);

		useEffect(
			function () {
				function onDocClick( event ) {
					if ( wrapRef.current && ! wrapRef.current.contains( event.target ) ) {
						setOpen( false );
					}
				}
				document.addEventListener( 'mousedown', onDocClick );
				return function () {
					document.removeEventListener( 'mousedown', onDocClick );
				};
			},
			[]
		);

		function onChange( event ) {
			var value = event.target.value;
			setQuery( value );
			debouncedSearch( value );
		}

		function onPick( pattern ) {
			if ( isPatternLocked( pattern ) ) {
				window.open( 'https://thepatternswp.com/pricing/', '_blank', 'noopener,noreferrer' );
				return;
			}
			insertPattern( pattern, { anchorClientId: clientId } );
		}

		return el(
			'div',
			{ className: 'patternswp-library-block__instant', ref: wrapRef },
			el(
				'div',
				{ className: 'patternswp-library-block__instant-head' },
				el(
					'label',
					{
						className: 'patternswp-library-block__instant-label',
						htmlFor: 'patternswp-instant-search-' + clientId,
					},
					__( 'Instant Search', 'patternswp' )
				),
				el(
					'p',
					{ className: 'patternswp-library-block__instant-help' },
					__( 'Type to find a pattern and insert it here.', 'patternswp' )
				)
			),
			el(
				'div',
				{ className: 'patternswp-library-block__search-wrap' },
				el( 'input', {
					id: 'patternswp-instant-search-' + clientId,
					type: 'search',
					className: 'patternswp-library-block__search-input',
					placeholder: __( 'Search patterns…', 'patternswp' ),
					value: query,
					onChange: onChange,
					onFocus: function () {
						if ( results.length || loading ) {
							setOpen( true );
						}
					},
					autoComplete: 'off',
				} ),
				el(
					'span',
					{ className: 'patternswp-library-block__search-icon', 'aria-hidden': true },
					loading
						? el( Spinner )
						: el( Icon, { icon: iconOrDash( icons.search, 'search' ) } )
				),
				isOpen && ( loading || results.length || query.trim() )
					? el(
							'div',
							{
								className: 'patternswp-library-block__results',
								role: 'listbox',
								'aria-label': __( 'Pattern search results', 'patternswp' ),
							},
							! loading && ! results.length
								? el(
										'div',
										{ className: 'patternswp-library-block__results-empty' },
										__( 'No patterns found.', 'patternswp' )
								  )
								: null,
							results.map( function ( pattern ) {
								var locked = isPatternLocked( pattern );
								return el(
									'button',
									{
										type: 'button',
										key: patternKey( pattern ),
										role: 'option',
										className:
											'patternswp-library-block__result' +
											( locked ? ' is-locked' : '' ),
										onClick: function () {
											onPick( pattern );
										},
									},
									el(
										'span',
										{ className: 'patternswp-library-block__result-title' },
										pattern.title || __( 'Untitled pattern', 'patternswp' )
									),
									pattern.type === 'pro' || pattern.locked
										? el(
												'span',
												{ className: 'patternswp-library-block__result-badge' },
												__( 'Pro', 'patternswp' )
										  )
										: null
								);
							} )
					  )
					: null
			)
		);
	}

	function PatternsLibraryBlockEdit( props ) {
		var clientId = props.clientId;
		var localOpenState = useState( false );
		var localOpen = localOpenState[ 0 ];
		var setLocalOpen = localOpenState[ 1 ];
		var removeBlock = useDispatch( storeBlockEditor ).removeBlock;

		function openLibrary() {
			var opened = openPatternsLibrary( { anchorClientId: clientId } );
			if ( ! opened ) {
				setInsertAnchor( clientId );
				setLocalOpen( true );
			}
		}

		function closeLocal() {
			setLocalOpen( false );
			setInsertAnchor( null );
		}

		function removeLibraryBlock() {
			setInsertAnchor( null );
			if ( removeBlock ) {
				removeBlock( clientId );
			}
		}

		return el(
			'div',
			{ className: 'patternswp-library-block' },
			el(
				Button,
				{
					className: 'patternswp-library-block__close',
					icon: iconOrDash( icons.close, 'no-alt' ),
					label: __( 'Remove PatternsWP Pattern Library', 'patternswp' ),
					showTooltip: true,
					onClick: removeLibraryBlock,
				}
			),
			el(
				'div',
				{ className: 'patternswp-library-block__inner' },
				el(
					'div',
					{ className: 'patternswp-library-block__header' },
					el(
						'span',
						{ className: 'patternswp-library-block__logo', 'aria-hidden': true },
						el( Icon, { icon: iconOrDash( icons.brand, 'layout' ) } )
					),
					el(
						'h2',
						{ className: 'patternswp-library-block__title' },
						__( 'PatternsWP Pattern Library', 'patternswp' )
					),
					el(
						'p',
						{ className: 'patternswp-library-block__desc' },
						__( 'Search and insert patterns into this page.', 'patternswp' )
					)
				),
				el( InstantSearch, { clientId: clientId } ),
				el(
					'div',
					{ className: 'patternswp-library-block__browse' },
					el(
						'button',
						{
							type: 'button',
							className: 'patternswp-library-block__browse-link',
							onClick: openLibrary,
						},
						__( 'Or browse the full library', 'patternswp' )
					)
				)
			),
			el( PatternsModal, {
				isOpen: localOpen,
				onRequestClose: closeLocal,
			} )
		);
	}

	if ( registerBlockType ) {
		registerBlockType( 'patternswp/library', {
			apiVersion: 3,
			title: __( 'PatternsWP Pattern Library', 'patternswp' ),
			description: __(
				'Browse the PatternsWP pattern library and insert patterns into your page.',
				'patternswp'
			),
			category: 'widgets',
			icon: iconOrDash( icons.brand, 'layout' ),
			keywords: [ 'patternswp', 'patterns', 'library', 'templates', 'blocks' ],
			supports: {
				html: false,
				multiple: true,
				reusable: false,
			},
			edit: PatternsLibraryBlockEdit,
			save: function () {
				return null;
			},
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Header toolbar + canvas block inserter (+) icon                      */
	/* ------------------------------------------------------------------ */

	var HEADER_SLOT_CLASS = 'patternswp-header-toolbar-slot';
	var HEADER_BUTTON_CLASS = 'patternswp-toolbar-button';
	var CANVAS_BUTTON_CLASS = 'patternswp-appender-button';
	var CANVAS_INSERTER_SELECTOR =
		'.components-dropdown.block-editor-inserter, .block-editor-inserter, .block-editor-inserter__toggle, .block-editor-button-block-appender, .block-list-appender__toggle, button[aria-label="Add block"]';
	var toolbarOpenRef = { current: null };

	var CANVAS_BRAND_SVG =
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">' +
		'<path d="M9.34 24L4.45 21.16L4.03 20.92V15.3L9.34 12V24Z"></path>' +
		'<path fill-rule="evenodd" clip-rule="evenodd" d="M14.66 9.2L20.97 6L14.66 2.8V9.2Z"></path>' +
		'<path d="M14.66 2.8L9.34 6L4.03 9.2V2.8L9.34 0L14.66 2.8Z"></path>' +
		'<path fill-rule="evenodd" clip-rule="evenodd" d="M10.79 11.44V17.84L14.66 15.3L20.97 12V6L14.66 9.2L10.79 11.44Z"></path>' +
		'</svg>';

	function openPatternsLibraryFromChrome() {
		if ( toolbarOpenRef.current ) {
			toolbarOpenRef.current();
		} else if ( openModalExternal ) {
			openModalExternal();
		}
	}

	function getHeaderToolbarLeft() {
		return (
			document.querySelector( '.editor-document-tools__left' ) ||
			document.querySelector( '.edit-post-header-toolbar__left' )
		);
	}

	var editorChrome = ( function () {
		var started = false;
		var syncTimer = null;
		var storeUnsub = null;
		var iframeWatch = null;
		var lastSelection = null;
		var headerLeftObservers = new WeakMap();
		var canvasInjectTimer = null;
		var canvasPollTimer = null;

		function getCanvasDocuments() {
			var docs = [];

			document
				.querySelectorAll( 'iframe' )
				.forEach( function ( iframe ) {
					try {
						if ( iframe.contentDocument && iframe.contentDocument.body ) {
							var doc = iframe.contentDocument;
							if (
								doc.querySelector( '.block-editor-block-list__layout' ) ||
								doc.querySelector( '.editor-styles-wrapper' ) ||
								doc.body.classList.contains( 'block-editor-page' ) ||
								iframe.name === 'editor-canvas'
							) {
								docs.push( doc );
							}
						}
					} catch ( e ) {}
				} );

			// Always include the main document, because Gutenberg renders side inserters
			// in the main document DOM even if the block list is inside an iframe.
			if ( docs.indexOf( document ) === -1 ) {
				docs.push( document );
			}

			return docs;
		}

		function canvasNeedsInjection() {
			var docs = getCanvasDocuments();
			for ( var d = 0; d < docs.length; d++ ) {
				var inserters = docs[ d ].querySelectorAll(
					CANVAS_INSERTER_SELECTOR
				);
				for ( var i = 0; i < inserters.length; i++ ) {
					var target = inserters[ i ];
					var parent = ( target.classList.contains('block-editor-inserter') || target.classList.contains('components-dropdown') ) ? target : target.parentNode;
					if (
						parent &&
						! parent.querySelector( '.' + CANVAS_BUTTON_CLASS )
					) {
						return true;
					}
				}
			}
			return false;
		}

		function injectCanvasIntoDropdown( target ) {
			if ( ! target || ! target.isConnected || target.classList.contains(CANVAS_BUTTON_CLASS) ) {
				return;
			}
			
			var parent;
			var toggleBtn;
			
			if ( target.classList.contains('block-editor-inserter') || target.classList.contains('components-dropdown') ) {
				parent = target;
				toggleBtn = target.querySelector('.block-editor-inserter__toggle') || target.querySelector('button');
			} else {
				parent = target.parentNode;
				toggleBtn = target;
			}

			// Do not inject if it's the top header toolbar (handled by syncHeaderToolbar)
			if ( parent && (parent.closest('.edit-post-header-toolbar') || parent.closest('.editor-document-tools')) ) {
				return;
			}

			// Do not inject next to the inline default block appender (e.g. empty paragraph block)
			if ( parent && parent.closest('.block-editor-default-block-appender') ) {
				return;
			}

			if ( ! parent || parent.querySelector( '.' + CANVAS_BUTTON_CLASS ) ) {
				return;
			}

			/* Copy the computed style from the + inserter toggle so we match it */
			var bgColor = '#1e1e1e';
			var btnSize = '24px';
			var borderRadius = '2px';

			try {
				if ( toggleBtn ) {
					var cs = toggleBtn.ownerDocument.defaultView.getComputedStyle( toggleBtn );
					if ( cs.backgroundColor && cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent' ) {
						bgColor = cs.backgroundColor;
					}
					if ( cs.height && cs.height !== 'auto' && cs.height !== '0px' ) {
						btnSize = cs.height;
					}
					if ( cs.borderRadius ) {
						borderRadius = cs.borderRadius;
					}
				}
			} catch ( e ) {}

			var btn = parent.ownerDocument.createElement( 'button' );
			btn.type = 'button';
			btn.setAttribute( 'aria-haspopup', 'true' );
			btn.className =
				'components-button block-editor-inserter__toggle has-icon ' +
				CANVAS_BUTTON_CLASS;
			btn.setAttribute(
				'aria-label',
				__( 'PatternsWP Library', 'patternswp' )
			);
			btn.innerHTML = CANVAS_BRAND_SVG;
			btn.style.cssText =
				'display:inline-flex;align-items:center;justify-content:center;' +
				'padding:0;width:' + btnSize + ';height:' + btnSize + ';border:none;' +
				'background:' + bgColor + ';color:#fff;cursor:pointer;' +
				'flex-shrink:0;border-radius:' + borderRadius + ';';
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				event.stopPropagation();
				openPatternsLibraryFromChrome();
			} );

			// Do not break Gutenberg layout by overriding parent display.
			// Just ensure our button is inline and has a margin to separate it.
			parent.style.display = 'flex';
			parent.style.gap = '5px';
			
			// Insert after the toggle button if it exists
			if ( toggleBtn && toggleBtn.nextSibling ) {
				parent.insertBefore( btn, toggleBtn.nextSibling );
			} else {
				parent.appendChild( btn );
			}
		}

		/**
		 * Inject a minimal inline <style> into an iframe/document so the
		 * appender button is visible even if the main CSS fails to load.
		 * Styled to match the + inserter: white icon on dark background.
		 */
		var styledDocs = new WeakSet();
		function ensureCanvasStyles( doc ) {
			if ( ! doc || ! doc.head || styledDocs.has( doc ) ) {
				return;
			}
			styledDocs.add( doc );
			var style = doc.createElement( 'style' );
			style.textContent =
				'.' + CANVAS_BUTTON_CLASS + '{' +
				'display:inline-flex!important;align-items:center;justify-content:center;' +
				'padding:0;min-width:24px;border:none;' +
				'background:#1e1e1e;color:#fff;cursor:pointer;' +
				'flex-shrink:0;border-radius:2px;}' +
				'.' + CANVAS_BUTTON_CLASS + ' svg{width:20px!important;height:20px!important;fill:#fff!important;}' +
				'.' + CANVAS_BUTTON_CLASS + ':hover{background:#000;color:#fff;}' +
				'.' + CANVAS_BUTTON_CLASS + ':focus{box-shadow:inset 0 0 0 var(--wp-admin-border-width-focus,1.5px) var(--wp-admin-theme-color,#3858e9);outline:1px solid transparent;}';
			doc.head.appendChild( style );
		}

		function injectAllCanvasButtons() {
			var docs = getCanvasDocuments();
			for ( var d = 0; d < docs.length; d++ ) {
				ensureCanvasStyles( docs[ d ] );
				var inserters = docs[ d ].querySelectorAll(
					CANVAS_INSERTER_SELECTOR
				);
				for ( var i = 0; i < inserters.length; i++ ) {
					injectCanvasIntoDropdown( inserters[ i ] );
				}
			}
		}

		function scheduleCanvasInject() {
			if ( canvasInjectTimer ) {
				window.clearTimeout( canvasInjectTimer );
			}
			canvasInjectTimer = window.setTimeout( function () {
				canvasInjectTimer = null;
				injectAllCanvasButtons();
			}, 120 );
		}

		function observeHeaderToolbarLeft( leftGroup ) {
			if ( ! leftGroup || headerLeftObservers.has( leftGroup ) ) {
				return;
			}

			var observer = new MutationObserver( function () {
				if (
					leftGroup.isConnected &&
					! leftGroup.querySelector( '.' + HEADER_BUTTON_CLASS )
				) {
					syncHeaderToolbar();
				}
			} );
			observer.observe( leftGroup, { childList: true } );
			headerLeftObservers.set( leftGroup, observer );
		}

		function isHeaderToolbarMounted() {
			var leftGroup = getHeaderToolbarLeft();
			return !!(
				leftGroup &&
				leftGroup.querySelector( '.' + HEADER_BUTTON_CLASS )
			);
		}

		function syncHeaderToolbar() {
			var leftGroup = getHeaderToolbarLeft();
			if ( ! leftGroup || leftGroup.querySelector( '.' + HEADER_BUTTON_CLASS ) ) {
				return;
			}

			var btn = leftGroup.ownerDocument.createElement( 'button' );
			btn.type = 'button';
			btn.className =
				'components-button is-tertiary has-icon ' + HEADER_BUTTON_CLASS;
			btn.setAttribute(
				'aria-label',
				__( 'PatternsWP Library', 'patternswp' )
			);
			btn.innerHTML =
				CANVAS_BRAND_SVG +
				'<span class="patternswp-toolbar-button__label">' +
				__( 'PatternsWP Library', 'patternswp' ) +
				'</span>';
			btn.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				openPatternsLibraryFromChrome();
			} );

			leftGroup.appendChild( btn );
			observeHeaderToolbarLeft( leftGroup );
		}

		function syncAll() {
			syncHeaderToolbar();
			getCanvasDocuments().forEach( observeCanvasBlockList );
			injectAllCanvasButtons();
		}

		function scheduleSync( followUpMs ) {
			if ( syncTimer ) {
				window.clearTimeout( syncTimer );
			}
			syncTimer = window.setTimeout( function () {
				syncTimer = null;
				syncAll();
			}, 100 );

			if ( followUpMs ) {
				window.setTimeout( syncAll, followUpMs );
			}
		}

		var canvasObservers = new WeakMap();

		function observeCanvasBlockList( doc ) {
			if ( ! doc || ! doc.body ) {
				return;
			}

			var existing = canvasObservers.get( doc );
			if (
				existing &&
				existing._pwpRoot &&
				existing._pwpRoot.isConnected
			) {
				return;
			}

			if ( existing ) {
				existing.disconnect();
			}

			var root =
				doc.querySelector( '.block-editor-block-list__layout' ) ||
				doc.querySelector( '.editor-styles-wrapper' ) ||
				doc.body;

			if ( ! root ) {
				return;
			}

			var observer = new MutationObserver( function () {
				scheduleCanvasInject();
			} );
			observer._pwpRoot = root;

			observer.observe( root, {
				childList: true,
				subtree: true,
			} );

			canvasObservers.set( doc, observer );
			scheduleCanvasInject();
		}

		function bindCanvasInteractionSync( doc ) {
			if ( ! doc || ! doc.body ) {
				return;
			}
			observeCanvasBlockList( doc );
			if ( doc.body.getAttribute( 'data-patternswp-click-bound' ) === 'true' ) {
				return;
			}
			doc.body.setAttribute( 'data-patternswp-click-bound', 'true' );
			doc.body.addEventListener(
				'click',
				function () {
					scheduleSync( 280 );
				},
				true
			);
			/* Re-inject on mouseover so buttons survive React re-renders */
			doc.body.addEventListener(
				'mouseover',
				function () {
					if ( canvasNeedsInjection() ) {
						scheduleCanvasInject();
					}
				},
				true
			);
		}

		function bindEditorIframe( iframe ) {
			if ( ! iframe || iframe.getAttribute( 'data-patternswp-bound' ) === 'true' ) {
				return;
			}
			iframe.setAttribute( 'data-patternswp-bound', 'true' );

			function onIframeReady() {
				try {
					if ( iframe.contentDocument ) {
						bindCanvasInteractionSync( iframe.contentDocument );
					}
				} catch ( e ) {}
				scheduleSync( 320 );
			}

			iframe.addEventListener( 'load', onIframeReady );
			onIframeReady();
		}

		function watchForEditorIframe() {
			document
				.querySelectorAll( 'iframe' )
				.forEach( bindEditorIframe );

			if ( iframeWatch ) {
				return;
			}

			iframeWatch = new MutationObserver( function ( mutations ) {
				for ( var i = 0; i < mutations.length; i++ ) {
					var mutation = mutations[ i ];
					for ( var j = 0; j < mutation.addedNodes.length; j++ ) {
						var node = mutation.addedNodes[ j ];
						if ( node.nodeType !== 1 ) {
							continue;
						}
						if (
							node.tagName && node.tagName.toLowerCase() === 'iframe'
						) {
							bindEditorIframe( node );
						} else if (
							node.querySelectorAll
						) {
							node
								.querySelectorAll( 'iframe' )
								.forEach( bindEditorIframe );
						}
					}
				}
			} );
			iframeWatch.observe( document.body, { childList: true, subtree: true } );
		}

		var headerBootObserver = null;
		var headerBootTimeoutId = null;

		function watchHeaderUntilMounted() {
			if ( isHeaderToolbarMounted() ) {
				return;
			}

			syncHeaderToolbar();
			if ( isHeaderToolbarMounted() ) {
				return;
			}

			if ( headerBootObserver ) {
				return;
			}

			var retryTimer = null;
			function retryHeaderMount() {
				if ( retryTimer ) {
					window.clearTimeout( retryTimer );
				}
				retryTimer = window.setTimeout( function () {
					retryTimer = null;
					syncHeaderToolbar();
					if ( isHeaderToolbarMounted() && headerBootObserver ) {
						headerBootObserver.disconnect();
						headerBootObserver = null;
					}
				}, 80 );
			}

			headerBootObserver = new MutationObserver( retryHeaderMount );
			headerBootObserver.observe( document.body, {
				childList: true,
				subtree: true,
			} );

			headerBootTimeoutId = window.setTimeout( function () {
				if ( headerBootObserver ) {
					headerBootObserver.disconnect();
					headerBootObserver = null;
				}
			}, 20000 );
		}

		function start() {
			scheduleSync( 350 );

			if ( started ) {
				watchHeaderUntilMounted();
				return;
			}
			started = true;

			bindCanvasInteractionSync( document );
			watchForEditorIframe();
			watchHeaderUntilMounted();

			[ 0, 200, 600, 1500, 3000, 5000 ].forEach( function ( delay ) {
				window.setTimeout( function () {
					syncAll();
					watchHeaderUntilMounted();
				}, delay );
			} );

			function pollCanvasButtons( attemptsLeft ) {
				injectAllCanvasButtons();
				if ( canvasNeedsInjection() && attemptsLeft > 0 ) {
					canvasPollTimer = window.setTimeout( function () {
						pollCanvasButtons( attemptsLeft - 1 );
					}, 200 );
				}
			}
			pollCanvasButtons( 30 );

			/* Persistent heartbeat: re-check every 800ms so the P button
			 * always comes back if React removes it during re-renders. */
			var heartbeatId = window.setInterval( function () {
				if ( canvasNeedsInjection() || ! isHeaderToolbarMounted() ) {
					syncAll();
				}
			}, 800 );

			if ( subscribe && storeBlockEditor ) {
				var lastHeaderCheck = 0;
				var lastBlockCount = null;
				storeUnsub = subscribe( function () {
					var select = wp.data.select( storeBlockEditor );
					if ( ! select || ! select.getBlockSelectionStart ) {
						return;
					}
					var selection = select.getBlockSelectionStart();
					var selectionChanged = selection !== lastSelection;
					var blockCount =
						select.getBlockCount && select.getBlockCount();
					var blockCountChanged = blockCount !== lastBlockCount;
					var canvasNeedsSync = canvasNeedsInjection();
					var headerMissing = ! isHeaderToolbarMounted();
					var now = Date.now();

					if (
						! selectionChanged &&
						! blockCountChanged &&
						! canvasNeedsSync &&
						! ( headerMissing && now - lastHeaderCheck > 1500 )
					) {
						return;
					}

					if ( selectionChanged ) {
						lastSelection = selection;
					}
					if ( blockCountChanged ) {
						lastBlockCount = blockCount;
					}
					if ( headerMissing ) {
						lastHeaderCheck = now;
						watchHeaderUntilMounted();
					}
					scheduleSync( 320 );
				} );
			}
		}

		return {
			start: start,
			scheduleSync: scheduleSync,
		};
	} )();

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			editorChrome.start();
		} );
	} else {
		editorChrome.start();
	}

	function HeaderToolbarBridge() {
		useEffect(
			function () {
				editorChrome.start();
				editorChrome.scheduleSync( 400 );
				return function () {};
			},
			[]
		);
		return null;
	}

	function PatternsWPModalHost() {
		var openState = useState( false );
		var isOpen = openState[ 0 ];
		var setOpen = openState[ 1 ];

		useEffect(
			function () {
				function openModalHandler() {
					setOpen( true );
				}
				openModalExternal = openModalHandler;
				toolbarOpenRef.current = openModalHandler;
				editorChrome.scheduleSync();
				return function () {
					openModalExternal = null;
					toolbarOpenRef.current = null;
				};
			},
			[]
		);

		function openModal() {
			setOpen( true );
		}

		function closeModal() {
			setOpen( false );
			setInsertAnchor( null );
		}

		var brandIcon = iconOrDash( icons.brand, 'layout' );

		return el(
			Fragment,
			null,
			el( HeaderToolbarBridge, null ),
			PluginMoreMenuItem
				? el(
						PluginMoreMenuItem,
						{
							icon: brandIcon,
							onClick: openModal,
						},
						__( 'PatternsWP Library', 'patternswp' )
				  )
				: null,
			el( PatternsModal, {
				isOpen: isOpen,
				onRequestClose: closeModal,
			} )
		);
	}

	if ( registerPlugin ) {
		registerPlugin( 'patternswp-library', {
			render: PatternsWPModalHost,
			icon: iconOrDash( icons.brand, 'layout' ),
		} );
	}
} )( window.wp, window.lodash || {} );
