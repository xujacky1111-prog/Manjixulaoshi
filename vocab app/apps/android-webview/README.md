# WordApp Android

This folder now contains the native Android APK project for WordApp.

## Behavior

- Kotlin + Jetpack Compose UI.
- Room/SQLite stores downloaded word banks and all study progress on the phone.
- The app fetches word bank metadata from `https://layer-city.com/wordapp/api/word-banks.php`.
- The app downloads words from `https://layer-city.com/wordapp/api/word-bank.php?code=high_school`.
- Study progress is local only. There is no account login or cloud sync.

## Build

Open this folder in Android Studio or build with Gradle:

```powershell
gradle :app:assembleDebug --no-daemon
```

GitHub Actions builds the debug APK from this same directory and uploads `app-debug.apk` as the `vocabapp-debug-apk` artifact.
