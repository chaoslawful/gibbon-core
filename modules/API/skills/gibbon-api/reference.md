# 接口一览（适用于 API 模块 1.3.06，与 skill `version` 同号）

Base：`$GIBBON_API_BASE`（例 `http://localhost/api.php`）。路径均以 `/v1` 开头。未特别说明时需要 Bearer。

权限列：能在 `GET /v1/me` 的 `capabilities` 里自检的写 capability 名（值为 `true` 才算有权）；没有对应 capability 的写成 `网页: …`，**不可自检**，只能以 403 判定。`school.structure` 是多项网页权限的 OR，有它仍可能因缺少该项网页权限而 403——这类行会写成 `school.structure`；实际 `网页: …`。

列表多返回 `{ "data": [ ... ] }`。参数必填性以服务端 422 为准。

**幂等性**：默认 POST 会新建资源，重复调用会重复创建。例外：挂班（`units/{id}/classes`）幂等；点名 POST 按同一上下文覆盖（教学班按班+课格；行政班按 Form Group；个人按 Person）；记分册 `PUT /entries`、人员医疗 `PUT`、预算周期 `PUT .../allocations` 是 upsert；记分册回复文件 `POST .../response` 替换磁盘文件；重置密码可重复。deploy / copy-forward / 直建教案 / 建人员 / 行为记录均非幂等。人员医疗 PUT 对**已有表**是三字段整包写（缺键用默认 `N`/空串，会冲掉未传的长期用药字段）。

## 元数据

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/v1/openapi.json` | 无 | OpenAPI 3.0.3。**路由清单**，绝大多数写操作无 requestBody |
| GET | `/v1/me` | 令牌有效 | 身份、锁定角色、`capabilities`（26 项 true/false）、学年 |
| GET | `/v1/school-year` | 令牌有效 | **当前学年**（单数）：`gibbonSchoolYearID`、`gibbonSchoolYearName`、`firstDay`、`lastDay`。不要与下方复数学年管理混淆 |

`/v1/me` 响应：`gibbonPersonID`、`username`、`preferredName`、`surname`、`gibbonRoleID`（锁定角色）、`roleName`、`roleCategory`、`gibbonSchoolYearID`、`token`（令牌 ID/名称/类型/过期时间）、`capabilities`。

## 学校结构

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/year-groups` | `school.structure`；实际 `网页: yearGroup_manage` |
| PATCH/DELETE | `/v1/year-groups/{id}` | 同上 |
| GET/POST | `/v1/departments` | `school.structure`；实际 `网页: department_manage` |
| PATCH/DELETE | `/v1/departments/{id}` | 同上 |
| GET/POST | `/v1/houses` | `school.structure`；实际 `网页: house_manage` |
| PATCH/DELETE | `/v1/houses/{id}` | 同上 |
| GET/POST | `/v1/form-groups` | `school.structure`；实际 `网页: formGroup_manage`；GET **必填** `gibbonSchoolYearID` |
| PATCH/DELETE | `/v1/form-groups/{id}` | 同上 |
| GET | `/v1/spaces` | `timetable.write`（排课用的场地列表） |
| POST | `/v1/spaces` | `school.structure`；实际 `网页: space_manage` |
| PATCH/DELETE | `/v1/spaces/{id}` | 同上 |
| GET/POST | `/v1/school-years` | `网页: schoolYear_manage`（**不在** `school.structure` 的 OR 里） |
| PATCH/DELETE | `/v1/school-years/{id}` | 同上 |

对照：单数 `GET /v1/school-year` = 读当前学年（无需管理权限）；复数 `/v1/school-years` = 学年 CRUD。

学年创建必填：`name`、`status`、`sequenceNumber`、`firstDay`、`lastDay`。`status` 取 `Past`/`Current`/`Upcoming`（服务端**不校验**，写错会原样入库）。

年级组创建必填：`name`、`nameShort`、`sequenceNumber`；可选 `gibbonPersonIDHOY`（年级主任）。学部创建必填：`name`、`nameShort`；`type` 默认 `Learning Area`。学院创建必填：`name`、`nameShort`。

## 学期与特殊日

