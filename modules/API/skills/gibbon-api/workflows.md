# 课表与课程规划工作流

先完成 [SKILL.md](SKILL.md) 的 `.env` 和 `GET /v1/me`。按 `capabilities` 分支；403 时换对应角色的令牌。

以下所有工作流里的写操作都受 SKILL.md「写操作：先报告，确认后才执行」约束：先把整批写操作列成报告给用户确认，再开始执行。

Gibbon 里：**课表**决定「哪天哪节哪个班」；**教案**是该班在该日期时段上的 `gibbonPlannerEntry`。覆盖率 = 课表展开出的课格里，有多少已有教案。

---

## A. 确认身份与学年

1. `GET /v1/me` → 记下 `gibbonSchoolYearID`、`capabilities`。
2. `GET /v1/school-year` → `firstDay` / `lastDay`。
3. 需要班级 ID：`GET /v1/classes`，用返回的 `id` 作为 `gibbonCourseClassID`，`gibbonCourseID` 建单元时用（1.3.05+）。

---

## B. 从零建一张课表

需 `timetable.write`。年级组 ID 来自 `GET /v1/year-groups`（通常要学校管理员令牌）。

1. `POST /v1/timetable-columns` → `{ "name":"标准日", "nameShort":"STD" }`，记下 `gibbonTTColumnID`。
2. 给模板加节：`POST /v1/timetable-columns/{id}/rows`

```json
{ "name":"Period 1", "nameShort":"P1", "timeStart":"09:00:00", "timeEnd":"09:45:00", "type":"Lesson" }
```

3. `POST /v1/timetables` → `{ "name":"...", "nameShort":"...", "gibbonYearGroupIDList":"001,002" }`，记下 `gibbonTTID`。
4. `POST /v1/timetables/{id}/days` → `{ "name":"Day 1", "nameShort":"D1", "gibbonTTColumnID":"..." }`。
5. 把日历日期绑到课表日：`POST /v1/timetables/{id}/dates` → `{ "gibbonTTDayID":"...", "date":"2026-08-20" }`。可按学期逐日或按循环重复；同一日期已被占用会 422。
6. 需要课程/班：`POST /v1/courses` 再 `POST /v1/courses/{id}/classes`（`timetable.courses`）。
7. 排班：`POST /v1/timetables/{ttId}/days/{dayId}/slots`。教室 `GET /v1/spaces`；节次 ID 用 `GET /v1/timetables/{id}/days/{dayId}/rows` 查（含每节已排班数 `classCount`）。
8. 某学生不上这节：`POST /v1/timetable-slots/{slotId}/exceptions` → `{ "gibbonPersonID":"..." }`。

改课格：`PATCH /v1/timetable-slots/{gibbonTTDayRowClassID}`。删课格不会自动删教案；但**删作息模板会连带删其全部节次**，删课表日前想清楚。

---

## C. 课程规划：查缺教案 / 按课格补

1. `GET /v1/planner/classes/{classId}/coverage?from=&to=` → 返回 `expected`（课格总数）、`filled`、`missing`、`coverageRate`、`missingSlots`。
2. 对 `missingSlots` 逐条 `POST /v1/planner/lessons`，**时间必须与课格一致**。
3. 再打 coverage 核对 `missing` 是否下降。

不要为没有课表课格的日期随意建教案，除非用户明确要求「额外加一节」。

---

## D. 单元：写块并部署到班

需 `planner.units`。

1. 先拿课程 ID：`GET /v1/classes` 找到目标班的 `gibbonCourseID`（旧实例没有该字段时，枚举 `GET /v1/courses` 再 `GET /v1/courses/{id}/classes` 直到匹配班级 `id`）。然后 `POST /v1/planner/units` → `{ "gibbonCourseID":"...", "name":"单元名" }`
2. `POST /v1/planner/units/{id}/blocks` → `{ "title":"块标题", "contents":"...", "length":"45" }`
3. 只挂班、还不生成教案：`POST /v1/planner/units/{id}/classes` → `{ "gibbonCourseClassID":"...", "running":"Y" }`
4. 按课表日期生成教案：先用 coverage/slots 拿到该班时段，再 `POST /v1/planner/units/{id}/deploy`，`lessons[].blocks` 用智能块 ID。没给 `name` 的课自动叫「单元名 N」；deploy 出的教案 `viewableStudents`/`viewableParents` 默认 `N`（与直建教案相反），要对学生可见就显式传 `Y`。
5. 复制到下学年课程：`POST /v1/planner/units/{id}/copy-forward` → `{ "gibbonCourseID":"目标课程" }`（连同块一起复制）
6. 工作副本改完拷回：`POST /v1/planner/unit-classes/{gibbonUnitClassID}/copy-back` —— **会清空单元原有全部块再覆盖，先跟用户确认**
7. 从已有教案生成块：`POST /v1/planner/units/{id}/smart-blockify` → `{ "gibbonPlannerEntryID":"..." }`（追加，不动已有块）

