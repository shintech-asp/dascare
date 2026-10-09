# dascare_api/config

Local secrets that must **never** be committed (this folder's files are gitignored).

- `firebase-service-account.json` — Firebase service account key used by `reusables/push.php` to send push
  notifications to the Android app. Without it, push is simply off. See `dascare_mobile/PUSH_SETUP.md`.
