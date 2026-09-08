---
name: gibbon-api
description: >-
  通过 Gibbon Agent REST API 读写课表、课程规划、学校结构、人员、教职工、出勤、记分册（含成绩回复文件）、行为记录与财务支出。
  仅在用户明确要求使用 gibbon-api skill、按该 REST API 操作 Gibbon、或安装本 skill 后点名操作课表/课程规划时使用。
disable-model-invocation: true
version: 1.3.04
---

# Gibbon Agent REST API

用个人访问令牌（PAT）调用 Gibbon REST。权限等于令牌所有者 + 创建时锁定的那一个角色。正式评估、学习成果写入、教案讨论不在范围内。

完整接口见 [reference.md](reference.md)。工作流见 [workflows.md](workflows.md)。

## 安装（给其它 agent）

把**本目录**（含 `SKILL.md`）完整拷到目标产品的 skills 目录，文件夹名保持 `gibbon-api`——下方更新机制也按这个名字做备份与替换。**不要拷 `.env`**——里面是本机的真实令牌，到目标机后由引导重新生成。

拷完后**立即按 [setup.md](setup.md) 执行首次配置引导**：它会检测缺失项（`.env`、令牌、更新主机占位符），一次一项地询问用户、逐项验证并生成 `.env`（其中第 1 步的 openapi.json 探测会顺带报出学校 API 模块版本，接口以 [reference.md](reference.md) 为准）。之后每次会话会自动查更新（见「版本与更新」），更新会保留本机 `.env`，无需重新配置令牌

`.env` 与所有 `.env.*` 变体（`.env.local`、`.env.production` 等）只放本机，不要写入 skill 正文或 git。使用时**不得暴露内容**：不要把文件原文或其中的 `GIBBON_API_TOKEN` 等值打印到对话、报告或任何生成的文件里；在 shell 里用 `$变量名` 引用即可。模板 `.env.example` 不含真实凭据，不受此限。

## 每次会话开始

1. 确认 **本 skill 目录** 的 `.env` 已填好、更新主机已配置（检测命令见 [setup.md](setup.md) 开篇）。检测到缺失——无 `.env`、令牌仍是占位值、或 SKILL.md 仍是 `SKILL_HOST` 占位且 `.env` 未设 `GIBBON_SKILL_MANIFEST_URL`——就先走 setup.md 引导补齐再继续。加载用 shell `source`（见下方命令）；**不要用读文件工具打开或打印 `.env`**（部分运行环境会直接拒绝读取）。
2. `GIBBON_API_BASE` 推荐 `http://主机/api.php`（不依赖 `/api` rewrite）。不要末尾斜杠。
3. 先探测身份，失败则停止，把响应里的 `error` 原样告诉用户：

```bash
set -a
source .env
set +a

curl -sS \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  -H "Content-Type: application/json" \
  "$GIBBON_API_BASE/v1/me"
```

4. 看 `capabilities` 再决定能做什么：
   - `planner.read` / `planner.write` / `planner.editAllClasses`：教案
   - `planner.units`：单元与智能块
   - `timetable.read` / `timetable.write`：课表结构与课格
   - `timetable.courses` / `timetable.enrolment`：课程、班级、选课
   - `school.structure`：年级组、学部、学院、行政班、场地、学年、学期、特殊日
   - `user.admin`：人员、角色、家庭
   - `staff.read` / `staff.write`：教职工名册与档案（Staff Directory / Manage Staff）
   - `attendance.class` / `attendance.formGroup` / `attendance.person`：按教学班 / 行政班 / 个人点名
   - `attendance.codes`：出勤代码管理；`attendance.reports`：出勤报表（只读）
   - `markbook.write` / `markbook.editAllClasses`：记分册栏目、给分、学生回复文件
   - `finance.expenses` / `finance.expensesAll`：报销；`finance.fees`：费用目录；`finance.budgets`：预算；`finance.pettyCash`：零用金
   - `behaviour.write` / `behaviour.writeAll`：行为记录
   - 预算周期（`budgetCycles_manage`）与报销审批人（`expenseApprovers_manage`）没有对应 capability，403 即缺权

5. 顺手做一次版本检查（见下方「版本与更新」）。拉不到 manifest 就跳过：**不重试、不影响后续任何操作**。

## 版本与更新

版本号在本文件开头 frontmatter 的 `version:`，与 API 模块版本（`version.php` 的 `$moduleVersion`、`GET /v1/openapi.json` 的 `info.version`）**必须是同一个号**。接口或技能行为有任何功能变化，两边一起升这个号再发版。与 Gibbon **核心**版本无关。更新源是一份固定地址的 manifest，声明最新版本、对应的 API 模块版本 `moduleVersion`（应与 `version` 相同）和下载包；manifest 由发布脚本生成，保证**一个字段占一行**。打包时若 skill `version` 与模块版本不一致会直接失败。

