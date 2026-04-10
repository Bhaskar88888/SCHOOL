<?php
// Generic module page builder helper
// Each module page includes this template with $config set
$classes  = isset($needsClasses) ? db_fetchAll("SELECT id, name FROM classes ORDER BY name") : [];
$teachers = isset($needsTeachers) ? db_fetchAll("SELECT id, name FROM users WHERE role IN ('teacher','admin') AND is_active=1 ORDER BY name") : [];
$students = isset($needsStudents) ? db_fetchAll("SELECT s.id, s.name, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 ORDER BY s.name LIMIT 500") : [];
$staff    = isset($needsStaff) ? db_fetchAll("SELECT id, name FROM users WHERE role != 'student' AND is_active=1 ORDER BY name") : [];
