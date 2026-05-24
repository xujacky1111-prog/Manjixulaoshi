# VocabApp App Wrappers

This folder contains installable wrappers for the hosted VocabApp website.

- `android-webview/`: Android Studio project. It opens `https://layer-city.com/word/` in a WebView.
- `desktop-electron/`: Electron desktop project. It opens the same hosted app in a desktop window.

These wrappers keep deployment simple: the website, database, AI API settings, and token monitor still live on the server. If you need a version that works without any server, build a separate offline edition with local SQLite storage and an import/export or sync workflow.