- manifest 地址默认 `https://SKILL_HOST/skills/gibbon-api/manifest.json`（占位符）。实际地址在安装时由 [setup.md](setup.md) 引导写入 `.env` 的 `GIBBON_SKILL_MANIFEST_URL`；换服务器、本地测试时也改这个键。

比较版本用（对 `1.3.03` 这类前导零补丁号同样正确）：

```bash
ver_cmp() { awk -v a="$1" -v b="$2" 'BEGIN{na=split(a,A,".");nb=split(b,B,".");
for(i=1;i<=na||i<=nb;i++){x=(i<=na)?A[i]+0:0;y=(i<=nb)?B[i]+0:0;
if(x>y){print 1;exit}if(x<y){print -1;exit}}print 0}'; }
```

### 检查更新

```bash
curl -fsS --max-time 15 "${GIBBON_SKILL_MANIFEST_URL:-https://SKILL_HOST/skills/gibbon-api/manifest.json}"
```

拉到后比较 manifest 的 `version` 与本地 frontmatter 版本（用 `ver_cmp`）：

- 相同：无事，继续。
- 线上更新：向用户报告本地版本、新版 `version`、`moduleVersion`、`notes` 摘要，先做兼容性核对，**等用户确认**后再按下方步骤更新。
- 线上更旧：**不要自动降级**（可能是换了服务器或正在回滚）。告知用户；要回退用更新留下的备份目录，或用户明确要求才按 manifest 重装。

### 兼容性核对（发现新版后、下载前做一次）

```bash
curl -fsS --max-time 15 "$GIBBON_API_BASE/v1/openapi.json"
```

响应是单行 JSON，看 `info.version`（学校实例的 API 模块版本），与 manifest 的 `moduleVersion` 比较：

- 相等：兼容，正常更新。
- 实例 < `moduleVersion`：实例模块落后，新版 skill 写的接口可能 404。在确认报告里单列这条风险，建议用户先升级学校 API 模块；用户仍确认要更新的可以继续。
- 实例 > `moduleVersion`：实例比 skill 覆盖的版本新，可能有未收录接口，提一句即可，不阻塞。

### 更新步骤（用户确认后）

在**包含 `gibbon-api/` 的 skills 目录**里执行：

```bash
die() { echo "$1" >&2; exit 1; }
MURL="${GIBBON_SKILL_MANIFEST_URL:-https://SKILL_HOST/skills/gibbon-api/manifest.json}"
[ -d gibbon-api ] || die "当前目录没有 gibbon-api/，请在 skills 目录执行"
TMP="$(mktemp -d)" && trap 'rm -rf "$TMP"' EXIT

# 1. 拉 manifest 取字段（生成器保证一个字段一行，值内无引号换行）
curl -fsS --max-time 15 "$MURL" > "$TMP/manifest.json" || die "manifest 拉取失败，放弃更新"
get() { sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" "$TMP/manifest.json" | head -1; }
NEW_VER="$(get version)"; MOD_VER="$(get moduleVersion)"
[ -n "$NEW_VER" ] && [ -n "$MOD_VER" ] || die "manifest 缺 version/moduleVersion，放弃"

# 2. 选包下载：有 unzip 用 zip，否则用 tar.gz
if command -v unzip >/dev/null 2>&1; then URL="$(get zipUrl)"; SHA="$(get sha256)"; EXT=zip
else URL="$(get tarUrl)"; SHA="$(get tarSha256)"; EXT=tgz; fi
[ -n "$URL" ] && [ -n "$SHA" ] || die "manifest 缺下载地址或 sha256，放弃"
curl -fsS --max-time 120 -o "$TMP/skill.$EXT" "$URL" || die "下载失败，放弃"

# 3. sha256 校验（sha256sum → shasum → openssl 依次回退），不符立即放弃，不动现有目录
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | cut -d' ' -f1
  elif command -v shasum >/dev/null 2>&1; then shasum -a 256 "$1" | cut -d' ' -f1
  else openssl dgst -sha256 "$1" | awk '{print $NF}'; fi; }
GOT="$(sha256 "$TMP/skill.$EXT")"
[ "$GOT" = "$SHA" ] || die "sha256 不符（期望 $SHA，实得 $GOT），已丢弃"

# 4. 解压并确认顶层目录
if [ "$EXT" = zip ]; then (cd "$TMP" && unzip -q skill.zip)
else (cd "$TMP" && tar -xzf skill.tgz); fi
[ -f "$TMP/gibbon-api/SKILL.md" ] || die "包里没有 gibbon-api/SKILL.md，放弃"

# 5. 备份旧目录 → 换新目录 → 从备份恢复本机 .env（失败自动还原）
BAK="gibbon-api.bak.$(date +%Y%m%d%H%M%S).$$"
mv gibbon-api "$BAK" || die "备份失败，原目录未动"
if mv "$TMP/gibbon-api" gibbon-api; then
  find "$BAK" -maxdepth 1 -type f \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' -exec cp -p {} gibbon-api/ \;
  echo "已更新到 $NEW_VER（对应 API 模块 $MOD_VER）。旧目录备份在 $BAK，验证无误后可删。"
else
  mv "$BAK" gibbon-api && die "替换失败，已还原原目录"
fi
```

