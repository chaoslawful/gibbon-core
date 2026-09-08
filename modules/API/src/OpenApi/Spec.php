<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\API\OpenApi;

class Spec
{
    public static function document(string $baseUrl): array
    {
        $bearer = [
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'PAT',
                'description' => 'Personal access token created in Gibbon (API → Manage API Tokens).',
            ],
        ];

        $security = [['bearerAuth' => []]];

        $idParam = function (string $name, string $desc) {
            return ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string'], 'description' => $desc];
        };

        $query = function (string $name, string $desc, $example = null) {
            $param = ['name' => $name, 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => $desc];
            if ($example !== null) {
                $param['example'] = $example;
            }
            return $param;
        };

        $json = function (array $properties, array $required = []) {
            $schema = ['type' => 'object', 'properties' => $properties];
            if ($required) {
                $schema['required'] = $required;
            }
            return ['content' => ['application/json' => ['schema' => $schema]]];
        };

        $ok = ['description' => 'OK'];
        $created = ['description' => 'Created'];
        $noContent = ['description' => 'No content'];
        $err = ['description' => 'Error', 'content' => ['application/json' => ['schema' => [
            'type' => 'object',
            'properties' => [
                'error' => ['type' => 'string'],
                'status' => ['type' => 'integer'],
                'type' => ['type' => 'string'],
                'path' => ['type' => 'string'],
                'method' => ['type' => 'string'],
                'hint' => ['type' => 'string'],
                'details' => ['type' => 'object'],
            ],
        ]]]];

