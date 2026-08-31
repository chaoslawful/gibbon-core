# Changelog

manifest 的 `notes` 取自本文件对应版本的小节（打包脚本自动提取、净化后写入）。

## 1.1.0 - 2026-08-31

- 新增 setup.md 首次配置引导：安装后自动检测缺失项（.env、令牌、更新主机占位符），一次一项询问用户并逐项验证，生成 .env 并把更新 manifest 地址固定到 .env 的 GIBBON_SKILL_MANIFEST_URL。SKILL.md 安装节与每次会话开始改为检测到缺失即触发引导。

## 1.0.0 - 2026-08-31

- 首个带版本号的发行：SKILL.md frontmatter 增加 `version:`，新增「版本与更新」机制——固定地址 manifest 检查新版、sha256 校验、整目录替换并保留本机 `.env`、用 `GET /v1/openapi.json` 的 `info.version` 做实例兼容性核对。
