<?php
// Official barangays of Dasmariñas City (PSGC). Service areas must come from this list.
const DASMARINAS_BARANGAYS = [
    "Burol",
    "Burol I",
    "Burol II",
    "Burol III",
    "Datu Esmael",
    "Emmanuel Bergado I",
    "Emmanuel Bergado II",
    "Fatima I",
    "Fatima II",
    "Fatima III",
    "H-2",
    "Langkaan I",
    "Langkaan II",
    "Luzviminda I",
    "Luzviminda II",
    "Paliparan I",
    "Paliparan II",
    "Paliparan III",
    "Sabang",
    "Saint Peter I",
    "Saint Peter II",
    "Salawag",
    "Salitran I",
    "Salitran II",
    "Salitran III",
    "Salitran IV",
    "Sampaloc I",
    "Sampaloc II",
    "Sampaloc III",
    "Sampaloc IV",
    "Sampaloc V",
    "San Agustin I",
    "San Agustin II",
    "San Agustin III",
    "San Andres I",
    "San Andres II",
    "San Antonio De Padua I",
    "San Antonio De Padua II",
    "San Dionisio",
    "San Esteban",
    "San Francisco I",
    "San Francisco II",
    "San Isidro Labrador I",
    "San Isidro Labrador II",
    "San Jose",
    "San Juan",
    "San Lorenzo Ruiz I",
    "San Lorenzo Ruiz II",
    "San Luis I",
    "San Luis II",
    "San Manuel I",
    "San Manuel II",
    "San Mateo",
    "San Miguel",
    "San Miguel II",
    "San Nicolas I",
    "San Nicolas II",
    "San Roque",
    "San Simon",
    "Santa Cristina I",
    "Santa Cristina II",
    "Santa Cruz I",
    "Santa Cruz II",
    "Santa Fe",
    "Santa Lucia",
    "Santa Maria",
    "Santo Cristo",
    "Santo Niño I",
    "Santo Niño II",
    "Victoria Reyes",
    "Zone I (Pob.)",
    "Zone I-B",
    "Zone II (Pob.)",
    "Zone III (Pob.)",
    "Zone IV (Pob.)"];

/** The list's spelling for a name typed in any case ("burol iii" → "Burol III"), or null. */
function barangayCanonical(string $name): ?string
{
    $needle = mb_strtolower(trim($name));
    foreach (DASMARINAS_BARANGAYS as $b) if (mb_strtolower($b) === $needle) return $b;
    return null;
}

/**
 * Cleans and validates an organization's service areas. A barangay rescue unit
 * serves exactly one barangay; every other type may list several.
 * Returns [areas, error]; error is null when valid.
 */
function organizationValidateServiceAreas($areas, string $organizationType): array
{
    if (!is_array($areas)) return [[], 'Service areas must be a list.'];
    $out = [];
    foreach ($areas as $a) {
        $c = barangayCanonical((string) $a);
        if ($c === null) return [[], '"' . mb_substr(trim(strip_tags((string) $a)), 0, 40) . '" is not a Dasmariñas barangay. Choose from the list.'];
        $out[$c] = true;
    }
    $out = array_keys($out);
    // The city rescue office (LGU) covers every barangay, so it may list none.
    if (!$out && $organizationType === 'city_rescue') return [[], null];
    if (!$out) return [[], 'Select at least one Dasmariñas barangay as a service area.'];
    if ($organizationType === 'barangay_rescue' && count($out) > 1) return [[], 'A barangay rescue unit can only serve its own barangay. Select one.'];
    return [$out, null];
}
