# 接口一览（与当前代码一致）

Base：`$GIBBON_API_BASE`（例 `http://localhost/api.php`）。路径均以 `/v1` 开头。未特别说明时需要 Bearer。

权限列的是 `GET /v1/me` 里的 `capabilities` 或对应网页模块。列表多返回 `{ "data": [ ... ] }`。

## 元数据

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/v1/openapi.json` | 无 | OpenAPI 3.0.3 |
| GET | `/v1/me` | 令牌有效 | 身份、锁定角色、`capabilities`、学年 |
| GET | `/v1/school-year` | 令牌有效 | 当前学年首末日 |

`/v1/me` 响应：`gibbonPersonID`、`username`、`preferredName`、`surname`、`gibbonRoleID`（锁定角色）、`roleName`、`roleCategory`、`gibbonSchoolYearID`、`token`（令牌 ID/名称/类型/过期时间）、`capabilities`。

## 学校结构

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/year-groups` | School Admin `yearGroup_manage` |
| PATCH/DELETE | `/v1/year-groups/{id}` | 同上 |
| GET/POST | `/v1/departments` | `department_manage` |
| PATCH/DELETE | `/v1/departments/{id}` | 同上 |
| GET/POST | `/v1/houses` | `house_manage` |
| PATCH/DELETE | `/v1/houses/{id}` | 同上 |
| GET/POST | `/v1/form-groups` | `formGroup_manage`；GET **必填** `gibbonSchoolYearID` |
| PATCH/DELETE | `/v1/form-groups/{id}` | 同上 |
| GET | `/v1/spaces` | `timetable.write`（排课用的场地列表） |
| POST | `/v1/spaces` | `space_manage` |
| PATCH/DELETE | `/v1/spaces/{id}` | `space_manage` |
| GET/POST | `/v1/school-years` | `schoolYear_manage` |
| PATCH/DELETE | `/v1/school-years/{id}` | 同上 |

学年创建必填：`name`、`status`（`Past`/`Current`/`Upcoming`）、`sequenceNumber`、`firstDay`、`lastDay`。

年级组创建必填：`name`、`nameShort`、`sequenceNumber`；可选 `gibbonPersonIDHOY`（年级主任）。学部创建必填：`name`、`nameShort`；`type` 默认 `Learning Area`。学院创建必填：`name`、`nameShort`。

## 学期与特殊日

特殊日必须挂在某个学期上（`gibbonSchoolYearTermID`）。`sequenceNumber` 在**全部学期**里唯一，不只是当前学年。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/terms` | `schoolYearTerm_manage`；GET **必填** `gibbonSchoolYearID` |
| PATCH/DELETE | `/v1/terms/{id}` | 同上 |
| GET/POST | `/v1/special-days` | `schoolYearSpecialDay_manage`；GET 需 `gibbonSchoolYearID` 或 `gibbonSchoolYearTermID` 或 `from`+`to` |
| PATCH/DELETE | `/v1/special-days/{id}` | 同上 |

学期创建必填：`gibbonSchoolYearID`、`name`、`nameShort`、`sequenceNumber`、`firstDay`、`lastDay`。`firstDay` 必须不晚于 `lastDay`，否则 422。

特殊日创建必填：`date`、`type`、`name`、`gibbonSchoolYearTermID`。`type` 只能是 `School Closure`、`Timing Change`、`Off Timetable`。日期必须落在该学期首末日内，且全校一天只能有一条特殊日。可带 `description`。

Timing Change 可带 `schoolOpen` / `schoolStart` / `schoolEnd` / `schoolClose`（`HH:MM` 或 `HH:MM:SS`）。Off Timetable 可带 `gibbonYearGroupIDList`、`gibbonFormGroupIDList`（数组或逗号串）以及 `cancelActivities` / `cancelDuty` / `cancelBookings` / `cancelClasses`（`Y`/`N`，默认全 `N`）；给了年级组或行政班列表时会自动补 `context`（`Year Group` / `Form Group`）。

行政班创建必填：`gibbonSchoolYearID`、`name`、`nameShort`。导师字段是 `gibbonPersonIDTutor` / `gibbonPersonIDTutor2` / `gibbonPersonIDTutor3`，教助是 `gibbonPersonIDEA` / `gibbonPersonIDEA2` / `gibbonPersonIDEA3`，还可带 `gibbonSpaceID`、`website`（默认空）、`attendance`（默认 `Y`，为 `N` 时该班不能点名）。

场地创建必填 `name`。设备标志默认 `N`，`type` 默认 `Classroom`，`active` 默认 `Y`，`bookable` 默认 `N`。

## 课表

作息模板（`gibbonTTColumn`）是独立资源，课表日绑定一份模板。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/timetables` | 读 `timetable.read`；写 `timetable.write` |
| GET/PATCH/DELETE | `/v1/timetables/{id}` | 同上 |
| GET/POST | `/v1/timetable-columns` | 读/写同上 |
| GET/PATCH/DELETE | `/v1/timetable-columns/{id}` | 同上；GET 含 `rows` |
| POST | `/v1/timetable-columns/{id}/rows` | 写 |
| PATCH/DELETE | `/v1/timetable-column-rows/{id}` | 写 |
| GET/POST | `/v1/timetables/{id}/days` | 读/写 |
| PATCH/DELETE | `/v1/timetables/{id}/days/{dayId}` | 写 |
| GET | `/v1/timetables/{id}/days/{dayId}/rows` | 读；该日节次，含每节 `classCount` |
| GET/POST | `/v1/timetables/{id}/days/{dayId}/slots` | 读/写课格 |
| GET/POST | `/v1/timetables/{id}/dates` | 读/写日期映射 |
| DELETE | `/v1/timetable-dates/{id}` | 写 |
| PATCH/DELETE | `/v1/timetable-slots/{id}` | 写 |
| GET/POST | `/v1/timetable-slots/{id}/exceptions` | 读/写（某人不上这节） |
| DELETE | `/v1/timetable-slot-exceptions/{id}` | 写 |

