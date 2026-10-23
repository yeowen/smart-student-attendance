<?php
require_once __DIR__ . '/conf/php/db.php';

$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';
$token     = trim($_GET['token'] ?? '');

// ── Load form config ──
$configFile = __DIR__ . '/conf/form_config.json';
$formConfig = [
    'fields'        => ['student_number' => true, 'full_name' => true, 'course' => true, 'year_level' => true, 'email' => false, 'remarks' => false],
    'custom_fields' => [],
    'settings'      => ['confirm' => true, 'success_page' => true, 'block_duplicate' => true, 'masterlist_only' => true]
];
if (file_exists($configFile)) {
    $raw = json_decode(file_get_contents($configFile), true);
    if (is_array($raw)) $formConfig = $raw;
}

$session     = null;
$errorMsg    = '';
$successMode = false;

if ($isPreview) {
    // Demo session for preview
    $session = [
        'id'           => 0,
        'section_name' => 'BSIT 3A',
        'subject_name' => 'IT 311 · Advanced Web Systems',
        'session_date' => date('Y-m-d'),
        'start_time'   => '08:00:00',
        'end_time'     => '09:30:00',
        'status'       => 'active',
        'qr_token'     => 'PREVIEW-MODE',
    ];
} elseif (!empty($token)) {
    // Validate real token
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.qr_token, s.status, s.session_date, s.start_time, s.end_time,
                   sec.section_name, sec.subject_name
            FROM attendance_sessions s
            JOIN sections sec ON s.section_id = sec.id
            WHERE s.qr_token = ?
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            $errorMsg = 'Invalid or expired QR code. Please ask your professor to regenerate.';
        } else {
            $now = new DateTime();
            $sessionEnd   = new DateTime($session['session_date'] . ' ' . ($session['end_time'] ?? '23:59:59'));
            $sessionStart = new DateTime($session['session_date'] . ' ' . ($session['start_time'] ?? '00:00:00'));
            if ($session['status'] !== 'active' || $now > $sessionEnd) {
                $errorMsg = 'This attendance session has ended and is now inactive. Attendance is closed.';
            } elseif ($now < $sessionStart) {
                $errorMsg = 'This attendance session has not started yet. Check-in opens at ' . fmtTime($session['start_time']) . '.';
            }
        }
    } catch (PDOException $e) {
        $errorMsg = 'Database error. Please try again.';
    }
} else {
    $errorMsg = 'No QR token provided. Please scan a valid QR code.';
}

// ── Format time helper ──
function fmtTime($t) {
    if (!$t) return '--';
    $parts = explode(':', $t);
    $h = (int)$parts[0]; $m = $parts[1] ?? '00';
    $a = $h >= 12 ? 'PM' : 'AM';
    $h = $h % 12 ?: 12;
    return "$h:$m $a";
}
$dateLabel = $session ? date('l, F j, Y', strtotime($session['session_date'])) : '';
$timeLabel = $session ? fmtTime($session['start_time']) . ' – ' . fmtTime($session['end_time']) : '';