特殊日必须挂在某个学期上（`gibbonSchoolYearTermID`）。`sequenceNumber` 在**全部学期**里唯一，不只是当前学年。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/terms` | `school.structure`；实际 `网页: schoolYearTerm_manage`；GET **必填** `gibbonSchoolYearID` |
| PATCH/DELETE | `/v1/terms/{id}` | 同上 |
| GET/POST | `/v1/special-days` | `school.structure`；实际 `网页: schoolYearSpecialDay_manage`；GET 需 `gibbonSchoolYearID` 或 `gibbonSchoolYearTermID` 或 `from`+`to` |
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
| GET | `/v1/courses`、`/v1/classes` | `planner.read` 或 `timetable.read`；`GET /v1/courses` 返回 `{id,name}`；`GET /v1/classes` 返回 `{id,name,gibbonCourseID}`（1.3.05+）；可带 `gibbonSchoolYearID`（默认当前学年）；无全校权限时只列自己任教/就读的。没有 `GET /v1/classes/{id}` |
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
| POST | `/v1/people/{id}/password` | 同上；重置；可省略 `password` 则生成（可重复） |
| GET/POST | `/v1/people/{id}/enrolment` | `网页: studentEnrolment_manage` |
| GET | `/v1/student-enrolments` | 同上；`gibbonSchoolYearID`、`gibbonFormGroupID`、`q` |
| PATCH/DELETE | `/v1/student-enrolments/{id}` | 同上 |
| GET/POST | `/v1/roles` | `网页: role_manage` |
| PATCH/DELETE | `/v1/roles/{id}` | 同上 |
| GET/POST | `/v1/families` | `网页: family_manage` |
| GET/PATCH/DELETE | `/v1/families/{id}` | 同上；GET 含 adults/children |
| POST | `/v1/families/{id}/adults`、`/v1/families/{id}/children` | 同上 |
| DELETE | `/v1/family-adults/{id}`、`/v1/family-children/{id}` | 同上 |
| GET/POST | `/v1/medical-conditions` | `网页: medicalConditions_manage` |
| PATCH/DELETE | `/v1/medical-conditions/{id}` | 同上 |
| GET/PUT | `/v1/people/{id}/medical` | `网页: medicalForm_manage`；PUT 是 upsert。**已有表**会把未传的 `longTermMedication` / `longTermMedicationDetails` / `comment` 写成默认 `N`/空，不是字段级 merge |
| POST | `/v1/people/{id}/medical-conditions` | 同上 |
| DELETE | `/v1/person-medical-conditions/{id}` | 同上 |

创建人员必填：`surname`、`firstName`、`preferredName`、`officialName`、`gender`、`username`、`gibbonRoleIDPrimary`。可选 `password`；没有则响应带 `generatedPassword`（只此一次）。密码必须过学校密码策略，否则 422；`username` 已占用也 422。`staffRecord=Y` 同时建教职工（`staffType` 默认 `Teaching`），**不校验**该人是否已有 Staff 角色（与 `POST /v1/staff` 不同）；`studentRecord=Y` 需同时给 `gibbonYearGroupID`、`gibbonFormGroupID`。已有人员补建教职工档案请用 `POST /v1/staff`。

人员精简字段（PATCH 只更新出现的键；`""` 对 `gibbonHouseID` / `dob` / `dateStart` / `dateEnd` / `gibbonSchoolYearIDClassOf` 会写成 null）：

| 字段 | POST | PATCH | 必填 / 默认 |
|---|---|---|---|
| `surname` `firstName` `preferredName` `officialName` `gender` `username` `gibbonRoleIDPrimary` | 可 | 可 | POST 必填 |
| `password` | 可（不入库明文） | 否（用 password 接口） | 省略则生成 |
| `status` | 可 | 可 | `Full` |
| `canLogin` | 可 | 可 | `Y` |
| `passwordForceReset` | 可 | 可 | `N` |
| `gibbonRoleIDAll` | 可 | 可 | 默认等于主键角色 |
| `email` `dob` `phone*` `title` 及其余人表字段 | 可 | 可 | 多数默认空串 |
| `staffRecord` `staffType` `studentRecord` `gibbonYearGroupID` `gibbonFormGroupID` | POST 侧写 | 否 | `studentRecord=Y` 时后两项必填 |

列表 `GET /v1/people` 为精简人员行；正文级字段（地址、紧急联系人等）要 `GET /v1/people/{id}`。

入学名册必填 `gibbonYearGroupID`、`gibbonFormGroupID`；`gibbonSchoolYearID` 默认当前学年，同一同学年重复入学会 422。可选 `autoEnrolStudent=Y` 按行政班自动选课。

家庭创建必填只有 `name`；`status` 默认 `Married`。加家庭成员（adults/children）必填 `gibbonPersonID`；adult 的 `contactPriority` 默认 `1`，`childDataAccess`/`contactCall`/`contactEmail` 默认 `Y`，`contactSMS`/`contactMail` 默认 `N`。

角色创建必填 `category`、`name`、`nameShort`；`type` 默认 `Additional`，`canLoginRole` / `pastYearsLogin` / `futureYearsLogin` 默认 `Y`。

个人医疗：必须先 `PUT /v1/people/{id}/medical` 建医疗表（字段 `longTermMedication`/`longTermMedicationDetails`/`comment`），才能 `POST medical-conditions` 加状况（必填 `name`，可选 `gibbonAlertLevelID`、`triggers`、`reaction`、`response`、`medication`、`lastEpisode` 等），否则 422。对已有表再 PUT 是三字段整包替换（缺键变默认），只改评语也会冲掉长期用药。医疗状况字典（`/v1/medical-conditions`）创建必填 `name`。

## 教职工

教师在 Gibbon 里是「人员 + `gibbonStaff` 档案」。查名册、按人查、按教学/教辅筛选用这组接口，不要用 `/v1/people` 硬筛。合同、代课覆盖排班不在范围内。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/staff` | `staff.read`；可 `q`、`type`=`Teaching`/`Support`、`gibbonPersonID`、`limit`；`all=Y` 含 Expected/Left，需完整目录或 `staff.write`。完整目录时还可 `status` |
| GET | `/v1/staff/{id}` | 同上；`id` 是 `gibbonStaffID`；含姓名、邮箱、电话、入离职日等人员字段 |
| POST | `/v1/staff` | `staff.write` |
| PATCH/DELETE | `/v1/staff/{id}` | 同上 |

