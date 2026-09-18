/**
 * PatternsWP — Admin UI (Gutenberg components, no build step).
 * Text domain: patternswp
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.components || ! wp.domReady ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var createRoot = wp.element.createRoot;
	var render = wp.element.render;

	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	var c = wp.components;
	var Button = c.Button;
	var Card = c.Card;
	var CardBody = c.CardBody;
	var CardHeader = c.CardHeader;
	var CardFooter = c.CardFooter;
	var ToggleControl = c.ToggleControl;
	var FormToggle = c.FormToggle;
	var TextControl = c.TextControl;
	var Notice = c.Notice;
	var Spinner = c.Spinner;
	var ExternalLink = c.ExternalLink;
	var Flex = c.Flex;
	var FlexItem = c.FlexItem;
	var FlexBlock = c.FlexBlock;

	var SVG = wp.primitives && wp.primitives.SVG;
	var Path = wp.primitives && wp.primitives.Path;

	var data = window.patternswpAdmin || {};

	function logoIcon() {
		if ( ! SVG || ! Path ) {
			return null;
		}
		return el(
			SVG,
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
				width: 22,
				height: 22,
				fill: 'currentColor',
				'aria-hidden': true,
			},
			el( Path, {
				d: 'M9.34 24L4.45 21.16L4.03 20.92V15.3L9.34 12V24Z',
			} ),
			el( Path, {
				fillRule: 'evenodd',
				clipRule: 'evenodd',
				d: 'M14.66 9.2L20.97 6L14.66 2.8V9.2Z',
			} ),
			el( Path, {
				d: 'M14.66 2.8L9.34 6L4.03 9.2V2.8L9.34 0L14.66 2.8Z',
			} ),
			el( Path, {
				fillRule: 'evenodd',
				clipRule: 'evenodd',
				d: 'M10.79 11.44V17.84L14.66 15.3L20.97 12V6L14.66 9.2L10.79 11.44Z',
			} )
		);
	}

	function arrowIcon() {
		if ( ! SVG || ! Path ) {
			return '→';
		}
		return el(
			SVG,
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
				width: 18,
				height: 18,
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				'aria-hidden': true,
			},
			el( Path, {
				d: 'M5 12h14M13 6l6 6-6 6',
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			} )
		);
	}

	function ajax( action, body ) {
		var form = new window.FormData();
		form.append( 'action', action );
		form.append( 'nonce', data.nonce || '' );

		if ( body && typeof body === 'object' ) {
			Object.keys( body ).forEach( function ( key ) {
				var value = body[ key ];
				if ( typeof value === 'boolean' ) {
					form.append( key, value ? '1' : '0' );
				} else if ( value !== undefined && value !== null ) {
					form.append( key, String( value ) );
				}
			} );
		}

		return window
			.fetch( data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: form,
			} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					var message =
						( json &&
							json.data &&
							( json.data.message || json.data ) ) ||
						__( 'Something went wrong. Please try again.', 'patternswp' );
					throw new Error(
						typeof message === 'string'
							? message
							: __( 'Something went wrong. Please try again.', 'patternswp' )
					);
				}
				return json.data || {};
			} );
	}

	function AdminNotice( props ) {
		if ( ! props.notice ) {
			return null;
		}
		return el(
			'div',
			{ className: 'patternswp-admin__notice' },
			el(
				Notice,
				{
					status: props.notice.status || 'info',
					isDismissible: true,
					onRemove: props.onDismiss,
				},
				props.notice.message
			)
		);
	}

	function Header( props ) {
		return el(
			'header',
			{ className: 'patternswp-admin__header' },
			el(
				'div',
				{ className: 'patternswp-admin__brand' },
				el( 'div', { className: 'patternswp-admin__logo' }, logoIcon() ),
				el(
					'div',
					null,
					el( 'h1', { className: 'patternswp-admin__title' }, 'PatternsWP' ),
					el(
						'p',
						{ className: 'patternswp-admin__subtitle' },
						props.subtitle ||
							__( 'Pattern library for the block editor', 'patternswp' )
					)
				)
			),
			el(
				'div',
				{ className: 'patternswp-admin__header-actions' },
				el(
					Button,
					{
						variant: 'secondary',
						href: 'https://thepatternswp.com/docs/',
						target: '_blank',
						rel: 'noopener noreferrer',
					},
					__( 'Documentation', 'patternswp' )
				),
				! data.isLicenseActive &&
					el(
						Button,
						{
							variant: 'primary',
							href: 'https://thepatternswp.com/pricing/',
							target: '_blank',
							rel: 'noopener noreferrer',
						},
						__( 'Upgrade to Pro', 'patternswp' )
					)
			)
		);
	}

	function Nav( props ) {
		var tabs = [
			{
				id: 'dashboard',
				label: __( 'Dashboard', 'patternswp' ),
				slug: 'patternswp-plugin-menu',
			},
			{
				id: 'settings',
				label: __( 'Settings', 'patternswp' ),
				slug: 'patternswp-settings',
			},
			{
				id: 'support',
				label: __( 'Support', 'patternswp' ),
				slug: 'patternswp-plugin-page-1',
			},
			{
				id: 'license',
				label: __( 'License', 'patternswp' ),
				slug: 'patternswp-license_section',
			},
		];

		return el(
			'nav',
			{
				className: 'patternswp-admin__nav',
				'aria-label': __( 'PatternsWP sections', 'patternswp' ),
			},
			tabs.map( function ( tab ) {
				return el(
					'a',
					{
						key: tab.id,
						href: data.adminUrl + '?page=' + tab.slug,
						className:
							'patternswp-admin__nav-item' +
							( props.current === tab.id ? ' is-active' : '' ),
						'aria-current': props.current === tab.id ? 'page' : undefined,
					},
					tab.label
				);
			} )
		);
	}

	function DashboardPage() {
		var steps = [
			{
				num: '01',
				title: __( 'Open the PatternsWP Library', 'patternswp' ),
				text: __(
					'When editing a page or post, add the PatternsWP Pattern Library block (search “patternswp” or type /patternswp), or use the PatternsWP Library button in the editor header.',
					'patternswp'
				),
				image: data.images && data.images.step1,
			},
			{
				num: '02',
				title: __( 'Browse Patterns & Templates', 'patternswp' ),
				text: __(
					'Explore a diverse range of block patterns and full-page layouts. Use search or filter by category to find the right design.',
					'patternswp'
				),
				image: data.images && data.images.step2,
			},
			{
				num: '03',
				title: __( 'Add Patterns & Customize', 'patternswp' ),
				text: __(
					'Insert a pattern with one click, then customize colors, typography, and content to match your site.',
					'patternswp'
				),
				image: data.images && data.images.step3,
			},
		];

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'patternswp-admin__card patternswp-admin__hero' },
				el(
					'p',
					{ className: 'patternswp-admin__hero-eyebrow' },
					sprintf(
						/* translators: %s: user display name */
						__( 'Hello, %s', 'patternswp' ),
						data.userName || __( 'there', 'patternswp' )
					)
				),
				el(
					'h2',
					{ className: 'patternswp-admin__hero-title' },
					__( 'Welcome to PatternsWP', 'patternswp' )
				),
				el(
					'p',
					{ className: 'patternswp-admin__hero-text' },
					__(
						'Thanks for choosing PatternsWP. Follow these three steps to start building faster with ready-made block patterns.',
						'patternswp'
					)
				),
				el(
					'div',
					{ className: 'patternswp-admin__hero-actions' },
					el(
						Button,
						{
							variant: 'primary',
							href: data.newPageUrl,
						},
						__( 'Start building with PatternsWP', 'patternswp' )
					),
					el(
						Button,
						{
							variant: 'tertiary',
							href: data.adminUrl + '?page=patternswp-settings',
						},
						__( 'Configure settings', 'patternswp' )
					)
				)
			),
			el(
				'div',
				{ className: 'patternswp-admin__steps' },
				steps.map( function ( step ) {
					return el(
						'article',
						{ key: step.num, className: 'patternswp-admin__step' },
						step.image &&
							el(
								'div',
								{ className: 'patternswp-admin__step-media' },
								el( 'img', {
									src: step.image,
									alt: '',
								} )
							),
						el(
							'div',
							{ className: 'patternswp-admin__step-body' },
							el(
								'p',
								{ className: 'patternswp-admin__step-num' },
								step.num
							),
							el(
								'h3',
								{ className: 'patternswp-admin__step-title' },
								step.title
							),
							el(
								'p',
								{ className: 'patternswp-admin__step-text' },
								step.text
							)
						)
					);
				} )
			)
		);
	}

	function SettingsPage( props ) {
		var settings = props.settings;
		var setSettings = props.setSettings;
		var setNotice = props.setNotice;
		var saving = props.saving;
		var setSaving = props.setSaving;
		var clearing = props.clearing;
		var setClearing = props.setClearing;

		function updateToggle( key, value ) {
			setSettings( Object.assign( {}, settings, { [ key ]: value } ) );
		}

		function saveSettings() {
			setSaving( true );
			setNotice( null );
			ajax( 'patternswp_save_settings', {
				hide_theme_patterns: settings.hideThemePatterns,
				hide_uncategorized_patterns: settings.hideUncategorizedPatterns,
				hide_core_patterns: settings.hideCorePatterns,
			} )
				.then( function ( result ) {
					setNotice( {
						status: 'success',
						message:
							( result && result.message ) ||
							__( 'Settings saved.', 'patternswp' ),
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error.message,
					} );
				} )
				.finally( function () {
					setSaving( false );
				} );
		}

		function clearCache() {
			setClearing( true );
			setNotice( null );
			ajax( 'patternswp_clear_cache_ajax' )
				.then( function ( result ) {
					setNotice( {
						status: 'success',
						message:
							( result && result.message ) ||
							__( 'Cache cleared successfully.', 'patternswp' ),
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error.message,
					} );
				} )
				.finally( function () {
					setClearing( false );
				} );
		}

		var rows = [
			{
				key: 'hideThemePatterns',
				label: __( 'Hide Theme Patterns', 'patternswp' ),
				help: __(
					'Prevent patterns registered by the active theme from displaying in the patterns list.',
					'patternswp'
				),
			},
			{
				key: 'hideUncategorizedPatterns',
				label: __( 'Hide Uncategorized Patterns', 'patternswp' ),
				help: __(
					'Prevent patterns that are not in any registered category from displaying.',
					'patternswp'
				),
			},
			{
				key: 'hideCorePatterns',
				label: __( 'Hide Core Patterns', 'patternswp' ),
				help: __(
					'Remove WordPress core patterns from the pattern selector.',
					'patternswp'
				),
			},
		];

		return el(
			Fragment,
			null,
			el(
				Card,
				{ className: 'patternswp-admin__card' },
				el(
					CardHeader,
					null,
					el(
						'div',
						null,
						el(
							'h2',
							{ className: 'patternswp-admin__card-title' },
							__( 'Pattern Visibility', 'patternswp' )
						),
						el(
							'p',
							{ className: 'patternswp-admin__card-desc' },
							__(
								'Control which patterns appear in the block editor.',
								'patternswp'
							)
						)
					)
				),
				el(
					CardBody,
					null,
					rows.map( function ( row ) {
						return el(
							'div',
							{
								key: row.key,
								className: 'patternswp-admin__setting-row',
							},
							el(
								'div',
								null,
								el(
									'p',
									{ className: 'patternswp-admin__setting-label' },
									row.label
								),
								el(
									'p',
									{ className: 'patternswp-admin__setting-help' },
									row.help
								)
							),
							el( FormToggle || ToggleControl, FormToggle
								? {
										checked: !! settings[ row.key ],
										onChange: function () {
											updateToggle(
												row.key,
												! settings[ row.key ]
											);
										},
										'aria-label': row.label,
								  }
								: {
										checked: !! settings[ row.key ],
										onChange: function ( value ) {
											updateToggle( row.key, value );
										},
										label: row.label,
										hideLabelFromVision: true,
										__nextHasNoMarginBottom: true,
								  } )
						);
					} )
				),
				el(
					CardFooter,
					null,
					el(
						Button,
						{
							variant: 'primary',
							onClick: saveSettings,
							isBusy: saving,
							disabled: saving,
						},
						saving
							? __( 'Saving…', 'patternswp' )
							: __( 'Save Settings', 'patternswp' )
					)
				)
			),
			el(
				Card,
				{ className: 'patternswp-admin__card' },
				el(
					CardHeader,
					null,
					el(
						'div',
						null,
						el(
							'h2',
							{ className: 'patternswp-admin__card-title' },
							__( 'Cache Management', 'patternswp' )
						),
						el(
							'p',
							{ className: 'patternswp-admin__card-desc' },
							__(
								'Clear cached patterns and data if the library looks out of date.',
								'patternswp'
							)
						)
					)
				),
				el(
					CardBody,
					null,
					el(
						'div',
						{ className: 'patternswp-admin__cache-box' },
						el(
							'p',
							null,
							__(
								'Clear all cached patterns and related data. Useful after changing visibility settings or if patterns are not updating correctly.',
								'patternswp'
							)
						),
						el(
							Button,
							{
								variant: 'secondary',
								onClick: clearCache,
								isBusy: clearing,
								disabled: clearing,
							},
							clearing
								? __( 'Clearing…', 'patternswp' )
								: __( 'Clear All Cache', 'patternswp' )
						)
					)
				)
			)
		);
	}

	function SupportPage() {
		var links = [
			{
				label: __( 'Getting Started with PatternsWP', 'patternswp' ),
				url: 'https://thepatternswp.com/docs/getting-started-with-patternswp/',
			},
			{
				label: __( 'How to install PatternsWP', 'patternswp' ),
				url: 'https://thepatternswp.com/docs/how-to-install-patternswp/',
			},
			{
				label: __( 'How to Upgrade PatternsWP to Pro', 'patternswp' ),
				url: 'https://thepatternswp.com/docs/how-to-upgrade-patternswp-to-pro/',
			},
			{
				label: __( 'PatternsWP Support', 'patternswp' ),
				url: 'https://thepatternswp.com/docs/patternswp-support/',
			},
		];

		return el(
			Card,
			{ className: 'patternswp-admin__card' },
			el(
				CardHeader,
				null,
				el(
					'div',
					null,
					el(
						'h2',
						{ className: 'patternswp-admin__card-title' },
						__( 'Support', 'patternswp' )
					),
					el(
						'p',
						{ className: 'patternswp-admin__card-desc' },
						__(
							'Need help? Browse the docs or contact us — we are happy to assist.',
							'patternswp'
						)
					)
				)
			),
			el(
				CardBody,
				null,
				el(
					'div',
					{ className: 'patternswp-admin__support-grid' },
					el(
						'div',
						null,
						el(
							'h3',
							{ className: 'patternswp-admin__card-title' },
							__( 'Frequently asked questions', 'patternswp' )
						),
						el(
							'p',
							{ className: 'patternswp-admin__card-desc' },
							__(
								'Find answers to common questions about installing, upgrading, and using PatternsWP.',
								'patternswp'
							)
						),
						el(
							'div',
							{ className: 'patternswp-admin__link-row' },
							el(
								Button,
								{
									variant: 'primary',
									href: 'https://thepatternswp.com/contact/',
									target: '_blank',
									rel: 'noopener noreferrer',
								},
								__( 'Contact support', 'patternswp' )
							),
							el(
								Button,
								{
									variant: 'secondary',
									href: 'https://thepatternswp.com/docs/',
									target: '_blank',
									rel: 'noopener noreferrer',
								},
								__( 'View documentation', 'patternswp' )
							)
						)
					),
					el(
						'ul',
						{ className: 'patternswp-admin__faq-list' },
						links.map( function ( item ) {
							return el(
								'li',
								{ key: item.url },
								el(
									'a',
									{
										className: 'patternswp-admin__faq-link',
										href: item.url,
										target: '_blank',
										rel: 'noopener noreferrer',
									},
									el( 'span', null, item.label ),
									arrowIcon()
								)
							);
						} )
					)
				)
			)
		);
	}

	function LicensePage( props ) {
		var licenseKey = props.licenseKey;
		var setLicenseKey = props.setLicenseKey;
		var isActive = props.isActive;
		var setIsActive = props.setIsActive;
		var setNotice = props.setNotice;
		var activating = props.activating;
		var setActivating = props.setActivating;

		function activate() {
			setActivating( true );
			setNotice( null );
			ajax( 'patternswp_activate_license', {
				license_key: licenseKey,
			} )
				.then( function ( result ) {
					if ( result.maskedKey ) {
						setLicenseKey( result.maskedKey );
					}
					if ( typeof result.activated !== 'undefined' ) {
						setIsActive( !! result.activated );
					}
					setNotice( {
						status: result.status || 'success',
						message:
							( result && result.message ) ||
							__( 'License activated successfully.', 'patternswp' ),
					} );
				} )
				.catch( function ( error ) {
					setNotice( {
						status: 'error',
						message: error.message,
					} );
				} )
				.finally( function () {
					setActivating( false );
				} );
		}

		return el(
			Card,
			{ className: 'patternswp-admin__card' },
			el(
				CardHeader,
				null,
				el(
					Flex,
					{ align: 'flex-start', justify: 'space-between', gap: 12 },
					el(
						FlexBlock,
						null,
						el(
							'h2',
							{ className: 'patternswp-admin__card-title' },
							__( 'License', 'patternswp' )
						),
						el(
							'p',
							{ className: 'patternswp-admin__card-desc' },
							__(
								'Already purchased? Enter your license key below to unlock PatternsWP Pro.',
								'patternswp'
							)
						)
					),
					el(
						FlexItem,
						null,
						el(
							'span',
							{
								className:
									'patternswp-admin__status ' +
									( isActive ? 'is-active' : 'is-inactive' ),
							},
							isActive
								? __( 'Active', 'patternswp' )
								: __( 'Inactive', 'patternswp' )
						)
					)
				)
			),
			el(
				CardBody,
				null,
				el(
					'div',
					{ className: 'patternswp-admin__license-form' },
					el( TextControl, {
						label: __( 'License key', 'patternswp' ),
						value: licenseKey,
						onChange: setLicenseKey,
						placeholder: __(
							'Enter your license key here…',
							'patternswp'
						),
						type: 'text',
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
					} ),
					el(
						'div',
						{ className: 'patternswp-admin__license-actions' },
						el(
							Button,
							{
								variant: 'primary',
								onClick: activate,
								isBusy: activating,
								disabled: activating || ! licenseKey,
							},
							activating
								? __( 'Activating…', 'patternswp' )
								: __( 'Save & Activate', 'patternswp' )
						),
						! isActive &&
							el(
								ExternalLink,
								{ href: 'https://thepatternswp.com/pricing/' },
								__( 'Get a license', 'patternswp' )
							)
					),
					isActive &&
						el(
							'p',
							{ className: 'patternswp-admin__card-desc' },
							__(
								'Your license is active and verified. Enjoy all premium features!',
								'patternswp'
							)
						)
				)
			)
		);
	}

	function App() {
		var page = data.currentPage || 'dashboard';
		var noticeState = useState( data.initialNotice || null );
		var notice = noticeState[ 0 ];
		var setNotice = noticeState[ 1 ];

		var settingsState = useState( {
			hideThemePatterns: !! data.settings.hideThemePatterns,
			hideUncategorizedPatterns: !! data.settings.hideUncategorizedPatterns,
			hideCorePatterns: !! data.settings.hideCorePatterns,
		} );
		var settings = settingsState[ 0 ];
		var setSettings = settingsState[ 1 ];

		var savingState = useState( false );
		var saving = savingState[ 0 ];
		var setSaving = savingState[ 1 ];

		var clearingState = useState( false );
		var clearing = clearingState[ 0 ];
		var setClearing = clearingState[ 1 ];

		var licenseState = useState( data.license.maskedKey || '' );
		var licenseKey = licenseState[ 0 ];
		var setLicenseKeyLocal = licenseState[ 1 ];

		var activeState = useState( !! data.license.activated );
		var isActive = activeState[ 0 ];
		var setIsActive = activeState[ 1 ];

		var activatingState = useState( false );
		var activating = activatingState[ 0 ];
		var setActivating = activatingState[ 1 ];

		var subtitles = {
			dashboard: __( 'Get started with PatternsWP', 'patternswp' ),
			settings: __( 'Pattern visibility and cache', 'patternswp' ),
			support: __( 'Help, docs, and contact', 'patternswp' ),
			license: __( 'Activate your Pro license', 'patternswp' ),
		};

		useEffect( function () {
			document.body.classList.add( 'patternswp-admin-body' );
			return function () {
				document.body.classList.remove( 'patternswp-admin-body' );
			};
		}, [] );

		var content;
		if ( page === 'settings' ) {
			content = el( SettingsPage, {
				settings: settings,
				setSettings: setSettings,
				setNotice: setNotice,
				saving: saving,
				setSaving: setSaving,
				clearing: clearing,
				setClearing: setClearing,
			} );
		} else if ( page === 'support' ) {
			content = el( SupportPage );
		} else if ( page === 'license' ) {
			content = el( LicensePage, {
				licenseKey: licenseKey,
				setLicenseKey: setLicenseKeyLocal,
				isActive: isActive,
				setIsActive: setIsActive,
				setNotice: setNotice,
				activating: activating,
				setActivating: setActivating,
			} );
		} else {
			content = el( DashboardPage );
		}

		return el(
			'div',
			{ className: 'patternswp-admin' },
			el( Header, { subtitle: subtitles[ page ] || subtitles.dashboard } ),
			el( Nav, { current: page } ),
			el( AdminNotice, {
				notice: notice,
				onDismiss: function () {
					setNotice( null );
				},
			} ),
			content
		);
	}

	function mount() {
		var rootEl = document.getElementById( 'patternswp-admin-root' );
		if ( ! rootEl ) {
			return;
		}
		if ( createRoot ) {
			createRoot( rootEl ).render( el( App ) );
		} else {
			render( el( App ), rootEl );
		}
	}

	wp.domReady( mount );
} )( window.wp );
