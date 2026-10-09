# DASCARE mobile (Android)

The DASCARE app for **requesters**: citizens (with login) and guests (SOS without an account).
Staff and admins use the web portal (`../dascare`).

**Stack:** Vue 3 + Vite + Tailwind v4 + daisyUI 5, packaged for Android with **Capacitor 8**.
It talks to the same PHP API as the web (`../dascare_api`, served by XAMPP).

## Install on a phone (APK)

```bash
cd dascare_mobile
npm run apk                 # phone on the same Wi-Fi as this Mac (uses the Mac's current Wi-Fi IP)
npm run apk -- emulator     # Android emulator
npm run apk -- 192.168.1.5  # a specific address
npm run apk:web             # Wi-Fi build + publish it to the web landing page's "Download for Android" button
```

The APK lands in `release/DASCARE-v<version>-<target>.apk` (debug-signed; Android asks to allow installs from
this source the first time). XAMPP must be running, and the phone on the same Wi-Fi — campus networks that
isolate devices won't work; a phone hotspot will. Rebuild after changing networks. Demo/defense steps:
**[TEST_CHECKLIST.md](TEST_CHECKLIST.md)**.

## First-time setup

```bash
cd dascare_mobile
npm install
cp .env.example .env      # then pick the emulator or real-phone API address
npm run sync              # build the Vue app + copy it into android/
npm run open              # opens android/ in Android Studio
```

In Android Studio: wait for Gradle sync, pick the emulator (or your phone) and press **Run ▶**.
You can also open the `dascare_mobile/android` folder directly from Android Studio.

> **Blank tracking map in the emulator?** The live map uses WebGL (MapLibre). Some emulator graphics modes
> don't display WebGL: Device Manager → ⋮ Edit → Show Advanced Settings → **Graphics: Software**, or start
> the emulator with `-gpu swiftshader_indirect`. Real phones are unaffected.

## Every time you change the app code

```bash
npm run sync              # rebuild + copy into android/
```
then press **Run ▶** in Android Studio again.

For quick UI work you can preview in a browser instead: `npm run dev` → http://localhost:5180
(no GPS/camera there; those need the emulator or a phone).

## API address (`.env`)

| Testing on | `VITE_API_BASE_URL` |
|---|---|
| Android emulator | `http://10.0.2.2/Dascare/dascare_api` (10.0.2.2 = your Mac) |
| Real phone, same Wi-Fi | `http://<your Mac's Wi-Fi IP>/Dascare/dascare_api` — find it with `ipconfig getifaddr en0` |

XAMPP Apache + MySQL must be running. Run `npm run sync` after changing `.env`.

## App icon and splash

Generated from `assets/logo.png` (the web's `dascare/img/logoo.png`):

```bash
npm run assets            # python3 scripts/generate_icons.py (needs Pillow)
```

## Design consistency with the web

- Theme colours, radii and dark mode are copied verbatim from `../dascare/src/assets/main.css`
  into `src/assets/main.css`. If you change one, change the other.
- daisyUI's built-in themes are disabled here (`themes: false`) — otherwise its stock light
  theme overrides DASCARE's cream colours inside the Android WebView.
- Icons: the same `<Icon icon="lucide:…">` as the web, but bundled (works offline).
- Font: Inter, bundled.

## What's in the app so far

- **Phase 0** – project, theme, icons, splash, API check (Profile → Server connection)
- **Phase 2** – Welcome (guest SOS + log in / sign up), Login, 2FA, Register + email code, Forgot / reset
  password, bottom tab bar (Home · Requests · **SOS** · Alerts · Profile), identity verification (KYC)
  with camera/gallery ID photo and map pin.
- **Phase 3** – Emergency SOS (guest or logged in): automatic GPS, one-tap send, optional details
  (patients, description, address, barangay, landmark, up to 3 photos), manual map pin, "already reported"
  notice with Request separately, offline error with Call 911.
- **Phase 4** – Home with the live active request, My Requests (filters, linked labels), live tracking
  (dispatch steps, responding unit, ambulance on the map, status history; refreshes every 15 s and on app
  resume, pull to refresh), Notifications with an unread badge, guests track their SOS from Welcome.
- **Phase 5** – Emergency information (medical records, unlocked by a verified ID; shared with the crew on
  every SOS), Account & security (name, mobile number, password, two-factor).
- **Phase 6** – No-internet banner with Call 911, plain permission-denied guidance (location asked once, never
  re-prompted), loading placeholders, Android Back closes popups first, v1.0.0, `npm run apk`, test checklist.
- **Phase 7** – Push notifications (Firebase): "Ambulance en route" etc. with the app closed, tap opens the
  request. Code is complete; turn it on with **[PUSH_SETUP.md](PUSH_SETUP.md)** (two Firebase files).

## Notes

- First Android build downloads **Java 21** (needed by the camera plugin) into `~/.gradle/jdks` — that's
  the foojay plugin in `android/settings.gradle`. One-time, ~200 MB.
- Icons: `npm run dev` / `npm run build` first run `scripts/build-icon-subset.mjs`, which bundles only the
  `line-md:` / `mdi:` icons used in `src/` (all of Lucide is bundled). Use literal icon names.

- `android/gradle/wrapper/gradle-wrapper.properties` uses **Gradle 9.1** (the Capacitor template ships 8.14,
  which can't run on the Java 25 bundled with Android Studio 2026.1).
- `capacitor.config.json` uses `androidScheme: http` + `cleartext: true` so the app can call the XAMPP API
  over plain http during development. Switch both off when the API is served over https.
