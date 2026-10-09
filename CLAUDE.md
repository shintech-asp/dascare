# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DASCARE — "Development of a Web-Based Ambulance Rescue Platform in the City of Dasmariñas with a Decision Support System and Mobile Application" (capstone/thesis). The repo contains the web frontend, the PHP API, a phpMyAdmin dump, and an Android app for requesters (`dascare_mobile/`, in progress). The web uses cookie sessions; the Android app uses bearer tokens (see "Mobile app auth" below). No push notifications.

- `dascare/` — Vue 3 + Vite + Tailwind v4 + daisyUI SPA
- `dascare_api/` — plain PHP 8 + PDO (no framework, no router; one file per endpoint). Only Composer dependency is `symfony/mailer` (`vendor/` is checked in; no `composer` binary is needed to run). `symfony/mailer ^7.4` requires **PHP ≥ 8.2**, but XAMPP here runs **PHP 8.0.28**; `vendor/composer/platform_check.php` was patched (`>= 80200` → `>= 80000`) so endpoints that load `email_helper.php` (e.g. `auth/login.php`) don't 500. Re-running Composer regenerates and reverts that patch. Email features (OTP, password reset) may still fail on 8.0 since Mailer itself targets 8.2+. Proper fix is to serve PHP ≥ 8.2.
- `dascare.sql` — full schema + seed data (MariaDB 10.4, phpMyAdmin export)
- `dascare_mobile/` — Android app for citizens + guest SOS: Vue 3 + Vite + Tailwind v4 + daisyUI + **Capacitor 8**. Separate npm project; Android Studio project in `dascare_mobile/android`. See `dascare_mobile/README.md`.

