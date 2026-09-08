# Changelog

manifest 的 `notes` 取自本文件对应版本的小节（打包脚本自动提取、净化后写入）。

## 1.3.04 - 2026-09-08

- 记分册栏目在 `uploadedResponse=Y` 时可按学生上传/下载/删除回复文件：`POST/GET/DELETE /v1/markbook/columns/{id}/entries/{studentId}/response`。POST 为 multipart 字段 `file`，成功是 **200**。须先 `PUT /entries` 建该生成绩行。GET 下载是文件字节（`curl -o`），不要当 JSON。GET entries 每条含 `response.present` 等元数据，不含磁盘路径。批量给分不再改动已有回复文件。需 API 模块 1.3.04+。
- 此后技能 `version` 与 API 模块版本同一号；有功能变化则两边一起升。

## 1.2.0 - 2026-08-31

- 下载服务器产物布局改为按 skill 分目录：包与 manifest 输出到 `skills/<skill>/`，manifest 固定叫 `manifest.json`。SKILL.md 内置的 manifest 默认占位地址与 setup.md 的裸主机名补全规则同步改为 `…/skills/gibbon-api/manifest.json`；已装机器 `.env` 里的 `GIBBON_SKILL_MANIFEST_URL` 不受影响，无需重配。

## 1.1.0 - 2026-08-31

- 新增 setup.md 首次配置引导：安装后自动检测缺失项（.env、令牌、更新主机占位符），一次一项询问用户并逐项验证，生成 .env 并把更新 manifest 地址固定到 .env 的 GIBBON_SKILL_MANIFEST_URL。SKILL.md 安装节与每次会话开始改为检测到缺失即触发引导。

## 1.0.0 - 2026-08-31

- 首个带版本号的发行：SKILL.md frontmatter 增加 `version:`，新增「版本与更新」机制——固定地址 manifest 检查新版、sha256 校验、整目录替换并保留本机 `.env`、用 `GET /v1/openapi.json` 的 `info.version` 做实例兼容性核对。