注意：

- **整目录替换，不做逐文件覆盖**——解压不会删除旧版已删掉的文件，逐文件覆盖会混出"半新半旧"的目录。
- `.env` 与所有 `.env.*`（不含 `.env.example`）从备份拷回。本地改过 `.env.example` 的会被新版覆盖，去备份找。
- 本地对 SKILL.md、reference.md 等的修改会被覆盖，**需要时从 `$BAK` 备份目录找回**。
- 更新完成后**重新读一遍本文件与 reference.md / workflows.md**（新版内容可能变化），再继续用户任务。

## 请求约定

- 除 `GET /v1/openapi.json` 外，所有接口都要 Bearer。
- JSON 请求带 `Content-Type: application/json`。记分册回复文件上传用 `multipart/form-data`（`curl -F file=@路径`），**不要**同时加 JSON 的 Content-Type。
- 路径按**模块**，不要发明短路径：覆盖率是 `/v1/planner/classes/{id}/coverage`。
- 列表班级用 `GET /v1/classes`，返回 `{ "data": [ { "id", "name" } ] }`。这里的 `id` 就是 `gibbonCourseClassID`。
- 日期 `YYYY-MM-DD`。时间 `HH:MM:SS`（`HH:MM` 服务端会补 `:00`）。
- ID 按响应里的字符串原样回传（Gibbon 常带前导零）。
- 创建成功 **201**，删除成功 **204** 无 body。deploy、copy-forward、行为 follow-up、报销审批记录也创建资源，返回 **201**；不创建资源的动作（copy-back、smart-blockify、重置密码、标记已付、零用金 action）以及**记分册回复文件 POST**（上传/替换）返回 **200**。
- 下载回复文件：同路径 GET，响应是**文件字节**不是 JSON。用 `curl -o 文件` 保存；不要按 JSON 解析，也不要加 `Content-Type: application/json`。
- 未提供密码时，创建/重置人员会生成随机密码，只在该次响应出现 `generatedPassword`。
- **Windows Git Bash 坑**：`curl -d` 内联 JSON 里带中文会被弄坏，服务端当成空 body 报 422。把 JSON 写进临时文件，用 `-d @文件` 发送。

## 写操作：先报告，确认后才执行

GET 直接执行。任何 **POST/PATCH/PUT/DELETE 之前必须停下出确认报告**，用户明确说"确认/可以/继续"后才动手：

1. 一句话概括要达成什么。
2. 每个写操作一行：**动作 + 对象 + 关键字段值**。例：
   `- 创建课格：课表 2627ALL / 秋季学期 D1 / P1 08:10-08:25 / 班级 PE Y01-Y06 / 教室 体育馆`
3. **整批一次确认**：多步工作流（如建整张课表）列成一份完整清单，用户确认一次后连续执行完，中途不再打扰。
4. **破坏性操作二次确认**：删除任何资源、单元 copy-back（清空单元原有块）、删记分册栏目（连带全部给分）、删除记分册回复文件（磁盘文件一并删除）、重置密码等不可逆操作，即使已包含在确认过的清单里，执行到那一步也要**再单独确认一次**。报告中把这类操作单列在 `⚠️ 破坏性操作` 区块，写清后果。
5. 用户没确认前，一个写请求都不发。

执行中任何一步失败：**立即停止**，报告失败步骤与响应里的 `error` 原文、已完成的改动清单、未执行的剩余清单，等用户指示。不要擅自改参数重试。

## 错误

失败时 JSON 含 `error`、`status`。路由不存在的 404 总带 `type`、`path`、`method`、`hint`，按 `hint` 改路径，不要猜测。405 带 `allowed`（该路径允许的方法）；部分错误带 `details`。

常见状态：`401` 令牌无效/过期/被吊销/锁定角色已移除、`403` 角色/班级范围不够、`404` 路径或资源不存在、`405` 方法不允许、`413` 上传文件超过服务器限制、`422` 字段校验失败、`429` 超限（按令牌每分钟，额度由学校设置）、`503` API 关闭。

## 仍然没有的接口

不要尝试：未来出勤、Ad Hoc、学生自签、正式评估（Formal Assessment）、记分册权重/目标分/量规/复制栏目、记分册栏目「更多资料」附件、教案正文/智能块插文件、作业提交文件、学习成果的写入（教案详情会只读返回已有 `outcomes`）、教案讨论/访客、资源库、导入、OAuth、照片上传、角色权限矩阵、个人证件、收费计划/缴费人/学生账单/在线支付、报销发票文件、行为信、行为模式分析、Excel/PDF 导出。需要时说明接口没有，改走网页。

注意：教案创建/更新接受 `homeworkCrowdAssess*` 系列开关字段（同伴互评的可见性设置），但没有互评评分接口——只能设开关，不能代替学生互评。
