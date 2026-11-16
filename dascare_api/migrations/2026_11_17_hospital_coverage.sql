-- Hospital coverage + pin corrections. Safe to run more than once.
--  1. Adds hospitals just across the city border (Imus, Bacoor, General Trias) so an accident near the edge of
--     Dasmariñas can be offered a genuinely closer hospital in the nearest-3 list.
--  2. Re-applies the OpenStreetMap coordinates/addresses (© OpenStreetMap contributors, ODbL) for every hospital
--     listed here, so a missing row is created and a drifted or wrong pin is put back. Phone numbers and
--     capabilities are left blank on purpose — they were not verified.
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'GMF Hospital', 'hospital', 'Felipe Tirona Street, Zone 4, Poblacion, Dasmariñas', 14.3284705, 120.9380563, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'GMF Hospital');
UPDATE medical_facilities SET latitude = 14.3284705, longitude = 120.9380563, address_text = 'Felipe Tirona Street, Zone 4, Poblacion, Dasmariñas' WHERE name = 'GMF Hospital';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'St. Paul Hospital', 'hospital', 'Pablo Campos Avenue, Fatima 1, Bagong Bayan, Dasmariñas', 14.3231401, 120.9627731, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'St. Paul Hospital');
UPDATE medical_facilities SET latitude = 14.3231401, longitude = 120.9627731, address_text = 'Pablo Campos Avenue, Fatima 1, Bagong Bayan, Dasmariñas' WHERE name = 'St. Paul Hospital';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Pagamutan ng Dasmariñas (Dasmariñas City Hospital)', 'hospital', 'Aklatan Drive, Burol 2, DBB-B, Dasmariñas', 14.3228227, 120.9615555, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Pagamutan ng Dasmariñas (Dasmariñas City Hospital)');
UPDATE medical_facilities SET latitude = 14.3228227, longitude = 120.9615555, address_text = 'Aklatan Drive, Burol 2, DBB-B, Dasmariñas' WHERE name = 'Pagamutan ng Dasmariñas (Dasmariñas City Hospital)';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Asia Medic Family Hospital and Medical Center', 'hospital', 'Pala-Pala Road, Sampaloc 1, Sampaloc, Dasmariñas', 14.2990186, 120.9566181, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Asia Medic Family Hospital and Medical Center');
UPDATE medical_facilities SET latitude = 14.2990186, longitude = 120.9566181, address_text = 'Pala-Pala Road, Sampaloc 1, Sampaloc, Dasmariñas' WHERE name = 'Asia Medic Family Hospital and Medical Center';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'De La Salle University Medical Center', 'hospital', 'Governor Dominador Mangubat Boulevard, Zone 4, Poblacion, Dasmariñas', 14.3278153, 120.9432521, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'De La Salle University Medical Center');
UPDATE medical_facilities SET latitude = 14.3278153, longitude = 120.9432521, address_text = 'Governor Dominador Mangubat Boulevard, Zone 4, Poblacion, Dasmariñas' WHERE name = 'De La Salle University Medical Center';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Emilio Aguinaldo College Medical Center-Cavite', 'hospital', 'Estanislao Carungcong Road, Salitran 2, Salitran, Dasmariñas', 14.3485204, 120.93969, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Emilio Aguinaldo College Medical Center-Cavite');
UPDATE medical_facilities SET latitude = 14.3485204, longitude = 120.93969, address_text = 'Estanislao Carungcong Road, Salitran 2, Salitran, Dasmariñas' WHERE name = 'Emilio Aguinaldo College Medical Center-Cavite';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Dasmariñas City Medical Center', 'hospital', 'Paliparan Road, Tibagan, Salawag, Dasmariñas', 14.3529723, 120.9817965, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Dasmariñas City Medical Center');
UPDATE medical_facilities SET latitude = 14.3529723, longitude = 120.9817965, address_text = 'Paliparan Road, Tibagan, Salawag, Dasmariñas' WHERE name = 'Dasmariñas City Medical Center';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Manuel V. Santos Medical Center & Orthopedic Hospital', 'hospital', 'Arnaldo Highway, San Francisco, General Trias', 14.3063926, 120.9213457, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Manuel V. Santos Medical Center & Orthopedic Hospital');
UPDATE medical_facilities SET latitude = 14.3063926, longitude = 120.9213457, address_text = 'Arnaldo Highway, San Francisco, General Trias' WHERE name = 'Manuel V. Santos Medical Center & Orthopedic Hospital';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Cavite Doctor''s Medical Center', 'hospital', 'De Fuego Link Road, De Fuego, San Francisco, General Trias', 14.2928058, 120.9286295, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Cavite Doctor''s Medical Center');
UPDATE medical_facilities SET latitude = 14.2928058, longitude = 120.9286295, address_text = 'De Fuego Link Road, De Fuego, San Francisco, General Trias' WHERE name = 'Cavite Doctor''s Medical Center';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'South Imus Specialist Hospital', 'hospital', 'D. C. Ilano Boulevard, Anabu II-C, Imus', 14.3768444, 120.9345463, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'South Imus Specialist Hospital');
UPDATE medical_facilities SET latitude = 14.3768444, longitude = 120.9345463, address_text = 'D. C. Ilano Boulevard, Anabu II-C, Imus' WHERE name = 'South Imus Specialist Hospital';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'Metro South Medical Center', 'hospital', 'Molino Road, Molino IV, Bacoor', 14.3737922, 120.9796979, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'Metro South Medical Center');
UPDATE medical_facilities SET latitude = 14.3737922, longitude = 120.9796979, address_text = 'Molino Road, Molino IV, Bacoor' WHERE name = 'Metro South Medical Center';
INSERT INTO medical_facilities (name, facility_type, address_text, latitude, longitude, status)
SELECT 'King Solomon Hospital and Medical Center', 'hospital', 'Daang Hari, Anabu II-E, General Trias', 14.3650362, 120.9120896, 'active' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM medical_facilities WHERE name = 'King Solomon Hospital and Medical Center');
UPDATE medical_facilities SET latitude = 14.3650362, longitude = 120.9120896, address_text = 'Daang Hari, Anabu II-E, General Trias' WHERE name = 'King Solomon Hospital and Medical Center';