课表创建必填 `name`、`nameShort`。`gibbonSchoolYearID` 可省，默认当前学年；`active` 默认 `Y`。`nameShortDisplay` 只能是 `Day Of The Week` 或 `Timetable Day Short Name`（默认前者）。`gibbonYearGroupIDList` 为年级组 ID 逗号串（或数组）。GET 列表可带 `gibbonSchoolYearID` 参数（默认当前学年），返回行里年级组字段叫 `yearGroups`。

课表日必填 `name`、`nameShort`、`gibbonTTColumnID`。`color`/`fontColor` 默认 `#ffffff` / `#000000`。

节次必填 `name`、`nameShort`、`timeStart`、`timeEnd`。`type` 默认 `Lesson`，枚举：`Lesson`、`Pastoral`、`Sport`、`Break`、`Service`、`Other`。**删除作息模板会连带删掉它的全部节次。**

日期映射 POST：`{ "gibbonTTDayID":"...", "date":"YYYY-MM-DD" }`。同一日期已被占用会 422。GET 的 `from`/`to` 可省，默认当前学年首末日。

课格 POST：`gibbonTTColumnRowID`、`gibbonCourseClassID`；可选 `gibbonSpaceID`。节次必须属于该课表日，否则 422。PATCH 可整体移动课格（换日/换节/换班），`gibbonSpaceID` 传空字符串即清除教室。

## 课程、班级、选课

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/courses`、`/v1/classes` | 能看教案或课表；返回精简 `{id,name}`，可带 `gibbonSchoolYearID`（默认当前学年）；无全校权限时只列自己任教/就读的 |
| POST | `/v1/courses` | `timetable.courses` |
| GET/PATCH/DELETE | `/v1/courses/{id}` | 同上；详情是完整课程行 |
| GET/POST | `/v1/courses/{id}/classes` | 同上 |
| PATCH/DELETE | `/v1/classes/{id}` | 同上 |
| GET/POST | `/v1/classes/{id}/enrolment` | `timetable.enrolment` |
| PATCH/DELETE | `/v1/enrolment/{id}` | 同上 |

课程必填 `name`、`nameShort`；`gibbonSchoolYearID` 默认当前学年。班级必填 `name`、`nameShort`，`reportable`/`attendance` 默认 `Y`。选课 POST 必填 `gibbonPersonID`；`role` 默认 `Student`，`reportable` 默认 `Y`。

## 人员、角色、家庭、医疗、入学名册

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/people` | `user.admin`；GET 可 `q`、`limit` |
| GET/PATCH/DELETE | `/v1/people/{id}` | 同上；响应不含密码哈希 |
| POST | `/v1/people/{id}/password` | 重置；可省略 `password` 则生成 |
| GET/POST | `/v1/people/{id}/enrolment` | Admissions `studentEnrolment_manage` |
| GET | `/v1/student-enrolments` | 同上；`gibbonSchoolYearID`、`gibbonFormGroupID`、`q` |
| PATCH/DELETE | `/v1/student-enrolments/{id}` | 同上 |
| GET/POST | `/v1/roles` | User Admin `role_manage` |
| PATCH/DELETE | `/v1/roles/{id}` | 同上 |
| GET/POST | `/v1/families` | `family_manage` |
| GET/PATCH/DELETE | `/v1/families/{id}` | 同上；GET 含 adults/children |
| POST | `/v1/families/{id}/adults`、`/children` | 同上 |
| DELETE | `/v1/family-adults/{id}`、`/v1/family-children/{id}` | 同上 |
| GET/POST | `/v1/medical-conditions` | School Admin `medicalConditions_manage` |
| PATCH/DELETE | `/v1/medical-conditions/{id}` | 同上 |
| GET/PUT | `/v1/people/{id}/medical` | Students `medicalForm_manage` |
| POST | `/v1/people/{id}/medical-conditions` | 同上 |
| DELETE | `/v1/person-medical-conditions/{id}` | 同上 |

