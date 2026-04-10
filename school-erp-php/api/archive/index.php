<?php
/**
 * Archive API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();
require_role(['admin', 'superadmin']);

$action = $_GET['action'] ?? 'students';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = pagination_limit($_GET['limit'] ?? null);
$search = trim((string) ($_GET['search'] ?? ''));
$year = trim((string) ($_GET['year'] ?? ''));
$offset = ($page - 1) * $limit;

switch ($action) {
    case 'students':
        respond_archive(build_archived_students($search, $year, $page, $limit, $offset), $page, $limit);
        break;
    case 'staff':
        respond_archive(build_archived_staff($search, $year, $page, $limit, $offset), $page, $limit);
        break;
    case 'fees':
        respond_archive(build_archived_fees($search, $year, $page, $limit, $offset), $page, $limit);
        break;
    case 'exams':
        respond_archive(build_archived_exams($search, $year, $page, $limit, $offset), $page, $limit);
        break;
    case 'attendance':
        respond_archive(build_archived_attendance($search, $year, $page, $limit, $offset), $page, $limit);
        break;
    default:
        json_response(['error' => 'Invalid archive action'], 400);
}

function respond_archive($result, $page, $limit)
{
    $rows = $result['rows'] ?? [];
    $total = (int) ($result['total'] ?? 0);
    json_response([
        'archived' => $rows,
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
        ],
        'meta' => [
            'source' => $result['source'] ?? 'unknown',
        ],
    ]);
}

function build_archived_students($search, $year, $page, $limit, $offset)
{
    if (db_table_exists('archived_students')) {
        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(a.name LIKE ? OR a.admission_no LIKE ? OR a.discharge_reason LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        if ($year !== '') {
            $where[] = 'YEAR(COALESCE(a.discharge_date, a.archived_at)) = ?';
            $params[] = (int) $year;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT a.*,
                c.name AS class_name,
                COALESCE(a.discharge_date, a.archived_at) AS archive_date
                FROM archived_students a
                LEFT JOIN classes c ON a.class_id = c.id
                WHERE $whereClause
                ORDER BY COALESCE(a.discharge_date, a.archived_at) DESC
                LIMIT $limit OFFSET $offset";
        $countSql = "SELECT COUNT(*)
                     FROM archived_students a
                     LEFT JOIN classes c ON a.class_id = c.id
                     WHERE $whereClause";

        return [
            'rows' => db_fetchAll($sql, $params),
            'total' => db_count($countSql, $params),
            'source' => 'archived_students'
        ];
    }

    $where = [];
    $params = [];
    if (db_column_exists('students', 'discharge_date')) {
        $where[] = '(s.discharge_date IS NOT NULL OR ' . student_active_expr() . ' = 0)';
    } else {
        $where[] = student_active_expr() . ' = 0';
    }

    if ($search !== '') {
        $searchParts = ['s.name LIKE ?', 's.admission_no LIKE ?'];
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        if (db_column_exists('students', 'discharge_reason')) {
            $searchParts[] = 's.discharge_reason LIKE ?';
            $params[] = $searchParam;
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    $dateExpr = db_column_exists('students', 'discharge_date') ? 'COALESCE(s.discharge_date, s.created_at)' : 's.created_at';
    if ($year !== '') {
        $where[] = 'YEAR(' . $dateExpr . ') = ?';
        $params[] = (int) $year;
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT
            s.id,
            s.admission_no,
            s.name,
            s.class_id,
            " . column_expr('students', 'admission_date', 's', 's.created_at') . ",
            " . column_expr('students', 'discharge_date', 's', 'NULL') . ",
            " . column_expr('students', 'discharge_reason', 's', "''") . ",
            c.name AS class_name,
            $dateExpr AS archive_date
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE $whereClause
            ORDER BY $dateExpr DESC
            LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*)
                 FROM students s
                 LEFT JOIN classes c ON s.class_id = c.id
                 WHERE $whereClause";

    return [
        'rows' => db_fetchAll($sql, $params),
        'total' => db_count($countSql, $params),
        'source' => 'students'
    ];
}

function build_archived_staff($search, $year, $page, $limit, $offset)
{
    if (db_table_exists('archived_staff')) {
        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(a.name LIKE ? OR a.email LIKE ? OR a.employee_id LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        if ($year !== '') {
            $where[] = 'YEAR(a.archived_at) = ?';
            $params[] = (int) $year;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT a.*, a.archived_at AS archive_date
                FROM archived_staff a
                WHERE $whereClause
                ORDER BY a.archived_at DESC
                LIMIT $limit OFFSET $offset";
        $countSql = "SELECT COUNT(*) FROM archived_staff a WHERE $whereClause";

        $rows = array_map('normalize_staff_archive_row', db_fetchAll($sql, $params));
        return [
            'rows' => $rows,
            'total' => db_count($countSql, $params),
            'source' => 'archived_staff'
        ];
    }

    $where = ["u.role NOT IN ('student', 'parent')"];
    $params = [];
    if (db_column_exists('users', 'is_active')) {
        $where[] = 'u.is_active = 0';
    }
    if ($search !== '') {
        $searchParts = ['u.name LIKE ?', 'u.email LIKE ?'];
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        if (db_column_exists('users', 'employee_id')) {
            $searchParts[] = 'u.employee_id LIKE ?';
            $params[] = $searchParam;
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    $dateExpr = db_column_exists('users', 'updated_at') ? 'COALESCE(u.updated_at, u.created_at)' : 'u.created_at';
    if ($year !== '') {
        $where[] = 'YEAR(' . $dateExpr . ') = ?';
        $params[] = (int) $year;
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            " . column_expr('users', 'employee_id', 'u', 'NULL') . ",
            " . column_expr('users', 'department', 'u', "''") . ",
            " . column_expr('users', 'designation', 'u', "''") . ",
            $dateExpr AS archive_date
            FROM users u
            WHERE $whereClause
            ORDER BY $dateExpr DESC
            LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*) FROM users u WHERE $whereClause";

    $rows = array_map('normalize_staff_archive_row', db_fetchAll($sql, $params));
    return [
        'rows' => $rows,
        'total' => db_count($countSql, $params),
        'source' => 'users'
    ];
}

function build_archived_fees($search, $year, $page, $limit, $offset)
{
    $where = [];
    $params = [];

    if (db_column_exists('fees', 'is_active')) {
        $where[] = 'f.is_active = 0';
    } else {
        $where[] = 'COALESCE(f.paid_date, f.created_at) < ?';
        $params[] = current_academic_year_start();
    }

    if ($search !== '') {
        $where[] = '(s.name LIKE ? OR f.receipt_no LIKE ? OR f.fee_type LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if ($year !== '') {
        $where[] = 'YEAR(COALESCE(f.paid_date, f.created_at)) = ?';
        $params[] = (int) $year;
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT
            f.id,
            " . fee_column_expr('receipt_no', "'-'") . ",
            " . fee_column_expr('fee_type', "'General Fee'") . ",
            " . fee_column_expr('total_amount', '0') . ",
            " . fee_column_expr('amount_paid', '0') . ",
            " . fee_column_expr('payment_method', "'cash'") . ",
            " . fee_column_expr('paid_date') . ",
            " . fee_column_expr('month', "''") . ",
            " . fee_column_expr('year', 'NULL') . ",
            " . fee_column_expr('academic_year', "''") . ",
            s.name AS student_name,
            s.admission_no,
            c.name AS class_name,
            COALESCE(f.paid_date, f.created_at) AS archive_date
            FROM fees f
            LEFT JOIN students s ON f.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE $whereClause
            ORDER BY COALESCE(f.paid_date, f.created_at) DESC
            LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*)
                 FROM fees f
                 LEFT JOIN students s ON f.student_id = s.id
                 LEFT JOIN classes c ON s.class_id = c.id
                 WHERE $whereClause";

    return [
        'rows' => db_fetchAll($sql, $params),
        'total' => db_count($countSql, $params),
        'source' => 'fees'
    ];
}

function build_archived_exams($search, $year, $page, $limit, $offset)
{
    $where = [];
    $params = [];

    if (db_column_exists('exams', 'is_archived')) {
        $where[] = 'e.is_archived = 1';
    } else {
        $where[] = 'e.exam_date < ?';
        $params[] = current_academic_year_start();
    }

    if ($search !== '') {
        $where[] = '(e.name LIKE ? OR e.subject LIKE ? OR c.name LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if ($year !== '') {
        $where[] = 'YEAR(e.exam_date) = ?';
        $params[] = (int) $year;
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT
            e.id,
            e.name,
            e.subject,
            e.exam_date,
            e.max_marks,
            c.name AS class_name,
            e.exam_date AS archive_date
            FROM exams e
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE $whereClause
            ORDER BY e.exam_date DESC
            LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*)
                 FROM exams e
                 LEFT JOIN classes c ON e.class_id = c.id
                 WHERE $whereClause";

    return [
        'rows' => db_fetchAll($sql, $params),
        'total' => db_count($countSql, $params),
        'source' => 'exams'
    ];
}

function build_archived_attendance($search, $year, $page, $limit, $offset)
{
    $where = ['a.date < ?'];
    $params = [current_academic_year_start()];

    if ($search !== '') {
        $where[] = '(s.name LIKE ? OR s.admission_no LIKE ? OR c.name LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if ($year !== '') {
        $where[] = 'YEAR(a.date) = ?';
        $params[] = (int) $year;
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT
            a.id,
            a.date,
            a.status,
            " . attendance_column_expr('subject', "'-'") . ",
            " . attendance_column_expr('note', "'-'") . ",
            s.name AS student_name,
            s.admission_no,
            c.name AS class_name,
            a.date AS archive_date
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN classes c ON a.class_id = c.id
            WHERE $whereClause
            ORDER BY a.date DESC, s.name ASC
            LIMIT $limit OFFSET $offset";
    $countSql = "SELECT COUNT(*)
                 FROM attendance a
                 LEFT JOIN students s ON a.student_id = s.id
                 LEFT JOIN classes c ON a.class_id = c.id
                 WHERE $whereClause";

    return [
        'rows' => db_fetchAll($sql, $params),
        'total' => db_count($countSql, $params),
        'source' => 'attendance'
    ];
}

function column_expr($table, $column, $alias, $fallback = 'NULL')
{
    if (db_column_exists($table, $column)) {
        return $alias . '.`' . $column . '` AS `' . $column . '`';
    }
    return $fallback . ' AS `' . $column . '`';
}

function fee_column_expr($column, $fallback = 'NULL')
{
    return column_expr('fees', $column, 'f', $fallback);
}

function attendance_column_expr($column, $fallback = 'NULL')
{
    return column_expr('attendance', $column, 'a', $fallback);
}

function student_active_expr()
{
    return db_column_exists('students', 'is_active') ? 's.is_active' : '1';
}

function normalize_staff_archive_row($row)
{
    $row['role'] = normalize_role_name($row['role'] ?? '');
    return $row;
}
