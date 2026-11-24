# Rentora Android app

The Android app is a **Trusted Web Activity (TWA)**: a small signed Android shell that opens the live tenant portal (`/portal`) full screen. Because it loads the website, every website update reaches the app at once. A new APK or AAB is needed only when the icon, name, start URL or package settings change.

- Package name: `com.heisbayour.rentora` (permanent once published on Google Play; never change it)
- Version: `appVersionName` follows the `VERSION` file; `appVersionCode` goes up by 1 on every Play Store upload (see `docs/RELEASING.md`)
- Configuration: `android/twa-manifest.json` (no secrets)

## The signing key (never in this repository)

| | |
|---|---|
| Keystore file | `C:\Rentora\keys\rentora.keystore` (PKCS12) |
| Key alias | `rentora` |
| Passwords | in `C:\Rentora\keys\SIGNING_DETAILS.txt`, next to the keystore |
| Fingerprints | in `SIGNING_DETAILS.txt` (SHA-1 and SHA-256) |

Back up the whole `C:\Rentora\keys\` folder somewhere safe (for example an encrypted USB drive and a password manager). **If the key is lost, the app can never be updated on Google Play under the same listing.** The `.gitignore` blocks `*.keystore`, `*.jks` and `keys/` as a safety net.

To read the fingerprint again:

```
keytool -list -v -keystore "C:\Rentora\keys\rentora.keystore" -alias rentora
```

## Before building: set your domain

`android/twa-manifest.json` ships with the placeholder host `rentora.example.com`. Replace it (in `host`, `iconUrl`, `maskableIconUrl`, `webManifestUrl` and `fullScopeUrl`) with your live HTTPS domain, for example `rentora.yourdomain.com`. The site must be live at that address when you build, because Bubblewrap downloads the icons from it.

## Build the APK and AAB with Bubblewrap

Requirements: Node 18+ and `npm i -g @bubblewrap/cli`. Bubblewrap downloads its own JDK 17 and Android SDK the first time (answer "yes" to its questions), or point `%USERPROFILE%\.bubblewrap\config.json` at existing ones.

From a folder **outside** the repository (so no generated Android project or key ends up in git):

```powershell
mkdir C:\Rentora\android-build
cd C:\Rentora\android-build
copy C:\Rentora\rentora\android\twa-manifest.json .
bubblewrap update --skipVersionUpgrade      # generates the Android project from twa-manifest.json
$env:BUBBLEWRAP_KEYSTORE_PASSWORD = "<keystore password>"
$env:BUBBLEWRAP_KEY_PASSWORD = "<key password>"
bubblewrap build --skipPwaValidation
```

Outputs in `C:\Rentora\android-build\`:

- `app-release-signed.apk` - install directly on a phone (allow "install unknown apps")
- `app-release-bundle.aab` - upload to Google Play Console

### Windows build notes

- If the build stops with `'gradlew.bat' is not recognized`, Windows has `NoDefaultCurrentDirectoryInExePath=1` set. Clear it for that PowerShell window first: `Remove-Item Env:NoDefaultCurrentDirectoryInExePath`.
- The first v1.0.0 build used a portable Temurin JDK 17 in `C:\Rentora\tools\jdk-17.0.21.1+2` and the Android command-line tools in `C:\Rentora\tools\android-sdk`, set in `%USERPROFILE%\.bubblewrap\config.json` (`jdkPath`, `androidSdkPath`). `bubblewrap doctor` checks both.
- `minSdkVersion` is 24 (Android 7.0), the minimum of the Android Browser Helper library Bubblewrap uses.
- Bubblewrap downloads the icons from `iconUrl` while generating the project, so the site (or a local server for the icons) must be reachable at that moment.

## New version of the app

1. Raise `appVersionCode` by 1 and set `appVersionName` to the new `VERSION` in `android/twa-manifest.json`; commit.
2. Copy the manifest into `C:\Rentora\android-build\`, run `bubblewrap update --skipVersionUpgrade` and `bubblewrap build` as above.

## Full screen without the browser bar

Android shows the app without an address bar only after it verifies `https://yourdomain/.well-known/assetlinks.json`. Rentora serves that file from two `.env` values:

```
ANDROID_PACKAGE_NAME=com.heisbayour.rentora
ANDROID_SHA256_FINGERPRINTS=<SHA-256 from SIGNING_DETAILS.txt>
```

If you publish on Google Play with Play App Signing, add Google's app signing SHA-256 (Play Console > Setup > App integrity) to `ANDROID_SHA256_FINGERPRINTS` too, separated by a comma.

On InfinityFree the browser-check security page blocks Google's verifier, so the app opens with a thin browser bar there. On paid hosting or Render it opens full screen. The app works either way.

## Alternative: PWABuilder

If Bubblewrap will not run on your computer:

1. Open https://www.pwabuilder.com, enter your live site URL and choose **Package for stores > Android**.
2. Package ID `com.heisbayour.rentora`, app name `Rentora`, start URL `/portal`, version name from `VERSION`, version code as above.
3. Under **Signing key** choose **Use mine**, upload `C:\Rentora\keys\rentora.keystore`, key alias `rentora`, and the keystore and key passwords from `SIGNING_DETAILS.txt`.
4. Download the zip: it contains the signed APK, the AAB and an `assetlinks.json` (its fingerprint must match `ANDROID_SHA256_FINGERPRINTS`).
