#!/bin/bash
# Build an installable DASCARE APK pointed at a given API address.
#
#   npm run apk                 # real phone on this Wi-Fi: uses this Mac's current Wi-Fi IP
#   npm run apk -- emulator     # Android emulator (http://10.0.2.2/...)
#   npm run apk -- 192.168.1.5  # a specific IP or host
#
# Output: release/DASCARE-v<version>-<target>.apk (debug-signed — install it
# directly; Android asks to allow installs from this source the first time).
# Needs XAMPP Apache + MySQL running on this Mac, and the phone on the same Wi-Fi.
set -euo pipefail
cd "$(dirname "$0")/.."

target="${1:-wifi}"
case "$target" in
  emulator) host="10.0.2.2" ;;
  wifi)     host="$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)" ;;
  *)        host="$target" ;;
esac
if [ -z "$host" ]; then
  echo "Couldn't find this Mac's Wi-Fi IP. Pass one: npm run apk -- 192.168.x.x" >&2
  exit 1
fi

api="http://${host}/Dascare/dascare_api"
version="$(node -p "require('./package.json').version")"
label="$([ "$target" = emulator ] && echo emulator || echo "$host")"
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
[ "$target" = emulator ] || echo "The phone must be on the same Wi-Fi as this Mac, and the Mac's IP must stay ${host}."