创建必填：`gibbonPersonID`、`type`（`Teaching`/`Support`，也接受 `staffType`）。该人必须已有 **Staff** 角色，且还没有 staff 记录，否则 422。可选 `initials`（全校唯一）、`jobTitle`、`firstAidQualified`（`Y`/`N`/空）、`firstAidQualification`、`firstAidExpiry`、`countryOfOrigin`、`qualifications`、`biographicalGrouping`、`biographicalGroupingPriority`、`biography`、`coverageExclude`、`coveragePriority`；`dateStart`/`dateEnd` 会写到人员记录。`firstAidQualified` 不是 `Y` 时急救资格与到期日会被清空。删除只删 staff 档案，不删人员账号。

## 教案与单元

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/planner/lessons` | 读 `planner.read` / 写 `planner.write` |
| GET/PATCH/DELETE | `/v1/planner/lessons/{id}` | 能看/改该班（无 `planner.editAllClasses` 时只能改自己任教的班） |
| GET | `/v1/planner/classes/{classId}/slots` | `planner.read` |
| GET | `/v1/planner/classes/{classId}/coverage` | `planner.read` |
| GET | `/v1/planner/units` | `planner.read`；**必填** `gibbonCourseID` |
| POST | `/v1/planner/units` | `planner.units` |
| GET/PATCH/DELETE | `/v1/planner/units/{id}` | `planner.units`；GET 含 blocks、classes |
| POST | `/v1/planner/units/{id}/blocks` | 同上 |
| PATCH/DELETE | `/v1/planner/unit-blocks/{id}` | 同上 |
| POST | `/v1/planner/units/{id}/classes` | 同上；幂等 |
| POST | `/v1/planner/units/{id}/deploy` | 同上；非幂等 |
| POST | `/v1/planner/units/{id}/copy-forward` | 同上；body：`gibbonCourseID` |
| POST | `/v1/planner/unit-classes/{id}/copy-back` | 同上 |
| POST | `/v1/planner/units/{id}/smart-blockify` | 同上；body：`gibbonPlannerEntryID` |
| GET | `/v1/planner/lessons/{id}/homework` | 能看该班（`planner.read`） |
| POST | `/v1/planner/lessons/{id}/homework` | `planner.write` |
| PATCH/DELETE | `/v1/planner/homework/{id}` | `planner.write` |

教案创建必填：`gibbonCourseClassID`、`date`、`timeStart`、`timeEnd`、`name`。PATCH 是与现有记录 merge 后再整份校验，未出现的字段保持原值。本节字段表即该端点字段全集（以服务端为准，含 spec 曾漏掉的作业/互评字段）。`fields` 只在 POST 生效，PATCH 传入会被忽略。

**可见性**：`viewableStudents` / `viewableParents` 是整课对学生/家长的访问开关（列表与详情），不是字段级 ACL。打开后网页才给学生看 `name` / `summary` / `description` / `homeworkDetails`。`teachersNotes` 在网页上恒为教师专属。含学生个人信息的批改/讲评一律写 `teachersNotes`；面向学生的教学内容写 `description`。API GET 仍会把 `teachersNotes` 返回给能看该班的令牌。

| 字段 | 类型 | POST | PATCH | 必填条件 | 默认值 | 教案页区域 | 可见对象 |
|---|---|---|---|---|---|---|---|
| `gibbonCourseClassID` | string | 可 | 可 | POST 必填 | — | 班级 | — |
| `date` | YYYY-MM-DD | 可 | 可 | POST 必填 | — | 日期 | — |
| `timeStart` `timeEnd` | HH:MM[:SS] | 可 | 可 | POST 必填 | — | 时段 | — |
| `name` | string | 可 | 可 | POST 必填 | — | 课名 | 受 `viewable*` |
| `summary` | string | 可 | 可 | 否 | 空则从 `description` 剥标签截 252 字 | 列表摘要 | 受 `viewable*` |
| `description` | HTML | 可 | 可 | 否 | `""` | 正文 | 受 `viewable*` |
| `teachersNotes` | HTML | 可 | 可 | 否 | `""` | 教师备注 | 仅教师（网页） |
| `gibbonUnitID` | string/null | 可 | 可 | 否 | `null`；`""`/`null` 均清空 | 所属单元 | — |
| `homework` | Y/N | 可 | 可 | 否 | `N` | 作业开关 | — |
| `homeworkDetails` | HTML | 可 | 可 | `homework=Y` 时必填 | `""` | 作业说明 | 受 `viewable*` |
| `homeworkDueDateTime` | datetime | 可 | 可 | `homework=Y` 时必填 | `null` | 作业截止 | 受 `viewable*` |
| `homeworkTimeCap` | string/null | 可 | 可 | 否 | `homework=Y` 才保留 | 作业时限 | — |
| `homeworkLocation` | enum | 可 | 可 | 否 | `homework=Y` 时默认 `Out of Class` | 作业地点 | — |
| `homeworkSubmission` | Y/N | 可 | 可 | 否 | `N` | 在线提交 | — |
| `homeworkSubmissionDateOpen` `homeworkSubmissionDrafts` `homeworkSubmissionType` `homeworkSubmissionRequired` | 杂 | 可 | 可 | 否 | 空/`null` | 提交设置 | — |
| `homeworkCrowdAssess` 及 `homeworkCrowdAssess*Read` | Y/N | 可 | 可 | 否 | `N` | 同伴互评开关 | — |
| `viewableStudents` | Y/N | 可 | 可 | 否 | 直建 `Y`（deploy 默认 `N`） | 学生可见开关 | 开关本身 |
| `viewableParents` | Y/N | 可 | 可 | 否 | 直建 `N`（deploy 默认 `N`） | 家长可见开关 | 开关本身 |
| `fields` | string/JSON | 可 | 否 | 否 | `null` | 自定义字段 | — |

`name` 超过 50 字会被截断，不报错。`homework=Y` 时必须有 `homeworkDetails` 与 `homeworkDueDateTime`——**创建与更新（merge 后）均校验**；违反 422：`homeworkDetails and homeworkDueDateTime are required when homework is Y.`。PATCH 只把 `homework` 改成 `Y` 时，会用合并后的旧值做这项检查。

GET `/v1/planner/lessons` 可带 `gibbonSchoolYearID`（默认当前学年）、`gibbonCourseClassID`、`from`、`to`；不带班级时只列自己相关的班。

**列表是精简视图**，行字段为：`gibbonPlannerEntryID`、`gibbonCourseClassID`、`gibbonUnitID`、`date`、`timeStart`、`timeEnd`、`name`、`summary`、`homework`、`homeworkDueDateTime`、`homeworkSubmission`、`viewableStudents`、`viewableParents`，外加三个**字符串**（不是对象）：`course`（课程简称）、`class`（班级简称）、`unit`（单元名，可空）。**不含** `description`、`teachersNotes`、`homeworkDetails`、`homeworkLocation`。正文类字段必须 `GET /v1/planner/lessons/{id}`。详情另附只读 `homeworkSubmissions`、`smartBlocks`、`outcomes`。

**从班级找课程与单元**（`GET /v1/planner/units` 必填 `gibbonCourseID`，`GET /v1/classes` 本身没有详情路由）：

1. `GET /v1/classes` → 用返回的 `gibbonCourseID`（需模块 1.3.05+）。
2. `GET /v1/planner/units?gibbonCourseID=…` 列单元；`GET /v1/courses/{id}` 看课程详情；`GET /v1/courses/{id}/classes` 反查该课程下的班。
3. 旧实例没有 `gibbonCourseID` 时：枚举 `GET /v1/courses`，再对每个课程 `GET /v1/courses/{id}/classes` 直到匹配班级 `id`。
4. 教案列表行的 `course`/`unit` 只是名称，**不能**当 ID 用。无全校权限时，课程/班级列表都只含自己任教或就读的。

单元精简字段（PATCH 只更新出现的键）：

| 字段 | POST | PATCH | 必填 / 默认 |
|---|---|---|---|
| `gibbonCourseID` `name` | 可 | 可 | POST 必填 |
| `description` `details` `tags` | 可 | 可 | `""` |
| `active` | 可 | 可 | `Y` |
| `ordering` | 可 | 可 | `0` |
| `map` | 可 | 可 | `Y` |
| `license` `sharedPublic` | 可 | 可 | 可选 |

智能块：创建只必填 `title`；可选 `type`、`length`、`contents`、`teachersNotes`、`sequenceNumber`（默认 1）。挂班（`units/{id}/classes`）是幂等的：已挂过就返回原记录，传 `running=Y` 可顺带激活。

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

GET 点名表返回学生名单（含每人当前 `type`，未点过则为学校默认代码）、`taken`（是否已点过）、`defaultType`。POST 的 `records` 里只允许当天实际在该班/该行政班的学生（教学班还会排除课格例外名单里的人），否则 422。学校若开了 `recordFirstClassAsSchool`，教学班点名时若该生**当天还没有任何 Person 上下文日志**会再插一条校级出勤（不校验是不是当天第一节课格）。重复点名按同一上下文覆盖：教学班按班+课格匹配后 update（改 type 也覆盖）；行政班匹配 Form Group 日志后 update，同一天的行政班「已点」记录也 update 而不是再插一行；个人匹配无班级的 Person 日志后 update。这与网页「改出勤码就追加一条历史」不同，API 以覆盖为准。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/attendance/codes` | 任一 `attendance.class` / `attendance.formGroup` / `attendance.person`，或 `attendance.codes` |
| POST | `/v1/attendance/codes` | `attendance.codes` |
| GET/PATCH/DELETE | `/v1/attendance/codes/{id}` | GET 同列表；PATCH/DELETE 同 POST。`type=Core` 的内置代码不能删，可以改 |
| GET/POST | `/v1/attendance/classes/{id}` | `attendance.class`；GET **必填** `date`；可选 `gibbonTTDayRowClassID` |
| GET/POST | `/v1/attendance/form-groups/{id}` | `attendance.formGroup`；GET **必填** `date`。无 `_all` 时只能点自己导师的、且 `attendance=Y` 的行政班；有 `_all` 时不查 `attendance` 标志，`attendance=N` 的班也能点 |
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