创建人员必填：`surname`、`firstName`、`preferredName`、`officialName`、`gender`、`username`、`gibbonRoleIDPrimary`。可选 `password`；没有则响应带 `generatedPassword`（只此一次）。密码必须过学校密码策略，否则 422；`username` 已占用也 422。`staffRecord=Y` 同时建教职工（`staffType` 默认 `Teaching`）；`studentRecord=Y` 需同时给 `gibbonYearGroupID`、`gibbonFormGroupID`。

入学名册必填 `gibbonYearGroupID`、`gibbonFormGroupID`；`gibbonSchoolYearID` 默认当前学年，同一同学年重复入学会 422。可选 `autoEnrolStudent=Y` 按行政班自动选课。

家庭创建必填只有 `name`；`status` 默认 `Married`。加家庭成员（adults/children）必填 `gibbonPersonID`；adult 的 `contactPriority` 默认 `1`，`childDataAccess`/`contactCall`/`contactEmail` 默认 `Y`，`contactSMS`/`contactMail` 默认 `N`。

角色创建必填 `category`、`name`、`nameShort`；`type` 默认 `Additional`，`canLoginRole` 默认 `Y`。

个人医疗：必须先 `PUT /v1/people/{id}/medical` 建医疗表（字段 `longTermMedication`/`longTermMedicationDetails`/`comment`），才能 `POST medical-conditions` 加状况（必填 `name`，可选 `gibbonAlertLevelID`、`triggers`、`reaction`、`response`、`medication`、`lastEpisode` 等），否则 422。医疗状况字典（`/v1/medical-conditions`）创建必填 `name`。

