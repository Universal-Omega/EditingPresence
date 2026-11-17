'use strict';

/**
 * The notice shown above the editor. It stays hidden until there is at
 * least one other editor to report.
 */
class PresenceBanner {
	constructor() {
		this.$content = $( '<div>' ).addClass( 'cdx-message__content' );
		this.$element = $( '<div>' )
			.addClass( 'cdx-message cdx-message--block cdx-message--notice ext-editingpresence-banner' )
			.attr( { role: 'status', 'aria-live': 'polite' } )
			.append( $( '<span>' ).addClass( 'cdx-message__icon' ), this.$content )
			.hide();
	}

	/**
	 * @param {Object} response The editingpresence part of the API response.
	 * @param {boolean} viewerEditsSection Whether the viewer is editing a single section.
	 */
	render( response, viewerEditsSection ) {
		const groups = { page: [], section: [] };
		( response.editors || [] ).forEach( ( editor ) => {
			if ( groups[ editor.group ] ) {
				groups[ editor.group ].push( editor );
			}
		} );

		this.$content.empty();
		let listed = [];
		const lines = [];
		[ 'page', 'section' ].forEach( ( group ) => {
			if ( groups[ group ].length ) {
				const listNames = !!response.showusernames &&
					groups[ group ].length < Math.max( 1, response.maxnames || 1 );
				const line = this.buildLine(
					groups[ group ], group, viewerEditsSection, listNames
				);
				lines.push( $( '<p>' ).text( line ) );
				if ( listNames ) {
					listed = listed.concat( groups[ group ] );
				}
			}
		} );

		const $text = $( '<div>' ).addClass( 'ext-editingpresence-text' ).append( lines );
		const $typing = this.buildTyping( listed );
		if ( $typing ) {
			$text.append( $typing );
		}

		const $row = $( '<div>' ).addClass( 'ext-editingpresence-row' );
		const $avatars = this.buildAvatars( listed );
		if ( $avatars ) {
			$row.append( $avatars );
		}
		this.$content.append( $row.append( $text ) );

		this.$element.toggle( lines.length > 0 );
	}

	/**
	 * Avatars and colours are only sent for editors whose name is shown.
	 *
	 * @param {Object[]} editors
	 * @return {jQuery|null}
	 */
	buildAvatars( editors ) {
		const rich = editors.filter( ( editor ) => editor.username && editor.hue !== undefined );
		if ( !rich.length ) {
			return null;
		}
		return $( '<div>' )
			.addClass( 'ext-editingpresence-avatars' )
			.append( rich.map( ( editor ) => this.buildAvatar( editor ) ) );
	}

	buildAvatar( editor ) {
		const $avatar = $( '<span>' )
			.addClass( 'ext-editingpresence-avatar' )
			.attr( 'title', editor.username );
		$avatar[ 0 ].style.setProperty( '--ext-editingpresence-hue', String( Number( editor.hue ) || 0 ) );

		if ( editor.typing ) {
			$avatar.addClass( 'ext-editingpresence-avatar--typing' );
		}

		if ( editor.avatar ) {
			$avatar.append( $( '<img>' ).attr( { src: editor.avatar, alt: '', loading: 'lazy' } ) );
		} else {
			$avatar.text( Array.from( editor.username )[ 0 ].toUpperCase() );
		}
		return $avatar;
	}

	buildTyping( editors ) {
		const names = editors
			.filter( ( editor ) => editor.typing && editor.username )
			.map( ( editor ) => editor.username );
		if ( !names.length ) {
			return null;
		}
		return $( '<p>' )
			.addClass( 'ext-editingpresence-typing' )
			.text( mw.message( 'editingpresence-typing', mw.language.listToText( names ), names.length ).text() );
	}

	buildLine( editors, group, viewerEditsSection, listNames ) {
		const viewer = viewerEditsSection ? 'section' : 'page';
		const base = 'editingpresence-' + viewer + '-' + group;

		if ( !listNames ) {
			return mw.message( base + '-count', editors.length ).text();
		}

		const names = [];
		let anonymous = 0;
		editors.forEach( ( editor ) => {
			if ( editor.anonymous || !editor.username ) {
				anonymous++;
			} else {
				names.push( editor.username );
			}
		} );
		if ( anonymous > 0 ) {
			names.push( mw.message( 'editingpresence-anonymous-count', anonymous ).text() );
		}

		return mw.message( base + '-named', mw.language.listToText( names ), editors.length ).text();
	}
}

module.exports = PresenceBanner;
