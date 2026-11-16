-- Real hospitals in/near Dasmariñas (names and coordinates from OpenStreetMap, © OpenStreetMap contributors, ODbL),
-- so the "where is the patient going?" picker can recommend the nearest 3. The fictional seed
-- "Development General Hospital" is switched off so it is never recommended; its handoff history stays.
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'GMF Hospital', 'hospital', 'Felipe Tirona Street, Zone 4, Poblacion, Dasmariñas', 14.3284705, 120.9380563, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'GMF Hospital');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'St. Paul Hospital', 'hospital', 'Pablo Campos Avenue, Fatima 1, Bagong Bayan, Dasmariñas', 14.3231401, 120.9627731, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'St. Paul Hospital');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Pagamutan ng Dasmariñas (Dasmariñas City Hospital)', 'hospital', 'Aklatan Drive, Burol 2, DBB-B, Dasmariñas', 14.3228227, 120.9615555, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Pagamutan ng Dasmariñas (Dasmariñas City Hospital)');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Asia Medic Family Hospital and Medical Center', 'hospital', 'Pala-Pala Road, Sampaloc 1, Sampaloc, Dasmariñas', 14.2990186, 120.9566181, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Asia Medic Family Hospital and Medical Center');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'De La Salle University Medical Center', 'hospital', 'Governor Dominador Mangubat Boulevard, Zone 4, Poblacion, Dasmariñas', 14.3278153, 120.9432521, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'De La Salle University Medical Center');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Emilio Aguinaldo College Medical Center-Cavite', 'hospital', 'Estanislao Carungcong Road, Salitran 2, Salitran, Dasmariñas', 14.3485204, 120.93969, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Emilio Aguinaldo College Medical Center-Cavite');
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Dasmariñas City Medical Center', 'hospital', 'Paliparan Road, Tibagan, Salawag, Dasmariñas', 14.3529723, 120.9817965, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Dasmariñas City Medical Center');
UPDATE medical_facilities SET status = 'inactive' WHERE name = 'Development General Hospital';
