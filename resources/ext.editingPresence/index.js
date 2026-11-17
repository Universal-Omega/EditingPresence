'use strict';

const config = require( './config.json' );
const PresenceTracker = require( './PresenceTracker.js' );
const PresenceBanner = require( './PresenceBanner.js' );
const transports = require( './transports.js' );

module.exports = {
	PresenceTracker: PresenceTracker,
	PresenceBanner: PresenceBanner,
	registerTransport: transports.registerTransport,
	heartbeatInterval: config.heartbeatInterval
};
