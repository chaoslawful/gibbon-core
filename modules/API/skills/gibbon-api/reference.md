# 接口一览（与当前代码一致）

Base：`$GIBBON_API_BASE`（例 `http://localhost/api.php`）。路径均以 `/v1` 开头。未特别说明时需要 Bearer。

权限列的是 `GET /v1/me` 里的 `capabilities` 或对应网页模块。列表多返回 `{ "data": [ ... ] }`。

## 元数据

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/v1/openapi.json` | 无 | OpenAPI 3.0.3 |
| GET | `/v1/me` | 令牌有效 | 身份、锁定角色、`capabilities`、学年 |
| GET | `/v1/school-year` | 令牌有效 | 当前学年 `gibbonSchoolYearID`、`gibbonSchoolYearName`、`firstDay`、`lastDay` |

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

学年创建必填：`name`、`status`、`sequenceNumber`、`firstDay`、`lastDay`。`status` 取 `Past`/`Current`/`Upcoming`（服务端**不校验**，写错会原样入库）。

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

课表创建必填 `name`、`nameShort`。`gibbonSchoolYearID` 可省，默认当前学年；`active` 默认 `Y`。`nameShortDisplay` 取 `Day Of The Week` 或 `Timetable Day Short Name`（默认前者；服务端**不校验**，写错原样入库）。`gibbonYearGroupIDList` 为年级组 ID 逗号串（或数组）。GET 列表可带 `gibbonSchoolYearID` 参数（默认当前学年），返回行里年级组字段叫 `yearGroups`。

课表日必填 `name`、`nameShort`、`gibbonTTColumnID`。`color`/`fontColor` 默认 `#ffffff` / `#000000`。

节次必填 `name`、`nameShort`、`timeStart`、`timeEnd`。`type` 默认 `Lesson`，取值 `Lesson`、`Pastoral`、`Sport`、`Break`、`Service`、`Other`（服务端**不校验**，写错原样入库）。**删除作息模板会连带删掉它的全部节次。**

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

创建人员必填：`surname`、`firstName`、`preferredName`、`officialName`、`gender`、`username`、`gibbonRoleIDPrimary`。可选 `password`；没有则响应带 `generatedPassword`（只此一次）。密码必须过学校密码策略，否则 422；`username` 已占用也 422。`staffRecord=Y` 同时建教职工（`staffType` 默认 `Teaching`）；`studentRecord=Y` 需同时给 `gibbonYearGroupID`、`gibbonFormGroupID`。已有人员补建教职工档案请用 `POST /v1/staff`。

入学名册必填 `gibbonYearGroupID`、`gibbonFormGroupID`；`gibbonSchoolYearID` 默认当前学年，同一同学年重复入学会 422。可选 `autoEnrolStudent=Y` 按行政班自动选课。

家庭创建必填只有 `name`；`status` 默认 `Married`。加家庭成员（adults/children）必填 `gibbonPersonID`；adult 的 `contactPriority` 默认 `1`，`childDataAccess`/`contactCall`/`contactEmail` 默认 `Y`，`contactSMS`/`contactMail` 默认 `N`。

角色创建必填 `category`、`name`、`nameShort`；`type` 默认 `Additional`，`canLoginRole` 默认 `Y`。

个人医疗：必须先 `PUT /v1/people/{id}/medical` 建医疗表（字段 `longTermMedication`/`longTermMedicationDetails`/`comment`），才能 `POST medical-conditions` 加状况（必填 `name`，可选 `gibbonAlertLevelID`、`triggers`、`reaction`、`response`、`medication`、`lastEpisode` 等），否则 422。医疗状况字典（`/v1/medical-conditions`）创建必填 `name`。

## 教职工

