'use strict';

/**
 * The notice shown above the editor. It stays hidden until there is at
 * least one other editor to report.
 */
class PresenceBanner {
	constructor() {
		this.$content = $( '<div>' ).addClass( 'cdx-message__content' );
		this.$element = $( '<div>' )
			.addClass( 'cdx-message cdx-message--block cdx-message--warning ext-editingpresence-banner' )
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
		[ 'page', 'section' ].forEach( ( group ) => {
			if ( groups[ group ].length ) {
				const listNames = !!response.showusernames &&
					groups[ group ].length < Math.max( 1, response.maxnames || 1 );
				const line = this.buildLine(
					groups[ group ], group, viewerEditsSection, listNames
				);
				this.$content.append( $( '<p>' ).text( line ) );
			}
		} );

		this.$element.toggle( this.$content.children().length > 0 );
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
