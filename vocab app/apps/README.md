# VocabApp Apps

This folder contains installable app projects related to the hosted VocabApp website.

- `android-webview/`: Native Android WordApp. It downloads word banks from `/wordapp/api`, then stores words and study progress locally with Room/SQLite.
- `desktop-electron/`: Electron desktop wrapper. It still opens the hosted web app in a desktop window.

The Android app does not use the hosted website as its main UI anymore.
