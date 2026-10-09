// Plain-language help when Android permissions are denied, used by the SOS
// screen, the map pin and the photo pickers so the wording is the same
// everywhere. (Android has no API for an app to switch these back on.)
export const SETTINGS_PATH = 'Settings › Apps › DASCARE › Permissions'

export const isPermissionDenied = (err) => /denied|permission/i.test(String(err?.message || err || ''))
export const isUserCancel = (err) => /cancel/i.test(String(err?.message || err || ''))

export const locationDeniedText = `Location access is off for DASCARE. Turn it on in ${SETTINGS_PATH} › Location, or pin the spot on the map.`
export const cameraDeniedText = `Camera or photo access is off for DASCARE. Turn it on in ${SETTINGS_PATH}.`
