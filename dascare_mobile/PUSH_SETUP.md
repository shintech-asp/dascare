# Turning on push notifications (Firebase)

The code is already in place (app + API). Push stays **off** until these two files exist, and turns on by
itself once they do. About 10 minutes, free (Firebase Spark plan).

| File | Where it goes | Secret? |
|---|---|---|
| `google-services.json` | `dascare_mobile/android/app/google-services.json` | Not really, but keep it out of git (already ignored) |
| Service account key (`.json`) | `dascare_api/config/firebase-service-account.json` | **Yes — never share or commit** (already ignored) |

## 1. Create the Firebase project

1. Go to <https://console.firebase.google.com> and sign in with your Google account.
2. **Create a project** → name it `DASCARE` → Google Analytics can be turned **off** → Create.

## 2. Register the Android app → `google-services.json`

1. On the project home, click the **Android** icon ("Add app").
2. **Android package name:** `ph.dascare.app` (must match exactly). App nickname: `DASCARE`. Leave SHA-1 empty.
3. **Register app** → **Download google-services.json**.
4. Put it at `dascare_mobile/android/app/google-services.json`.
5. Skip the "Add Firebase SDK" steps — Capacitor already does that. Click through to the console.

## 3. Check Cloud Messaging is on

Project settings (gear icon) → **Cloud Messaging** tab → **Firebase Cloud Messaging API (V1)** should say
**Enabled**. If it says disabled, click the ⋮ menu → *Manage API in Google Cloud Console* → **Enable**.

## 4. Service account key → lets the API send pushes

1. Project settings → **Service accounts** tab → **Generate new private key** → **Generate key**.
2. A `.json` file downloads. Rename it to `firebase-service-account.json` and move it to
   `dascare_api/config/firebase-service-account.json`.
3. Don't email, upload or commit this file — anyone with it can send notifications as your project.

## 5. Rebuild and install

```bash
cd dascare_mobile
npm run apk              # real phone on this Wi-Fi  (or: npm run apk -- emulator)
```

Push is switched on automatically when `google-services.json` is present. Install the new APK.

> Emulator: the virtual device needs **Google Play** services (the AVD's system image says "Google Play").

## 6. Test it

1. Open the app and **log in** (or send a guest SOS) → Android asks **Allow DASCARE to send notifications?** → Allow.
2. In the web (`dascare_api` DB), a row appears in `push_devices` for the phone.
3. **Close the app** (swipe it away).
4. On the web as the organization: accept the incident, assign a unit, set **Responding**.
5. The phone shows "Ambulance En Route" with the DASCARE icon. Tapping it opens that request's tracking screen.

Also tested by the same hooks: ID verification approved/rejected, account suspended/reactivated, patient
handoff completed, and reports linked by duplicate detection (they get the incident's updates too).

## Troubleshooting

- **Nothing arrives:** check `/Applications/XAMPP/xamppfiles/logs/php_error_log` for lines starting with
  `Push:` or `FCM send failed`. `invalid_grant` = wrong/expired service account key → generate a new one.
- **No `push_devices` row:** the app was built before `google-services.json` was added → run `npm run apk` again;
  or notifications were not allowed → Android Settings › Apps › DASCARE › Notifications.
- **Turn push off again:** delete `dascare_api/config/firebase-service-account.json`.
