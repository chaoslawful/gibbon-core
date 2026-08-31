# Changelog

manifest 的 `notes` 取自本文件对应版本的小节（打包脚本自动提取、净化后写入）。

## 1.0.0 - 2026-08-31

- 首个带版本号的发行：SKILL.md frontmatter 增加 `version:`，新增「版本与更新」机制——固定地址 manifest 检查新版、sha256 校验、整目录替换并保留本机 `.env`、用 `GET /v1/openapi.json` 的 `info.version` 做实例兼容性核对。