只做栏目、按班给分、以及栏目开启后的每生回复文件。量表只读。无 `markbook.editAllClasses` 时只能改自己任教的班。回复文件接口需要学校 API 模块 **1.3.04+**（`GET /v1/openapi.json` 的 `info.version`）；更旧的实例会 404。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET | `/v1/grade-scales`、`/v1/grade-scales/{id}` | `markbook.write`；详情含 `grades` |
| GET/POST | `/v1/markbook/classes/{classId}/columns` | 同上；GET 还返回可用 `types` |
| GET/PATCH/DELETE | `/v1/markbook/columns/{id}` | 同上 |
| GET/PUT | `/v1/markbook/columns/{id}/entries` | 同上 |
| POST/GET/DELETE | `/v1/markbook/columns/{id}/entries/{studentId}/response` | 同上；该生回复文件 |

栏目创建必填：`name`、`description`、`type`、`date`。栏目精简字段：

| 字段 | POST | PATCH | 必填 / 默认 |
|---|---|---|---|
| `name` `description` `type` `date` | 可 | 可 | POST 必填 |
| `attainment` | 可 | 可 | `Y`；为 `Y` 时 POST 还要 `gibbonScaleIDAttainment` |
| `gibbonScaleIDAttainment` `gibbonScaleIDEffort` | 可 | 可 | `effort=N` 或学校关 effort 时被清空 |
| `effort` | 可 | 可 | 跟随学校 `enableEffort`（关了就是 `N`） |
| `comment` | 可 | 可 | `Y` |
| `uploadedResponse` | 可 | 可 | `N` |
| `viewableStudents` `viewableParents` | 可 | 可 | `N`（学生/家长看成绩，与教案开关无关） |
| `complete` `completeDate` | 可 | 可 | `N` / `complete=N` 时日期清空 |
| `gibbonUnitID` `gibbonPlannerEntryID` `gibbonSchoolYearTermID` | 可 | 可 | 学期可按 `date` 自动归入 |
| `columnColor` | 可 | 可 | `""` |

