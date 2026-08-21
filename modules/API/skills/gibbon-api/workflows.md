# 课表与课程规划工作流

先完成 [SKILL.md](SKILL.md) 的 `.env` 和 `GET /v1/me`。按 `capabilities` 分支；403 时换对应角色的令牌。

以下所有工作流里的写操作都受 SKILL.md「写操作：先报告，确认后才执行」约束：先把整批写操作列成报告给用户确认，再开始执行。

Gibbon 里：**课表**决定「哪天哪节哪个班」；**教案**是该班在该日期时段上的 `gibbonPlannerEntry`。覆盖率 = 课表展开出的课格里，有多少已有教案。

---

## A. 确认身份与学年

1. `GET /v1/me` → 记下 `gibbonSchoolYearID`、`capabilities`。
2. `GET /v1/school-year` → `firstDay` / `lastDay`。
3. 需要班级 ID：`GET /v1/classes`，用返回的 `id` 作为 `gibbonCourseClassID`。

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

1. `POST /v1/planner/units` → `{ "gibbonCourseID":"...", "name":"单元名" }`
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

---

## F. 学期与特殊日

需 `school.structure`（具体是学期/特殊日管理权限）。

1. `GET /v1/school-years` 拿到学年 ID。
2. `POST /v1/terms` 建学期。`sequenceNumber` 全局唯一，可先 `GET /v1/terms?gibbonSchoolYearID=` 看已有序号再 +1。
3. 停课/调时/不上课表：`POST /v1/special-days`。先有学期。`School Closure` 当天不能点名。

---

## G. 点名

需对应 `attendance.*`。先 `GET /v1/attendance/codes`，`type` 用返回的 `name`。

1. 教学班：`GET /v1/attendance/classes/{classId}?date=YYYY-MM-DD` 看学生名单、`taken`（是否已点）和每人的默认 `type`，再 POST `records`。名单以外或当天有课格例外的人会被 422 拒绝。
2. 行政班：路径换成 `/v1/attendance/form-groups/{id}`。行政班 `attendance=N` 或不是你导师的班（无 `_all` 权限时）会直接 403。
3. 个人：`POST /v1/attendance/people/{gibbonPersonID}`。

不要给未来日期或停课日点名（会 422）。重复 POST 同一天是覆盖更新。不要改出勤代码、不要走报表接口。

---

## H. 记分册给分

需 `markbook.write`。

1. `GET /v1/grade-scales` 再 `GET /v1/grade-scales/{id}` 拿 `value`。
2. `POST /v1/markbook/classes/{classId}/columns` 建栏目（`type` 用 GET columns 返回的 `types`；学校没开 effort 就别传 `effort`，传了也会被清成 `N`）。
3. `GET /v1/markbook/columns/{id}/entries` 看学生（全班都在 `data` 里，没给分的字段为 `null`）。
4. `PUT /v1/markbook/columns/{id}/entries` 按学生 upsert 分数/努力/评语；`attainmentValue` 传空字符串即清除该生分数。

不要做权重、目标分、量规、正式评估。删栏目会连带删全部给分，先确认。

---

## 权限对照

| 网页能力（锁定角色） | API |
|---|---|
| Lesson Planner 编辑 | 教案 CRUD、作业提交 |
| Unit Planner | 单元、智能块、部署 |
| Timetable / Timetable Admin | 课表、作息、课格、日期 |
| Timetable Admin 课程/选课 | courses / classes / enrolment |
| School Admin 结构 | 年级组、学部、学院、行政班、场地、学年、学期、特殊日 |
| User Admin | 人员、角色、家庭、密码 |
| Admissions 入学名册 | student-enrolments |
| Students 医疗 | 人员医疗表 |
| Attendance 按班/行政班/个人点名 | attendance.* |
| Markbook 编辑 | 记分册栏目与给分 |

令牌锁定角色后，即使用户网页能切换其它角色，API 也只用锁定的那一个。
