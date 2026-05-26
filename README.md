# VocalPractice

一个浏览器可用的英语句型口语练习 App，包含：

- 手机优先的中文练习界面
- 学习画像问卷
- 听示范、替换练习、录音、语音转文字和反馈
- 用户自带 API Key 设置页
- PHP + SQLite 在线内容库
- `/vocalpractice/admin/` 内容管理后台

## 本地预览

```bash
python -m http.server 8001 --bind 127.0.0.1
```

然后打开：

```text
http://127.0.0.1:8001/pattern-drill.html
```

## 部署目录

Hostinger 线上部署文件位于：

```text
layer-city/vocalpractice/
```

首次部署后台前，请复制配置示例：

```bash
cp layer-city/vocalpractice/includes/config.example.php layer-city/vocalpractice/includes/config.php
```

然后把 `VP_ADMIN_PASSWORD_HASH` 换成你自己的管理员密码哈希。
