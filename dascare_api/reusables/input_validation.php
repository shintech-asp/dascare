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