没给 `gibbonSchoolYearTermID` 时会按 `date` 自动归入对应学期。不做量规。**删除栏目会连带删掉该栏全部给分，不可恢复。** PATCH 栏目是 merge 校验后再只写入请求里出现的键；学校关了 effort 时，即使 body 没带 `effort`，也可能把 `gibbonScaleIDEffort` 写成 `null`。

`entries` GET 返回 `{ "column": ..., "data": [...] }`，`data` 覆盖全班学生（没给分的字段为 `null`）。每条含 `response`：无文件为 `{ "present": false }`；有文件为 `{ "present": true, "size", "contentType", "download" }`，`download` 是 API 路径，不是 `/uploads/` 磁盘路径。没有客户端原文件名。

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

`attainmentValue` / `effortValue` 必须是该栏目量表里的 `value`，否则 422；传空字符串表示清除该维度。已有 entry 时 **缺键保留原值**（只改评语不会把分数清空）；新建 entry 缺键则为空。只对栏目开启的维度给分（栏目 `comment=N` 时评语会被丢弃）。PUT 给分**不会**改 `response`；请求里带了 `response` / 文件字段也会被忽略。

回复文件（栏目须 `uploadedResponse=Y`，该生须已有 entry）：

```bash
curl -sS -X POST \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  -F "file=@./feedback.pdf" \
  "$GIBBON_API_BASE/v1/markbook/columns/{id}/entries/{studentId}/response"
```

