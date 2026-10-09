# DASCARE — Live Updates Evaluation (R6)

How DASCARE moved from **15-second polling** to **live updates**, and the measurements that compare the two.
Measured on 2026-10-09 on the development setup described below. Re-run the live part any time from
**Technical → Configuration → Live updates vs polling → Run latency test**.

## 1. What changed

| | Before | After |
|---|---|---|
| How screens learn about changes | Each screen asks the server again every 15 s (polling) | The server announces each change the moment it is saved; open screens update within a fraction of a second (Ably) |
| Ambulance on the requester's map | Jumps every 15 s, straight dashed line | Moves within ~0.4 s of each GPS fix (crew sends every 5 s), glides between fixes, road route |
| ETA | None | Road route + traffic-aware ETA (TomTom), recalculated as the unit moves |
| Crew directions | None | "Navigate" opens Google Maps / Waze to the current destination |
| If live updates are unavailable | — | Every screen falls back to polling every 15 s automatically |

Covered screens: requester tracking (web + Android app), requester lists and dashboard, organization Incident Offers,
Active Missions, Live Mission Tracking and Fleet, platform City-wide Incidents, Incident Escalations, Fleet
Availability and dashboard, notification bells and sidebar badges (all portals), the app's Alerts tab.

## 2. Architecture in one paragraph

PHP saves the change in MySQL as before. Only **after the database transaction commits**, it sends a small
"something changed" message to Ably over HTTPS (`reusables/realtime.php`). Screens hold a WebSocket to Ably and,
on a message, re-load the endpoint they already use — so **all permission checks stay in the PHP guards** and no
private data travels through Ably (the only payload with data is the ambulance position/route on that request's
own channel). Each browser/app gets a **listen-only, 1-hour token** from `realtime/auth.php` that lists exactly the
channels it may hear (its own requests; its organization; the platform channel for executives). No long-running
server process is needed, so it works on ordinary shared hosting.

## 3. Results

### 3.1 Delivery latency — 20 test events (Technical → Configuration)

Server request → event received in the browser (Android emulator Chrome, Wi-Fi):

| min | median | 95th percentile | max | delivered |
|---|---|---|---|---|
| 216 ms | **248 ms** | 378 ms | 421 ms | 20 / 20 |

### 3.2 Live vs polling on a real mission — 12 GPS updates

A crew account sent GPS fixes through the real endpoint (`organizations/tracking/update.php`) at random moments.
Two requester clients watched the same request at the same time: one **live** (Ably, as the Track screen now
works), one **polling `citizen/detail.php` every 15 s** (as it worked before). Time = from the crew's request until
each client first saw the new position.

| # | Live | 15 s polling |
|---|---|---|
| 1 | 278 ms | 22 ms ¹ |
| 2 | 196 ms | 7,318 ms |
| 3 | 250 ms | 8,966 ms |
| 4 | 208 ms | 8,715 ms |
| 5 | 469 ms | 12,375 ms |
| 6 | 447 ms | 7,608 ms |
| 7 | 204 ms | 8,412 ms |
| 8 | 466 ms | 9,187 ms |
| 9 | 455 ms | 7,550 ms |
| 10 | 448 ms | 12,334 ms |
| 11 | 443 ms | 7,696 ms |
| 12 | 418 ms | 11,272 ms |

| | min | median | mean | max |
|---|---|---|---|---|
| **Live** | 196 ms | **418 ms** | **357 ms** | 469 ms |
| **15 s polling** | 22 ms | **8,412 ms** | **8,455 ms** | 12,375 ms |

→ **Live updates were ~24× faster on average** (8.5 s → 0.36 s) and, just as important, **predictable**: the slowest
live update (0.47 s) beat 11 of 12 polls. Polling's delay depends on luck — when the next refresh happens to fire
(theoretically anywhere from 0 to 15 s, average 7.5 s).

¹ The poller's refresh happened to fire right after the change — the luck factor polling depends on.

### 3.3 End-to-end checks (automated, real HTTP + real Ably)

