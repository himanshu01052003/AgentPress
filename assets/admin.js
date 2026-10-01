( function () {
	var i18n = window.mcp100p || { copied: 'Copied!', copy: 'Copy', revoke: 'Revoke this key?' };

	function init() {
		var tabs = Array.prototype.slice.call( document.querySelectorAll( '.mcp100p-client' ) );

		function select( tab, focus ) {
			tabs.forEach( function ( t ) {
				var active = t === tab;
				var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );
				t.classList.toggle( 'is-active', active );
				t.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				t.tabIndex = active ? 0 : -1;
				if ( panel ) {
					panel.hidden = ! active;
				}
			} );
			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				select( tab, false );
			} );
			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
					next = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
					next = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === event.key ) {
					next = tabs[ 0 ];
				} else if ( 'End' === event.key ) {
					next = tabs[ tabs.length - 1 ];
				}
				if ( next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );

		// "Create API key" links: scroll to the form and focus the name field.
		document.querySelectorAll( 'a[href="#mcp100p-create"]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				var form = document.getElementById( 'mcp100p-create' );
				var input = document.getElementById( 'mcp100p-label' );
				if ( ! form ) {
					return;
				}
				event.preventDefault();
				form.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				form.classList.add( 'mcp100p-flash' );
				setTimeout( function () {
					form.classList.remove( 'mcp100p-flash' );
					if ( input ) {
						input.focus( { preventScroll: true } );
					}
				}, 600 );
			} );
		} );

		document.querySelectorAll( '.mcp100p-revoke-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				if ( ! window.confirm( i18n.revoke ) ) {
					event.preventDefault();
				}
			} );
		} );

		document.querySelectorAll( '.mcp100p-copy' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var container = button.closest( '.mcp100p-code, .mcp100p-endpoint__row' );
				var label = button.querySelector( '.mcp100p-copy__text' );

				copyText( container.querySelector( 'code' ).textContent ).then( function () {
					button.classList.add( 'is-copied' );
					label.textContent = i18n.copied;
					setTimeout( function () {
						button.classList.remove( 'is-copied' );
						label.textContent = i18n.copy;
					}, 1500 );
				} );
			} );
		} );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return fallbackCopy( text );
			} );
		}
		return Promise.resolve( fallbackCopy( text ) );
	}

	function fallbackCopy( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		document.body.removeChild( area );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