成功 **200**（不是 201），body 含 `gibbonMarkbookColumnID`、`gibbonPersonIDStudent`、`gibbonMarkbookEntryID` 和 `response` 元数据。已有文件则替换并删除旧磁盘文件。

下载（字节，不是 JSON）：

```bash
curl -sS -L \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  -o ./feedback.pdf \
  "$GIBBON_API_BASE/v1/markbook/columns/{id}/entries/{studentId}/response"
```

删除：**204**，磁盘文件一并删除：

```bash
curl -sS -X DELETE \
  -H "Authorization: Bearer $GIBBON_API_TOKEN" \
  "$GIBBON_API_BASE/v1/markbook/columns/{id}/entries/{studentId}/response"
```

尚无 entry 时 POST **422** `Markbook entry not found. Create it with PUT /entries first.`；栏目未开回复时 **422** `This column does not include uploaded responses. Set uploadedResponse to Y.`；学生不在该班 **422** `gibbonPersonIDStudent is not a student in this class.`；没有回复文件时 GET/DELETE **404** `Uploaded response not found.`；文件类型不允许 **422** `File type is not allowed.`；超过 PHP 上传限制 **413** `The uploaded file exceeds the server size limit.`。文件扩展名须在学校「允许的文件类型」里，且不能是 `js/html/php` 等禁止类型。

