---
name: gibbon-api
description: >-
  通过 Gibbon Agent REST API 读写课表、课程规划、学校结构、人员、出勤与记分册。
  仅在用户明确要求使用 gibbon-api skill、按该 REST API 操作 Gibbon、或安装本 skill 后点名操作课表/课程规划时使用。
disable-model-invocation: true
---

# Gibbon Agent REST API

用个人访问令牌（PAT）调用 Gibbon REST。权限等于令牌所有者 + 创建时锁定的那一个角色。正式评估、出勤报表、学习成果、教案讨论不在范围内。

完整接口见 [reference.md](reference.md)。工作流见 [workflows.md](workflows.md)。

## 安装（给其它 agent）

把**本目录**（含 `SKILL.md`）完整拷到目标产品的 skills 目录，文件夹名保持 `gibbon-api`。

然后：

1. 复制 `.env.example` 为 `.env`
2. 填写 `GIBBON_API_BASE` 和 `GIBBON_API_TOKEN`
3. 在 Gibbon 网页 **API → Manage API Tokens** 创建令牌（明文只显示一次，前缀 `gib_pat_`）
4. 教师写教案、点名、记分册，排课管理员改课表，学校管理员改结构/学期，用户管理员改人员，通常需要**不同角色的令牌**，不要混用

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
- 创建成功 **201**，删除成功 **204** 无 body。
- 未提供密码时，创建/重置人员会生成随机密码，只在该次响应出现 `generatedPassword`。

## 错误

失败时 JSON 含 `error`、`status`。404 可能带 `path`、`method`、`hint`。按 `hint` 改路径，不要猜测。

常见状态：`401` 令牌无效、`403` 角色/班级范围不够、`404` 路径或资源不存在、`422` 字段校验失败、`429` 超限、`503` API 关闭。

## 仍然没有的接口

不要尝试：出勤报表、未来出勤、Ad Hoc、学生自签、出勤代码增删改、正式评估（Formal Assessment）、Crowd Assessment、记分册权重/目标分/量规/复制栏目、学习成果、教案讨论/访客、资源库、报告、导入、OAuth、照片上传、角色权限矩阵、个人证件。需要时说明接口没有，改走网页。