## 教案与单元

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/planner/lessons` | 读/写教案 |
| GET/PATCH/DELETE | `/v1/planner/lessons/{id}` | 能看/改该班 |
| GET | `/v1/planner/classes/{classId}/slots` | 课格+已有教案 |
| GET | `/v1/planner/classes/{classId}/coverage` | 覆盖率 |
| GET | `/v1/planner/units` | **必填** `gibbonCourseID` |
| POST | `/v1/planner/units` | `planner.units` |
| GET/PATCH/DELETE | `/v1/planner/units/{id}` | 同上；GET 含 blocks、classes |
| POST | `/v1/planner/units/{id}/blocks` | 智能块 |
| PATCH/DELETE | `/v1/planner/unit-blocks/{id}` | 同上 |
| POST | `/v1/planner/units/{id}/classes` | 挂到班（只建 `gibbonUnitClass`） |
| POST | `/v1/planner/units/{id}/deploy` | 按课表时段生成教案并挂块 |
| POST | `/v1/planner/units/{id}/copy-forward` | body：`gibbonCourseID` |
| POST | `/v1/planner/unit-classes/{id}/copy-back` | 工作副本拷回单元 |
| POST | `/v1/planner/units/{id}/smart-blockify` | body：`gibbonPlannerEntryID` |
| GET/POST | `/v1/planner/lessons/{id}/homework` | 教师端提交记录 |
| PATCH/DELETE | `/v1/planner/homework/{id}` | 同上 |

教案创建必填：`gibbonCourseClassID`、`date`、`timeStart`、`timeEnd`、`name`（超过 50 字会被截断，不报错）。`homework=Y` 时必须有 `homeworkDetails`、`homeworkDueDateTime`。

GET `/v1/planner/lessons` 可带 `gibbonSchoolYearID`（默认当前学年）、`gibbonCourseClassID`、`from`、`to`；不带班级时只列自己相关的班。GET 单个教案会附带 `homeworkSubmissions`、`smartBlocks`、`outcomes`（均只读）。直建教案 `viewableStudents` 默认 `Y`、`viewableParents` 默认 `N`（注意与 deploy 的默认值不同）。

单元创建必填 `gibbonCourseID`、`name`。智能块创建只必填 `title`，可选 `type`、`length`、`contents`、`teachersNotes`、`sequenceNumber`。挂班（`units/{id}/classes`）是幂等的：已挂过就返回原记录，传 `running=Y` 可顺带激活。

部署 JSON：

```json
{
  "gibbonCourseClassID": "00000007",
  "viewableStudents": "N",
  "viewableParents": "N",
  "lessonNameReplace": "N",
  "lessons": [
    {
      "date": "2026-08-20",
      "timeStart": "09:00:00",
      "timeEnd": "09:45:00",
      "name": "可选",
      "blocks": ["00000001", {"gibbonUnitBlockID": "00000002"}]
    }
  ]
}
```

作业提交 POST 必填 `gibbonPersonID`。`type` 默认 `File`（`File`/`Link`），`status` 默认 `On Time`，`version` 默认 `Final`，`count` 默认 `1`。

deploy 注意：未给 `name` 的课会自动命名「单元名 N」；`running` 默认 `Y`；`blocks` 里引用不存在的块 ID 会 404 且整批失败。copy-forward 连同单元块一起复制。**copy-back 会先清空单元原有的全部块**，再用该班工作副本的块覆盖——不可逆，执行前向用户确认。smart-blockify 是追加（把教案的工作块拷进单元，不动已有块）。

## 出勤

点名日期不能是未来，且必须是开学日（学期内、校历教学日、当天不是 School Closure），违反任一都是 422。`type` 用出勤代码的 **name**（如 `Present`），必须是启用中的代码，先 `GET /v1/attendance/codes`。

GET 点名表返回学生名单（含每人当前 `type`，未点过则为学校默认代码）、`taken`（是否已点过）、`defaultType`。POST 的 `records` 里只允许当天实际在该班/该行政班的学生（教学班还会排除课格例外名单里的人），否则 422。学校若开了 `recordFirstClassAsSchool`，当天第一节班级点名会同步写一条校级（Person 上下文）出勤。重复点名是覆盖更新，不是追加。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/attendance/codes` | 任一出勤点名权限 |
| GET/POST | `/v1/attendance/classes/{id}` | `attendance.class`；GET **必填** `date`；可选 `gibbonTTDayRowClassID` |
| GET/POST | `/v1/attendance/form-groups/{id}` | `attendance.formGroup`；GET **必填** `date`。无 `_all` 时只能点自己导师的行政班 |
| GET/POST | `/v1/attendance/people/{id}` | `attendance.person`；GET **必填** `date` |

教学班/行政班 POST：

```json
{
  "date": "2026-08-20",
  "gibbonTTDayRowClassID": "可选，仅教学班",
  "records": [
    { "gibbonPersonID": "0000000001", "type": "Present", "reason": "", "comment": "" }
  ]
}
```

个人 POST：`{ "date":"2026-08-20", "type":"Present", "reason":"", "comment":"" }`。

## 记分册

只做栏目与按班给分。量表只读。无 `markbook.editAllClasses` 时只能改自己任教的班。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/grade-scales`、`/v1/grade-scales/{id}` | `markbook.write`；详情含 `grades` |
| GET/POST | `/v1/markbook/classes/{classId}/columns` | 同上；GET 还返回可用 `types` |
| GET/PATCH/DELETE | `/v1/markbook/columns/{id}` | 同上 |
| GET/PUT | `/v1/markbook/columns/{id}/entries` | 同上 |

栏目创建必填：`name`、`description`、`type`、`date`。`attainment` 默认 `Y`，此时还要 `gibbonScaleIDAttainment`。`effort` 默认跟随学校 `enableEffort` 设置（关了就是 `N`，给了 `gibbonScaleIDEffort` 也会被清掉）。`comment` 默认 `Y`。`viewableStudents` / `viewableParents` 默认 `N`。没给 `gibbonSchoolYearTermID` 时会按 `date` 自动归入对应学期。不做量规。**删除栏目会连带删掉该栏全部给分，不可恢复。**

`entries` GET 返回 `{ "column": ..., "data": [...] }`，`data` 覆盖全班学生（没给分的字段为 `null`）。

给分 PUT：

```json
{
  "entries": [
    {
      "gibbonPersonIDStudent": "0000000001",
      "attainmentValue": "A",
      "effortValue": "4",
      "comment": "Well done"
    }
  ]
}
```

`attainmentValue` / `effortValue` 必须是该栏目量表里的 `value`，否则 422；传空字符串表示清除该生分数。只对栏目开启的维度给分（栏目 `comment=N` 时评语会被丢弃）。
