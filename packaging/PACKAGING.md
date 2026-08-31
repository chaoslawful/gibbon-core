# Gibbon Core 打包说明

## 文件模式总结

根据 `gibbon-core-v30.0.00` 目录的分析，发布包应包含以下内容：

### 1. 根目录文件
- 所有 `.php` 文件（入口文件、处理文件等）
- 所有 `.sql` 文件（数据库脚本）
- `CHANGELOG.txt`, `README.md`, `LICENSE`
- `favicon.ico`, `robots.txt`
- `.htaccess`（Apache 配置）
- `composer.json`, `composer.lock`

### 2. 主要目录
- **cli/** - 命令行脚本
- **installer/** - 安装程序
- **lib/** - 第三方库（jQuery, TinyMCE, Chart.js 等）
- **modules/** - 所有功能模块
- **resources/** - 资源文件（CSS, JS, 模板等）
- **src/** - 核心源代码
- **themes/** - 主题文件
- **uploads/** - 上传目录（仅结构，不含用户文件）
- **vendor/** - Composer 依赖包
- **i18n/** - 国际化文件（**排除 zh_CN**，因为 v30 中没有）

### 3. 排除的内容
以下内容**不应**包含在发布包中：

- **开发工具和配置**
  - `.git/`, `.github/`, `.gitlab-ci.yml`
  - `.gitignore`, `.gitattributes`, `.gitmodules`
  - `.editorconfig`
  - `phpstan.neon`
  
- **测试文件**
  - `tests/` 目录
  
- **开发脚本**
  - `scripts/` 目录
  - `merge_po_translations.py`
  - `xgettextGenerationCommands.sh`
  
- **特定语言包**
  - `i18n/zh_CN/`（v30.0.00 中不包含）

### 4. 特殊处理

#### uploads 目录
- 只复制目录结构
- 复制 `.htaccess` 等配置文件
- **不复制**用户上传的实际文件

#### i18n 目录
- 复制所有语言包
- **排除** `zh_CN` 目录**

## 使用方法

运行打包脚本：

```bash
./package_release.sh
```

脚本会：
1. 从 `version.php` 自动提取版本号
2. 根据上述模式复制文件
3. 验证关键文件是否存在
4. 创建 `gibbon-core-{VERSION}.tar.gz` 文件
5. 输出到 `/home/wxz/src/` 目录

## 验证

打包完成后，可以验证包的内容：

```bash
tar -tzf /home/wxz/src/gibbon-core-{VERSION}.tar.gz | head -20
```

确保：
- ✅ 包含所有必要的 PHP 文件
- ✅ 包含所有模块
- ✅ 包含 vendor 目录
- ✅ **不包含** .git 目录
- ✅ **不包含** tests 目录
- ✅ **不包含** scripts 目录
- ✅ **不包含** i18n/zh_CN

## Agent Skill 打包（-k / --skills-zip）

加 `-k` 后，脚本额外把 `modules/API/skills/` 下每个含 `SKILL.md` 的 skill 打成可安装、可检查更新的产物，输出到 `<output>/skills/`：

- `<skill>-<skill版本>.zip` 与 `<skill>-<skill版本>.tar.gz`：同一份 staging 内容的双格式包（tar.gz 给没有 `unzip` 的环境回退）
- `manifest.json`：只有一个 skill 时；将来有多个 skill 时各写 `manifest-<name>.json`

包内排除 `.git`、`.workbuddy`、`.env` 与 `.env.*`（保留 `.env.example` 模板），保留 `.gitignore`、`CHANGELOG.md` 等其余文件。

### 版本号来源

- **skill 版本**：`SKILL.md` frontmatter 的 `version:`（semver，唯一来源）。缺失或不是 `X.Y.Z` 时脚本直接报错退出，**不会回落到核心版本**。
- **moduleVersion**：`modules/API/version.php` 的 `$moduleVersion`（本版 skill 所对应的 API 模块版本）。agent 端拿它与 `GET /v1/openapi.json` 的 `info.version` 比对所连实例的兼容性。

### manifest.json 字段

| 字段 | 说明 |
|---|---|
| `name` | skill 目录名 |
| `version` | 最新 skill semver |
| `moduleVersion` | 本版 skill 对应的 API 模块版本 |
| `zipUrl` / `tarUrl` | 绝对下载地址；文件名带版本号、内容不可变 |
| `sha256` / `tarSha256` | zip / tar.gz 的 sha256（两种格式字节不同，分开算） |
| `size` | zip 字节数 |
| `releasedAt` | UTC ISO 8601 |
| `notes` | 取自 skill 目录 `CHANGELOG.md` 对应 `## <版本>` 小节，净化（去引号/反斜杠/换行）后截 500 字 |

**格式硬约定**：生成器保证一个字段占一行、值内无引号换行——skill 端（SKILL.md「版本与更新」）用 sed 按行提取。改动 manifest 格式前必须同步改 SKILL.md。

### 发版流程

1. 改 skill 内容（SKILL.md / reference.md / workflows.md / .env.example）
2. bump SKILL.md frontmatter 的 `version:`
3. 在 `CHANGELOG.md` 顶部加 `## <版本> - <日期>` 小节（它就是未来 manifest 的 `notes`）
4. `./package_release.sh -k -o ~/releases`
5. 自检：`unzip -l` 确认包内无 `.env`；manifest 的 sha256 与本地 `sha256sum` 一致；有条件时本地 `python3 -m http.server` 跑一遍更新流程
6. 上传（见下），或手动 scp——**manifest 最后传**
7. 线上 `curl <manifest 地址>` 核对 version 与 sha256
8. git 提交 skill 源文件与脚本（manifest 产物不进仓库）

### 上传（可选，默认关闭）

环境变量：

- `GIBBON_SKILLS_BASE_URL`：写进 manifest 的公开地址前缀（默认 `https://SKILL_HOST/skills`，**占位符，服务器地址定了要全局替换**）
- `GIBBON_SKILLS_UPLOAD_TARGET`：如 `user@host:/srv/dl/gibbon/skills`；设置了才启用上传
- `GIBBON_SKILLS_UPLOAD_CMD`：`rsync`（默认）或 `scp`

上传顺序固定**先传包、最后传 manifest**，避免 manifest 指向未传完的包；**绝不用 `--delete`**——旧版本包必须常驻服务器供回滚。

### 回滚

- 全局：把线上 `manifest.json` 换回旧版内容即可。注意 agent 端默认不自动降级——会提示用户"线上是旧版"，用户明确同意才按 manifest 重装（防误传/测试服务器导致静默回退）。
- 单机：`mv gibbon-api gibbon-api.broken && mv gibbon-api.bak.<时间戳> gibbon-api`（`.env` 在备份目录里）。

### 服务器缓存建议

`manifest.json` 设短缓存（如 `Cache-Control: max-age=300`）以便发版尽快生效；带版本号的 zip / tar.gz 设长缓存且视为不可变。