### Mobile app (`dascare_mobile/`)
- Build loop: `npm run sync` (vite build + `cap sync android`), then Run in Android Studio. Browser preview: `npm run dev` → :5180.
- API URL from `dascare_mobile/.env` (gitignored; template `.env.example`): emulator uses `http://10.0.2.2/Dascare/dascare_api`, a real phone uses the Mac's Wi-Fi IP. `cors.php` whitelists `http://localhost` (Capacitor WebView, `androidScheme: http`) and `http://localhost:5180`.
- Design must match the web: theme tokens in `src/assets/main.css` are copied verbatim from `dascare/src/assets/main.css`. daisyUI built-in themes are disabled there (`themes: false`) because the stock light theme's `:is(:root:has(...))` selector out-specifies the custom `[data-theme=light]` and turned the app grey in the WebView. Lucide icons are bundled via `addCollection` (offline).
- Gradle wrapper bumped to 9.1 (Capacitor's 8.14 can't run on Android Studio 2026.1's bundled Java 25). Icons/splash: `npm run assets` (Pillow script, not `@capacitor/assets`).
- **Mobile app auth** (`reusables/mobile_auth.php`, migration `dascare_api/migrations/2026_10_09_mobile_auth.sql` → tables `api_tokens`, `guest_request_tokens`): the app sends `X-Dascare-Client: mobile` on every request. Only then does `cors.php` skip `session_start()` and call `mobileAuthBootstrap()`, which fills `$_SESSION` (user_id/user_name/user_email/user_phone/user_level — same keys as `auth/login.php` — plus `user_status`/`user_kyc_status`, which the web caches in its cookie session via `session.php` and `citizen/dashboard.php` reads) from `Authorization: Bearer <token>`, and `$_SESSION['guest_request_ids']` from `X-Guest-Tokens`. So existing `citizen/*` endpoints and `session.php` work for the app unchanged. Tokens are SHA-256 hashed, issued only to active verified **citizens**, re-validated every request; a bad token never blocks (request proceeds as guest, response header `X-Dascare-Token-Status: invalid`). Endpoints: `mobile/auth/login.php` (refuses staff with `STAFF_USE_WEB`), `verify_2fa.php`/`resend_2fa.php` (challenge token replaces the web's `pending_2fa` session), `logout.php`. Register/verify-email/forgot/reset use the web `auth/*` endpoints as-is (they're session-free). `citizen/create.php` returns `guestAccessToken` only for app guests.
- **App structure (Phase 2):** hash router (`src/router/index.js`; meta `auth`/`guestOnly`/`tab`/`kyc`/`root`), `layouts/AppShell.vue` (top bar + bottom tab bar with raised SOS, copied from the web's phone nav), stacked screens use `components/ScreenHeader.vue`. Session: `composables/useSession.js` (token in Capacitor Preferences, profile + KYC from `session.php`). Toast/Alert components and composables are copied verbatim from the web. Icons: all Lucide + only the `line-md:`/`mdi:` icons found in `src/` (`scripts/build-icon-subset.mjs` runs before dev/build, output gitignored). Leaflet is bundled (`components/MapPinPicker.vue`). KYC uses the web's `citizen/kyc_submit.php` unchanged; same states/ID lists/age rules as `Verification.vue`; History/medical unlock at `kyc_status === 2`, SOS never gated.
- Android build needs **Java 21** for `@capacitor/camera`: `android/settings.gradle` enables the foojay toolchain resolver, so Gradle downloads it to `~/.gradle/jdks` (Android Studio's bundled JBR 25 can't satisfy a 21 toolchain).
- Phased plan: 0 setup ✓ · 1 backend token auth ✓ (citizen-only login, guest access keys) · 2 app shell + accounts + KYC ✓ · 3 SOS ✓ (`views/SosView.vue`: mobile port of `EmergencyRequestForm.vue`, same payload to `citizen/create.php`; Capacitor GPS/camera/haptics; guest keys saved via `useGuestKeys`; offline error + Call 911) · 4 tracking/history ✓ (`TrackView` = mobile `TrackRequest` on `citizen/detail.php`, 15 s polling via `useLiveRefresh` + app-resume refresh; Home from `dashboard.php`, or `list.php` for unverified citizens since the web dashboard 403s them; notifications via `notifications/*.php` with an Alerts badge; guests track via saved keys — `citizen/detail.php` now also accepts the request's guest key) · 5 profile ✓ (`MedicalRecordsView` on `citizen/get.php`+`save.php`, KYC-gated like the web; `AccountView` on `account/edit_profile.php` + `toggle_two_factor.php`) · 6 polish/APK ✓ (`npm run apk [-- emulator|<ip>]` → `dascare_mobile/release/`, API URL baked in at build time via `VITE_API_BASE_URL`; `TEST_CHECKLIST.md`) · 7 push ✓ (Firebase project `dascare-af6db` configured locally 2026-10-09; verified on the emulator: delivered with the app closed, tap opens the request, foreground shows a toast). Rule: mobile work must not change web behaviour.
- **Web download:** the landing page section `views/landing/MobileApp.vue` (`#mobile-app`) shows a "Download for Android" button that reads `public/downloads/app.json` and links `public/downloads/DASCARE.apk`; both are written by `npm run apk:web` in `dascare_mobile/` (gitignored, Wi-Fi-IP build) along with an `.htaccess` declaring the APK MIME type. `dascare/vite.config.js` has a small plugin serving `.apk` with `application/vnd.android.package-archive` — without it Android saves the download as `.apk.zip`.
- **Push (Phase 7):** `reusables/push.php` (FCM HTTP v1, OAuth via service-account JWT, token cached in the temp dir). Inert unless `dascare_api/config/firebase-service-account.json` exists (gitignored); the app compiles push in only when `dascare_mobile/android/app/google-services.json` exists (`__DASCARE_PUSH__` in vite.config.js). Tables `push_devices` / `push_request_watch` (migration `2026_10_09_mobile_push.sql`); app registers via `mobile/push/register.php` (account via Bearer, guest requests via X-Guest-Tokens). Hooks: `assignmentNotifyUser()` (all mission/assignment notifications), `offers/respond.php`, `missions/status.php`, `assignments/create.php`, `care/handoff_status.php`, `platform/kyc/review.php`, `platform/citizens/status.php`. Pushes are queued and sent at shutdown only after re-checking the notification row / request status, so rolled-back actions never push; followers include reports dedup-linked to the incident. Setup steps: `dascare_mobile/PUSH_SETUP.md`.

## Commands

There are no tests and no linter configured.

```bash
# Frontend
cd dascare && npm install
npm run dev        # http://localhost:5173 (port is in vite.config.js and must match cors.php)
npm run build      # outputs dascare/dist

# Database (XAMPP MariaDB; db/db.php hardcodes localhost / dascare / root / empty password)
/Applications/XAMPP/xamppfiles/bin/mariadb -u root -e "CREATE DATABASE dascare CHARACTER SET utf8mb4"
/Applications/XAMPP/xamppfiles/bin/mariadb -u root dascare < dascare.sql

# PHP syntax check
find dascare_api -name '*.php' -not -path '*/vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'
```

The API is served by XAMPP Apache, not a PHP dev server. `dascare/.env` sets `VITE_API_BASE_URL=http://localhost/dascare_api`, so `dascare_api/` must be reachable at `htdocs/dascare_api`. In this checkout it actually lives at `htdocs/Dascare/dascare_api`, so either symlink it to `htdocs/dascare_api` or point the env var at `http://localhost/Dascare/dascare_api`. `dascare_api/.env.example` exists but nothing reads it. `cors.php` whitelists `localhost:5173`, `localhost:5174` and `127.0.0.1:5173`; any other dev origin has to be added there.

Seeded dev accounts in the dump (`*@dascare.test`): techadmin, executive, orgadmin, dispatcher, citizen.

## Architecture

### Roles and portals
One role per user, stored as a string in `user_roles.role`. Each maps to a frontend route prefix + layout, and to a backend guard:

| Role | Frontend prefix / layout | Backend guard (`dascare_api/reusables/`) |
|---|---|---|
| `citizen` | `/citizen` — `CitizenLayout` | session check in each `citizen/*` endpoint |
| `organization_admin`, `organization_operational_user` | `/organization` — `OrganizationLayout` | `requireOrganizationAccess($pdo, 'module.resource.action')` (organization_guard.php) |
| `platform_executive_admin` | `/platform` — `PlatformAdminLayout` | `requirePlatformExecutive($pdo, 'perm.key')` (platform_guard.php) |
| `technical_super_admin` | `/system` — `TechnicalAdminLayout` | `requireTechnicalSuperAdmin($pdo)` (technical_guard.php) |

The three management portals share `layouts/management/*`; their sidebar menus and per-item permission keys live in `src/config/dashboardNavigation.js`. The router guard in `src/router/index.js` reads `meta.roles` / `meta.permissions` and the session from `useSession()` (which calls `session.php`). Frontend checks are cosmetic — authorization must be enforced in the PHP guard.

### Permissions (two separate RBAC systems)
- **Platform**: `platform_roles` → `platform_role_permissions` → `platform_permissions`, assigned via `platform_account_roles`.
- **Organization**: per-organization `org_roles` → `org_role_permissions` → `rbac_permissions` (grouped by `rbac_modules`), assigned to members via `org_member_roles`. Default roles come from `reusables/organization_role_templates.php`. `organization_admin` bypasses permission checks. A DB trigger (`trg_org_roles_active_org_only`) rejects `org_roles` inserts unless the organization is `active`.

Permission keys are `module.resource.action` strings (e.g. `fleet.ambulance_status.update`, `oversight.emergency_requests.read`) and must exist in the corresponding permissions table.

### Backend endpoint conventions
Every endpoint is a standalone script that starts with:
```php
require_once __DIR__ . '/../../cors.php';   // CORS whitelist + session_start()
require_once __DIR__ . '/../../db/db.php';  // provides $pdo (ERRMODE_EXCEPTION, FETCH_ASSOC, real prepares)
require_once __DIR__ . '/../../reusables/<guard or helpers>.php';
header('Content-Type: application/json');
```
Request bodies are JSON (`json_decode(file_get_contents('php://input'), true)`), except multipart uploads (KYC, emergency photos). Responses are `{success, message, ...}`; errors go through `organizationJsonError` / `platformJsonError` / `technicalJsonError`. Mutations use explicit transactions with `SELECT ... FOR UPDATE`, and write to `audit_logs` / `rbac_audit_log` / `*_status_logs`. Domain logic lives in `reusables/*_helpers.php` (fleet, care, assignment) rather than in endpoints. `dispatch/index.php` and `emergency/index.php` are dead stubs that require non-existent files.

### Frontend conventions
- `@` is aliased to `dascare/src` (vite.config.js).
- Use the shared axios client `src/services/api.js` (`withCredentials: true`, base URL from env); domain wrappers are in `src/services/*.js`. Some older views still call raw `axios` with `import.meta.env.VITE_API_BASE_URL` — prefer `api.js` for new code.
- Global UI: `useToast`, `useAlert`, `useSession`, `useTheme` composables (singleton state).
- Maps use Leaflet loaded at runtime from unpkg (not an npm dependency), OSM tiles, and Nominatim geocoding.
- No realtime transport: pages poll every ~15 s (tracking, offers, missions, notification/sidebar badges via `navigation/sidebar_badges.php`).

### Emergency → dispatch flow (core domain)
1. `citizen/create.php` inserts `emergency_requests` (citizen or guest; guests rate-limited by `reusables/guest_request_limit.php`), snapshots medical info, stores photos. The citizen UI is a single merged form, `components/emergency-request/EmergencyRequestForm.vue` (the old Instant/Standard split and `InstantRescueForm.vue`/`DetailedRequestForm.vue` are now unused dead code), used on both `/citizen/request` and the guest modal in `views/landing/hero.vue`. It is intentionally near-field-less: no category/severity pickers, everything optional, submit always clickable. Only **location is required** (GPS/geofence — you can't dispatch to nowhere). The backend fills safe defaults for everything else: category → `8` (Other), severity → `critical`, blank description/address/barangay → placeholders, guest name → "Guest requester", phone → the logged-in account's number or empty. Contact details are gathered later by the responding org.
2. For `request_mode='instant'` it immediately calls `dssGenerateRecommendations()` in `reusables/dispatch_dss.php`. Platform admins can re-run it via `platform/incidents/run_dss.php`.
3. DSS: eligible units = ambulance `available` + latest `ambulance_readiness_checks.overall_status='ready'` + valid registration/inspection + no in-progress maintenance + org `active` + (service-area barangay match unless `city_rescue`). Score = weighted sum of distance (haversine, from `ambulances.last_latitude/longitude` or org coords), availability, capability (ambulance type vs severity/category), workload. Weights come from `system_settings['dispatch.dss.weights']` (edited at `platform/settings/dss.php`). Best unit per org → `dss_recommendation_runs` + `dss_recommendations`.
4. Top org gets an `incident_offers` row with a timeout (`dispatch.offer_timeout_seconds`); decline/timeout escalates to the next rank (`dssEscalateNextOffer`). Expiry is reconciled lazily by `dssSyncExpiredOffers()` when offer/incident lists are loaded — there is no cron. Keep offer timestamps on the DB clock (`NOW()`); PHP's timezone differs from MariaDB's.
5. Org accepts (`organizations/dispatch/offers/respond.php`) → assigns ambulance + crew (`dispatch/assignments/create.php`, `dispatch_assignments` + `crew_assignments`) → mission status updates (`dispatch/missions/status.php`) drive both `emergency_requests.status` and `ambulances.status`. Crew GPS pings go through `organizations/tracking/update.php` into `ambulance_locations` and `ambulances.last_*`.
6. Care: `organizations/care/*` — patient assessments, hospital handoffs (`medical_facilities`), incident report. Mission completion sets the ambulance to `ambulances.status='returning'`; Fleet must return it to `available` via `organizations/fleet/status.php` (which runs readiness/credential/maintenance gates). That endpoint treats `reserved/dispatched/on_scene/transporting` as "active mission" and blocks Fleet edits — but **not** `returning`, which is the post-mission state Fleet is meant to clear.
7. The org can backfill requester details (name/phone/barangay/address/landmark/description) on an incident it handles via `organizations/incidents/update_details.php` (permission `incidents.emergency_requests.update`; scoped to an org with an offer/assignment for that request; rejects closed incidents). Exposed in the UI from both `OrganizationIncidentRecords.vue` and the Active Missions action row (`OrganizationActiveMissions.vue`), reusing the `updateIncidentDetails` service.

**Barangays as responders:** a barangay is just an organization with `organization_type='barangay_rescue'` — it reuses the whole org stack (self-signup at `/apply/organization`, fleet, crew, offers, missions, handoffs). The DSS already gates non-`city_rescue` orgs to requests whose `barangay` string **exactly matches** (lowercased/trimmed) one of their `organization_service_areas` rows — matching is by name, not geography, and the request's barangay is an unvalidated reverse-geocode guess (or "Unspecified"), so coverage must list every barangay name a unit serves. Service areas are set during application and editable at Organization → Profile.

Platform "attention" flags on stale incidents (`emergency_requests.attention_level`) are computed in `reusables/incident_attention.php` using `platform.incident_*_stale_minutes` settings.

### Duplicate-request dedup (Part B)

When many people report the same emergency, later reports are linked to the first instead of each getting an ambulance. Logic is in `reusables/dispatch_dedup.php`.

- **Where it runs:** `citizen/create.php`, instant mode only, after the insert commits and **before** `dssGenerateRecommendations()`. A merged report gets `status='duplicate'` + `merged_into_request_id = primary.id` and **no DSS**. Response includes `mergedInto`.
- **Rule:** candidates are active, un-merged instant requests strictly *earlier* than the new one (by `submitted_at`, then `id`), submitted within `window_seconds` of it (window measured from each earlier report, so it never extends), within `radius_meters` (`dssHaversineKm`). Primary = earliest. Merge only if cluster size (nearby + primary's existing children + new) ≥ `min_requests`. A completed/cancelled primary is ignored, so the next report starts fresh. Org-agnostic.
- **Race safety:** `create.php` holds MariaDB named lock `dascare_dispatch_dedup` from before the insert through the dedup check (GET_LOCK is re-entrant in 10.4). Without it, a slow insert (photo upload) let two simultaneous reports both dispatch. If the lock times out, dedup is skipped (dispatch twice > never).
- **Settings:** `dispatch.dedup.enabled|radius_meters|window_seconds|min_requests` in `system_settings`, edited at **Technical → Configuration** (structured card; endpoint range-checks them via `DEDUP_LIMITS` and accepts batch `{items:[{key,value}]}`). Defaults 150 m / 300 s / 2 apply when rows are absent.
- **Unmerge (safety valve):** `dedupUnmergeRequest()` → status back to `submitted`, own DSS run, status log + `audit_logs` (`dispatch.dedup_merged` / `dispatch.dedup_unmerged`). Three entry points: `citizen/unmerge.php` (requester only — citizen by `requester_user_id`, guest by `$_SESSION['guest_request_ids']` set in create.php), `platform/incidents/unmerge.php` (`oversight.emergency_requests.update`), `organizations/incidents/unmerge.php` (`incidents.emergency_requests.update`, org must hold an accepted offer/assignment on the primary).
- **Reviewer signals:** `dedupLinkedReports()` returns children with distance, time gap, photo count and warnings computed on read: near radius edge (>70%), late in window (>70%), different details (different "Patients involved: N" or differing free-text descriptions). Shown by `components/incidents/LinkedReportsPanel.vue` on Platform City-wide Incidents, Org Active Missions and Org Incident Records (`organizations/incidents/linked.php`). Lists carry `linked_count`.
- **Citizen side:** `citizen/detail.php` returns `merged_into` (primary status/location) and the primary's ambulance; `TrackRequest.vue` follows the primary's status and shows the "Linked to … / Request separately" banner. `list.php`/`dashboard.php` return `merged_into_reference` + `linked_status`. Guests see the same notice in the `hero.vue` result modal.
- Known brittleness (unchanged): barangay-unit DSS eligibility is exact-name match on an unvalidated geocoded barangay string; a radius-based coverage check would use the same `dssHaversineKm()` primitive.

### Config and secrets
- `system_settings` table holds runtime config as JSON values (DSS weights, offer timeout, tracking intervals, guest toggle); read by helpers like `dssJsonSetting()`, edited from Technical → Configuration.
- SMTP credentials are hardcoded in `reusables/email_helper.php`; DB credentials in `db/db.php`.
- **PHP 8.0 gotcha:** XAMPP runs 8.0, so no 8.1+ syntax — e.g. spreading string-keyed arrays (`[...$assoc]`) is a fatal error (it broke `citizen/save.php` until 2026-10-09; now `array_merge`).
- **Known security issue (left as-is by the user, 2026-10-09):** `auth/reset_password.php` resets ANY account's password given only `{email, password}` — it never checks that `verify_otp.php` (context `forgot`) succeeded (that endpoint clears `reset_otp` on success, so nothing links the two). The unused `password_reset_tokens` table fits a one-time reset key. The mobile app's Forgot-password screen calls the same endpoints.
- Uploads go through `dascareUploadsDir('kyc'|'emergency_requests')` (`reusables/upload_paths.php`) → `dascare/uploads/<sub>/`, resolved from the code's own location (dascare_api/ and dascare/ are siblings). It replaced `$_SERVER['DOCUMENT_ROOT'] . '/dascare/uploads/...'`, which pointed at a non-existent `Dascare/uploads/` in this checkout (KYC uploads failed; admin ID viewer couldn't find files). Apache runs as `daemon`, so `dascare/uploads/{kyc,emergency_requests}` must be writable by it (chmod 777 locally).