        $lessonWrite = [
            'gibbonCourseClassID' => ['type' => 'string'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'timeStart' => ['type' => 'string', 'example' => '09:00:00'],
            'timeEnd' => ['type' => 'string', 'example' => '09:45:00'],
            'name' => ['type' => 'string'],
            'summary' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'teachersNotes' => ['type' => 'string'],
            'gibbonUnitID' => ['type' => 'string', 'nullable' => true],
            'homework' => ['type' => 'string', 'enum' => ['Y', 'N']],
            'homeworkDetails' => ['type' => 'string'],
            'homeworkDueDateTime' => ['type' => 'string', 'format' => 'date-time'],
            'homeworkLocation' => ['type' => 'string', 'enum' => ['Out of Class', 'In Class']],
            'homeworkSubmission' => ['type' => 'string', 'enum' => ['Y', 'N']],
            'viewableStudents' => ['type' => 'string', 'enum' => ['Y', 'N']],
            'viewableParents' => ['type' => 'string', 'enum' => ['Y', 'N']],
        ];

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Gibbon Agent API',
                'version' => '1.3.04',
                'description' => 'REST API for authorised agents. Requests run as the token owner with the role locked at token creation. Covers school structure, terms, special days, timetables, courses, people, staff, units, lesson planner, attendance, attendance reports, markbook, behaviour, and finance expenses.',
            ],
            'servers' => [
                ['url' => $baseUrl.'/api.php', 'description' => 'API front controller'],
                ['url' => $baseUrl.'/api', 'description' => 'Pretty URL when /api rewrite is enabled'],
            ],
            'security' => $security,
            'components' => ['securitySchemes' => $bearer],
            'paths' => [
                '/v1/openapi.json' => ['get' => [
                    'summary' => 'OpenAPI document',
                    'operationId' => 'getOpenApi',
                    'security' => [],
                    'responses' => ['200' => $ok],
                ]],
                '/v1/me' => ['get' => [
                    'summary' => 'Current token identity and capabilities',
                    'operationId' => 'getMe',
                    'responses' => ['200' => $ok, '401' => $err],
                ]],
                '/v1/school-year' => ['get' => [
                    'summary' => 'Current school year',
                    'operationId' => 'getSchoolYear',
                    'responses' => ['200' => $ok],
                ]],
                '/v1/courses' => ['get' => [
                    'summary' => 'List courses the token can see',
                    'operationId' => 'listCourses',
                    'parameters' => [$query('gibbonSchoolYearID', 'Defaults to the current school year')],
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/classes' => ['get' => [
                    'summary' => 'List classes the token can see',
                    'operationId' => 'listClasses',
                    'parameters' => [$query('gibbonSchoolYearID', 'Defaults to the current school year')],
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/spaces' => ['get' => [
                    'summary' => 'List facilities (timetable admin only)',
                    'operationId' => 'listSpaces',
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/planner/lessons' => [
                    'get' => [
                        'summary' => 'List lesson plans',
                        'operationId' => 'listLessons',
                        'parameters' => [
                            $query('gibbonCourseClassID', 'Filter by class'),
                            $query('from', 'Start date YYYY-MM-DD', '2026-08-01'),
                            $query('to', 'End date YYYY-MM-DD'),
                            $query('gibbonSchoolYearID', 'School year id'),
                        ],
                        'responses' => ['200' => $ok, '403' => $err],
                    ],
                    'post' => [
                        'summary' => 'Create a lesson plan',
                        'operationId' => 'createLesson',
                        'requestBody' => $json($lessonWrite, ['gibbonCourseClassID', 'date', 'timeStart', 'timeEnd', 'name']),
                        'responses' => ['201' => $created, '403' => $err, '422' => $err],
                    ],
                ],
                '/v1/planner/lessons/{id}' => [
                    'get' => [
                        'summary' => 'Get a lesson plan, including homework submissions, smart blocks and outcomes',
                        'operationId' => 'getLesson',
                        'parameters' => [$idParam('id', 'gibbonPlannerEntryID')],
                        'responses' => ['200' => $ok, '403' => $err, '404' => $err],
                    ],
                    'patch' => [
                        'summary' => 'Update a lesson plan',
                        'operationId' => 'updateLesson',
                        'parameters' => [$idParam('id', 'gibbonPlannerEntryID')],
                        'requestBody' => $json($lessonWrite),
                        'responses' => ['200' => $ok, '403' => $err, '422' => $err],
                    ],
                    'delete' => [
                        'summary' => 'Delete a lesson plan',
                        'operationId' => 'deleteLesson',
                        'parameters' => [$idParam('id', 'gibbonPlannerEntryID')],
                        'responses' => ['204' => $noContent, '403' => $err, '404' => $err],
                    ],
                ],
                '/v1/planner/classes/{classId}/slots' => ['get' => [
                    'summary' => 'Timetable slots for a class joined to existing lesson plans',
                    'operationId' => 'listClassSlots',
                    'parameters' => [
                        $idParam('classId', 'gibbonCourseClassID'),
                        $query('from', 'Start date YYYY-MM-DD'),
                        $query('to', 'End date YYYY-MM-DD'),
                    ],
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/planner/classes/{classId}/coverage' => ['get' => [
                    'summary' => 'Lesson-plan coverage vs timetable slots',
                    'operationId' => 'getClassCoverage',
                    'parameters' => [
                        $idParam('classId', 'gibbonCourseClassID'),
                        $query('from', 'Start date YYYY-MM-DD'),
                        $query('to', 'End date YYYY-MM-DD'),
                    ],
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/planner/units' => ['get' => [
                    'summary' => 'List units for a course',
                    'operationId' => 'listUnits',
                    'parameters' => [['name' => 'gibbonCourseID', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]],
                    'responses' => ['200' => $ok, '422' => $err],
                ]],
                '/v1/timetables' => ['get' => [
                    'summary' => 'List timetables',
                    'operationId' => 'listTimetables',
                    'parameters' => [$query('gibbonSchoolYearID', 'Defaults to the current school year')],
                    'responses' => ['200' => $ok, '403' => $err],
                ]],
                '/v1/timetables/{id}' => ['get' => [
                    'summary' => 'Get a timetable',
                    'operationId' => 'getTimetable',
                    'parameters' => [$idParam('id', 'gibbonTTID')],
                    'responses' => ['200' => $ok, '404' => $err],
                ]],
                '/v1/timetables/{id}/days' => ['get' => [
                    'summary' => 'List days in a timetable',
                    'operationId' => 'listTimetableDays',
                    'parameters' => [$idParam('id', 'gibbonTTID')],
                    'responses' => ['200' => $ok],
                ]],
                '/v1/timetables/{id}/days/{dayId}/rows' => ['get' => [
                    'summary' => 'List period rows for a timetable day',
                    'operationId' => 'listTimetableDayRows',
                    'parameters' => [$idParam('id', 'gibbonTTID'), $idParam('dayId', 'gibbonTTDayID')],
                    'responses' => ['200' => $ok],
                ]],
                '/v1/timetables/{id}/days/{dayId}/slots' => [
                    'get' => [
                        'summary' => 'List class slots on a timetable day',
                        'operationId' => 'listTimetableDaySlots',
                        'parameters' => [$idParam('id', 'gibbonTTID'), $idParam('dayId', 'gibbonTTDayID')],
                        'responses' => ['200' => $ok],
                    ],
                    'post' => [
                        'summary' => 'Add a class to a period (timetable admin)',
                        'operationId' => 'createTimetableSlot',
                        'parameters' => [$idParam('id', 'gibbonTTID'), $idParam('dayId', 'gibbonTTDayID')],
                        'requestBody' => $json([
                            'gibbonTTColumnRowID' => ['type' => 'string'],
                            'gibbonCourseClassID' => ['type' => 'string'],
                            'gibbonSpaceID' => ['type' => 'string', 'nullable' => true],
                        ], ['gibbonTTColumnRowID', 'gibbonCourseClassID']),
                        'responses' => ['201' => $created, '403' => $err, '422' => $err],
                    ],
                ],
                '/v1/timetables/{id}/dates' => ['get' => [
                    'summary' => 'Map calendar dates to timetable days',
                    'operationId' => 'listTimetableDates',
                    'parameters' => [
                        $idParam('id', 'gibbonTTID'),
                        $query('from', 'Start date YYYY-MM-DD'),
                        $query('to', 'End date YYYY-MM-DD'),
                    ],
                    'responses' => ['200' => $ok],
                ]],
                '/v1/timetable-slots/{id}' => [
                    'patch' => [
                        'summary' => 'Update a timetable slot (timetable admin)',
                        'operationId' => 'updateTimetableSlot',
                        'parameters' => [$idParam('id', 'gibbonTTDayRowClassID')],
                        'requestBody' => $json([
                            'gibbonTTDayID' => ['type' => 'string'],
                            'gibbonTTColumnRowID' => ['type' => 'string'],
                            'gibbonCourseClassID' => ['type' => 'string'],
                            'gibbonSpaceID' => ['type' => 'string', 'nullable' => true],
                        ]),
                        'responses' => ['200' => $ok, '403' => $err],
                    ],
                    'delete' => [
                        'summary' => 'Delete a timetable slot (timetable admin)',
                        'operationId' => 'deleteTimetableSlot',
                        'parameters' => [$idParam('id', 'gibbonTTDayRowClassID')],
                        'responses' => ['204' => $noContent, '403' => $err],
                    ],
                ],
            ],
        ];

