# 首次配置引导（安装后执行一次）

安装完本 skill、或会话开始检测到未配置时，按本文件逐步引导用户补齐配置并生成 `.env`。**一次只问一项**，每项填完立即验证，验证不过就带着错误原文回到该步重问，不要替用户猜值。已配置的项直接跳过——引导是幂等的，随时可重跑只补缺。

先跑检测，决定要问什么：

```bash
cd <本 skill 目录>
MISS_ENV=0; MISS_HOST=0
[ -f .env ] || MISS_ENV=1
if [ -f .env ]; then
  set -a; source .env; set +a
  { [ -z "${GIBBON_API_TOKEN:-}" ] || [ "$GIBBON_API_TOKEN" = "gib_pat_replace_me" ]; } && MISS_ENV=1
fi
# 更新主机：SKILL.md 仍是占位符 且 .env 未覆盖（占位值视同未设）→ 需要询问
MURL="${GIBBON_SKILL_MANIFEST_URL:-}"
[ "$MURL" = "https://SKILL_HOST/skills/gibbon-api/manifest.json" ] && MURL=""
{ [ -z "$MURL" ] && grep -q 'SKILL_HOST' SKILL.md; } && MISS_HOST=1
echo "MISS_ENV=$MISS_ENV MISS_HOST=$MISS_HOST"
```

`.env` 只用 shell `source` 加载，**不要用读文件工具打开或打印**。往 `.env` 写键统一用下面的函数（有则更新、无则追加，不回读、不回显任何已有值）：

```bash
set_key() { if grep -q "^$1=" .env 2>/dev/null; then sed -i "s|^$1=.*|$1=$2|" .env
  else printf '%s=%s\n' "$1" "$2" >> .env; fi; chmod 600 .env; }
```

## 1. API 主机（MISS_ENV=1 时）

问用户学校 Gibbon 服务器地址。推荐 `http://主机/api.php`（不依赖 `/api` rewrite），末尾不带斜杠；用户给了斜杠就先去掉。

拿到地址先验证（免鉴权）：

```bash
BASE="${BASE%/}"
curl -fsS --max-time 15 "$BASE/v1/openapi.json"
```

- 成功：把 `info.version` 告诉用户（学校 API 模块版本），进入下一步。
- 失败且用户给的是裸主机名（不含路径）：依次自动试 `$BASE/api.php/v1/openapi.json`、`$BASE/api/v1/openapi.json`，哪个通用哪个，并告诉用户最终使用的完整地址。
- 全部失败：把 curl 错误原文给用户，回到本步重问，不要猜测拼接其它路径。

## 2. 访问令牌（MISS_ENV=1 时）

指引用户创建个人访问令牌：Gibbon 网页 **API → Manage API Tokens** 创建，明文只显示一次，前缀 `gib_pat_`。提醒角色选择：教师写教案、点名、记分册，排课管理员改课表，学校管理员改结构/学期，用户管理员改人员，财务管报销/预算——不同任务通常需要**不同角色的令牌**，不要混用。

请用户把令牌粘贴到对话里。

## 3. 写入 .env（MISS_ENV=1 时，第 1、2 步值齐后）

```bash
set_key GIBBON_API_BASE "$BASE"
set_key GIBBON_API_TOKEN "$TOKEN"
```

值来自用户刚提供的消息，写入本身不算暴露；写完**不要再读、再打印**（`chmod 600` 已含在 `set_key` 里）。

## 4. 验证令牌（MISS_ENV=1 时）

```bash
set -a; source .env; set +a

curl -sS \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  -H "Content-Type: application/json" \
  "$GIBBON_API_BASE/v1/me"
```

- 成功：向用户展示返回里的身份与 `capabilities` 摘要（这些不是机密）。
- 失败：把响应里的 `error` 原文告诉用户，回到第 2 步重新要令牌（地址第 1 步已验证过，通常不用重问）。

## 5. 更新主机（MISS_HOST=1 时）

说明现状：本 skill 的更新检查默认指向占位地址 `https://SKILL_HOST/skills/gibbon-api/manifest.json`（SKILL.md 内置），需要用户提供 skill 下载服务器地址才能检查更新。

问用户服务器地址。给了裸主机名就自动补成 `$地址/skills/gibbon-api/manifest.json`（服务器上每个 skill 一个同名子目录），用户直接给了 manifest 完整地址就用原样。验证：

```bash
curl -fsS --max-time 15 "$MURL"
```

- 成功：把 manifest 里的 `version` 告诉用户，然后 `set_key GIBBON_SKILL_MANIFEST_URL "$MURL"`。该键写在 `.env` 里，更新整目录替换时会随 `.env` 一起保留，不用每次重配。
- 用户说没有 / 暂时跳过：不写，告知后果——更新检查会一直静默跳过，之后随时重跑本引导补配。

## 6. 收尾

向用户报告：API 主机（地址可展示）、令牌只说「已配置」（**绝不回显值**）、更新主机「已配置为 X / 已跳过」、`/v1/me` 的身份。然后回到 SKILL.md「每次会话开始」流程继续（版本检查、正式任务）。
