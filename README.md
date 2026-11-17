# EditingPresence

Shows a notice above the editor, in both the wikitext editor and VisualEditor,
while other users have the same page open for editing.

Requires MediaWiki 1.47 or later and PHP 8.4 or later.

## Install

```php
wfLoadExtension( 'EditingPresence' );
```

## Configuration

| Setting | Default | Meaning |
|---|---|---|
| `$wgEditingPresenceShowUsernames` | `true` | Show who is editing. When `false` only a count is shown and names are never sent to the browser. Logged out editors are never named. |
| `$wgEditingPresenceIncludeOwnOtherSessions` | `false` | Count your own account in another tab or device as another editor. Handy for testing with one account. |
| `$wgEditingPresenceMaxListedNames` | `5` | Names are listed only while fewer than this many editors are in a group. At or above it only the count is shown. |
| `$wgEditingPresenceSectionIncludeWholePageEditors` | `true` | While editing one section, include users editing the whole page. |
| `$wgEditingPresenceSectionIncludeSameSectionEditors` | `true` | While editing one section, include users editing that same section. |
| `$wgEditingPresenceHeartbeatInterval` | `20` | Seconds between updates from an open editor (minimum 5). |
| `$wgEditingPresenceActiveHeartbeatInterval` | `3` | Seconds between updates while polling and someone else is editing (between 2 and the heartbeat interval). |
| `$wgEditingPresencePushBackend` | `''` | Push backend name, for example `nchan`. Empty means polling only. |
| `$wgEditingPresenceNchanSubscribeUrl` | `''` | Where browsers subscribe, without the channel id. |
| `$wgEditingPresenceNchanPublishUrl` | `''` | Where PHP publishes, without the channel id. Keep it private. |
| `$wgEditingPresenceNchanSecret` | `''` | Long random string used to derive channel ids. Push stays off while empty. |
| `$wgEditingPresenceNchanProtocol` | `'websocket'` | `websocket` or `sse`. |
| `$wgEditingPresenceTimeout` | `60` | Seconds without an update before an editor is dropped (at least twice the interval). |
| `$wgEditingPresenceCacheId` | `''` | Key from `$wgObjectCaches` used for storage. Empty uses `$wgMainStash`. |

While editing the whole page, everyone editing the page is shown, whether they
have the whole page or a section open.

## Updates

By default editors poll. An editor alone on a page sends an update every
`EditingPresenceHeartbeatInterval` seconds, which is also the longest a new
arrival can go unnoticed by someone already there. Once someone else is present
the interval drops to `EditingPresenceActiveHeartbeatInterval`.

### Instant updates with Nchan

With the Nchan module for nginx, other editors are woken the moment someone
joins, leaves or switches section. PHP publishes a short message to a channel
for the page and nginx pushes it to the browsers over a WebSocket (or server
sent events). The message carries no names. Browsers react by asking the API
for the current list, so hiding names and section filtering still happen in PHP.

nginx:

```nginx
location ~ ^/editingpresence/sub/(\w+)$ {
	nchan_subscriber;
	nchan_channel_id $1;
}

location ~ ^/editingpresence/pub/(\w+)$ {
	allow 127.0.0.1;
	deny all;
	nchan_publisher;
	nchan_channel_id $1;
}
```

LocalSettings.php:

```php
$wgEditingPresencePushBackend = 'nchan';
$wgEditingPresenceNchanSubscribeUrl = '/editingpresence/sub';
$wgEditingPresenceNchanPublishUrl = 'http://127.0.0.1/editingpresence/pub';
$wgEditingPresenceNchanSecret = 'a long random string';
```

If the connection drops or Nchan is not reachable, editors keep working by
polling and reconnect in the background.

### Other push backends

A backend implements `MediaWiki\Extension\EditingPresence\Push\PushBackend`
and is registered with an extension attribute, then chosen with
`$wgEditingPresencePushBackend`:

```json
"attributes": {
	"EditingPresence": {
		"PushBackends": {
			"mybackend": { "class": "My\\Backend", "services": [] }
		}
	}
}
```

`getClientConfig()` returns an array with a `type` (and optionally a
ResourceLoader `module` to load first). In that module call
`require( 'ext.editingPresence' ).registerTransport( type, factory )` where the
factory returns an object with `start( onSignal, onState )` and `stop()`.

Presence data lives in the object cache only, so there are no database tables
to create. On a busy wiki point `EditingPresenceCacheId` at Redis or Memcached.

## Development

```
composer install && composer test
composer phan
npm install && npm test
```

PHPUnit tests live in `tests/phpunit` and run through MediaWiki's own test runner
inside an installed wiki. CI uses the reusable workflow in `.github/workflows`.
