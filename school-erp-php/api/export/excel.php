<?php
/**
 * Excel Export API Endpoint
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/excel_export.php';

require_auth();

$module = $_GET['module'] ?? '';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;
$classId = $_GET['class_id'] ?? null;
$search = $_GET['search'] ?? null;

$filters = [];
if ($dateFrom) $filters['date_from'] = $dateFrom;
if ($dateTo) $filters['date_to'] = $dateTo;
if ($classId) $filters['class_id'] = $classId;
if ($search) $filters['search'] = $search;

switch ($module) {
    case 'students':
        ExcelExport::students($filters);
        break;
    case 'attendance':
        ExcelExport::attendance($filters);
        break;
    case 'fees':
        ExcelExport::fees($filters);
        break;
    case 'exams':
        ExcelExport::exam_results($filters);
        break;
    case 'staff':
        ExcelExport::staff();
        break;
    default:
        json_response(['error' => 'Invalid module. Use: students, attendance, fees, exams, staff'], 400);
}
