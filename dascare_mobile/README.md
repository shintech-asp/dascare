# DASCARE mobile (Android)

The DASCARE app for **requesters**: citizens (with login) and guests (SOS without an account).
Staff and admins use the web portal (`../dascare`).

**Stack:** Vue 3 + Vite + Tailwind v4 + daisyUI 5, packaged for Android with **Capacitor 8**.
It talks to the same PHP API as the web (`../dascare_api`, served by XAMPP).

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

## Notes

- `android/gradle/wrapper/gradle-wrapper.properties` uses **Gradle 9.1** (the Capacitor template ships 8.14,
  which can't run on the Java 25 bundled with Android Studio 2026.1).
- `capacitor.config.json` uses `androidScheme: http` + `cleartext: true` so the app can call the XAMPP API
  over plain http during development. Switch both off when the API is served over https.
