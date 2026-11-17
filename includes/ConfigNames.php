<?php

declare( strict_types=1 );

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
namespace MediaWiki\Extension\EditingPresence;

/**
 * A class containing constants representing the names of configuration variables,
 * to protect against typos.
 */
final class ConfigNames {

	public const string ActiveHeartbeatInterval = 'EditingPresenceActiveHeartbeatInterval';

	public const string CacheId = 'EditingPresenceCacheId';

	public const string HeartbeatInterval = 'EditingPresenceHeartbeatInterval';

	public const string IncludeOwnOtherSessions = 'EditingPresenceIncludeOwnOtherSessions';

	public const string MaxListedNames = 'EditingPresenceMaxListedNames';

	public const string NchanProtocol = 'EditingPresenceNchanProtocol';

	public const string NchanPublishUrl = 'EditingPresenceNchanPublishUrl';

	public const string NchanSecret = 'EditingPresenceNchanSecret';

	public const string NchanSubscribeUrl = 'EditingPresenceNchanSubscribeUrl';

	public const string PushBackend = 'EditingPresencePushBackend';

	public const string SectionIncludeSameSectionEditors = 'EditingPresenceSectionIncludeSameSectionEditors';

	public const string SectionIncludeWholePageEditors = 'EditingPresenceSectionIncludeWholePageEditors';

	public const string ShowUsernames = 'EditingPresenceShowUsernames';

	public const string Timeout = 'EditingPresenceTimeout';
}