教师在 Gibbon 里是「人员 + `gibbonStaff` 档案」。查名册、按人查、按教学/教辅筛选用这组接口，不要用 `/v1/people` 硬筛。合同、代课覆盖排班不在范围内。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/staff` | `staff.read`（Staff Directory）；可 `q`、`type`=`Teaching`/`Support`、`gibbonPersonID`、`limit`；`all=Y` 含 Expected/Left，需完整目录或 Manage Staff |
| GET | `/v1/staff/{id}` | 同上；`id` 是 `gibbonStaffID`；含姓名、邮箱、电话、入离职日等人员字段 |
| POST | `/v1/staff` | `staff.write`（Manage Staff） |
| PATCH/DELETE | `/v1/staff/{id}` | 同上 |

创建必填：`gibbonPersonID`、`type`（`Teaching`/`Support`，也接受 `staffType`）。该人必须已有 **Staff** 角色，且还没有 staff 记录，否则 422。可选 `initials`（全校唯一）、`jobTitle`、`firstAidQualified`（`Y`/`N`/空）、`firstAidQualification`、`firstAidExpiry`、`countryOfOrigin`、`qualifications`、`biographicalGrouping`、`biographicalGroupingPriority`、`biography`、`coverageExclude`、`coveragePriority`；`dateStart`/`dateEnd` 会写到人员记录。`firstAidQualified` 不是 `Y` 时急救资格与到期日会被清空。删除只删 staff 档案，不删人员账号。

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

deploy 注意：未给 `name` 的课会自动命名「单元名 N」；`running` 默认 `Y`；`blocks` 里引用不存在的块 ID 会 404 中止——但 **deploy 没有事务**，中止前已创建的教案保留在库里，不会回滚；失败后先打 coverage 核对实际建到哪，再决定清理还是补齐，不要原样重跑整批（会重复建课）。copy-forward 连同单元块一起复制。**copy-back 会先清空单元原有的全部块**，再用该班工作副本的块覆盖——不可逆，执行前向用户确认。smart-blockify 是追加（把教案的工作块拷进单元，不动已有块）。

## 出勤

点名日期不能是未来，且必须是开学日（学期内、校历教学日、当天不是 School Closure），违反任一都是 422。`type` 用出勤代码的 **name**（如 `Present`），必须是启用中的代码，先 `GET /v1/attendance/codes`。

GET 点名表返回学生名单（含每人当前 `type`，未点过则为学校默认代码）、`taken`（是否已点过）、`defaultType`。POST 的 `records` 里只允许当天实际在该班/该行政班的学生（教学班还会排除课格例外名单里的人），否则 422。学校若开了 `recordFirstClassAsSchool`，当天第一节班级点名会同步写一条校级（Person 上下文）出勤。重复点名是覆盖更新，不是追加。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/attendance/codes` | 任一出勤点名权限，或 School Admin 出勤设置 |
| POST | `/v1/attendance/codes` | School Admin `attendanceSettings`（`attendance.codes`） |
| GET/PATCH/DELETE | `/v1/attendance/codes/{id}` | GET 同列表；PATCH/DELETE 同 POST。`type=Core` 的内置代码不能删，可以改 |
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

新建出勤代码（学校管理员）必填：`name`、`nameShort`、`direction`（`In`/`Out`）、`scope`（`Onsite` / `Onsite - Late` / `Offsite` / `Offsite - Left` / `Offsite - Late`）、`sequenceNumber`。`type` 固定为 `Additional`。可选 `active`/`reportable`/`prefill`/`future`（`Y`/`N`），`gibbonRoleIDAll` 为可用角色 ID 列表。

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

## 出勤报表

