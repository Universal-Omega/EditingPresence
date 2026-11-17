<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence;

use function is_int;
use function is_string;

/**
 * One open editor. A user id of 0 means the editor is not logged in, and then
 * the name is always empty so no IP address is ever stored.
 */
final class PresenceEntry {

	/** How long after the last report an editor still counts as typing. */
	public const int TYPING_TTL = 10;

	// Doesn't understand property hooks.
	// phpcs:disable PSR2.Classes.PropertyDeclaration,Squiz.Scope.MemberVarScope
	public bool $isWholePage {
		get => $this->section === '';
	}

	public bool $isRegistered {
		get => $this->userId !== 0;
	}
	// phpcs:enable PSR2.Classes.PropertyDeclaration,Squiz.Scope.MemberVarScope

	public function __construct(
		public readonly string $clientId,
		public readonly int $userId,
		public readonly string $userName,
		public readonly string $section,
		public readonly int $touched,
		public readonly int $typingUntil,
	) {
	}

	public function isTypingAt( int $now ): bool {
		return $this->typingUntil > $now;
	}

	/**
	 * @return array{clientId: string, userId: int, userName: string, section: string, touched: int, typingUntil: int}
	 */
	public function toArray(): array {
		return [
			'clientId' => $this->clientId,
			'userId' => $this->userId,
			'userName' => $this->userName,
			'section' => $this->section,
			'touched' => $this->touched,
			'typingUntil' => $this->typingUntil,
		];
	}

	/**
	 * @param array<mixed> $data
	 */
	public static function fromArray( array $data ): ?self {
		$clientId = $data['clientId'] ?? null;
		$userId = $data['userId'] ?? null;
		$userName = $data['userName'] ?? null;
		$section = $data['section'] ?? null;
		$touched = $data['touched'] ?? null;
		$typingUntil = $data['typingUntil'] ?? 0;

		if (
			!is_string( $clientId ) || !is_int( $userId ) || !is_string( $userName ) ||
			!is_string( $section ) || !is_int( $touched ) || !is_int( $typingUntil )
		) {
			return null;
		}

		return new self(
			clientId: $clientId,
			userId: $userId,
			userName: $userName,
			section: $section,
			touched: $touched,
			typingUntil: $typingUntil,
		);
	}
}
