# Desktop Electron Wrapper

Install dependencies and run:

```bash
npm install
npm start
```

Build installers:

```bash
npm run pack:win
npm run pack:mac
npm run pack:linux
```

By default, the desktop app opens:

```text
https://layer-city.com/word/
```

To point it at another deployment:

```bash
VOCABAPP_URL=https://your-domain.com/word/ npm start
```

This is a thin desktop wrapper. The PHP server and MySQL database are still required.
