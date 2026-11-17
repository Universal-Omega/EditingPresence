'use strict';

const NchanTransport = require( './NchanTransport.js' );

const factories = Object.create( null );

/**
 * Registers a way of receiving pushes from the server. Other extensions can
 * call this from their own module, then name that module in the "module" key
 * of their push backend's client config.
 *
 * @param {string} type Matches the "type" key of the push backend's client config.
 * @param {Function} factory Receives the client config and returns an object with
 *  start( onSignal, onState ) and stop(). onSignal means the editors may have changed.
 *  onState( connected ) reports whether the connection is working.
 */
function registerTransport( type, factory ) {
	factories[ type ] = factory;
}

/**
 * @param {Object} push The push config sent by the API.
 * @return {jQuery.Promise} Resolves with a transport, or null when none fits.
 */
function createTransport( push ) {
	const loaded = push.module ?
		mw.loader.using( push.module ) :
		$.Deferred().resolve().promise();

	return loaded.then(
		() => ( factories[ push.type ] ? factories[ push.type ]( push ) : null ),
		() => null
	);
}

registerTransport( 'nchan', ( push ) => new NchanTransport( push ) );

module.exports = {
	registerTransport: registerTransport,
	createTransport: createTransport
};
