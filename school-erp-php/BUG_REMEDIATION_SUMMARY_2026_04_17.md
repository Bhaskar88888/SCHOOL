# Bug Remediation Summary (Runtime Report)

Date: 2026-04-17

All the remaining defects listed in the runtime report have been addressed successfully.

## 1. Hostel API Schema Mismatch
- **Issue**: `Unknown column 'r.room_number' in 'order clause'` in `api/hostel/index.php`.
- **Fix**: Replaced `ORDER BY $roomNumberExpr` with `ORDER BY room_number`. Since `room_number` is already defined as an alias in the `SELECT` statement (via `$roomNumberExpr AS room_number`), using the alias directly avoids the explicit `r.` table prefix resolution error when `room_number` doesn't exist in the physical `hostel_rooms` table.

## 2. Notices API Ambiguous `is_active` Filter
- **Issue**: `Column 'is_active' in where clause is ambiguous` due to `$whereExtra` extending an unqualified `is_active = 1`.
- **Fix**: Verified and confirmed as resolved. In the current codebase, `api/notices/index.php` explicitly specifies `WHERE n.is_active = 1`, which perfectly prefixes the condition against the `notices` table `n`. The `$whereExtra` variable append properly avoids modifying `is_active` and simply applies the `expiry_date` condition.

## 3. `create-staff.php` Request Shape Robustness
- **Issue**: `Undefined array key "email"` and `Undefined array key "password"` when passing missing or poorly formatted payloads, followed by string length validation breaking. 
- **Fix**: Replaced direct `$data` usage with `$dataArray = is_array($data) ? $data : [];` and used safety checks for the array keys (`!empty($dataArray['email'])`) inside `api/auth/create-staff.php` before calling `Validator::email` and `Validator::password`. The core validator logic successfully coalesces to string downstream, resolving the error.

These changes ensure the API endpoints are fully functional and adhere to proper PHP 8 deprecation standards.
