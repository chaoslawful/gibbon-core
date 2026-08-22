---
name: gibbon-api
description: >-
  通过 Gibbon Agent REST API 读写课表、课程规划、学校结构、人员、出勤、记分册、行为记录与财务支出。
  仅在用户明确要求使用 gibbon-api skill、按该 REST API 操作 Gibbon、或安装本 skill 后点名操作课表/课程规划时使用。
disable-model-invocation: true
---

# Gibbon Agent REST API

用个人访问令牌（PAT）调用 Gibbon REST。权限等于令牌所有者 + 创建时锁定的那一个角色。正式评估、学习成果写入、教案讨论不在范围内。

完整接口见 [reference.md](reference.md)。工作流见 [workflows.md](workflows.md)。

## 安装（给其它 agent）

把**本目录**（含 `SKILL.md`）完整拷到目标产品的 skills 目录，文件夹名保持 `gibbon-api`。

然后：

1. 复制 `.env.example` 为 `.env`
2. 填写 `GIBBON_API_BASE` 和 `GIBBON_API_TOKEN`
3. 在 Gibbon 网页 **API → Manage API Tokens** 创建令牌（明文只显示一次，前缀 `gib_pat_`）
4. 教师写教案、点名、记分册，排课管理员改课表，学校管理员改结构/学期，用户管理员改人员，财务管报销/预算，通常需要**不同角色的令牌**，不要混用

`.env` 只放本机，不要写入 skill 正文或 git。

## 每次会话开始

1. 在 **本 skill 目录** 读 `.env`。没有就复制 `.env.example`，停下来问用户要 base 和 token。
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
   - `attendance.class` / `attendance.formGroup` / `attendance.person`：按教学班 / 行政班 / 个人点名
   - `markbook.write` / `markbook.editAllClasses`：记分册栏目与给分

## 请求约定

- 除 `GET /v1/openapi.json` 外，所有接口都要 Bearer。
- JSON 请求带 `Content-Type: application/json`。
- 路径按**模块**，不要发明短路径：覆盖率是 `/v1/planner/classes/{id}/coverage`。
- 列表班级用 `GET /v1/classes`，返回 `{ "data": [ { "id", "name" } ] }`。这里的 `id` 就是 `gibbonCourseClassID`。
- 日期 `YYYY-MM-DD`。时间 `HH:MM:SS`（`HH:MM` 服务端会补 `:00`）。
- ID 按响应里的字符串原样回传（Gibbon 常带前导零）。
- 创建成功 **201**，删除成功 **204** 无 body。动作类接口（deploy、copy-back、smart-blockify、重置密码等）不是创建，返回 **200**。
- 未提供密码时，创建/重置人员会生成随机密码，只在该次响应出现 `generatedPassword`。
- **Windows Git Bash 坑**：`curl -d` 内联 JSON 里带中文会被弄坏，服务端当成空 body 报 422。把 JSON 写进临时文件，用 `-d @文件` 发送。

## 写操作：先报告，确认后才执行

GET 直接执行。任何 **POST/PATCH/PUT/DELETE 之前必须停下出确认报告**，用户明确说"确认/可以/继续"后才动手：

1. 一句话概括要达成什么。
2. 每个写操作一行：**动作 + 对象 + 关键字段值**。例：
   `- 创建课格：课表 2627ALL / 秋季学期 D1 / P1 08:10-08:25 / 班级 PE Y01-Y06 / 教室 体育馆`
3. **整批一次确认**：多步工作流（如建整张课表）列成一份完整清单，用户确认一次后连续执行完，中途不再打扰。
4. **破坏性操作二次确认**：删除任何资源、单元 copy-back（清空单元原有块）、删记分册栏目（连带全部给分）、重置密码等不可逆操作，即使已包含在确认过的清单里，执行到那一步也要**再单独确认一次**。报告中把这类操作单列在 `⚠️ 破坏性操作` 区块，写清后果。
5. 用户没确认前，一个写请求都不发。

执行中任何一步失败：**立即停止**，报告失败步骤与响应里的 `error` 原文、已完成的改动清单、未执行的剩余清单，等用户指示。不要擅自改参数重试。

## 错误

失败时 JSON 含 `error`、`status`。404 可能带 `path`、`method`、`hint`。按 `hint` 改路径，不要猜测。405 带 `allowed`（该路径允许的方法）；部分错误带 `details`。

常见状态：`401` 令牌无效/过期/被吊销/锁定角色已移除、`403` 角色/班级范围不够、`404` 路径或资源不存在、`405` 方法不允许、`422` 字段校验失败、`429` 超限（按令牌每分钟，额度由学校设置）、`503` API 关闭。

## 仍然没有的接口

不要尝试：未来出勤、Ad Hoc、学生自签、正式评估（Formal Assessment）、记分册权重/目标分/量规/复制栏目、学习成果的写入（教案详情会只读返回已有 `outcomes`）、教案讨论/访客、资源库、导入、OAuth、照片上传、角色权限矩阵、个人证件、收费计划/缴费人/学生账单/在线支付、报销发票文件、行为信、行为模式分析、Excel/PDF 导出。需要时说明接口没有，改走网页。

注意：教案创建/更新接受 `homeworkCrowdAssess*` 系列开关字段（同伴互评的可见性设置），但没有互评评分接口——只能设开关，不能代替学生互评。
