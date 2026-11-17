<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\EditingPresence\PresenceEntry;
use MediaWiki\Extension\EditingPresence\PresenceManager;
use MediaWiki\Extension\EditingPresence\PresenceSettings;
use MediaWiki\Extension\EditingPresence\Push\PushBackend;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use function preg_match;
use function sha1;
use function wfEscapeWikiText;
use const NS_MAIN;

/**
 * Internal module used by the editors to report that they are open and to
 * learn who else is editing the same page.
 */
final class ApiEditingPresence extends ApiBase {

	private const string CLIENT_ID_PATTERN = '/^[A-Za-z0-9]{8,64}$/D';
	private const string SECTION_PATTERN = '/^(?:|\d+|T-\d+|new)$/D';

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly PresenceManager $manager,
		private readonly PresenceSettings $settings,
		private readonly PushBackend $push,
		private readonly TitleFactory $titleFactory,
	) {
		parent::__construct( $mainModule, $moduleName, modulePrefix: '' );
	}

	/** @inheritDoc */
	public function execute(): void {
		$params = $this->extractRequestParams( options: [] );

		$clientId = $params['clientid'];
		if ( preg_match( self::CLIENT_ID_PATTERN, $clientId ) !== 1 ) {
			$this->dieBadParameter( 'clientid' );
		}

		$title = $this->titleFactory->newFromText( $params['title'], NS_MAIN );
		if ( $title === null || !$title->canExist() ) {
			$this->dieWithError(
				[ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ],
				code: null,
				data: null,
				httpCode: 0
			);
		}
		$pageKey = sha1( $title->getPrefixedDBkey() );

		if ( $params['task'] === 'leave' ) {
			$this->manager->leave( $pageKey, $clientId );
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'left' => true ], flags: 0 );
			return;
		}

		$section = $params['section'];
		if ( preg_match( self::SECTION_PATTERN, $section ) !== 1 ) {
			$this->dieBadParameter( 'section' );
		}

		$this->checkTitleUserPermissions( $title, 'edit', options: [] );

		$user = $this->getUser();
		$now = ConvertibleTimestamp::time();
		$typing = $this->settings->typingIndicator && $this->settings->richPresence &&
			$this->settings->showUsernames && $params['typing'] && $user->isRegistered();
		$viewer = new PresenceEntry(
			clientId: $clientId,
			userId: $user->isRegistered() ? $user->getId() : 0,
			userName: $user->isRegistered() ? $user->getName() : '',
			section: $section,
			touched: $now,
			typingUntil: $typing ? $now + PresenceEntry::TYPING_TTL : 0,
		);

		$entries = $params['task'] === 'refresh'
			? $this->manager->peek( $pageKey )
			: $this->manager->heartbeat( $pageKey, $viewer );
		$editors = $this->manager->visibleEditors( $viewer, $entries );
		ApiResult::setIndexedTagName( $editors, 'editor' );

		$value = [
			'interval' => $this->settings->heartbeatInterval,
			'activeinterval' => $this->settings->activeHeartbeatInterval,
			'showusernames' => $this->settings->showUsernames,
			'maxnames' => $this->settings->maxListedNames,
			'typingenabled' => $this->settings->typingIndicator && $this->settings->richPresence &&
				$this->settings->showUsernames,
			'editors' => $editors,
		];

		$push = $this->push->getClientConfig( $pageKey );
		if ( $push !== null ) {
			$value['push'] = $push;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $value, flags: 0 );
	}

	private function dieBadParameter( string $paramName ): never {
		$this->dieWithError(
			[ 'apierror-badparameter', $this->encodeParamName( $paramName ) ],
			code: null,
			data: null,
			httpCode: 0
		);
	}

	/** @inheritDoc */
	public function isInternal(): bool {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function mustBePosted(): bool {
		return true;
	}

	/** @inheritDoc */
	public function needsToken(): string {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'task' => [
				ParamValidator::PARAM_TYPE => [ 'heartbeat', 'refresh', 'leave' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'section' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'typing' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'clientid' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=editingpresence&task=heartbeat&title=Main_Page&section=2&clientid=0123456789abcdef&token=123ABC'
				=> 'apihelp-editingpresence-example-heartbeat',
			'action=editingpresence&task=leave&title=Main_Page&clientid=0123456789abcdef&token=123ABC'
				=> 'apihelp-editingpresence-example-leave',
		];
	}
}