全部只读 JSON，不生成图片或 PDF。权限跟对应网页报表。`date` 一律 `YYYY-MM-DD`，不能是未来。

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/v1/attendance/reports/student-history` | Attendance `report_studentHistory` | `_all` 必填 `gibbonPersonID`；`_my` 强制自己；`_myChildren` 只能查子女 |
| GET | `/v1/attendance/reports/consecutive-absences` | `report_consecutiveAbsences` | `numberOfSchoolDays` 默认 7（1–99） |
| GET | `/v1/attendance/reports/not-present` | `report_studentsNotPresent_byDate` | **必填** `date`；可选 `allStudents=Y` |
| GET | `/v1/attendance/reports/not-onsite` | `report_studentsNotOnsite_byDate` | 同上 |
| GET | `/v1/attendance/reports/not-in-class` | `report_studentsNotInClass_byDate` | **必填** `date`；可选 `allStudents`、`types`、`gibbonYearGroupIDList` |
| GET | `/v1/attendance/reports/form-groups-not-registered` | `report_formGroupsNotRegistered_byDate` | `dateStart`/`dateEnd` 或单个 `date` |
| GET | `/v1/attendance/reports/classes-not-registered` | `report_courseClassesNotRegistered_byDate` | 同上 |
| GET | `/v1/attendance/reports/trends` | `report_graph_byType` | 返回 `{ days, series }` 计数，不是图；可选 `dateStart`/`dateEnd`/`gibbonFormGroupID` |

`capabilities.attendance.reports` 为任一上述报表即可。

## 财务（学校支出）

**没有**收费计划、缴费人、学生账单、在线支付、Excel/PDF。打印接口返回 JSON 明细，不是文件。网页也不提供删除费用条目，所以 API 没有 DELETE `/v1/finance/fees/{id}`。内置类别 ID `0001`（Other）不能改、不能删；删除其它类别时，其下费用条目与发票费用行会被迁移到 `0001`。删除预算会连带删其 staff 授权。

报销审批按资源创建，**不直接改 `status`**：`POST /v1/finance/expenses/{id}/approvals`，`decision`=`approve`/`reject`/`comment`。服务端按网页同一套审批链写日志、推进状态并发通知。令牌用户必须是审批链上**这一轮**该批的人（`reject`/`comment` 除外），否则 403，状态不会变。只有 `Requested` 状态的报销能 approve/reject，否则 422；学校未配置审批设置（`expenseApprovalType` 或审批人为空）也 422。成功 **201**，body 是新日志行，并带上更新后的 `expense`。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/finance/fee-categories` | `feeCategories_manage` |
| GET/PATCH/DELETE | `/v1/finance/fee-categories/{id}` | 同上 |
| GET/POST | `/v1/finance/fees` | `fees_manage`；GET **必填** `gibbonSchoolYearID` |
| GET/PATCH | `/v1/finance/fees/{id}` | 同上 |
| GET/POST | `/v1/finance/budget-cycles` | `budgetCycles_manage`；POST 可带 `allocations` |
| GET/PATCH/DELETE | `/v1/finance/budget-cycles/{id}` | 同上；GET 含各预算科目额度 `allocations` |
| GET/PUT | `/v1/finance/budget-cycles/{id}/allocations` | 同上；PUT 按预算科目 upsert 额度 |
| GET/POST | `/v1/finance/budgets` | `budgets_manage` |
| GET/PATCH/DELETE | `/v1/finance/budgets/{id}` | 同上；GET 含 `staff` |
| POST | `/v1/finance/budgets/{id}/staff` | 同上；`gibbonPersonID` + `access`=`Full`/`Write`/`Read` |
| DELETE | `/v1/finance/budget-staff/{id}` | 同上 |
| GET/POST | `/v1/finance/expense-approvers` | `expenseApprovers_manage` |
| PATCH/DELETE | `/v1/finance/expense-approvers/{id}` | 同上 |
| GET/POST | `/v1/finance/expenses` | GET：`expenses_manage` 或 `expenseRequest_manage`；POST 默认走「我的申请」（需 `expenseRequest_manage`）；仅 `expensesAll` 且学校开启直接添加（`allowExpenseAdd`）时，传非 `Requested` 的 `status` 才生效，否则 `status` 被静默改回 `Requested`，不报错 |
| GET | `/v1/finance/expenses/{id}`、`.../print` | 同上；含 `log` |
| POST | `/v1/finance/expenses/{id}/approvals` | `expenses_manage`；`{ "decision": "approve"|"reject"|"comment", "comment": "" }`，**201** |
| POST | `/v1/finance/expenses/{id}/reimburse` | `expenseRequest_manage` |
| GET/POST | `/v1/finance/petty-cash` | `pettyCash`；GET 可选 `gibbonSchoolYearID` |
| PATCH/DELETE | `/v1/finance/petty-cash/{id}` | 同上 |
| POST | `/v1/finance/petty-cash/{id}/action` | 同上；按 `actionRequired` 标 `Repaid`/`Refunded` |

