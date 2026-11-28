# Rentora Android app

Since version 1.0.2 (versionCode 3) the Android app is a **Capacitor WebView** that opens the live tenant portal, `https://rentora-housing.infinityfreeapp.com/portal`, full screen, like a native app with no browser bar. It replaced the Trusted Web Activity, which fell back to a Chrome tab with an address bar because InfinityFree blocks Google's Digital Asset Links check (see `docs/DECISIONS.md`).

The app loads the website, so website updates reach it at once. A new APK or AAB is needed only when the native code, icon, name, start URL or Capacitor version changes.

- Package name: `com.heisbayour.rentora` (permanent; the same as the old app, so it installs as an update)
- Version: `versionCode` and `versionName` in `android/app/build.gradle`
- Signing key: `C:\Rentora\keys\rentora.keystore`, alias `rentora` (never in this repository)

## What the app does

| Feature | Where |
|---|---|
| Opens `/portal`; the live domain and Paystack (`checkout.paystack.com`, `*.paystack.com`, `*.paystack.co`) stay inside the app | `capacitor.config.json` (`server.url`, `allowNavigation`) |
| Adds `RentoraApp` to the user agent; the site then hides its "Install app" card and skips its service worker | `capacitor.config.json`, `app/Support/NativeApp.php`, `resources/js/pwa.js` |
| JavaScript, DOM storage and cookies on (InfinityFree's browser check passes); cookies flushed to disk on pause, so logins are remembered | `MainActivity.setUpWebView`, `onPause` |
| Status bar `#1A73E8` with white icons on every Android version, including edge-to-edge (Android 15+); the page is padded by the bar and keyboard sizes | `MainActivity.setUpSystemBars`, SystemBars `insetsHandling: "disable"` |
| Back button: back in page history; exits only from a home page (`/portal`, `/app`, `/login`, `/`) | `MainActivity.setUpBackButton` |
| File inputs: one chooser with the camera and the gallery or files; camera permission asked only when a photo field opens | `FileChooser.java` |
| PDF downloads (receipts, invoices, statements): fetched by the page with the logged-in session, saved to Downloads, opened in the PDF viewer | `NativeBridge.java` |
| `wa.me`, `tel:`, `mailto:`, `sms:`, `intent://` and other sites open in their app or the browser | `RentoraWebViewClient.java` |
| "No connection" screen with Retry when a page cannot load (HTTP errors such as 404 still show the real page) | `RentoraWebViewClient.onReceivedError`, `www/offline.html` |
| Pull down to refresh (only when the page is at the top) | `MainActivity.setUpPullToRefresh` |
| Splash screen and launcher icons from `public/icons` | `scripts/make-icons.php` |

On Android 7 to 9 downloads are saved in the app's own Downloads folder (`Android/data/com.heisbayour.rentora/files/Download`) and opened from there, because the public folder needs a storage permission on those versions.

## Requirements

- Node 22 or newer
- JDK 21: the portable Temurin JDK in `C:\Rentora\tools\jdk-21.0.13.1+2`
- Android SDK with platform 36 and build-tools 36: `C:\Rentora\tools\android-sdk`

`android/local.properties` (git-ignored) points Gradle at the SDK:

```
sdk.dir=C\:\\Rentora\\tools\\android-sdk
```

## Signing (never committed)

Copy `android/keystore.properties.example` to `android/keystore.properties` (git-ignored) and fill in the passwords from `C:\Rentora\keys\SIGNING_DETAILS.txt`:

```
storeFile=C:/Rentora/keys/rentora.keystore
storePassword=...
keyAlias=rentora
keyPassword=...
```

Instead of the file you can set `RENTORA_KEYSTORE_FILE`, `RENTORA_KEYSTORE_PASSWORD`, `RENTORA_KEY_ALIAS` and `RENTORA_KEY_PASSWORD`. Back up `C:\Rentora\keys\` in two safe places: without that key the app can never be updated.

## Build the signed APK and AAB

```powershell
cd C:\Rentora\rentora\android-app
$env:JAVA_HOME = 'C:\Rentora\tools\jdk-21.0.13.1+2'
$env:ANDROID_HOME = 'C:\Rentora\tools\android-sdk'
npm ci
npx cap sync android
cd android
.\gradlew.bat assembleRelease bundleRelease
```

Outputs:

- `android/app/build/outputs/apk/release/app-release.apk` (install on a phone)
- `android/app/build/outputs/bundle/release/app-release.aab` (Google Play)

Copy them to `C:\Rentora\android-build\` as `rentora-<version>.apk` and `.aab`. Check them:

```powershell
$bt = 'C:\Rentora\tools\android-sdk\build-tools\36.1.0'
& "$bt\apksigner.bat" verify --print-certs C:\Rentora\android-build\rentora-1.0.2.apk   # SHA-256 must be 11:AB:02:...:15:82
& "$bt\aapt2.exe" dump badging C:\Rentora\android-build\rentora-1.0.2.apk | Select-String "package:"
```

If Windows says `'gradlew.bat' is not recognized`, clear `NoDefaultCurrentDirectoryInExePath` for that window: `Remove-Item Env:NoDefaultCurrentDirectoryInExePath`.

## New version of the app

1. In `android/app/build.gradle` raise `versionCode` by 1 (it must always go up, or phones refuse the update) and set `versionName` to the `VERSION` of the site.
2. Rebuild as above, copy the outputs to `C:\Rentora\android-build\`, commit.
3. Add a line to `CHANGELOG.md`.

Icons changed? Update `public/icons/icon-512.png` and `icon-maskable-512.png`, then run `php scripts/make-icons.php` from `android-app/` before building.

Another domain? Change it in `capacitor.config.json` (`server.url`, `allowNavigation`), `MainActivity.HOST` and `www/offline.html`.

## Testing on a phone

Install the APK over the old app (allow "Install unknown apps" for the file manager or browser), then check: login and staying logged in after closing the app, the back button (exits only from the home page), a photo upload from the camera and from the gallery, a receipt PDF download, a WhatsApp link, and airplane mode (the "No connection" screen, then Retry). There must be no address bar anywhere, including on the Paystack page.

The Android emulator needs Windows Hypervisor Platform. On this machine it was off (enabling it needs a restart), so 1.0.2 was verified by build checks and on a phone.
