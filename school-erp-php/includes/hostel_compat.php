<?php

function hostel_sql_first_existing(string $table, array $columns, string $alias): ?string
{
    foreach ($columns as $column) {
        if (db_column_exists($table, $column)) {
            return $alias . '.' . $column;
        }
    }
    return null;
}

function hostel_sql_coalesce_existing(string $table, array $columns, string $alias, string $fallback): string
{
    $parts = [];
    foreach ($columns as $column) {
        if (db_column_exists($table, $column)) {
            $parts[] = $alias . '.' . $column;
        }
    }

    if (empty($parts)) {
        return $fallback;
    }

    if (count($parts) === 1) {
        return $parts[0];
    }

    return 'COALESCE(' . implode(', ', $parts) . ')';
}

function hostel_room_number_expr(string $alias = 'r'): string
{
    return hostel_sql_first_existing('hostel_rooms', ['room_number', 'room_no'], $alias) ?? "''";
}

function hostel_room_block_expr(string $alias = 'r'): string
{
    return db_column_exists('hostel_rooms', 'block') ? "COALESCE($alias.block, '')" : "''";
}

function hostel_room_type_join(string $roomAlias = 'r', string $roomTypeAlias = 'hrt'): string
{
    if (!db_table_exists('hostel_room_types') || !db_column_exists('hostel_rooms', 'room_type_id')) {
        return '';
    }

    return " LEFT JOIN hostel_room_types $roomTypeAlias ON $roomAlias.room_type_id = $roomTypeAlias.id ";
}

function hostel_room_type_expr(string $roomAlias = 'r', string $roomTypeAlias = 'hrt'): string
{
    if (db_column_exists('hostel_rooms', 'type')) {
        return "COALESCE($roomAlias.type, '')";
    }

    if (db_table_exists('hostel_room_types') && db_column_exists('hostel_rooms', 'room_type_id')) {
        return "COALESCE($roomTypeAlias.name, '')";
    }

    return "''";
}

function hostel_room_fee_expr(string $alias = 'r'): string
{
    return db_column_exists('hostel_rooms', 'monthly_fee') ? "COALESCE($alias.monthly_fee, 0)" : '0';
}

function hostel_room_active_condition(string $alias = 'r'): string
{
    return db_column_exists('hostel_rooms', 'is_active') ? "$alias.is_active = 1" : '1=1';
}

function hostel_allocation_active_condition(string $alias = 'a'): string
{
    $conditions = [];

    if (db_column_exists('hostel_allocations', 'status')) {
        $conditions[] = "UPPER(COALESCE($alias.status, '')) = 'ACTIVE'";
    }
    if (db_column_exists('hostel_allocations', 'is_active')) {
        $conditions[] = "$alias.is_active = 1";
    }

    if (empty($conditions)) {
        return '1=1';
    }

    return '(' . implode(' OR ', $conditions) . ')';
}

function hostel_allocation_start_expr(string $alias = 'a'): string
{
    return hostel_sql_coalesce_existing('hostel_allocations', ['check_in_date', 'allocated_date', 'allotment_date'], $alias, "$alias.created_at");
}

function hostel_allocation_end_expr(string $alias = 'a'): string
{
    return hostel_sql_coalesce_existing('hostel_allocations', ['check_out_date', 'vacated_date', 'vacated_on'], $alias, 'NULL');
}

function hostel_insert_row(string $table, array $payload): int
{
    $payload = db_filter_data_for_table($table, $payload);
    if (empty($payload)) {
        throw new RuntimeException("No compatible columns found for $table");
    }

    $columns = array_keys($payload);
    $placeholders = array_fill(0, count($columns), '?');

    return db_insert(
        "INSERT INTO $table (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
        array_values($payload)
    );
}

function hostel_update_row(string $table, array $payload, string $whereClause, array $whereParams = []): void
{
    $payload = db_filter_data_for_table($table, $payload);
    if (empty($payload)) {
        return;
    }

    $assignments = [];
    foreach (array_keys($payload) as $column) {
        $assignments[] = $column . ' = ?';
    }

    db_query(
        "UPDATE $table SET " . implode(', ', $assignments) . " WHERE $whereClause",
        array_merge(array_values($payload), $whereParams)
    );
}

function hostel_room_payload(array $data): array
{
    $roomNumber = sanitize((string) ($data['room_number'] ?? ''));

    return [
        'room_no' => $roomNumber,
        'room_number' => $roomNumber,
        'block' => sanitize((string) ($data['block'] ?? '')),
        'floor' => (string) ($data['floor'] ?? '1'),
        'capacity' => (int) ($data['capacity'] ?? 4),
        'type' => sanitize((string) ($data['type'] ?? 'double')),
        'monthly_fee' => (float) ($data['monthly_fee'] ?? 0),
        'status' => 'available',
        'is_active' => 1,
    ];
}

function hostel_allocation_payload(array $data, array $room = []): array
{
    $checkInDate = $data['check_in_date'] ?? date('Y-m-d');

    return [
        'room_id' => (int) ($data['room_id'] ?? 0),
        'student_id' => (int) ($data['student_id'] ?? 0),
        'room_type_id' => !empty($room['room_type_id']) ? (int) $room['room_type_id'] : null,
        'fee_structure_id' => ($data['fee_structure_id'] ?? '') !== '' ? (int) $data['fee_structure_id'] : null,
        'academic_year' => $data['academic_year'] ?? current_academic_year(),
        'bed_label' => sanitize((string) ($data['bed_label'] ?? 'A')),
        'allocated_date' => $checkInDate,
        'check_in_date' => $checkInDate,
        'allotment_date' => $checkInDate,
        'is_active' => 1,
        'status' => 'ACTIVE',
    ];
}

function hostel_vacate_payload(): array
{
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    return [
        'is_active' => 0,
        'status' => 'VACATED',
        'check_out_date' => $today,
        'vacated_date' => $today,
        'vacated_on' => $now,
    ];
}
