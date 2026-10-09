<?php

/**
 * Where uploaded files live: dascare/uploads/<sub>/ in this repo
 * (KYC IDs in kyc/, emergency photos in emergency_requests/).
 *
 * Resolved from this file's own location — dascare_api/ and dascare/ are
 * siblings — instead of $_SERVER['DOCUMENT_ROOT'] . '/dascare/uploads',
 * which only worked when the repo itself was htdocs/dascare. With the repo
 * at htdocs/Dascare/ that pointed at Dascare/uploads/ (doesn't exist), so
 * KYC uploads failed and the admin ID viewer couldn't find existing IDs.
 * __DIR__ is symlink-resolved, so this also works when dascare_api/ is
 * symlinked to htdocs/dascare_api.
 */
function dascareUploadsDir(string $sub): string
{
    return dirname(__DIR__, 2) . '/dascare/uploads/' . trim($sub, '/') . '/';
}
