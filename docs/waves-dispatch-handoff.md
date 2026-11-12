# Dispatch waves — IMPLEMENTED locally (2026-11-12, not yet deployed)

> Built and tested locally (wave timing, decline, exhaustion, simultaneous accept, legacy mode). The sections below are the original design brief; see CLAUDE.md step 4 for the final behaviour. To publish: run `dascare_api/migrations/2026_11_12_dispatch_waves.sql` on live, deploy web + API, rebuild the app.

## Decision (owner, 2026-11-12)
Replace the one-organization-at-a-time offer cascade with **waves**; the first accept wins.

1. **Wave 1 (at once):** the barangay unit covering the incident's barangay + the top DSS-ranked organization get an offer.
   With no barangay unit, the top 2 ranked organizations. The **LGU (`city_rescue`) sees every incident from t=0** and may accept at any time (backup).
2. **After a short timer (default 2 min, admin-configurable):** add the next-ranked organizations. Earlier offers stay open (not withdrawn).
3. **After another timer:** every eligible organization.
4. **First accept wins** (atomic; others see "taken by <org>"). A decline closes the offer only for that organization.
Platform executives keep full visibility. Open items to confirm with the owner: wave timers and how many organizations each wave adds (make them settings).

## Current behaviour (what to change)
- `dascare_api/reusables/dispatch_dss.php`: `dssGenerateRecommendations()` ranks candidates and offers only rank 1 via `dssCreateOfferForRecommendation()`;
  `dssEscalateNextOffer()` moves to the next rank on decline/timeout; `dssSyncExpiredOffers()` reconciles expiry lazily (no cron) when offer/incident lists load.
- `incident_offers.offer_status` ENUM('sent','accepted','declined','timed_out','cancelled'). Today at most one `sent` offer exists per request.
- `organizations/dispatch/offers/respond.php` (accept/decline), `organizations/dispatch/offers/list.php`, `platform/incidents/run_dss.php`, `reusables/request_close.php` (withdraws `sent` offers), `reusables/realtime.php` (`realtimeRequestChanged`).
- The LGU is just another ranked candidate today (always eligible; other types are gated by exact barangay-name match in `organization_service_areas`).
- Offer timestamps must use the DB clock (`NOW()`); PHP and MariaDB timezones differ.

## Things to get right
- Several `sent` offers per request; accept under `SELECT ... FOR UPDATE` / named lock so two simultaneous accepts cannot both win; losing offers become `cancelled` with a "taken" reason + notification/realtime event.
- Wave expansion must stay lazy (`dssSyncExpiredOffers`) or be tied to list loads; keep the existing 15 s poll / live-update behaviour.
- LGU read-only monitoring of incidents + ability to accept; update `organizations/incidents/report.php` access rules if needed.
- Settings in `system_settings` (e.g. `dispatch.waves`) with range checks, editable on Platform → DSS & Operations (confirm before save).
- Update web + app (`dascare_mobile/src/views/staff/StaffOffersView.vue`), `CLAUDE.md`, and the process flow doc (`~/Desktop/DASCARE_Process_Flow.*`, section E/F).
- Test locally with real Apache logins (curl cookie jars: `executive@dascare.test`, `orgadmin@dascare.test`, password in CLAUDE.md/seed notes); clean test data afterwards.