`GET /v1/finance/expenses` **必填** `gibbonFinanceBudgetCycleID`。可选 `status`（`Requested` / `Approved` / `Rejected` / `Cancelled` / `Ordered` / `Paid`，非法值 422）、`gibbonFinanceBudgetID`（预算不存在 404）。两个筛选都是精确匹配；不传则不限制。`mine=Y` 只看自己的申请。非 `expensesAll` 的管理员只能看到并操作自己有权预算科目下的报销；`reimburse` 仅限本人申请或 `expensesAll`，且状态须为 `Approved` / `Paid`。一次返回该周期下全部匹配记录，没有分页。

报销申请 POST：

```json
{
  "gibbonFinanceBudgetCycleID": "000001",
  "gibbonFinanceBudgetID": "0001",
  "title": "打印机墨盒",
  "cost": "120.00",
  "countAgainstBudget": "Y",
  "purchaseBy": "School",
  "body": "",
  "purchaseDetails": ""
}
```

批准 POST：`{ "decision": "approve", "comment": "" }`。标已报销：`{ "paymentDate": "2026-08-21", "paymentAmount": "120.00", "paymentMethod": "Bank Transfer" }`。

费用类别创建必填：`name`、`nameShort`、`active`。费用条目创建必填：`name`、`nameShort`、`active`、`gibbonFinanceFeeCategoryID`、`fee`；`gibbonSchoolYearID` 默认当前学年。审批人创建必填 `gibbonPersonID`；学校审批链为 Chain Of All 时还必须 `sequenceNumber`。零用金创建必填 `gibbonPersonID`、`amount`；`gibbonSchoolYearID` 默认当前学年。

预算周期创建必填：`name`、`status`（`Past`/`Current`/`Upcoming`）、`sequenceNumber`、`dateStart`、`dateEnd`。可选 `allocations`，与单独 PUT 额度相同。预算创建必填：`name`、`nameShort`、`active`、`category`；可选 `staff` 数组和统一 `access`。

周期额度 PUT（网页编辑周期时给每个预算科目填的金额）：

```json
{
  "allocations": [
    { "gibbonFinanceBudgetID": "0001", "value": "10000.00" }
  ]
}
```

未出现在数组里的科目不会被删掉，只更新/插入给出的项。GET 会列出全部预算科目，没有额度的 `value` 为 `0.00`。删周期会连带删该周期全部额度。

## 行为记录

无行为信、无模式分析。`type` 只能是 `Positive` / `Negative` / `Observation`。学校开了描述词（`enableDescriptors=Y`）时 `descriptor` 必填。`Manage Behaviour Records_my` 只能改自己写的记录。删除行为记录会连带删其全部 followUps。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/behaviour` | `behaviour_manage` |
| GET/PATCH/DELETE | `/v1/behaviour/{id}` | 同上；GET 含 `followUps` |
| POST | `/v1/behaviour/{id}/follow-up` | 同上；`{ "followUp": "..." }` |

单人 POST：

```json
{
  "gibbonPersonID": "0000000001",
  "date": "2026-08-21",
  "type": "Negative",
  "descriptor": "Disruptive",
  "level": "",
  "comment": "...",
  "followUp": "",
  "copyToNotes": "Y"
}
```

一次多人：把 `gibbonPersonID` 换成 `gibbonPersonIDs` 数组，如 `"gibbonPersonIDs": ["0000000001","0000000002"]`，返回 `{ "gibbonMultiIncidentID": "...", "data": [ ... ] }`。

