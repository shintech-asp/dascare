<?php
/**
 * ==================================================
 * PASSWORD HELPERS
 * --------------------------------------------------
 * Shared by register.php / reset_password.php / any future
 * account-settings password change endpoint.
 *
 * Rule set (matches what Likhavite's register.php/reset_password.php
 * comments describe): 10+ chars, upper/lower/number/special, not a
 * common password, and doesn't contain the account's email/name.
 *
 * Usage:
 *     $err = passwordStrengthError($password, [$email, $firstName, $lastName]);
 *     if ($err !== null) { ...reject... }
 * ================================================== */

const COMMON_PASSWORDS = [
    'password', 'password1', 'password123', '12345678', '123456789',
    '1234567890', 'qwerty123', 'qwertyuiop', 'letmein123', 'welcome123',
    'admin1234', 'iloveyou1', 'abc123456', 'football1', 'monkey123',
    'dragon123', 'sunshine1', 'princess1', 'trustno1!', 'passw0rd!',
];

/**
 * Returns a human-readable error message, or null if the password
 * passes all checks.
 *
 * @param string   $password
 * @param string[] $excludeStrings Values the password must not contain
 *                                 (case-insensitive) — typically the
 *                                 account's email, first name, last name.
 */
function passwordStrengthError(string $password, array $excludeStrings = []): ?string
{
    if (strlen($password) < 10) {
        return "Password must be at least 10 characters long";
    }

    if (strlen($password) > 128) {
        return "Password is too long";
    }

    if (!preg_match('/[a-z]/', $password)) {
        return "Password must include at least one lowercase letter";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must include at least one uppercase letter";
    }

    if (!preg_match('/[0-9]/', $password)) {
        return "Password must include at least one number";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must include at least one special character";
    }

    $lowerPassword = strtolower($password);

    if (in_array($lowerPassword, COMMON_PASSWORDS, true)) {
        return "This password is too common. Please choose a stronger one";
    }

    foreach ($excludeStrings as $value) {
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }

        // Also check the local part of an email (before the @) so
        // "j.delacruz@x.com" still blocks a password containing
        // "jdelacruz" / "j.delacruz".
        $candidates = [$value];
        if (strpos($value, '@') !== false) {
            $candidates[] = explode('@', $value)[0];
        }

        foreach ($candidates as $candidate) {
            $candidate = strtolower(preg_replace('/[^a-z0-9]/i', '', $candidate));
            if (strlen($candidate) >= 3 && strpos(preg_replace('/[^a-z0-9]/i', '', $lowerPassword), $candidate) !== false) {
                return "Password must not contain your name or email";
            }
        }
    }

    return null;
}