$showCourse    = !empty($formConfig['fields']['course']);
$showYearLevel = !empty($formConfig['fields']['year_level']);
$showEmail     = !empty($formConfig['fields']['email']);
$showRemarks   = !empty($formConfig['fields']['remarks']);
$customFields  = $formConfig['custom_fields'] ?? [];
$settingConfirm     = !empty($formConfig['settings']['confirm']);
$settingSuccessPage = !empty($formConfig['settings']['success_page']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="/SmartStudent_Attendance/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Check-In | Smart Attendance</title>
    <link rel="icon" type="image/png" href="pub/assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #0c4a6e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Preview Banner */
        .preview-banner {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #f59e0b;
            color: #78350f;
            text-align: center;
            padding: 10px;
            font-size: 13px;
            font-weight: 700;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .preview-banner a {
            color: #78350f;
            font-weight: 800;
            text-decoration: underline;
        }

        /* Card */
        .checkin-card {
            background: #ffffff;
            border-radius: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            margin-top: <?= $isPreview ? '50px' : '0' ?>;
        }

        /* Header */
        .card-header {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 60%, #0891b2 100%);
            padding: 28px 24px 24px;
            color: white;
        }
        .logo-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .logo-badge {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .logo-text .app-name  { font-size: 13px; font-weight: 800; display: block; }
        .logo-text .app-sub   { font-size: 10.5px; opacity: 0.7; font-weight: 500; }

        .session-info-box {
            background: rgba(255,255,255,0.12);
            border-radius: 14px;
            padding: 14px 16px;
            backdrop-filter: blur(4px);
        }
        .session-subject {
            font-size: 16px; font-weight: 800;
            margin-bottom: 8px; line-height: 1.3;
        }
        .session-meta {
            display: flex; flex-direction: column; gap: 5px;
        }
        .session-meta-row {
            display: flex; align-items: center; gap: 8px;
            font-size: 12.5px; font-weight: 500; opacity: 0.9;
        }
        .meta-icon { font-size: 13px; flex-shrink: 0; }

        /* Body */
        .card-body { padding: 24px; }

        .form-title {
            font-size: 15px; font-weight: 800; color: #0f172a;
            margin-bottom: 4px;
        }
        .form-subtitle {
            font-size: 12.5px; color: #64748b; margin-bottom: 20px;
        }

        /* Form Fields */
        .field-group { margin-bottom: 16px; }
        .field-label {
            font-size: 12px; font-weight: 700; color: #374151;
            margin-bottom: 6px; display: flex; align-items: center; gap: 6px;
        }
        .field-label .req { color: #ef4444; }
        .auto-tag {
            font-size: 9.5px; font-weight: 700;
            background: #dbeafe; color: #1d4ed8;
            padding: 2px 6px; border-radius: 4px;
        }
        .field-input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: #fff;
        }
        .field-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .field-input.auto-filled {
            background: #f0fdf4;
            border-color: #86efac;
            color: #15803d;
            font-weight: 600;
        }
        .field-input.error-field {
            border-color: #f87171;
            background: #fff5f5;
        }
        .field-input[readonly] { cursor: default; }
        textarea.field-input { resize: none; height: 80px; padding-top: 10px; }

        /* Verify Row */
        .verify-row {
            display: flex; gap: 8px; align-items: flex-start;
        }
        .verify-row .field-input { flex: 1; }
        .btn-verify {
            background: #2563eb; color: white;
            border: none; border-radius: 12px;
            padding: 12px 16px; font-size: 13px; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-verify:hover { background: #1d4ed8; }
        .btn-verify:disabled { background: #94a3b8; cursor: not-allowed; }

        /* Status badge below student number */
        .student-status {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }
        .student-status.found    { background: #dcfce7; color: #15803d; display: flex; align-items: center; gap: 6px; }
        .student-status.notfound { background: #fee2e2; color: #b91c1c; display: flex; align-items: center; gap: 6px; }

        /* Auto-fetched fields group */
        .autofetch-group {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 16px;
            display: none;
        }
        .autofetch-group.visible { display: block; }
        .autofetch-header {
            font-size: 11px; font-weight: 700; color: #94a3b8;
            letter-spacing: 0.5px; margin-bottom: 12px;
            text-transform: uppercase;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 14px;
            padding: 15px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.45); }
        .btn-submit:disabled { background: #94a3b8; box-shadow: none; cursor: not-allowed; transform: none; }

        /* Spinner inside button */
        .spinner {
            width: 16px; height: 16px;
            border: 2.5px solid rgba(255,255,255,0.4);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Error/Notice card */
        .notice-card {
            text-align: center;
            padding: 32px 24px;
        }
        .notice-icon {
            font-size: 52px;
            margin-bottom: 14px;
        }
        .notice-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .notice-msg   { font-size: 13.5px; color: #64748b; line-height: 1.6; }

        /* Success Screen */
        #successScreen {
            display: none;
            text-align: center;
            padding: 32px 24px;
        }
        .success-anim {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0%   { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-title  { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .success-sub    { font-size: 13.5px; color: #64748b; margin-bottom: 20px; line-height: 1.6; }
        .success-detail {
            background: #f0fdf4; border: 1.5px solid #bbf7d0;
            border-radius: 12px; padding: 14px; text-align: left;
        }
        .success-detail-row {
            display: flex; justify-content: space-between;
            font-size: 12.5px; padding: 4px 0;
        }
        .success-detail-row .label { color: #64748b; font-weight: 500; }
        .success-detail-row .value { color: #15803d; font-weight: 700; }

        /* Footer */
        .card-footer {
            padding: 14px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 11.5px;
            color: #94a3b8;
            font-weight: 500;
        }

        /* Duplicate warning */
        .duplicate-notice {
            background: #fef3c7;
            border: 1.5px solid #fde68a;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12.5px;
            font-weight: 600;
            color: #b45309;
            margin-bottom: 14px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        /* Photo / Uniform Upload Styles */
        .photo-upload-container {
            margin-top: 6px;
        }
        .photo-dropzone {
            border: 2px dashed #93c5fd;
            background: #f8fafc;
            border-radius: 14px;
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .photo-dropzone:hover {
            background: #eff6ff;
            border-color: #2563eb;
            transform: translateY(-1px);
        }
        .photo-icon-circle {
            width: 44px;
            height: 44px;
            background: #eff6ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            border: 1px solid #bfdbfe;
        }
        .photo-prompt-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 3px;
        }
        .photo-prompt-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .btn-choose-photo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            pointer-events: none;
        }
        .photo-preview-box {
            border: 1.5px solid #bbf7d0;
            background: #f0fdf4;
            border-radius: 14px;
            padding: 12px;
        }
        .photo-preview-img-wrapper {
            position: relative;
            width: 100%;
            max-height: 240px;
            border-radius: 10px;
            overflow: hidden;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-preview-img-wrapper img {
            width: 100%;
            max-height: 240px;
            object-fit: contain;
            display: block;
        }
        .photo-preview-badge {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(16, 185, 129, 0.9);
            color: #fff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            backdrop-filter: blur(4px);
        }
        .photo-preview-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .photo-file-name {
            font-size: 11.5px;
            color: #166534;
            font-weight: 600;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-photo-action {
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-photo-action.retake {
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-photo-action.retake:hover {
            background: #bae6fd;
        }
        .btn-photo-action.remove {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-photo-action.remove:hover {
            background: #fecaca;
        }

        /* ── Modern Duplicate Check-in Modal ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 99999;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .modal-dialog-box {
            background: #ffffff;
            border-radius: 22px;
            max-width: 410px;
            width: 100%;
            padding: 28px 24px 24px;
            text-align: center;
            box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.45);
            transform: scale(0.92) translateY(12px);
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }
        .modal-overlay.active .modal-dialog-box {
            transform: scale(1) translateY(0);
        }
        .modal-warn-icon {
            width: 62px;
            height: 62px;
            background: #fef3c7;
            color: #d97706;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 0 0 8px #fef9c3;
        }
        .modal-warn-title {
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .modal-warn-msg {
            font-size: 13.5px;
            color: #475569;
            line-height: 1.55;
            margin-bottom: 18px;
        }
        .modal-record-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 16px;
            margin-bottom: 22px;
            text-align: left;
            font-size: 13px;
        }
        .modal-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 7px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .modal-detail-row:last-child {
            border-bottom: none;
        }
        .modal-detail-row .lbl {
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
        }
        .modal-detail-row .val {
            color: #0f172a;
            font-weight: 700;
            font-size: 12.5px;
            text-align: right;
            max-width: 65%;
            word-break: break-word;
        }
        .modal-badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .modal-badge-status.present {
            background: #dcfce7;
            color: #15803d;
        }
        .modal-badge-status.late {
            background: #fef3c7;
            color: #b45309;
        }
        .modal-btn-confirm {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            font-family: inherit;
        }
        .modal-btn-confirm:hover {
            background: #1d4ed8;
        }
        .modal-btn-confirm:active {
            transform: scale(0.98);
        }

        @media (max-width: 480px) {
            body { padding: 10px; align-items: flex-start; }
            .checkin-card { border-radius: 18px; margin-top: <?= $isPreview ? '54px' : '10px' ?>; }
            .card-header, .card-body { padding-left: 18px !important; padding-right: 18px !important; }
            .preview-banner { padding: 8px 10px; font-size: 11px; flex-wrap: wrap; }
            .modal-record-details { padding: 10px 12px; }
        }
    </style>
</head>
<body>

<?php if ($isPreview): ?>
<div class="preview-banner">
    👁️ PREVIEW MODE — This is how students will see the check-in form.
    <a href="pub/html/student_form_editor.html">← Back to Editor</a>
</div>
<?php endif; ?>

<div class="checkin-card">

    <?php if ($errorMsg): ?>
    <!-- Error State -->
    <div class="card-header" style="padding: 20px 24px;">
        <div class="logo-row">
            <div class="logo-badge">
                <img src="pub/assets/logo.png" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
            </div>
            <div class="logo-text">
                <span class="app-name">Smart Attendance</span>
                <span class="app-sub">QR CHECK-IN SYSTEM</span>
            </div>
        </div>
    </div>
    <div class="notice-card">
        <div class="notice-icon"><?= empty($token) ? '🔑' : '⚠️' ?></div>
        <div class="notice-title"><?= empty($token) ? 'Enter Attendance Code' : 'Session Unavailable' ?></div>
        <div class="notice-msg"><?= empty($token) ? 'Please enter the attendance session code provided by your instructor:' : htmlspecialchars($errorMsg) ?></div>
        <?php if (empty($token)): ?>
        <form method="GET" action="checkin/" onsubmit="const c = this.tok.value.trim(); if(!c){return false;} window.location.href='checkin/' + encodeURIComponent(c); return false;" style="margin-top: 16px; display: flex; flex-direction: column; gap: 10px;">
            <input type="text" name="tok" placeholder="e.g. QR-15CF6F530F23" required style="padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-size: 14px; text-align: center; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <button type="submit" class="submit-btn" style="width: 100%; border: none; cursor: pointer; padding: 12px; border-radius: 10px; background: #2563eb; color: #fff; font-weight: 700; font-size: 14px;">Proceed to Check-In →</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-footer">Smart Student Attendance System</div>

    <?php else: ?>
    <!-- Normal / Preview State -->
    <div class="card-header">
        <div class="logo-row">
            <div class="logo-badge">
                <img src="pub/assets/logo.png" alt="Logo" style="width: 24px; height: 24px; object-fit: contain;">
            </div>
            <div class="logo-text">
                <span class="app-name">Smart Attendance</span>
                <span class="app-sub">QR CHECK-IN SYSTEM</span>
            </div>
        </div>
        <div class="session-info-box">
            <div class="session-subject"><?= htmlspecialchars($session['subject_name']) ?></div>
            <div class="session-meta">
                <div class="session-meta-row"><span class="meta-icon">👥</span><span>Section: <strong><?= htmlspecialchars($session['section_name']) ?></strong></span></div>
                <div class="session-meta-row"><span class="meta-icon">📅</span><span><?= htmlspecialchars($dateLabel) ?></span></div>
                <div class="session-meta-row"><span class="meta-icon">🕐</span><span><?= htmlspecialchars($timeLabel) ?></span></div>
                <?php if ($isPreview): ?>
                <div class="session-meta-row" style="margin-top:4px;"><span style="background:rgba(245,158,11,0.3);color:#fbbf24;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px;">PREVIEW MODE</span></div>
                <?php else: ?>
                <div class="session-meta-row" style="margin-top:4px;"><span style="background:rgba(34,197,94,0.25);color:#86efac;font-size:10px;font-weight:700;padding:2px 8px;border-radius:6px;">● ACTIVE</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Success screen (hidden until submit) -->
        <div id="successScreen">
            <div class="success-anim">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
            </div>
            <div class="success-title">Attendance Recorded! ✅</div>
            <div class="success-sub">Your attendance has been successfully logged.<br>You may now close this window.</div>
            <div class="success-detail" id="successDetail"></div>
        </div>

        <!-- Main Form -->
        <div id="mainForm">
            <div class="form-title">Student Check-In</div>
            <div class="form-subtitle">Enter your Student Number to verify and submit attendance.</div>

            <div class="duplicate-notice" id="duplicateNotice">
                ⚠️ You have already submitted attendance for this session.
            </div>

            <!-- Student Number -->
            <div class="field-group">
                <label class="field-label">
                    Student Number <span class="req">*</span>
                </label>
                <div class="verify-row">
                    <input type="text" id="studentNumberInput" class="field-input"
                           placeholder="e.g. DEM0120260020"
                           autocomplete="off" autocapitalize="characters" spellcheck="false">
                    <button class="btn-verify" id="verifyBtn" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Verify
                    </button>
                </div>
                <div class="student-status" id="studentStatus"></div>
            </div>

            <!-- Auto-fetched fields (shown after verification) -->
            <div class="autofetch-group" id="autofetchGroup">
                <div class="autofetch-header">✅ Verified from Masterlist</div>

                <!-- Full Name — always shown -->
                <div class="field-group">
                    <label class="field-label">Full Name <span class="auto-tag">auto</span></label>
                    <input type="text" id="fullNameInput" class="field-input auto-filled" readonly placeholder="—">
                </div>

                <?php if ($showCourse): ?>
                <div class="field-group">
                    <label class="field-label">Course <span class="auto-tag">auto</span></label>
                    <input type="text" id="courseInput" class="field-input auto-filled" readonly placeholder="—">
                </div>
                <?php endif; ?>

                <?php if ($showYearLevel): ?>
                <div class="field-group">
                    <label class="field-label">Year Level <span class="auto-tag">auto</span></label>
                    <input type="text" id="yearLevelInput" class="field-input auto-filled" readonly placeholder="—">
                </div>
                <?php endif; ?>

                <?php if ($showEmail): ?>
                <div class="field-group">
                    <label class="field-label">Email Address <span class="auto-tag">auto</span></label>
                    <input type="text" id="emailInput" class="field-input auto-filled" readonly placeholder="—">
                </div>
                <?php endif; ?>
            </div>

            <?php if ($showRemarks): ?>
            <div class="field-group" id="remarksGroup" style="display:none;">
                <label class="field-label">Remarks / Note <span style="font-size:10px;color:#94a3b8;">(optional)</span></label>
                <textarea class="field-input" id="remarksInput" placeholder="e.g. Reason for late, etc."></textarea>
            </div>
            <?php endif; ?>

            <?php foreach ($customFields as $i => $cf): ?>
            <div class="field-group" id="customGroup<?= $i ?>" style="display:none;">
                <label class="field-label">
                    <?= htmlspecialchars($cf['label']) ?>
                    <?php if (!empty($cf['required'])): ?><span class="req">*</span><?php else: ?><span style="font-size:10.5px;color:#94a3b8;font-weight:600;">(optional)</span><?php endif; ?>
                </label>
                <?php if ($cf['type'] === 'image'): ?>
                    <div class="photo-upload-container" id="photoContainer<?= $i ?>">
                        <div class="photo-dropzone" id="photoDropzone<?= $i ?>" onclick="document.getElementById('customField<?= $i ?>').click()">
                            <input type="file" id="customField<?= $i ?>" data-type="image" data-label="<?= htmlspecialchars($cf['label']) ?>" data-required="<?= !empty($cf['required']) ? '1' : '0' ?>" accept="image/*" capture="environment" style="display:none;" onchange="handleImageSelected(this, <?= $i ?>)">
                            <div class="photo-dropzone-content">
                                <div class="photo-icon-circle">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                </div>
                                <div class="photo-prompt-title">Take Photo or Choose File</div>
                                <div class="photo-prompt-sub">Clear photo showing your school uniform / attire</div>
                                <button type="button" class="btn-choose-photo">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    Snap / Choose Photo
                                </button>
                            </div>
                        </div>

                        <div class="photo-preview-box" id="photoPreviewBox<?= $i ?>" style="display:none;">
                            <div class="photo-preview-img-wrapper">
                                <img id="photoPreviewImg<?= $i ?>" src="" alt="Uniform Preview">
                                <span class="photo-preview-badge">✓ Photo Attached</span>
                            </div>
                            <div class="photo-preview-actions">
                                <span class="photo-file-name" id="photoFileName<?= $i ?>">photo.jpg</span>
                                <div style="display:flex;gap:8px;">
                                    <button type="button" class="btn-photo-action retake" onclick="document.getElementById('customField<?= $i ?>').click()">Retake Photo</button>
                                    <button type="button" class="btn-photo-action remove" onclick="removeSelectedPhoto(<?= $i ?>)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($cf['type'] === 'textarea'): ?>
                    <textarea class="field-input" id="customField<?= $i ?>" data-type="textarea" data-label="<?= htmlspecialchars($cf['label']) ?>" data-required="<?= !empty($cf['required']) ? '1' : '0' ?>" placeholder="Type here..."></textarea>
                <?php elseif ($cf['type'] === 'select'): ?>
                    <select class="field-input" id="customField<?= $i ?>" data-type="select" data-label="<?= htmlspecialchars($cf['label']) ?>" data-required="<?= !empty($cf['required']) ? '1' : '0' ?>">
                        <option value="">— Select —</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                <?php elseif ($cf['type'] === 'number'): ?>
                    <input type="number" class="field-input" id="customField<?= $i ?>" data-type="number" data-label="<?= htmlspecialchars($cf['label']) ?>" data-required="<?= !empty($cf['required']) ? '1' : '0' ?>" placeholder="Enter number...">
                <?php else: ?>
                    <input type="text" class="field-input" id="customField<?= $i ?>" data-type="text" data-label="<?= htmlspecialchars($cf['label']) ?>" data-required="<?= !empty($cf['required']) ? '1' : '0' ?>" placeholder="Enter value...">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Submit -->
            <button class="btn-submit" id="submitBtn" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" id="submitIcon">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
                <div class="spinner" id="submitSpinner"></div>
                <span id="submitBtnText">Submit Attendance</span>
            </button>
        </div>
    </div>

    <div class="card-footer">
        🔒 Smart Student Attendance &nbsp;·&nbsp; Secure QR Check-In
    </div>
    <?php endif; ?>
</div>

<!-- ── Modern Duplicate Attendance Alert Modal ── -->
<div id="duplicateModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="dupModalTitle">
    <div class="modal-dialog-box">
        <div class="modal-warn-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        </div>
        <div class="modal-warn-title" id="dupModalTitle">Attendance Already Recorded</div>
        <div class="modal-warn-msg">
            This Student Number has already submitted attendance for this class session. Duplicate check-ins are not permitted.
        </div>
        <div class="modal-record-details">
            <div class="modal-detail-row">
                <span class="lbl">Student No:</span>
                <span class="val" id="dupModalStudentNumber">—</span>
            </div>
            <div class="modal-detail-row">
                <span class="lbl">Student Name:</span>
                <span class="val" id="dupModalStudentName">—</span>
            </div>
            <div class="modal-detail-row">
                <span class="lbl">Status:</span>
                <span class="val"><span class="modal-badge-status present" id="dupModalStatus">✅ Present</span></span>
            </div>
            <div class="modal-detail-row" id="dupModalTimeRow">
                <span class="lbl">Time Recorded:</span>
                <span class="val" id="dupModalTime">—</span>
            </div>
        </div>
        <button type="button" class="modal-btn-confirm" id="closeDuplicateModalBtn">Understood</button>
    </div>
</div>

<script>
const SESSION_ID   = <?= json_encode($session['id'] ?? 0) ?>;
const QR_TOKEN     = <?= json_encode($token) ?>;
const IS_PREVIEW   = <?= json_encode($isPreview) ?>;
const SETTING_CONFIRM      = <?= json_encode($settingConfirm) ?>;

// Immediately hide the token from the browser address bar so students cannot copy or share the token link
if (window.history && window.history.replaceState && !IS_PREVIEW) {
    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState({}, document.title, cleanUrl);
}
const SETTING_SUCCESS_PAGE = <?= json_encode($settingSuccessPage) ?>;
const BLOCK_DUPLICATE      = <?= json_encode(!empty($formConfig['settings']['block_duplicate'])) ?>;
const MASTERLIST_ONLY      = <?= json_encode(!empty($formConfig['settings']['masterlist_only'])) ?>;
const SHOW_REMARKS    = <?= json_encode($showRemarks) ?>;
const CUSTOM_COUNT    = <?= json_encode(count($customFields)) ?>;

let verifiedStudentId   = null;
let verifiedStudentData = null;

const studentInput  = document.getElementById('studentNumberInput');
const verifyBtn     = document.getElementById('verifyBtn');
const studentStatus = document.getElementById('studentStatus');
const autofetchGrp  = document.getElementById('autofetchGroup');
const submitBtn     = document.getElementById('submitBtn');
const submitBtnText = document.getElementById('submitBtnText');
const remarksGrp    = document.getElementById('remarksGroup');
const duplicateModal = document.getElementById('duplicateModal');
const closeDuplicateModalBtn = document.getElementById('closeDuplicateModalBtn');

// ── Duplicate Check-In Modal Functions ──
function showDuplicateModal(student, scannedAt, status) {
    if (!duplicateModal) return;

    const sNum = (student && (student.student_number || student.studentNumber)) 
        || studentInput?.value.trim() || '—';
    const sName = (student && student.first_name && student.last_name)
        ? (student.last_name + ', ' + student.first_name)
        : (document.getElementById('fullNameInput')?.value || '—');

    const sNumEl = document.getElementById('dupModalStudentNumber');
    const sNameEl = document.getElementById('dupModalStudentName');
    const sStatEl = document.getElementById('dupModalStatus');
    const sTimeEl = document.getElementById('dupModalTime');
    const sTimeRow = document.getElementById('dupModalTimeRow');

    if (sNumEl) sNumEl.textContent = sNum;
    if (sNameEl) sNameEl.textContent = sName;
    
    const rawStat = (status || 'present').toLowerCase();
    if (sStatEl) {
        sStatEl.className = 'modal-badge-status ' + (rawStat === 'late' ? 'late' : 'present');
        sStatEl.innerHTML = (rawStat === 'late' ? '⚠️ Late' : '✅ Present');
    }

    if (scannedAt) {
        if (sTimeEl) sTimeEl.textContent = scannedAt;
        if (sTimeRow) sTimeRow.style.display = 'flex';
    } else {
        if (sTimeRow) sTimeRow.style.display = 'none';
    }

    duplicateModal.classList.add('active');
}

function hideDuplicateModal() {
    if (duplicateModal) duplicateModal.classList.remove('active');
}

closeDuplicateModalBtn?.addEventListener('click', hideDuplicateModal);
duplicateModal?.addEventListener('click', (e) => {
    if (e.target === duplicateModal) hideDuplicateModal();
});

// Reset verification if student number is modified
studentInput?.addEventListener('input', () => {
    if (verifiedStudentId !== null) {
        verifiedStudentId = null;
        verifiedStudentData = null;
        submitBtn.disabled = true;
        if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
        autofetchGrp?.classList.remove('visible');
        if (remarksGrp) remarksGrp.style.display = 'none';
        hideCustomFields();
        if (studentStatus) studentStatus.style.display = 'none';
        const dn = document.getElementById('duplicateNotice');
        if (dn) dn.style.display = 'none';
    }
});

// ── Verify Button ──
verifyBtn?.addEventListener('click', async () => {
    const snum = studentInput?.value.trim();
    if (!snum) { studentInput?.focus(); return; }

    if (IS_PREVIEW) {
        // Mock verify in preview mode
        simulateVerify(snum);
        return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = '...';
    studentStatus.className = 'student-status';
    studentStatus.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('action', 'verify_student');
        fd.append('student_number', snum);
        fd.append('session_id', SESSION_ID);
        fd.append('qr_token', QR_TOKEN);

        const res  = await fetch('conf/php/student_checkin_data.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.student) {
            const s = data.student;
            verifiedStudentId   = s.id;
            verifiedStudentData = s;

            document.getElementById('fullNameInput').value   = s.last_name + ', ' + s.first_name;
            if (document.getElementById('courseInput'))    document.getElementById('courseInput').value    = s.course    || '—';
            if (document.getElementById('yearLevelInput')) document.getElementById('yearLevelInput').value = s.year_level || '—';
            if (document.getElementById('emailInput'))     document.getElementById('emailInput').value     = s.email     || '—';

            autofetchGrp.classList.add('visible');
            if (remarksGrp) remarksGrp.style.display = 'block';
            showCustomFields();

            studentStatus.className = 'student-status found';
            studentStatus.innerHTML = '✅ ' + s.last_name + ', ' + s.first_name + ' — found in masterlist';
            studentStatus.style.display = 'flex';

            // Check duplicate attendance
            if (data.already_submitted) {
                const dn = document.getElementById('duplicateNotice');
                if (dn) {
                    dn.innerHTML = '⚠️ <strong>Notice:</strong> This student number has already submitted attendance for this session.';
                    dn.style.display = 'flex';
                }
                submitBtn.disabled = true;
                if (submitBtnText) submitBtnText.textContent = 'Attendance Already Submitted';
                showDuplicateModal(s, data.scanned_at, data.record_status);
            } else {
                const dn = document.getElementById('duplicateNotice');
                if (dn) dn.style.display = 'none';
                submitBtn.disabled = false;
                if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
            }
        } else {
            studentStatus.className = 'student-status notfound';
            studentStatus.innerHTML = '❌ ' + (data.message || 'Student not found in masterlist');
            studentStatus.style.display = 'flex';
            autofetchGrp.classList.remove('visible');
            if (remarksGrp) remarksGrp.style.display = 'none';
            hideCustomFields();
            submitBtn.disabled = true;
            if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
            verifiedStudentId   = null;
            verifiedStudentData = null;
        }
    } catch(e) {
        studentStatus.className = 'student-status notfound';
        studentStatus.innerHTML = '❌ Connection error. Please try again.';
        studentStatus.style.display = 'flex';
    }

    verifyBtn.disabled = false;
    verifyBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> Verify';
});

// Enter key on student number
studentInput?.addEventListener('keydown', e => { if (e.key === 'Enter') verifyBtn?.click(); });

// ── Preview Mock Verify ──
function simulateVerify(snum) {
    verifiedStudentId = 999;
    document.getElementById('fullNameInput').value   = 'ABAD, BRANDON NAVARRO';
    if (document.getElementById('courseInput'))    document.getElementById('courseInput').value    = 'BSIT-A';
    if (document.getElementById('yearLevelInput')) document.getElementById('yearLevelInput').value = 'Fourth Year';
    if (document.getElementById('emailInput'))     document.getElementById('emailInput').value     = 'brandon@school.edu';
    autofetchGrp?.classList.add('visible');
    if (remarksGrp) remarksGrp.style.display = 'block';
    showCustomFields();
    submitBtn.disabled = false;
    if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
    studentStatus.className = 'student-status found';
    studentStatus.innerHTML = '✅ ABAD, BRANDON NAVARRO — found in masterlist (preview)';
    studentStatus.style.display = 'flex';
}

function showCustomFields() { for(let i=0;i<CUSTOM_COUNT;i++) { const g=document.getElementById('customGroup'+i); if(g) g.style.display='block'; } }
function hideCustomFields() { for(let i=0;i<CUSTOM_COUNT;i++) { const g=document.getElementById('customGroup'+i); if(g) g.style.display='none'; } }

function handleImageSelected(input, index) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    
    if (!file.type.startsWith('image/')) {
        alert('Please choose a valid image file (JPG, PNG, or WEBP).');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const previewImg = document.getElementById('photoPreviewImg' + index);
        const previewBox = document.getElementById('photoPreviewBox' + index);
        const dropzone   = document.getElementById('photoDropzone' + index);
        const fileNameEl = document.getElementById('photoFileName' + index);

        if (previewImg) previewImg.src = e.target.result;
        if (fileNameEl) fileNameEl.textContent = file.name;
        if (dropzone)   dropzone.style.display = 'none';
        if (previewBox) previewBox.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function removeSelectedPhoto(index) {
    const input      = document.getElementById('customField' + index);
    const previewImg = document.getElementById('photoPreviewImg' + index);
    const previewBox = document.getElementById('photoPreviewBox' + index);
    const dropzone   = document.getElementById('photoDropzone' + index);

    if (input) input.value = '';
    if (previewImg) previewImg.src = '';
    if (previewBox) previewBox.style.display = 'none';
    if (dropzone)   dropzone.style.display = 'block';
}

// ── Submit ──
submitBtn?.addEventListener('click', async () => {
    if (!verifiedStudentId || submitBtn.disabled) return;

    // Validate required custom fields & photo
    const customData = {};
    let photoFile = null;

    for (let i = 0; i < CUSTOM_COUNT; i++) {
        const el = document.getElementById('customField' + i);
        if (!el) continue;
        const type = el.dataset.type;
        const isReq = el.dataset.required === '1';
        const label = el.dataset.label || ('Field ' + (i + 1));

        if (type === 'image') {
            if (el.files && el.files[0]) {
                photoFile = el.files[0];
            } else if (isReq) {
                alert('⚠️ Please take or upload a photo for: ' + label);
                return;
            }
        } else {
            const val = el.value.trim();
            if (isReq && !val) {
                alert('⚠️ Please fill out: ' + label);
                el.focus();
                return;
            }
            if (val) customData[label] = val;
        }
    }

    if (SETTING_CONFIRM && !IS_PREVIEW) {
        const name = verifiedStudentData
            ? verifiedStudentData.last_name + ', ' + verifiedStudentData.first_name
            : document.getElementById('fullNameInput')?.value;
        if (!confirm('Confirm attendance submission?\n\nName: ' + name + '\nStudent No: ' + studentInput.value.trim())) return;
    }

    if (IS_PREVIEW) {
        showSuccess({ name: 'ABAD, BRANDON NAVARRO', student_number: studentInput.value.trim() || 'PREVIEW-001', has_photo: !!photoFile });
        return;
    }

    submitBtn.disabled = true;
    document.getElementById('submitIcon').style.display = 'none';
    document.getElementById('submitSpinner').style.display = 'block';
    if (submitBtnText) submitBtnText.textContent = 'Submitting...';

    try {
        const fd = new FormData();
        fd.append('action', 'submit_attendance');
        fd.append('session_id', SESSION_ID);
        fd.append('student_id', verifiedStudentId);
        fd.append('qr_token', QR_TOKEN);
        if (SHOW_REMARKS && document.getElementById('remarksInput'))
            fd.append('remarks', document.getElementById('remarksInput')?.value || '');

        if (photoFile) {
            fd.append('photo', photoFile);
        }
        if (Object.keys(customData).length > 0) {
            fd.append('custom_data', JSON.stringify(customData));
        }

        const res  = await fetch('conf/php/student_checkin_data.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showSuccess(data);
        } else if (data.already_submitted) {
            submitBtn.disabled = true;
            document.getElementById('submitIcon').style.display = 'block';
            document.getElementById('submitSpinner').style.display = 'none';
            if (submitBtnText) submitBtnText.textContent = 'Attendance Already Submitted';
            showDuplicateModal(data.student || verifiedStudentData, data.scanned_at, data.record_status);
        } else {
            alert('Error: ' + (data.message || 'Could not submit attendance.'));
            submitBtn.disabled = false;
            document.getElementById('submitIcon').style.display = 'block';
            document.getElementById('submitSpinner').style.display = 'none';
            if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
        }
    } catch(e) {
        alert('Connection error. Please try again.');
        submitBtn.disabled = false;
        document.getElementById('submitIcon').style.display = 'block';
        document.getElementById('submitSpinner').style.display = 'none';
        if (submitBtnText) submitBtnText.textContent = 'Submit Attendance';
    }
});

function showSuccess(data) {
    if (!SETTING_SUCCESS_PAGE) { alert('✅ Attendance submitted!'); return; }
    document.getElementById('mainForm').style.display = 'none';
    const ss = document.getElementById('successScreen');
    ss.style.display = 'block';
    const name = data.name || (verifiedStudentData ? verifiedStudentData.last_name + ', ' + verifiedStudentData.first_name : '—');
    const photoRow = (data.photo_url || data.has_photo)
        ? '<div class="success-detail-row"><span class="label">Uniform Check</span><span class="value">👔 Photo Verified</span></div>'
        : '';
    const statusLabel = (data.status === 'late')
        ? '<span class="value" style="color: #d97706; font-weight: 700;">⚠️ Late (Lagpas sa Grace Period)</span>'
        : '<span class="value" style="color: #16a34a; font-weight: 700;">✅ Present (On Time)</span>';

    document.getElementById('successDetail').innerHTML =
        '<div class="success-detail-row"><span class="label">Name</span><span class="value">' + esc(name) + '</span></div>' +
        '<div class="success-detail-row"><span class="label">Student No</span><span class="value">' + esc(data.student_number || studentInput?.value || '—') + '</span></div>' +
        '<div class="success-detail-row"><span class="label">Status</span>' + statusLabel + '</div>' +
        photoRow +
        '<div class="success-detail-row"><span class="label">Time</span><span class="value">' + new Date().toLocaleTimeString() + '</span></div>';
}

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
