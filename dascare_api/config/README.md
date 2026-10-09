# dascare_api/config

Local secrets that must **never** be committed (this folder's files are gitignored).

- `firebase-service-account.json` — Firebase service account key used by `reusables/push.php` to send push
  notifications to the Android app. Without it, push is simply off. See `dascare_mobile/PUSH_SETUP.md`.
- `ably.json` — Ably API key for live updates (`reusables/realtime.php`), e.g.
  `{"key": "<appId>.<keyId>:<secret>"}`; optional `"channel_prefix"` (default `dascare`) to keep a second
  server's events apart when it shares the same Ably app. Without it, live updates are off and screens keep
  refreshing every 15 s. The secret never leaves the server: browsers/the app get short-lived, listen-only
  tokens from `realtime/auth.php`.
