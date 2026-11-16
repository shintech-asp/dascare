# dascare_api/config

Local secrets that must **never** be committed (this folder's files are gitignored).

- `firebase-service-account.json` — Firebase service account key used by `reusables/push.php` to send push
  notifications to the Android app. Without it, push is simply off. See `dascare_mobile/PUSH_SETUP.md`.
- `ably.json` — Ably API key for live updates (`reusables/realtime.php`), e.g.
  `{"key": "<appId>.<keyId>:<secret>"}`; optional `"channel_prefix"` (default `dascare`) to keep a second
  server's events apart when it shares the same Ably app. Without it, live updates are off and screens keep
  refreshing every 15 s. The secret never leaves the server: browsers/the app get short-lived, listen-only
  tokens from `realtime/auth.php`.
- `tomtom.json` — TomTom API key for road routes + traffic-aware ETAs on the live maps (`reusables/routing.php`),
  e.g. `{"key": "<your key>"}` (free at developer.tomtom.com, Routing API). Without it, the maps show a
  straight-line estimate labelled as such. Calls are capped at 2,000/day (`routing_api_usage` table).

## paymongo.json (donations)

```json
{ "secret_key": "sk_test_...", "webhook_secret": "whsk_..." }
```

Use `sk_test_…` while testing, `sk_live_…` in production. In the PayMongo dashboard add a webhook for the
event `checkout_session.payment.paid` pointing to `https://<site>/api/support/webhook.php` and paste its
signing secret as `webhook_secret`. Without this file donations are simply switched off on the Support page.
