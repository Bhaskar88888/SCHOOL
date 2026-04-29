<?php

function build_mobile_user_payload(array $user)
{
    $role = normalize_role_name($user['role'] ?? '');
    $hasStudentUid = db_column_exists('students', 'student_uid');
    $hasAdmissionNo = db_column_exists('students', 'admission_no');
    $hasStudentSection = db_column_exists('students', 'section');
    $hasClassTable = db_table_exists('classes');
    $hasClassSection = $hasClassTable && db_column_exists('classes', 'section');
    $studentUidExpr = $hasStudentUid ? 's.student_uid' : 'NULL AS student_uid';
    $admissionNoExpr = $hasAdmissionNo ? 's.admission_no' : 'NULL AS admission_no';
    $studentSectionExpr = $hasStudentSection ? "COALESCE(s.section, '') AS section_name" : "'' AS section_name";
    $classJoin = $hasClassTable ? 'LEFT JOIN classes c ON s.class_id = c.id' : '';
    $classNameExpr = $hasClassTable ? 'c.name AS class_name' : 'NULL AS class_name';
    $classSectionExpr = $hasClassSection ? "COALESCE(c.section, '') AS class_section" : "'' AS class_section";

    $payload = [
        'id' => (int) ($user['id'] ?? 0),
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? null,
        'role' => $role,
        'avatar' => $user['avatar'] ?? null,
    ];

    if ($role === 'student') {
        $student = db_fetch(
            "SELECT s.id, $studentUidExpr, $admissionNoExpr, s.class_id, $studentSectionExpr,
                    $classNameExpr, $classSectionExpr
             FROM students s
             $classJoin
             WHERE s.user_id = ? AND s.is_active = 1
             LIMIT 1",
            [$payload['id']]
        );

        if ($student) {
            $studentIdentifier = $student['student_uid'] ?: ($student['admission_no'] ?: null);
            $payload['student_row_id'] = (int) $student['id'];
            $payload['student_id'] = $studentIdentifier;
            $payload['student_uid'] = $student['student_uid'] ?: null;
            $payload['admission_no'] = $student['admission_no'] ?: null;
            $payload['class_id'] = (int) ($student['class_id'] ?? 0);
            $payload['class_name'] = $student['class_name'] ?: null;
            $payload['section'] = $student['section_name'] ?: ($student['class_section'] ?: null);
        }
    }

    if ($role === 'parent') {
        $children = db_fetchAll(
            "SELECT s.id, s.name, $studentUidExpr, $admissionNoExpr, s.class_id, $studentSectionExpr,
                    $classNameExpr, $classSectionExpr
             FROM students s
             $classJoin
             WHERE s.parent_user_id = ? AND s.is_active = 1
             ORDER BY s.name",
            [$payload['id']]
        );

        if (empty($children) && db_column_exists('students', 'parent_phone') && !empty($user['phone'])) {
            $children = db_fetchAll(
                "SELECT s.id, s.name, $studentUidExpr, $admissionNoExpr, s.class_id, $studentSectionExpr,
                        $classNameExpr, $classSectionExpr
                 FROM students s
                 $classJoin
                 WHERE s.parent_phone = ? AND s.is_active = 1
                 ORDER BY s.name",
                [$user['phone']]
            );
        }

        $payload['children'] = array_map(static function ($child) {
            return [
                'id' => (int) ($child['id'] ?? 0),
                'name' => $child['name'] ?? '',
                'student_id' => $child['student_uid'] ?: ($child['admission_no'] ?: null),
                'student_uid' => $child['student_uid'] ?: null,
                'admission_no' => $child['admission_no'] ?: null,
                'class_id' => (int) ($child['class_id'] ?? 0),
                'class_name' => $child['class_name'] ?: null,
                'section' => $child['section_name'] ?: ($child['class_section'] ?: null),
            ];
        }, $children ?: []);
        $payload['children_count'] = count($payload['children']);
        $payload['student_id'] = $payload['children_count'] === 1 ? ($payload['children'][0]['student_id'] ?? null) : null;
        $payload['is_default_password'] = !empty($user['portal_generated']);
    }

    if (in_array($role, ['teacher', 'staff', 'hr', 'accounts', 'librarian', 'canteen', 'conductor', 'driver'], true)) {
        $payload['employee_id'] = $user['employee_id'] ?? null;
        $payload['department_id'] = isset($user['department_id']) ? (int) $user['department_id'] : null;
        $payload['department'] = $user['department'] ?? null;
    }

    return $payload;
}

function build_mobile_portal_url($role)
{
    $role = normalize_role_name($role);
    $appUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : '';

    $panelMap = [
        'superadmin' => '/dashboard.php',
        'admin' => '/dashboard.php',
        'teacher' => '/dashboard.php',
        'student' => '/dashboard.php',
        'parent' => '/dashboard.php',
        'staff' => '/dashboard.php',
        'accounts' => '/dashboard.php',
        'librarian' => '/dashboard.php',
        'hr' => '/dashboard.php',
        'canteen' => '/dashboard.php',
        'conductor' => '/dashboard.php',
        'driver' => '/dashboard.php',
    ];

    return $appUrl . ($panelMap[$role] ?? '/dashboard.php');
}
