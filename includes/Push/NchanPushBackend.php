<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence\Push;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use function hash_hmac;
use function rtrim;

/**
 * Pushes through the Nchan module of nginx. Browsers subscribe to a channel
 * per page and PHP publishes a short message to it whenever the editors change.
 * The channel name is an HMAC of the page, so only users who were told it by
 * the API can subscribe.
 */
final class NchanPushBackend implements PushBackend {

	private const int PUBLISH_TIMEOUT = 2;

	public function __construct(
		private readonly PushSettings $settings,
		private readonly HttpRequestFactory $http,
		private readonly LoggerInterface $logger,
	) {
	}

	public static function factory( PushSettings $settings, HttpRequestFactory $http ): self {
		return new self( $settings, $http, LoggerFactory::getInstance( 'EditingPresence' ) );
	}

	/** @inheritDoc */
	public function getClientConfig( string $pageKey ): ?array {
		if ( !$this->isConfigured() ) {
			return null;
		}

		return [
			'type' => 'nchan',
			'protocol' => $this->settings->nchanProtocol,
			'url' => $this->channelUrl( $this->settings->nchanSubscribeUrl, $pageKey ),
		];
	}

	/** @inheritDoc */
	public function publishChange( string $pageKey ): void {
		if ( !$this->isConfigured() ) {
			return;
		}

		DeferredUpdates::addCallableUpdate(
			fn () => $this->publishNow( $pageKey ),
			DeferredUpdates::POSTSEND,
			[]
		);
	}

	public function publishNow( string $pageKey ): void {
		$url = $this->channelUrl( $this->settings->nchanPublishUrl, $pageKey );

		$response = $this->http->post( $url, [
			'postData' => 'changed',
			'timeout' => self::PUBLISH_TIMEOUT,
			'connectTimeout' => self::PUBLISH_TIMEOUT,
		], __METHOD__ );

		if ( $response === null ) {
			$this->logger->warning( 'Could not publish to Nchan at {url}', [ 'url' => $url ] );
		}
	}

	public function getChannelId( string $pageKey ): string {
		return hash_hmac( 'sha256', $pageKey, $this->settings->nchanSecret );
	}

	private function channelUrl( string $base, string $pageKey ): string {
		return rtrim( $base, '/' ) . '/' . $this->getChannelId( $pageKey );
	}

	private function isConfigured(): bool {
		return $this->settings->nchanSecret !== ''
			&& $this->settings->nchanSubscribeUrl !== ''
			&& $this->settings->nchanPublishUrl !== '';
	}
}