        foreach (self::extraPaths($ok, $created, $noContent, $err, $idParam, $query) as $path => $methods) {
            $spec['paths'][$path] = array_merge($spec['paths'][$path] ?? [], $methods);
        }

        return $spec;
    }

    protected static function extraPaths(array $ok, array $created, array $noContent, array $err, callable $idParam, callable $query): array
    {
        $crud = function (string $summary) use ($ok, $created, $err) {
            return [
                'get' => ['summary' => 'List '.$summary, 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create '.$summary, 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ];
        };
        $item = function (string $summary) use ($ok, $noContent, $err, $idParam) {
            return [
                'patch' => ['summary' => 'Update '.$summary, 'parameters' => [$idParam('id', 'id')], 'responses' => ['200' => $ok, '403' => $err]],
                'delete' => ['summary' => 'Delete '.$summary, 'parameters' => [$idParam('id', 'id')], 'responses' => ['204' => $noContent, '403' => $err]],
            ];
        };

        return [
            '/v1/year-groups' => $crud('year groups'),
            '/v1/year-groups/{id}' => $item('year group'),
            '/v1/departments' => $crud('departments'),
            '/v1/departments/{id}' => $item('department'),
            '/v1/houses' => $crud('houses'),
            '/v1/houses/{id}' => $item('house'),
            '/v1/form-groups' => [
                'get' => ['summary' => 'List form groups', 'parameters' => [$query('gibbonSchoolYearID', 'Required')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Create form group', 'responses' => ['201' => $created]],
            ],
            '/v1/form-groups/{id}' => $item('form group'),
            '/v1/school-years' => $crud('school years'),
            '/v1/school-years/{id}' => $item('school year'),
            '/v1/spaces' => [
                'post' => ['summary' => 'Create facility', 'responses' => ['201' => $created, '403' => $err]],
            ],
            '/v1/spaces/{id}' => $item('facility'),
            '/v1/timetables' => [
                'post' => ['summary' => 'Create timetable', 'responses' => ['201' => $created, '403' => $err]],
            ],
            '/v1/timetables/{id}' => [
                'patch' => ['summary' => 'Update timetable', 'parameters' => [$idParam('id', 'gibbonTTID')], 'responses' => ['200' => $ok]],
                'delete' => ['summary' => 'Delete timetable', 'parameters' => [$idParam('id', 'gibbonTTID')], 'responses' => ['204' => $noContent]],
            ],
            '/v1/timetable-columns' => $crud('period templates'),
            '/v1/timetable-columns/{id}' => array_merge(['get' => ['summary' => 'Get period template with rows', 'parameters' => [$idParam('id', 'gibbonTTColumnID')], 'responses' => ['200' => $ok]]], $item('period template')),
            '/v1/timetable-columns/{id}/rows' => ['post' => ['summary' => 'Add a period to a template', 'parameters' => [$idParam('id', 'gibbonTTColumnID')], 'responses' => ['201' => $created]]],
            '/v1/timetable-column-rows/{id}' => $item('period row'),
            '/v1/timetables/{id}/days' => ['post' => ['summary' => 'Add a timetable day', 'parameters' => [$idParam('id', 'gibbonTTID')], 'responses' => ['201' => $created]]],
            '/v1/timetables/{id}/days/{dayId}' => [
                'patch' => ['summary' => 'Update timetable day', 'parameters' => [$idParam('id', 'gibbonTTID'), $idParam('dayId', 'gibbonTTDayID')], 'responses' => ['200' => $ok]],
                'delete' => ['summary' => 'Delete timetable day', 'parameters' => [$idParam('id', 'gibbonTTID'), $idParam('dayId', 'gibbonTTDayID')], 'responses' => ['204' => $noContent]],
            ],
            '/v1/timetables/{id}/dates' => ['post' => ['summary' => 'Map a calendar date to a timetable day', 'parameters' => [$idParam('id', 'gibbonTTID')], 'responses' => ['201' => $created]]],
            '/v1/timetable-dates/{id}' => ['delete' => ['summary' => 'Remove date mapping', 'parameters' => [$idParam('id', 'gibbonTTDayDateID')], 'responses' => ['204' => $noContent]]],
            '/v1/timetable-slots/{id}/exceptions' => [
                'get' => ['summary' => 'List slot exceptions', 'parameters' => [$idParam('id', 'gibbonTTDayRowClassID')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Add slot exception', 'parameters' => [$idParam('id', 'gibbonTTDayRowClassID')], 'responses' => ['201' => $created]],
            ],
            '/v1/timetable-slot-exceptions/{id}' => ['delete' => ['summary' => 'Delete slot exception', 'parameters' => [$idParam('id', 'gibbonTTDayRowClassExceptionID')], 'responses' => ['204' => $noContent]]],
            '/v1/courses' => ['post' => ['summary' => 'Create course', 'responses' => ['201' => $created]]],
            '/v1/courses/{id}' => array_merge(['get' => ['summary' => 'Get course', 'parameters' => [$idParam('id', 'gibbonCourseID')], 'responses' => ['200' => $ok]]], $item('course')),
            '/v1/courses/{id}/classes' => [
                'get' => ['summary' => 'List classes in a course', 'parameters' => [$idParam('id', 'gibbonCourseID')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Create class', 'parameters' => [$idParam('id', 'gibbonCourseID')], 'responses' => ['201' => $created]],
            ],
            '/v1/classes/{id}' => $item('class'),
            '/v1/classes/{id}/enrolment' => [
                'get' => ['summary' => 'List class enrolment', 'parameters' => [$idParam('id', 'gibbonCourseClassID')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Add class enrolment', 'parameters' => [$idParam('id', 'gibbonCourseClassID')], 'responses' => ['201' => $created]],
            ],
            '/v1/enrolment/{id}' => $item('enrolment record'),
            '/v1/staff' => [
                'get' => [
                    'summary' => 'List staff',
                    'parameters' => [
                        $query('q', 'Search preferred name, surname, username, job title'),
                        $query('type', 'Teaching or Support'),
                        $query('all', 'Y to include Expected/Left (full directory or manage only)'),
                        $query('gibbonPersonID', 'Look up the staff record for a person'),
                        $query('limit', 'Page size, default 50'),
                    ],
                    'responses' => ['200' => $ok, '403' => $err],
                ],
                'post' => ['summary' => 'Create a staff record for an existing person', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/staff/{id}' => array_merge(
                ['get' => ['summary' => 'Get staff record with person identity', 'parameters' => [$idParam('id', 'gibbonStaffID')], 'responses' => ['200' => $ok, '404' => $err]]],
                $item('staff record')
            ),
            '/v1/people' => $crud('people'),
            '/v1/people/{id}' => array_merge(['get' => ['summary' => 'Get person', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok]]], $item('person')),
            '/v1/people/{id}/password' => ['post' => ['summary' => 'Reset person password', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok]]],
            '/v1/people/{id}/enrolment' => [
                'get' => ['summary' => 'List student enrolment years', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Enrol student in a year/form group', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['201' => $created]],
            ],
            '/v1/student-enrolments' => ['get' => ['summary' => 'List student enrolments', 'parameters' => [$query('gibbonSchoolYearID', 'Defaults to current year')], 'responses' => ['200' => $ok]]],
            '/v1/student-enrolments/{id}' => $item('student enrolment'),
            '/v1/roles' => $crud('roles'),
            '/v1/roles/{id}' => $item('role'),
            '/v1/families' => $crud('families'),
            '/v1/families/{id}' => array_merge(['get' => ['summary' => 'Get family', 'parameters' => [$idParam('id', 'gibbonFamilyID')], 'responses' => ['200' => $ok]]], $item('family')),
            '/v1/families/{id}/adults' => ['post' => ['summary' => 'Add family adult', 'parameters' => [$idParam('id', 'gibbonFamilyID')], 'responses' => ['201' => $created]]],
            '/v1/family-adults/{id}' => ['delete' => ['summary' => 'Remove family adult', 'parameters' => [$idParam('id', 'id')], 'responses' => ['204' => $noContent]]],
            '/v1/families/{id}/children' => ['post' => ['summary' => 'Add family child', 'parameters' => [$idParam('id', 'gibbonFamilyID')], 'responses' => ['201' => $created]]],
            '/v1/family-children/{id}' => ['delete' => ['summary' => 'Remove family child', 'parameters' => [$idParam('id', 'id')], 'responses' => ['204' => $noContent]]],
            '/v1/medical-conditions' => $crud('medical conditions'),
            '/v1/medical-conditions/{id}' => $item('medical condition'),
            '/v1/people/{id}/medical' => [
                'get' => ['summary' => 'Get person medical form', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok]],
                'put' => ['summary' => 'Create or update person medical form', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok]],
            ],
            '/v1/planner/units' => ['post' => ['summary' => 'Create unit', 'responses' => ['201' => $created]]],
            '/v1/planner/units/{id}' => array_merge(['get' => ['summary' => 'Get unit', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['200' => $ok]]], $item('unit')),
            '/v1/planner/units/{id}/blocks' => ['post' => ['summary' => 'Add unit smart block', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['201' => $created]]],
            '/v1/planner/unit-blocks/{id}' => $item('unit block'),
            '/v1/planner/units/{id}/classes' => ['post' => ['summary' => 'Attach unit to a class', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['201' => $created]]],
            '/v1/planner/units/{id}/deploy' => ['post' => ['summary' => 'Deploy unit blocks into lesson plans', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['201' => $created]]],
            '/v1/planner/units/{id}/copy-forward' => ['post' => ['summary' => 'Copy unit to another course/year', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['201' => $created]]],
            '/v1/planner/unit-classes/{id}/copy-back' => ['post' => ['summary' => 'Copy working blocks back to the unit', 'parameters' => [$idParam('id', 'gibbonUnitClassID')], 'responses' => ['200' => $ok]]],
            '/v1/planner/units/{id}/smart-blockify' => ['post' => ['summary' => 'Create unit blocks from a lesson', 'parameters' => [$idParam('id', 'gibbonUnitID')], 'responses' => ['200' => $ok]]],
            '/v1/planner/lessons/{id}/homework' => [
                'get' => ['summary' => 'List homework submissions', 'parameters' => [$idParam('id', 'gibbonPlannerEntryID')], 'responses' => ['200' => $ok]],
                'post' => ['summary' => 'Add homework submission', 'parameters' => [$idParam('id', 'gibbonPlannerEntryID')], 'responses' => ['201' => $created]],
            ],
            '/v1/planner/homework/{id}' => $item('homework submission'),
            '/v1/terms' => [
                'get' => ['summary' => 'List terms', 'parameters' => [['name' => 'gibbonSchoolYearID', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => $ok, '422' => $err]],
                'post' => ['summary' => 'Create term', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/terms/{id}' => $item('term'),
            '/v1/special-days' => [
                'get' => ['summary' => 'List special days', 'parameters' => [$query('gibbonSchoolYearID', 'Year id'), $query('gibbonSchoolYearTermID', 'Term id'), $query('from', 'Start date'), $query('to', 'End date')], 'responses' => ['200' => $ok, '422' => $err]],
                'post' => ['summary' => 'Create special day (School Closure, Timing Change, Off Timetable)', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/special-days/{id}' => $item('special day'),
            '/v1/attendance/codes' => [
                'get' => ['summary' => 'List attendance codes', 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create an additional attendance code', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/attendance/codes/{id}' => array_merge(['get' => ['summary' => 'Get attendance code', 'parameters' => [$idParam('id', 'gibbonAttendanceCodeID')], 'responses' => ['200' => $ok]]], $item('attendance code')),
            '/v1/attendance/classes/{id}' => [
                'get' => ['summary' => 'Class attendance sheet', 'parameters' => [$idParam('id', 'gibbonCourseClassID'), ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']], $query('gibbonTTDayRowClassID', 'Optional slot')], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Take class attendance', 'parameters' => [$idParam('id', 'gibbonCourseClassID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]],
            ],
            '/v1/attendance/form-groups/{id}' => [
                'get' => ['summary' => 'Form group attendance sheet', 'parameters' => [$idParam('id', 'gibbonFormGroupID'), ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Take form group attendance', 'parameters' => [$idParam('id', 'gibbonFormGroupID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]],
            ],
            '/v1/attendance/people/{id}' => [
                'get' => ['summary' => 'Person attendance logs for a date', 'parameters' => [$idParam('id', 'gibbonPersonID'), ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Take attendance by person', 'parameters' => [$idParam('id', 'gibbonPersonID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]],
            ],
            '/v1/grade-scales' => ['get' => ['summary' => 'List grade scales', 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/grade-scales/{id}' => ['get' => ['summary' => 'Get grade scale with grades', 'parameters' => [$idParam('id', 'gibbonScaleID')], 'responses' => ['200' => $ok, '404' => $err]]],
            '/v1/markbook/classes/{classId}/columns' => [
                'get' => ['summary' => 'List markbook columns for a class', 'parameters' => [$idParam('classId', 'gibbonCourseClassID')], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create markbook column', 'parameters' => [$idParam('classId', 'gibbonCourseClassID')], 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/markbook/columns/{id}' => array_merge(['get' => ['summary' => 'Get markbook column', 'parameters' => [$idParam('id', 'gibbonMarkbookColumnID')], 'responses' => ['200' => $ok]]], $item('markbook column')),
            '/v1/markbook/columns/{id}/entries' => [
                'get' => ['summary' => 'List markbook entries for a column', 'parameters' => [$idParam('id', 'gibbonMarkbookColumnID')], 'responses' => ['200' => $ok]],
                'put' => ['summary' => 'Upsert markbook entries for a class', 'parameters' => [$idParam('id', 'gibbonMarkbookColumnID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]],
            ],
            '/v1/markbook/columns/{id}/entries/{studentId}/response' => [
                'post' => [
                    'summary' => 'Upload or replace a student uploaded-response file',
                    'parameters' => [$idParam('id', 'gibbonMarkbookColumnID'), $idParam('studentId', 'gibbonPersonIDStudent')],
                    'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
                        'type' => 'object',
                        'required' => ['file'],
                        'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                    ]]]],
                    'responses' => ['200' => $ok, '403' => $err, '404' => $err, '413' => $err, '422' => $err],
                ],
                'get' => [
                    'summary' => 'Download a student uploaded-response file',
                    'parameters' => [$idParam('id', 'gibbonMarkbookColumnID'), $idParam('studentId', 'gibbonPersonIDStudent')],
                    'responses' => [
                        '200' => ['description' => 'File bytes', 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                        '403' => $err,
                        '404' => $err,
                    ],
                ],
                'delete' => [
                    'summary' => 'Delete a student uploaded-response file',
                    'parameters' => [$idParam('id', 'gibbonMarkbookColumnID'), $idParam('studentId', 'gibbonPersonIDStudent')],
                    'responses' => ['204' => $noContent, '403' => $err, '404' => $err],
                ],
            ],
            '/v1/attendance/reports/student-history' => ['get' => ['summary' => 'Student attendance history for the current year', 'parameters' => [$query('gibbonPersonID', 'Student id')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/consecutive-absences' => ['get' => ['summary' => 'Students with consecutive absences', 'parameters' => [$query('numberOfSchoolDays', 'School days, 1-99')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/not-present' => ['get' => ['summary' => 'Students not present on a date', 'parameters' => [$query('date', 'YYYY-MM-DD')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/not-onsite' => ['get' => ['summary' => 'Students not onsite on a date', 'parameters' => [$query('date', 'YYYY-MM-DD')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/not-in-class' => ['get' => ['summary' => 'Students not in class on a date', 'parameters' => [$query('date', 'YYYY-MM-DD')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/form-groups-not-registered' => ['get' => ['summary' => 'Form groups that have not taken attendance', 'parameters' => [$query('dateStart', 'Start date'), $query('dateEnd', 'End date')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/classes-not-registered' => ['get' => ['summary' => 'Classes that have not taken attendance', 'parameters' => [$query('dateStart', 'Start date'), $query('dateEnd', 'End date')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/attendance/reports/trends' => ['get' => ['summary' => 'Attendance counts by type over a date range (JSON series)', 'parameters' => [$query('dateStart', 'Start date'), $query('dateEnd', 'End date')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/finance/budget-cycles' => [
                'get' => ['summary' => 'List budget cycles', 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create budget cycle', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/budget-cycles/{id}' => array_merge(['get' => ['summary' => 'Get budget cycle with allocations', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetCycleID')], 'responses' => ['200' => $ok]]], $item('budget cycle')),
            '/v1/finance/budget-cycles/{id}/allocations' => [
                'get' => ['summary' => 'List budget allocations for a cycle', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetCycleID')], 'responses' => ['200' => $ok, '403' => $err]],
                'put' => ['summary' => 'Upsert budget allocations for a cycle', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetCycleID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/budgets' => [
                'get' => ['summary' => 'List budgets', 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create budget', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/budgets/{id}' => array_merge(['get' => ['summary' => 'Get budget with staff', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetID')], 'responses' => ['200' => $ok]]], $item('budget')),
            '/v1/finance/budgets/{id}/staff' => ['post' => ['summary' => 'Add staff to a budget', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetID')], 'responses' => ['201' => $created, '403' => $err]]],
            '/v1/finance/budget-staff/{id}' => ['delete' => ['summary' => 'Remove staff from a budget', 'parameters' => [$idParam('id', 'gibbonFinanceBudgetPersonID')], 'responses' => ['204' => $ok]]],
            '/v1/finance/expense-approvers' => [
                'get' => ['summary' => 'List expense approvers', 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Add expense approver', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/expense-approvers/{id}' => $item('expense approver'),
            '/v1/finance/expenses' => [
                'get' => [
                    'summary' => 'List expenses',
                    'parameters' => [
                        ['name' => 'gibbonFinanceBudgetCycleID', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                        $query('status', 'Requested, Approved, Rejected, Cancelled, Ordered, or Paid'),
                        $query('gibbonFinanceBudgetID', 'Filter by budget'),
                        $query('mine', 'Y for own requests'),
                    ],
                    'responses' => ['200' => $ok, '403' => $err, '422' => $err],
                ],
                'post' => ['summary' => 'Create expense request (or admin expense if allowed)', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/expenses/{id}' => ['get' => ['summary' => 'Get expense with log', 'parameters' => [$idParam('id', 'gibbonFinanceExpenseID')], 'responses' => ['200' => $ok, '404' => $err]]],
            '/v1/finance/expenses/{id}/print' => ['get' => ['summary' => 'Print payload for an expense (JSON, not PDF)', 'parameters' => [$idParam('id', 'gibbonFinanceExpenseID')], 'responses' => ['200' => $ok]]],
            '/v1/finance/expenses/{id}/approvals' => ['post' => ['summary' => 'Create an approval, rejection or comment on an expense', 'parameters' => [$idParam('id', 'gibbonFinanceExpenseID')], 'responses' => ['201' => $created, '403' => $err, '422' => $err]]],
            '/v1/finance/expenses/{id}/reimburse' => ['post' => ['summary' => 'Mark an approved expense as reimbursed', 'parameters' => [$idParam('id', 'gibbonFinanceExpenseID')], 'responses' => ['200' => $ok, '403' => $err, '422' => $err]]],
            '/v1/finance/petty-cash' => [
                'get' => ['summary' => 'List petty cash', 'parameters' => [$query('gibbonSchoolYearID', 'Year id')], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create petty cash record', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/petty-cash/{id}' => $item('petty cash record'),
            '/v1/finance/petty-cash/{id}/action' => ['post' => ['summary' => 'Mark petty cash repaid or refunded', 'parameters' => [$idParam('id', 'gibbonFinancePettyCashID')], 'responses' => ['200' => $ok, '403' => $err]]],
            '/v1/finance/fee-categories' => [
                'get' => ['summary' => 'List fee categories', 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create fee category', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/fee-categories/{id}' => array_merge(['get' => ['summary' => 'Get fee category', 'parameters' => [$idParam('id', 'gibbonFinanceFeeCategoryID')], 'responses' => ['200' => $ok]]], $item('fee category')),
            '/v1/finance/fees' => [
                'get' => ['summary' => 'List fees', 'parameters' => [['name' => 'gibbonSchoolYearID', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create fee', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/finance/fees/{id}' => [
                'get' => ['summary' => 'Get fee', 'parameters' => [$idParam('id', 'gibbonFinanceFeeID')], 'responses' => ['200' => $ok, '404' => $err]],
                'patch' => ['summary' => 'Update fee', 'parameters' => [$idParam('id', 'gibbonFinanceFeeID')], 'responses' => ['200' => $ok, '403' => $err]],
            ],
            '/v1/behaviour' => [
                'get' => ['summary' => 'List behaviour records', 'parameters' => [$query('gibbonSchoolYearID', 'Year id'), $query('type', 'Positive, Negative or Observation')], 'responses' => ['200' => $ok, '403' => $err]],
                'post' => ['summary' => 'Create one or many behaviour records', 'responses' => ['201' => $created, '403' => $err, '422' => $err]],
            ],
            '/v1/behaviour/{id}' => array_merge(['get' => ['summary' => 'Get behaviour record with follow-ups', 'parameters' => [$idParam('id', 'gibbonBehaviourID')], 'responses' => ['200' => $ok]]], $item('behaviour record')),
            '/v1/behaviour/{id}/follow-up' => ['post' => ['summary' => 'Add a follow-up comment', 'parameters' => [$idParam('id', 'gibbonBehaviourID')], 'responses' => ['201' => $created, '403' => $err]]],
        ];
    }
}
