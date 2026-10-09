-- Live updates R5: road route + ETA for an active mission (reusables/routing.php).
-- One row per dispatch assignment, replaced as the ambulance moves; plus a
-- per-day counter of calls to the routing provider (TomTom free-tier guard).

CREATE TABLE IF NOT EXISTS mission_routes (
  dispatch_assignment_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  provider ENUM('tomtom','estimate') NOT NULL,
  destination_kind ENUM('incident','facility') NOT NULL,
  destination_label VARCHAR(160) NULL,
  origin_latitude DECIMAL(10,7) NOT NULL,
  origin_longitude DECIMAL(10,7) NOT NULL,
  destination_latitude DECIMAL(10,7) NOT NULL,
  destination_longitude DECIMAL(10,7) NOT NULL,
  distance_m INT UNSIGNED NOT NULL,
  duration_s INT UNSIGNED NOT NULL,
  traffic_delay_s INT UNSIGNED NOT NULL DEFAULT 0,
  polyline MEDIUMTEXT NULL COMMENT 'Google encoded polyline (precision 5); NULL for straight-line estimates',
  computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mission_routes_assignment FOREIGN KEY (dispatch_assignment_id) REFERENCES dispatch_assignments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS routing_api_usage (
  usage_date DATE NOT NULL,
  provider VARCHAR(20) NOT NULL,
  calls INT UNSIGNED NOT NULL DEFAULT 0,
  failures INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (usage_date, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
