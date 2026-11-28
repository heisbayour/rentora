# Releasing Rentora

## Where the version lives

- `VERSION` in the project root is the single source of truth (for example `1.0.0`).
- `config('rentora.version')` reads it. The footer of the console, the tenant portal and the public site shows `Rentora v1.0.0 (abc1234)`, and Settings and System health show the full version block.
- `composer deploy:build` writes `build-info.json` into the deploy folder with the version, the short git commit hash and the build date (UTC). The footer and System health read the commit and date from it.
- The PWA manifest (`/manifest.webmanifest`) reports the same version, and the Android app uses it as `versionName` (`android-app/android/app/build.gradle`).

## Version numbers (semantic versioning)

`MAJOR.MINOR.PATCH`

- **Patch** (`1.0.1`): bug fixes only, nothing new for users to learn.
- **Minor** (`1.1.0`): new features that do not break existing data, links or habits.
- **Major** (`2.0.0`): breaking changes, for example a change that needs manual steps on the server or removes a feature.

## Release checklist

1. Make sure `composer test`, `composer lint` and `composer analyse` all pass.
2. Update `VERSION`.
3. Add an entry at the top of `CHANGELOG.md` with the date and what changed (Added, Changed, Fixed).
4. If the Android app changes (native code, icon, name, start URL or a new store release), raise `versionCode` in `android-app/android/app/build.gradle` by 1 and set `versionName` to the new version, then rebuild (see `android-app/README.md`). Android requires `versionCode` to go up on every install-over update and Play Store upload.
5. Commit: `git commit -am "release vX.Y.Z"`.
6. Tag and push: `git tag vX.Y.Z && git push && git push origin vX.Y.Z`.
7. Build the deploy package: `composer deploy:build` (creates `dist/rentora-vX.Y.Z.zip`).
8. Upload to the server following `docs/DEPLOYMENT.md` ("Updating a live site"), then open `/system/upgrade?key=...` as the owner to run new migrations.

Website changes reach the Android app automatically, because the app opens the live site. A new APK or AAB is only needed when the Android package settings change.