| Phase | What was verified | Result |
|---|---|---|
| R0 foundation | Token permissions per role (citizen, guest, org, platform, technical, suspended), signature, listen-only, queue only after commit | 17 + 9 + 9 + 8 passed |
| R1 tracking | Crew GPS → requester + org maps; refused pings announce nothing | 13 passed; ping → screen 0.1–0.2 s |
| R2 dispatch | SOS → offer → expiry → accept → assign → mission steps → duplicate link/unlink; uninvolved orgs hear nothing | 29 passed |
| R3 oversight | Fleet changes, overdue flags (announced once), platform feed | 12 passed |
| R4 notifications | Bells for the right person only; "mark all read" syncs other tabs; review-queue badges | 14 passed |
| R5 routes | Route caching, daily cap, provider failure fallback, hospital destination, route event | 18 + 5 passed; real TomTom route verified |

Visual checks on the Android emulator: requester tracking (web + app), org Incident Offers (new offer + toast within
2 s), City-wide Incidents (count updates without reload), notification bell, live route map.

### 3.4 Fallback — the platform keeps working without live updates

| Scenario | Crew GPS request | Requester still sees the update |
|---|---|---|
| Normal | 200 OK | Yes, live (~0.4 s) |
| Live updates switched off (Technical → Configuration) | 200 OK, nothing published | Yes, by 15 s polling |
| Wrong Ably key (simulated outage) | 200 OK, failure only logged | Yes, by 15 s polling |
| Browser holds a token Ably rejects | — | Client is refused in ~0.1 s, never reports "connected", screens poll every 15 s and keep retrying live |
| No TomTom key / TomTom down / daily cap reached | 200 OK | Map shows a labelled straight-line ETA estimate |

### 3.5 Free-tier usage

| Service | Free allowance | Used on the test day (all development and testing above) |
|---|---|---|
| Ably messages | 6,000,000 / month | **163** |
| Ably peak connections | 200 | **5** |
| TomTom routing | ~2,500 / day (DASCARE caps itself at 2,000) | **2** (route recalculated only after 150 m of movement or 60 s) |

Estimated cost of one 20-minute mission with GPS every 5 s and four screens watching: about 1,300 Ably messages
and ~20 TomTom routes — i.e. thousands of missions per month inside the free tiers.

## 4. Limitations (honest notes for the paper)

- **Offer expiry has no scheduler.** An unanswered offer is passed to the next organization when someone loads an
  offers/incident list. Live screens trigger this at the exact second of expiry (Incident Offers) or every 60 s
  (City-wide Incidents, staff sidebars); with nobody online it waits. A server cron job would remove this.
- **Crew GPS comes from a browser tab** (Live Mission Tracking → Share This Device GPS). If the phone's screen sleeps,
  updates stop until it wakes. A dedicated crew app with background location would fix it.
- **On XAMPP (Apache mod_php)** the crew's GPS request returns after the live message and route are sent
  (~0.4 s, up to ~1.3 s when TomTom is called). On PHP-FPM hosting the response is released first
  (`fastcgi_finish_request`). This does not affect what requesters see.
- **ETA** is the last calculated value from the ambulance's latest position (no client-side countdown, so a stalled
  GPS cannot show a false "Arriving").
- **Android emulator:** the WebGL map may render blank unless the virtual device uses software graphics
  (`-gpu swiftshader_indirect`); real phones are unaffected.
- Measurements were taken on one development machine and an emulator on the same network; production numbers
  depend on the host's location and the users' connections.

## 5. How to reproduce

1. Live latency: sign in as technical admin → **System Configuration → Live updates vs polling → Run latency test**.
2. Watch it in the demo: open a request's tracking page (requester) and Live Mission Tracking (crew) side by side,
   press **Share This Device GPS**, move — the requester's map follows within a second.
3. Fallback: switch **Live updates** off in the same page; every live badge turns to "Every 15s" and screens keep
   updating on the 15 s cycle. Switch it back on.