教师改作业提交：`GET/POST /v1/planner/lessons/{id}/homework`。

---

## E. 人员与行政班

建学生（`user.admin` + 入学字段）：

```json
{
  "surname": "Li",
  "firstName": "Ming",
  "preferredName": "Ming",
  "officialName": "Li Ming",
  "gender": "M",
  "username": "liming",
  "gibbonRoleIDPrimary": "003",
  "studentRecord": "Y",
  "gibbonYearGroupID": "001",
  "gibbonFormGroupID": "00001"
}
```

已有人员再编入行政班：`POST /v1/people/{id}/enrolment`。只把人放进课班：`POST /v1/classes/{id}/enrolment`。

查教师/教职工（`staff.read`）：`GET /v1/staff`，`type=Teaching` 只看教学人员。按人查：`GET /v1/staff?gibbonPersonID=`。已有人员补建档案（`staff.write`）：`POST /v1/staff`，必填 `gibbonPersonID`、`type`（`Teaching`/`Support`），该人必须已有 Staff 角色且还没有 staff 记录。

---

## F. 学期与特殊日

需 `school.structure`（学期/特殊日仍要对应网页权限；学年 CRUD 是 `网页: schoolYear_manage`，不在 `school.structure` 里）。

1. 当前学年 ID：`GET /v1/school-year` 或 `/v1/me`。列出全部学年才用 `GET /v1/school-years`。
2. `POST /v1/terms` 建学期。`sequenceNumber` 全局唯一，可先 `GET /v1/terms?gibbonSchoolYearID=` 看已有序号再 +1。
3. 停课/调时/不上课表：`POST /v1/special-days`。先有学期。`School Closure` 当天不能点名。

---

## G. 点名

需对应 `attendance.*`。先 `GET /v1/attendance/codes`，`type` 用返回的 `name`。

1. 教学班：`GET /v1/attendance/classes/{classId}?date=YYYY-MM-DD` 看学生名单、`taken`（是否已点）和每人的默认 `type`，再 POST `records`。名单以外或当天有课格例外的人会被 422 拒绝。
2. 行政班：路径换成 `/v1/attendance/form-groups/{id}`。行政班 `attendance=N` 或不是你导师的班（无 `_all` 权限时）会直接 403；有 `_all` 权限时不查 `attendance` 标志，`attendance=N` 的班也能点。
3. 个人：`POST /v1/attendance/people/{gibbonPersonID}`。

不要给未来日期或停课日点名（会 422）。重复 POST 同一天是覆盖更新。改出勤代码用学校管理员令牌：`POST/PATCH/DELETE /v1/attendance/codes`；内置 `Core` 代码不能删。

历史与报表（只读）：

1. 某个学生：`GET /v1/attendance/reports/student-history?gibbonPersonID=`
2. 连续缺勤：`GET /v1/attendance/reports/consecutive-absences?numberOfSchoolDays=7`
3. 某天谁没来/不在校/不在课上：`not-present` / `not-onsite` / `not-in-class`，都要 `date=`
4. 哪些班还没点名：`form-groups-not-registered`、`classes-not-registered`
5. 按类型统计：`GET /v1/attendance/reports/trends`（JSON 数列，不是图）

---

## H. 记分册给分与回复文件

需 `markbook.write`。回复文件还要学校 API 模块 ≥ 1.3.04。

