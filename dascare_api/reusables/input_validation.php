<?php
// Shared input checks for free text and clinical values, so keyboard-mash and
// impossible numbers are refused on the server as well as in the forms.
// Same rules as dascare/src/utils/validation.js — keep in sync.

/** True when $s reads like text a person wrote: enough letters, no key-mashing. */
function inputLooksLikeText(string $s, int $minLetters = 3): bool
{
    $s = trim($s);
    preg_match_all('/\p{L}/u', $s, $m);
    if (count($m[0]) < $minLetters) return false;
    if (preg_match('/(.)\1{3,}/u', $s)) return false;                       // "aaaaa", "!!!!!"
    foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (mb_strlen($w) >= 5 && !preg_match('/[aeiouyáéíóúñ]/u', $w)) return false; // "zxcvb"
    }
    return true;
}

/** A person's name: letters, spaces and ' . - only. */
function inputIsPersonName(string $s): bool
{
    return (bool) preg_match("/^[\p{L}][\p{L} .'\-]{1,}$/u", trim($s));
}

/**
 * Cleans vital signs. Returns [cleanArray, error|null]. Blank values are skipped.
 * Ranges are what a living adult/child could plausibly show; they catch typos, not diagnoses.
 */
function inputCleanVitals(array $vitals): array
{
    $out = [];
    $num = static fn($v) => is_numeric($v) ? (float) $v : null;
    $rules = [
        'pulse_bpm'        => ['Pulse', 20, 250, 'bpm', true],
        'respiratory_rate' => ['Respiratory rate', 4, 80, 'breaths/min', true],
        'spo2_percent'     => ['SpO2', 50, 100, '%', true],
        'temperature_c'    => ['Temperature', 30, 45, '°C', false],
    ];
    foreach ($rules as $key => [$label, $min, $max, $unit, $int]) {
        $raw = trim((string) ($vitals[$key] ?? ''));
        if ($raw === '') continue;
        $n = $num($raw);
        if ($n === null || $n < $min || $n > $max || ($int && floor($n) != $n)) {
            return [[], "$label must be a " . ($int ? 'whole ' : '') . "number between $min and $max $unit."];
        }
        $out[$key] = $int ? (string) (int) $n : rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
    $bp = trim((string) ($vitals['blood_pressure'] ?? ''));
    if ($bp !== '') {
        if (!preg_match('#^(\d{2,3})\s*/\s*(\d{2,3})$#', $bp, $m) || (int) $m[1] < 50 || (int) $m[1] > 260 || (int) $m[2] < 30 || (int) $m[2] > 160 || (int) $m[1] <= (int) $m[2]) {
            return [[], 'Blood pressure must look like 120/80 (top 50–260, bottom 30–160).'];
        }
        $out['blood_pressure'] = $m[1] . '/' . $m[2];
    }
    return [$out, null];
}

/** Government IDs accepted for employees (key => label). Same list as dascare/src/utils/validation.js. */
const EMPLOYEE_ID_TYPES = [
    'drivers_license' => "Driver's license (LTO)",
    'philsys' => 'PhilSys National ID',
    'umid' => 'UMID',
    'prc' => 'PRC ID',
    'passport' => 'Passport',
    'sss' => 'SSS ID',
    'voters' => "Voter's ID",
    'postal' => 'Postal ID',
    'other_government_id' => 'Other government ID',
];

/**
 * Normalizes an employee ID number for its type; null when it isn't valid.
 * Driver's license = LTO format: letter + 2 digits - 2 digits - 6 digits (N01-12-345678), with or
 * without dashes/spaces. Every other ID: 5–25 letters/digits/dashes containing at least 4 digits.
 */
function inputNormalizeIdNumber(string $type, string $raw): ?string
{
    $v = strtoupper(preg_replace('/[\s]+/', '', trim($raw)));
    if ($type === 'drivers_license') {
        $c = str_replace('-', '', $v);
        return preg_match('/^([A-Z])(\d{2})(\d{2})(\d{6})$/', $c, $m) ? $m[1] . $m[2] . '-' . $m[3] . '-' . $m[4] : null;
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{4,24}$/', $v) || preg_match_all('/\d/', $v) < 4 || preg_match('/(.)\1{5,}/', $v)) return null;
    return $v;
}

/** SQL comparing an ID-number column ignoring dashes/spaces/case. */
function inputIdSql(string $column): string
{
    return "REPLACE(REPLACE(UPPER($column), '-', ''), ' ', '')";
}

/** True when another employee/pending invitation already has this ID (same type + number). */
function inputIdInUse(PDO $pdo, string $type, string $normalized, int $exceptMemberId = 0): bool
{
    $needle = str_replace('-', '', $normalized);
    $a = $pdo->prepare('SELECT 1 FROM personnel_profiles WHERE id_type = ? AND ' . inputIdSql('license_number') . ' = ? AND organization_member_id <> ? LIMIT 1');
    $a->execute([$type, $needle, $exceptMemberId]);
    if ($a->fetchColumn()) return true;
    $b = $pdo->prepare("SELECT 1 FROM organization_invitations WHERE status = 'pending' AND id_type = ? AND " . inputIdSql('license_number') . ' = ? LIMIT 1');
    $b->execute([$type, $needle]);
    return (bool) $b->fetchColumn();
}
