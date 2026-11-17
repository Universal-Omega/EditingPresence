<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\EditingPresence;

/**
 * One open editor. A user id of 0 means the editor is not logged in, and then
 * the name is always empty so no IP address is ever stored.
 */
final class PresenceEntry {

	public bool $isWholePage {
		get => $this->section === '';
	}

	public bool $isRegistered {
		get => $this->userId !== 0;
	}

	public function __construct(
		public readonly string $clientId,
		public readonly int $userId,
		public readonly string $userName,
		public readonly string $section,
		public readonly int $touched,
	) {
	}

	/**
	 * @return array{clientId: string, userId: int, userName: string, section: string, touched: int}
	 */
	public function toArray(): array {
		return [
			'clientId' => $this->clientId,
			'userId' => $this->userId,
			'userName' => $this->userName,
			'section' => $this->section,
			'touched' => $this->touched,
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

		if (
			!is_string( $clientId ) || !is_int( $userId ) || !is_string( $userName ) ||
			!is_string( $section ) || !is_int( $touched )
		) {
			return null;
		}

		return new self(
			clientId: $clientId,
			userId: $userId,
			userName: $userName,
			section: $section,
			touched: $touched,
		);
	}
}