1. `GET /v1/grade-scales` 再 `GET /v1/grade-scales/{id}` 拿 `value`。
2. `POST /v1/markbook/classes/{classId}/columns` 建栏目（`type` 用 GET columns 返回的 `types`；学校没开 effort 就别传 `effort`，传了也会被清成 `N`）。要上传回复文件时设 `uploadedResponse=Y`（默认 `N`）。已有栏目用 `PATCH /v1/markbook/columns/{id}` 把 `uploadedResponse` 改成 `Y`。
3. `GET /v1/markbook/columns/{id}/entries` 看学生（全班都在 `data` 里，没给分的字段为 `null`；`response.present` 表示是否已有回复文件）。
4. `PUT /v1/markbook/columns/{id}/entries` 按学生 upsert 分数/努力/评语；`attainmentValue` 传空字符串即清除该生分数。给分不会动回复文件。该生必须先有 entry，才能传文件。
5. 上传回复（multipart，不要带 JSON Content-Type）：

```bash
curl -sS -X POST \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  -F "file=@./feedback.pdf" \
  "$GIBBON_API_BASE/v1/markbook/columns/{id}/entries/{studentId}/response"
```

核对：再 GET entries，看该生 `response.present`。下载用同路径 GET 加 `-o`（响应是文件不是 JSON）。删除用 DELETE（会删磁盘文件，属破坏性操作，须二次确认）。

不要做权重、目标分、量规、正式评估、栏目「更多资料」附件。删栏目会连带删全部给分，先确认。

---

## I. 报销与预算

需对应 `finance.*`。账单、缴费、在线支付没有接口，不要尝试。

1. `GET /v1/finance/budget-cycles` 拿周期 ID。列出该周期费用：`GET /v1/finance/expenses?gibbonFinanceBudgetCycleID=`，可选 `status`、`gibbonFinanceBudgetID`。需要收费目录时：`GET /v1/finance/fee-categories`、`GET /v1/finance/fees?gibbonSchoolYearID=`。
2. 给该周期各预算科目额度：`PUT /v1/finance/budget-cycles/{id}/allocations`，或先 `GET .../allocations` 看现有科目。
3. 提交：`POST /v1/finance/expenses`（`gibbonFinanceBudgetCycleID`、预算、标题、金额、`purchaseBy`=`School`/`Self`、`countAgainstBudget`）。
4. 审批：`POST /v1/finance/expenses/{id}/approvals`，`decision`=`approve`/`reject`/`comment`。不要自己改 `status`。成功是 **201**，但要以返回的 `expense.status` 为准——仍是 `Requested` 表示只过了预算关或学校关的一部分。学校财务的 `expenseApprovalType`（One Of / Two Of / Chain Of All）和是否先要预算负责人批，决定要几个人、按什么顺序；不是这一轮的人 `approve` 会 403。详见 [reference.md](reference.md)「财务」节。
5. 打印用 `GET /v1/finance/expenses/{id}/print`（JSON）。标已付：`POST .../reimburse`。
6. 零用金：`POST /v1/finance/petty-cash`，需要还款/退款时再 `POST .../action`。

---

## J. 学生行为记录

需 `behaviour.write`。

1. `GET /v1/behaviour` 可加 `gibbonSchoolYearID`、`type=Positive|Negative|Observation`。
2. 单人：`POST /v1/behaviour`。多人同一事件用 `gibbonPersonIDs`。
3. 跟进：`POST /v1/behaviour/{id}/follow-up`。需要进学生备注时创建时带 `"copyToNotes": "Y"`。
4. 不要做行为信、模式分析。

---

## 权限对照

| 网页能力（锁定角色） | API |
|---|---|
| Lesson Planner 编辑 | 教案 CRUD、作业提交 |
| Unit Planner | 单元、智能块、部署 |
| Timetable / Timetable Admin | 课表、作息、课格、日期 |
| Timetable Admin 课程/选课 | courses / classes / enrolment |
| School Admin 结构 | 年级组、学部、学院、行政班、场地、学期、特殊日（学年 CRUD 另需 `网页: schoolYear_manage`） |
| User Admin | 人员（`user.admin`）；角色/家庭为 `网页: role_manage` / `family_manage` |
| Admissions 入学名册 | student-enrolments |
| Students 医疗 | 人员医疗表 |
| Attendance 按班/行政班/个人点名 | attendance.* |
| School Admin 出勤设置 | attendance.codes |
| Attendance 报表 | attendance.reports |
| Markbook 编辑 | 记分册栏目、给分、学生回复文件 |
| Finance 报销/预算/零用金/费用目录 | finance.expenses / finance.budgets / finance.pettyCash / finance.fees |
| Behaviour 管理记录 | behaviour.write |

令牌锁定角色后，即使用户网页能切换其它角色，API 也只用锁定的那一个。
