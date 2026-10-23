<?php
require_once __DIR__ . '/php/db.php';
$results = [];
try {
    // Add late_threshold_minutes to attendance_sessions
    $pdo->exec("ALTER TABLE attendance_sessions ADD COLUMN IF NOT EXISTS late_threshold_minutes INT DEFAULT 15");
    $results[] = "✅ late_threshold_minutes added to attendance_sessions";
} catch(PDOException $e) {
    $results[] = "⚠️ attendance_sessions: " . $e->getMessage();
}
try {
    // Add schedule_reminder to users
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS schedule_reminder INT DEFAULT 0");
    $results[] = "✅ schedule_reminder added to users";
} catch(PDOException $e) {
    $results[] = "⚠️ users: " . $e->getMessage();
}
foreach ($results as $r) { echo $r . "\n"; }
