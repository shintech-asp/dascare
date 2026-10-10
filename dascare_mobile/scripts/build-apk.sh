#!/bin/bash
# Build an installable DASCARE APK pointed at a given API address.
#
#   npm run apk                 # real phone on this Wi-Fi: uses this Mac's current Wi-Fi IP
#   npm run apk -- emulator     # Android emulator (http://10.0.2.2/...)
#   npm run apk -- 192.168.1.5  # a specific IP or host
#   npm run apk -- live         # the deployed site (https://dascare.online/api), works anywhere
#   npm run apk:web             # Wi-Fi build, also published to the web's
#                               # "Download for Android" button (landing page)
#
# Output: release/DASCARE-v<version>-<target>.apk (debug-signed — install it
# directly; Android asks to allow installs from this source the first time).
# Needs XAMPP Apache + MySQL running on this Mac, and the phone on the same Wi-Fi.
set -euo pipefail
cd "$(dirname "$0")/.."

target="${1:-wifi}"
publish_web="${2:-}"
case "$target" in
  live)     host="" ;;  # the deployed site (LIVE_API_URL, default https://dascare.online/api)
  emulator) host="10.0.2.2" ;;
  wifi)     host="$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)" ;;
  *)        host="$target" ;;
esac
if [ -z "$host" ] && [ "$target" != live ]; then
  echo "Couldn't find this Mac's Wi-Fi IP. Pass one: npm run apk -- 192.168.x.x" >&2
  exit 1
fi

api="http://${host}/Dascare/dascare_api"
[ "$target" = live ] && api="${LIVE_API_URL:-https://dascare.online/api}"
version="$(node -p "require('./package.json').version")"
label="$([ "$target" = emulator ] || [ "$target" = live ] && echo "$target" || echo "$host")"
echo "Building DASCARE v${version} for API ${api}"

# VITE_API_BASE_URL from the environment wins over .env for this build only.
VITE_API_BASE_URL="$api" npm run build
npx cap sync android

# Android Studio's bundled Java runs Gradle; the camera plugin's Java 21 is
# downloaded automatically (see android/settings.gradle).
export JAVA_HOME="${JAVA_HOME:-/Applications/Android Studio.app/Contents/jbr/Contents/Home}"
(cd android && ./gradlew assembleDebug -q)

mkdir -p release
out="release/DASCARE-v${version}-${label}.apk"
cp android/app/build/outputs/apk/debug/app-debug.apk "$out"
echo
echo "APK ready: dascare_mobile/${out}"
echo "API:       ${api}"
[ "$target" = emulator ] || [ "$target" = live ] || echo "The phone must be on the same Wi-Fi as this Mac, and the Mac's IP must stay ${host}."

# --web: publish for the landing page's "Download for Android" button
# (dascare/src/views/landing/MobileApp.vue reads downloads/app.json).
if [ "$publish_web" = "--web" ]; then
  web_dir="../dascare/public/downloads"
  mkdir -p "$web_dir"
  cp "$out" "$web_dir/DASCARE.apk"
  size=$(stat -f%z "$web_dir/DASCARE.apk")
  printf '{"version":"%s","size_bytes":%s,"built_at":"%s","api":"%s"}\n' "$version" "$size" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$api" > "$web_dir/app.json"
  # Apache (XAMPP / production): send .apk as an Android package, not "zip".
  printf 'AddType application/vnd.android.package-archive .apk\n<IfModule mod_headers.c>\n  <FilesMatch "\\.apk$">\n    Header set Content-Disposition "attachment"\n  </FilesMatch>\n</IfModule>\n' > "$web_dir/.htaccess"
  echo "Published to the web: dascare/public/downloads/DASCARE.apk"
fi
