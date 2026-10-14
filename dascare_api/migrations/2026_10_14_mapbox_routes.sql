-- Mapbox Directions (driving-traffic) as the first route/ETA provider (reusables/routing.php).
ALTER TABLE mission_routes MODIFY provider ENUM('mapbox','tomtom','estimate') NOT NULL;
