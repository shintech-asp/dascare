# DASCARE Mobile — Test Checklist (v1.0.0)

Use this to demo and verify the Android app end to end. Each item lists what to do and what should happen.
Tick **Pass** / **Fail** and note anything unexpected.

## 0. Before the demo

| | Step | Expected |
|---|---|---|
| ☐ | Start **Apache** and **MySQL** in XAMPP. | Both green. |
| ☐ | Phone and Mac on the **same Wi-Fi**. (Campus Wi-Fi that isolates devices won't work — use a phone hotspot or a home router.) | — |
| ☐ | In `dascare_mobile/`, run `npm run apk`. | Prints `APK ready: release/DASCARE-v1.0.0-<ip>.apk`. |
| ☐ | Copy that APK to the phone and install it (allow "Install unknown apps" when asked). | DASCARE icon appears. |
| ☐ | *Or:* run `npm run apk:web`, open the website's landing page → **Mobile App** section → **Download for Android** (Chrome warns for any non-Play-Store app → *Download anyway*). | Installs the same app. |
| ☐ | Open the app → **Server connection** (bottom of Welcome). | "Connected to DASCARE API". |
| ☐ | Have ready: a **citizen** test account, a **staff** account (e.g. dispatcher), and the web open as **platform admin** and **organization** users. | — |

> Emulator instead of a phone: `npm run apk -- emulator`, or press Run ▶ in Android Studio.
> If the Mac's Wi-Fi IP changes (new network), run `npm run apk` again.

## 1. First launch & look

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 1.1 | Launch the app. | DASCARE splash, then Welcome with logo, "Emergency SOS", Log in, Create an account. | ☐ / ☐ |
| 1.2 | Tap the sun/moon icon. | Switches light ↔ dark; colours match the web (cream / navy). Status bar follows. | ☐ / ☐ |
| 1.3 | Press Android **Back** twice on Welcome. | First press: "Press back again to exit". Second: app closes. | ☐ / ☐ |

## 2. Accounts

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 2.1 | Create an account with valid details, accept Terms. | "Confirm your details" → code screen; 6-digit code arrives by email. | ☐ / ☐ |
| 2.2 | Tap **Terms of Service** on the sign-up form, then go back. | Terms open; returning keeps everything typed. | ☐ / ☐ |
| 2.3 | Enter the emailed code. | "Email verified" → Log in screen with the email filled in. | ☐ / ☐ |
| 2.4 | Log in as the citizen. | Home: "Good morning/afternoon, <name>". | ☐ / ☐ |
| 2.5 | Close the app completely and reopen. | Still logged in. | ☐ / ☐ |
| 2.6 | Log in with the **staff** account. | Refused: "This app is for people requesting help… use the web portal". | ☐ / ☐ |
| 2.7 | Wrong password. | "Invalid email or password". | ☐ / ☐ |
| 2.8 | Forgot password → enter email → code → new password. | Code arrives; new password works at login. | ☐ / ☐ |
| 2.9 | (Account with 2FA on) Log in. | Code screen; correct code logs in. | ☐ / ☐ |

## 3. Identity verification (KYC)

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 3.1 | New citizen → Home. | "Verify your identity" card. Requests tab says history is locked. | ☐ / ☐ |
| 3.2 | Verify now → birthdate → ID type list matches the age (minor / adult / senior). | Only allowed IDs listed. | ☐ / ☐ |
| 3.3 | **Take photo** of an ID (camera) or pick from **gallery**. | Preview shows; can remove and retake. | ☐ / ☐ |
| 3.4 | Address, barangay, zip; tap **Use my current location** or tap the map. | Pin placed; barangay/zip auto-filled when possible. | ☐ / ☐ |
| 3.5 | Submit for Verification. | "Verification under review" (24–48 hours). | ☐ / ☐ |
| 3.6 | On the web (platform admin) → Citizen Verifications → approve. | — | ☐ / ☐ |
| 3.7 | Back in the app, pull down on Home (or reopen the app). | Card disappears; Requests and Emergency information unlock. | ☐ / ☐ |

## 4. Emergency SOS

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 4.1 | Log out → Welcome → **Emergency SOS** (guest). | Location prompt; then "GPS location locked". | ☐ / ☐ |
| 4.2 | Tap **Request Rescue Now** without filling anything. | Vibrates; "Request sent", reference DAS-…; Track button. | ☐ / ☐ |
| 4.3 | On the web (platform) → City-wide Incidents. | The request is there with a DSS offer to an organization. | ☐ / ☐ |
| 4.4 | Logged-in citizen: SOS → **Add details** → patients, description, 1–3 photos → send. | Sent; on the web the incident shows the details, photos and the citizen's medical info. | ☐ / ☐ |
| 4.5 | Within 5 minutes, send another SOS from **the same spot** (another phone/account). | "Linked to DAS-… — a unit is already being handled"; no second ambulance offer on the web. | ☐ / ☐ |
| 4.6 | Tap **Not the same emergency? Request separately**. | "Done — dispatch is finding a separate unit"; on the web it becomes its own incident. | ☐ / ☐ |
| 4.7 | Deny location when asked. | Clear message on how to turn location on; **Pin location manually** works. | ☐ / ☐ |
| 4.8 | Turn on airplane mode → tap Request Rescue Now. | Red "No internet" banner with **Call 911**; "Couldn't send your request" with Try again / Call 911. | ☐ / ☐ |

## 5. Tracking

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 5.1 | Open the request (Track this request / Home card). | Status, dispatch steps, map with the incident pin, history. | ☐ / ☐ |
| 5.2 | On the web, the organization accepts and assigns a unit, then sets **Responding**. | Within ~15 s the app shows Responding, the unit card, and the ambulance on the map. | ☐ / ☐ |
| 5.3 | Crew GPS pings (organization tracking). | Ambulance dot moves; "GPS live". | ☐ / ☐ |
| 5.4 | Move the mission to **On scene → Transporting → Completed**. | Steps fill in; history grows; "Completed" at the end. | ☐ / ☐ |
| 5.5 | Guest: Welcome → **Your emergency requests** → open one. | Guest can track without an account. | ☐ / ☐ |
| 5.6 | Pull down on the tracking screen. | Refreshes. | ☐ / ☐ |

## 6. Home, Requests, Alerts, Profile

| | Step | Expected | Pass/Fail |
|---|---|---|---|
| 6.1 | Home during an active request. | "Active request" card with progress and **Track live**. | ☐ / ☐ |
| 6.2 | Requests tab (verified). | Totals, All / Active / Completed filters, tap opens tracking. | ☐ / ☐ |
| 6.3 | Alerts tab after a status update. | Red badge with the unread count; tapping a notification opens the request. | ☐ / ☐ |
| 6.4 | Profile → **Emergency information** → fill blood type, allergies, contact → Save. | "Saved"; next SOS carries this info to the crew (check on the web). | ☐ / ☐ |
| 6.5 | Profile → **Account & security** → change name / mobile → Save. | Saved; Profile shows the new name. | ☐ / ☐ |
| 6.6 | Turn **two-factor** on (password confirm). | Enabled; next login asks for an emailed code. | ☐ / ☐ |
| 6.7 | Appearance: Light / Dark / System. | Theme changes and is remembered. | ☐ / ☐ |
| 6.8 | Log out. | Back to Welcome; SOS still available. | ☐ / ☐ |

## 7. Notes for the panel

- **Platform:** Android app built with Vue 3 + Capacitor 8, sharing the web platform's design system and PHP API.
- **Who uses it:** requesters only — citizens and guests. Staff and admins are refused and use the web portal.
- **Login:** token-based (hashed, revocable bearer tokens, citizens only); the web keeps cookie sessions.
- **Updates:** the app refreshes every ~15 s while open and when reopened (same as the web dashboards). Push notifications with the app closed are not included in v1.0.0.
- **Known limitations:** the API runs on a local XAMPP server, so the phone must be on the same network; email features depend on the SMTP account being reachable.