## 出勤报表

全部只读 JSON，不生成图片或 PDF。权限跟对应网页报表。`date` 一律 `YYYY-MM-DD`，不能是未来。

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/v1/attendance/reports/student-history` | `attendance.reports`；实际 `网页: report_studentHistory` | `_all` 必填 `gibbonPersonID`；`_my` 强制自己；`_myChildren` 只能查子女 |
| GET | `/v1/attendance/reports/consecutive-absences` | `attendance.reports`；实际 `网页: report_consecutiveAbsences` | `numberOfSchoolDays` 默认 7（1–99） |
| GET | `/v1/attendance/reports/not-present` | `attendance.reports`；实际 `网页: report_studentsNotPresent_byDate` | **必填** `date`；可选 `allStudents=Y` |
| GET | `/v1/attendance/reports/not-onsite` | `attendance.reports`；实际 `网页: report_studentsNotOnsite_byDate` | 同上 |
| GET | `/v1/attendance/reports/not-in-class` | `attendance.reports`；实际 `网页: report_studentsNotInClass_byDate` | **必填** `date`；可选 `allStudents`、`types`、`gibbonYearGroupIDList` |
| GET | `/v1/attendance/reports/form-groups-not-registered` | `attendance.reports`；实际 `网页: report_formGroupsNotRegistered_byDate` | `dateStart`/`dateEnd` 或单个 `date`；都不传则默认今天 |
| GET | `/v1/attendance/reports/classes-not-registered` | `attendance.reports`；实际 `网页: report_courseClassesNotRegistered_byDate` | 同上 |
| GET | `/v1/attendance/reports/trends` | `attendance.reports`；实际 `网页: report_graph_byType` | 返回 `{ days, series }` 计数，不是图；可选 `dateStart`/`dateEnd`/`gibbonFormGroupID` |

`capabilities.attendance.reports` 为任一上述报表即可。

## 财务（学校支出）

**没有**收费计划、缴费人、学生账单、在线支付、Excel/PDF。打印接口返回 JSON 明细，不是文件。网页也不提供删除费用条目，所以 API 没有 DELETE `/v1/finance/fees/{id}`。内置类别 ID `0001`（Other）不能改、不能删；删除其它类别时，其下费用条目与发票费用行会被迁移到 `0001`。删除预算会连带删其 staff 授权。

报销审批按资源创建，**不直接改 `status`**：`POST /v1/finance/expenses/{id}/approvals`，`decision`=`approve`/`reject`/`comment`。服务端按网页同一套审批链写日志、推进状态并发通知，规则来自学校财务设定 `expenseApprovalType` 和 `budgetLevelExpenseApproval`（API **没有**读取这两项的接口，以返回的 `expense` 为准）。令牌必须先有 `finance.expensesAll` 或该预算 `Full` 权限，才会进入「这一轮审批人」校验；纯学校审批人若两样都没有会直接 403。令牌用户必须是审批链上**这一轮**该批的人（`reject`/`comment` 除外），否则 403，状态不会变。只有 `Requested` 状态的报销能 approve/reject，否则 422；学校未配置审批设置（`expenseApprovalType` / `budgetLevelExpenseApproval` 为空，或审批人为空）也 422。成功 **201**，body 是新日志行，并带上更新后的 `expense`。**201 不等于已经批准**：看 `expense.status`，仍是 `Requested` 就是只过了一关。

审批分两层，都批完 `status` 才变 `Approved`：

1. **预算关**（`budgetLevelExpenseApproval`）：为 `Y` 且 `statusApprovalBudgetCleared=N` 时，先要该预算 `access=Full` 的人 `approve`；通过后该字段变 `Y`，`status` 仍是 `Requested`。提交人自己是该预算 Full，或学校关掉了预算级审批（`N`），创建时就会写成 `Y`，跳过这一关。
2. **学校关**（`expenseApprovalType`，预算关过后才算）：

| 设定 | 谁能 `approve` | 何时变 `Approved` |
|---|---|---|
| **One Of** | 任一尚未批过的学校审批人 | 1 条学校级通过日志 |
| **Two Of** | 同上 | **两名**不同审批人各批一次 |
| **Chain Of All** | 只允许 `sequenceNumber` 上**下一个**人，不能跳号 | 链上所有人都批完 |

部分通过时 log 的 `action` 是 `Approval - Partial - Budget` 或 `Approval - Partial - School`；整条链走完会再写 `Approval - Final` 并把 `status` 改成 `Approved`。`reject` 立即变 `Rejected`，与类型无关。`comment` 不推进状态。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/finance/fee-categories` | `finance.fees`；实际 `网页: feeCategories_manage` |
| GET/PATCH/DELETE | `/v1/finance/fee-categories/{id}` | 同上 |
| GET/POST | `/v1/finance/fees` | `finance.fees`；实际 `网页: fees_manage`；GET **必填** `gibbonSchoolYearID` |
| GET/PATCH | `/v1/finance/fees/{id}` | 同上 |
| GET/POST | `/v1/finance/budget-cycles` | `网页: budgetCycles_manage`；POST 可带 `allocations` |
| GET/PATCH/DELETE | `/v1/finance/budget-cycles/{id}` | 同上；GET 含各预算科目额度 `allocations` |
| GET/PUT | `/v1/finance/budget-cycles/{id}/allocations` | 同上；PUT 按预算科目 upsert 额度 |
| GET/POST | `/v1/finance/budgets` | `finance.budgets` |
| GET/PATCH/DELETE | `/v1/finance/budgets/{id}` | 同上；GET 含 `staff` |
| POST | `/v1/finance/budgets/{id}/staff` | 同上；`gibbonPersonID` + `access`=`Full`/`Write`/`Read` |
| DELETE | `/v1/finance/budget-staff/{id}` | 同上 |
| GET/POST | `/v1/finance/expense-approvers` | `网页: expenseApprovers_manage` |
| PATCH/DELETE | `/v1/finance/expense-approvers/{id}` | 同上 |
| GET/POST | `/v1/finance/expenses` | GET：`finance.expenses` 或 `finance.expensesAll`；POST 默认走「我的申请」（需 `网页: expenseRequest_manage`）；仅 `finance.expensesAll` 且学校开启直接添加（`allowExpenseAdd`）时，传非 `Requested` 的 `status` 才生效，否则 `status` 被静默改回 `Requested`，不报错 |
| GET | `/v1/finance/expenses/{id}`、`/v1/finance/expenses/{id}/print` | 同上；含 `log` |
| POST | `/v1/finance/expenses/{id}/approvals` | `finance.expenses`；实际 `网页: expenses_manage`；`{ "decision": "approve"|"reject"|"comment", "comment": "" }`，**201** |
| POST | `/v1/finance/expenses/{id}/reimburse` | `网页: expenseRequest_manage` |
| GET/POST | `/v1/finance/petty-cash` | `finance.pettyCash`；GET 可选 `gibbonSchoolYearID` |
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

无行为信、无模式分析。`type` 只能是 `Positive` / `Negative` / `Observation`。学校开了描述词（`enableDescriptors=Y`）时 `descriptor` 必填。无 `behaviour.writeAll` 时只能改自己写的记录。删除行为记录会连带删其全部 followUps。GET 可带 `gibbonSchoolYearID`（默认当前学年）和 `type`。

| 方法 | 路径 | 权限 |
|---|---|---|
| GET/POST | `/v1/behaviour` | `behaviour.write`；`behaviour.writeAll` 可看/改全部 |
| GET/PATCH/DELETE | `/v1/behaviour/{id}` | 同上；GET 含 `followUps`；PATCH 可带 `followUp` 侧写一条跟进 |
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

