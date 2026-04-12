<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$pdo = pdo();
$teacher = current_user();
$teacherId = (int) $teacher['id'];
$sections = teacher_sections($teacherId);
$search = trim($_GET['q'] ?? '');
$initialLiveSearch = $search;
$previewSessionKey = 'teacher_student_import_preview_' . $teacherId;

function teacher_students_redirect_target(string $fallback = 'teacher/students.php'): string {
    $anchor = trim((string) ($_POST['return_anchor'] ?? ''));
    if ($anchor !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $anchor)) {
        return $fallback . '#' . $anchor;
    }
    return $fallback;
}

function teacher_student_template_path(string $type): string {
    return __DIR__ . '/../backend/templates/student_import_template.' . ($type === 'csv' ? 'csv' : 'xlsx');
}

function teacher_download_template(string $type): void {
    $path = teacher_student_template_path($type);
    if (!is_file($path)) {
        http_response_code(404);
        exit('Template not found');
    }
    header('Content-Type: ' . ($type === 'csv' ? 'text/csv; charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="student_import_template.' . ($type === 'csv' ? 'csv' : 'xlsx') . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function import_normalize_header(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string) $value, '_');
}

function import_column_letter_to_index(string $letters): int {
    $letters = strtoupper($letters);
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

function import_parse_xlsx_rows(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel import is not available on this server yet because the PHP Zip extension is disabled. Upload the CSV template instead, or enable php_zip in XAMPP to import .xlsx files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('The uploaded XLSX file could not be opened.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                    continue;
                }
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('The uploaded XLSX file is missing its first worksheet.');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData)) {
        throw new RuntimeException('The uploaded XLSX file could not be read.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/([A-Z]+)(\d+)/', $ref, $matches);
            $colIndex = isset($matches[1]) ? import_column_letter_to_index($matches[1]) : count($cells);
            $value = '';
            $type = (string) $cell['t'];
            if ($type === 's') {
                $sharedIndex = (int) $cell->v;
                $value = $sharedStrings[$sharedIndex] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) $cell->is->t;
            } else {
                $value = isset($cell->v) ? (string) $cell->v : '';
            }
            $cells[$colIndex] = trim($value);
        }
        if ($cells) {
            ksort($cells);
            $max = max(array_keys($cells));
            $normalized = array_fill(0, $max + 1, '');
            foreach ($cells as $index => $value) {
                $normalized[$index] = $value;
            }
            $rows[] = $normalized;
        }
    }
    return $rows;
}

function import_parse_csv_rows(string $path): array {
    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException('The uploaded CSV file could not be opened.');
    }
    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        $clean = array_map(static fn($value) => trim((string) $value), $data);
        if ($rows === [] && isset($clean[0])) {
            $clean[0] = preg_replace('/^ï»¿/', '', $clean[0]);
        }
        $rows[] = $clean;
    }
    fclose($handle);
    return $rows;
}

function import_read_uploaded_rows(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        throw new RuntimeException('Choose an Excel or CSV file to import.');
    }
    $name = strtolower((string) ($file['name'] ?? ''));
    if (str_ends_with($name, '.csv')) {
        return import_parse_csv_rows($file['tmp_name']);
    }
    if (str_ends_with($name, '.xlsx')) {
        return import_parse_xlsx_rows($file['tmp_name']);
    }
    throw new RuntimeException('Unsupported file type. Upload .xlsx or .csv.');
}

function build_import_preview(array $rows, array $sections, PDO $pdo): array {
    $required = [
        'student_id' => ['student_id', 'studentid', 'id_number'],
        'full_name' => ['full_name', 'fullname', 'student_name', 'name'],
        'email' => ['email', 'email_address', 'student_email'],
        'section' => ['section', 'section_name'],
    ];

    $headerIndex = null;
    $columnMap = [];
    foreach ($rows as $index => $row) {
        $normalized = array_map('import_normalize_header', $row);
        $candidateMap = [];
        foreach ($required as $target => $aliases) {
            foreach ($normalized as $colIndex => $header) {
                if (in_array($header, $aliases, true)) {
                    $candidateMap[$target] = $colIndex;
                    break;
                }
            }
        }
        if (count($candidateMap) === count($required)) {
            $headerIndex = $index;
            $columnMap = $candidateMap;
            break;
        }
    }

    if ($headerIndex === null) {
        throw new RuntimeException('The file must include these columns: Student ID, Full Name, Email, Section.');
    }

    $sectionLookup = [];
    foreach ($sections as $section) {
        $sectionLookup[strtolower(trim($section['section_name']))] = $section;
    }

    $previewRows = [];
    $seenStudentIds = [];
    $seenEmails = [];
    $importableCount = 0;
    $errorCount = 0;

    $existingByStudent = [];
    $existingByEmail = [];
    $existing = $pdo->query('SELECT id, student_id, email, account_status FROM students')->fetchAll();
    foreach ($existing as $row) {
        $existingByStudent[strtolower((string) $row['student_id'])] = $row;
        $existingByEmail[strtolower((string) $row['email'])] = $row;
    }

    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $studentId = trim((string) ($row[$columnMap['student_id']] ?? ''));
        $fullName = trim((string) ($row[$columnMap['full_name']] ?? ''));
        $email = trim((string) ($row[$columnMap['email']] ?? ''));
        $sectionName = trim((string) ($row[$columnMap['section']] ?? ''));

        if ($studentId === '' && $fullName === '' && $email === '' && $sectionName === '') {
            continue;
        }

        $errors = [];
        $notes = [];
        $normalizedSection = strtolower($sectionName);
        $section = $sectionLookup[$normalizedSection] ?? null;

        if ($studentId === '') { $errors[] = 'Missing student ID'; }
        if ($fullName === '') { $errors[] = 'Missing full name'; }
        if ($email === '') { $errors[] = 'Missing email'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email'; }
        if ($sectionName === '') {
            $errors[] = 'Missing section';
        } elseif (!$section) {
            $errors[] = 'Section is not in your teaching scope';
        }

        $studentKey = strtolower($studentId);
        $emailKey = strtolower($email);
        if ($studentKey !== '') {
            if (isset($seenStudentIds[$studentKey])) {
                $errors[] = 'Duplicate student ID in this file';
            }
            $seenStudentIds[$studentKey] = true;
        }
        if ($emailKey !== '') {
            if (isset($seenEmails[$emailKey])) {
                $errors[] = 'Duplicate email in this file';
            }
            $seenEmails[$emailKey] = true;
        }

        $existingMatch = null;
        if ($studentKey !== '' && isset($existingByStudent[$studentKey])) {
            $existingMatch = $existingByStudent[$studentKey];
        }
        if (!$existingMatch && $emailKey !== '' && isset($existingByEmail[$emailKey])) {
            $existingMatch = $existingByEmail[$emailKey];
        }

        if ($existingMatch) {
            if (($existingMatch['account_status'] ?? '') === 'active') {
                $notes[] = 'Existing active account will update. No new activation email needed.';
            } else {
                $notes[] = 'Existing account will update and receive a fresh activation link.';
            }
        } else {
            $notes[] = 'New pending account will be created and invited.';
        }

        $status = $errors ? 'error' : 'ready';
        if ($status === 'ready') { $importableCount++; } else { $errorCount++; }

        $previewRows[] = [
            'row_number' => $i + 1,
            'student_id' => $studentId,
            'full_name' => $fullName,
            'email' => $email,
            'section_id' => $section ? (int) $section['id'] : 0,
            'section_name' => $sectionName,
            'status' => $status,
            'errors' => $errors,
            'notes' => $notes,
            'existing_status' => $existingMatch['account_status'] ?? '',
        ];
    }

    return [
        'rows' => $previewRows,
        'importable_count' => $importableCount,
        'error_count' => $errorCount,
        'total_rows' => count($previewRows),
        'created_at' => time(),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'invite';

    if ($action === 'download_template') {
        $type = (($_POST['template_type'] ?? '') === 'csv') ? 'csv' : 'xlsx';
        teacher_download_template($type);
    }

    if ($action === 'clear_preview') {
        unset($_SESSION[$previewSessionKey]);
        set_flash('success', 'Import preview cleared.');
        redirect_to(teacher_students_redirect_target());
    }

    if ($action === 'invite') {
        $studentId = trim($_POST['student_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $allowed = array_column($sections, 'id');
        if (!$studentId || !$fullName || !$email || !$sectionId || !in_array($sectionId, $allowed, true)) {
            set_flash('error', 'Complete the manual add form using one of your assigned sections.');
            redirect_to(teacher_students_redirect_target());
        }
        try {
            $pdo->beginTransaction();
            $existingStmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ? OR email = ? LIMIT 1');
            $existingStmt->execute([$studentId, $email]);
            $existing = $existingStmt->fetch();
            if ($existing) {
                $studentPk = (int) $existing['id'];
                $pdo->prepare('UPDATE students SET full_name = ?, email = ?, section_id = ?, username = student_id, account_status = IF(account_status = "archived", "pending", account_status), can_submit = IF(account_status = "active", can_submit, 0) WHERE id = ?')
                    ->execute([$fullName, $email, $sectionId, $studentPk]);
            } else {
                $tempHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO students (student_id, full_name, email, username, password_hash, section_id, account_status, can_submit) VALUES (?, ?, ?, ?, ?, ?, "pending", 0)')
                    ->execute([$studentId, $fullName, $email, $studentId, $tempHash, $sectionId]);
                $studentPk = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM account_activation_tokens WHERE student_id = ? AND used_at IS NULL')->execute([$studentPk]);
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('INSERT INTO account_activation_tokens (student_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR))')->execute([$studentPk, $token]);
            $student = $pdo->prepare('SELECT * FROM students WHERE id = ?');
            $student->execute([$studentPk]);
            $studentRow = $student->fetch();
            send_student_activation_invite($studentRow, $token, $teacher['full_name']);
            create_notification('student', $studentPk, 'Account invitation sent', 'Your teacher invited you to activate your portal account.', 'info');
            log_action('teacher', $teacherId, 'invite_student', 'student', $studentPk, 'Invitation sent from teacher portal');
            $pdo->commit();
            set_flash('success', 'Invitation sent successfully. The student can activate using the emailed link.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            set_flash('error', 'Unable to send invitation. Check for duplicate student ID or email.');
        }
        redirect_to(teacher_students_redirect_target());
    }

    if ($action === 'preview_import') {
        try {
            $rows = import_read_uploaded_rows($_FILES['student_file'] ?? []);
            $preview = build_import_preview($rows, $sections, $pdo);
            if ($preview['total_rows'] === 0) {
                throw new RuntimeException('No student rows were found in the uploaded file.');
            }
            $_SESSION[$previewSessionKey] = $preview;
            set_flash('success', 'Import preview generated. Review the rows below before confirming.');
        } catch (Throwable $e) {
            unset($_SESSION[$previewSessionKey]);
            set_flash('error', $e->getMessage());
        }
        redirect_to(teacher_students_redirect_target());
    }

    if ($action === 'confirm_import') {
        $preview = $_SESSION[$previewSessionKey] ?? null;
        if (!$preview || empty($preview['rows'])) {
            set_flash('error', 'Upload a file and review the preview before importing.');
            redirect_to(teacher_students_redirect_target());
        }

        $created = 0;
        $updated = 0;
        $invited = 0;
        $keptActive = 0;
        $failed = 0;

        foreach ($preview['rows'] as $row) {
            if (($row['status'] ?? '') !== 'ready') {
                continue;
            }
            try {
                $pdo->beginTransaction();
                $existingStmt = $pdo->prepare('SELECT * FROM students WHERE student_id = ? OR email = ? LIMIT 1');
                $existingStmt->execute([$row['student_id'], $row['email']]);
                $existing = $existingStmt->fetch();
                if ($existing) {
                    $studentPk = (int) $existing['id'];
                    $wasActive = ($existing['account_status'] ?? '') === 'active';
                    $pdo->prepare('UPDATE students SET student_id = ?, full_name = ?, email = ?, username = student_id, section_id = ?, account_status = IF(account_status = "archived", "pending", account_status), can_submit = IF(account_status = "active", can_submit, 0) WHERE id = ?')
                        ->execute([$row['student_id'], $row['full_name'], $row['email'], (int) $row['section_id'], $studentPk]);
                    $updated++;
                } else {
                    $tempHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO students (student_id, full_name, email, username, password_hash, section_id, account_status, can_submit) VALUES (?, ?, ?, ?, ?, ?, "pending", 0)')
                        ->execute([$row['student_id'], $row['full_name'], $row['email'], $row['student_id'], $tempHash, (int) $row['section_id']]);
                    $studentPk = (int) $pdo->lastInsertId();
                    $created++;
                    $wasActive = false;
                }

                if ($wasActive) {
                    $keptActive++;
                    log_action('teacher', $teacherId, 'bulk_update_student', 'student', $studentPk, 'Bulk import refreshed active student profile');
                } else {
                    $pdo->prepare('DELETE FROM account_activation_tokens WHERE student_id = ? AND used_at IS NULL')->execute([$studentPk]);
                    $token = bin2hex(random_bytes(24));
                    $pdo->prepare('INSERT INTO account_activation_tokens (student_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR))')->execute([$studentPk, $token]);
                    $studentStmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
                    $studentStmt->execute([$studentPk]);
                    $studentRow = $studentStmt->fetch();
                    send_student_activation_invite($studentRow, $token, $teacher['full_name']);
                    create_notification('student', $studentPk, 'Account invitation sent', 'Your teacher imported your account and sent an activation link.', 'info');
                    log_action('teacher', $teacherId, 'bulk_invite_student', 'student', $studentPk, 'Bulk import sent activation link');
                    $invited++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $failed++;
            }
        }

        unset($_SESSION[$previewSessionKey]);
        $parts = [];
        if ($created > 0) { $parts[] = $created . ' created'; }
        if ($updated > 0) { $parts[] = $updated . ' updated'; }
        if ($invited > 0) { $parts[] = $invited . ' activation emails sent'; }
        if ($keptActive > 0) { $parts[] = $keptActive . ' already active'; }
        if ($failed > 0) { $parts[] = $failed . ' failed'; }
        set_flash($failed > 0 ? 'error' : 'success', 'Bulk import finished: ' . implode(', ', $parts) . '.');
        redirect_to(teacher_students_redirect_target());
    }

    if ($action === 'resend') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $stmt = $pdo->prepare('SELECT st.* FROM students st JOIN sections sec ON sec.id = st.section_id JOIN section_subjects ss ON ss.section_id = sec.id JOIN subjects subj ON subj.id = ss.subject_id WHERE st.id = ? AND subj.teacher_id = ? LIMIT 1');
        $stmt->execute([$studentPk, $teacherId]);
        $student = $stmt->fetch();
        if ($student) {
            $pdo->prepare('DELETE FROM account_activation_tokens WHERE student_id = ? AND used_at IS NULL')->execute([$studentPk]);
            $token = bin2hex(random_bytes(24));
            $pdo->prepare('INSERT INTO account_activation_tokens (student_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR))')->execute([$studentPk, $token]);
            send_student_activation_invite($student, $token, $teacher['full_name']);
            create_notification('student', $studentPk, 'Activation link resent', 'A fresh activation link was sent to your email address.', 'info');
            log_action('teacher', $teacherId, 'resend_student_invite', 'student', $studentPk, 'Activation link resent');
            set_flash('success', 'Invitation resent.');
        }
        redirect_to(teacher_students_redirect_target());
    }
}

$preview = $_SESSION[$previewSessionKey] ?? null;
$excelImportAvailable = class_exists('ZipArchive');
$mailTransportActive = ((string) MAIL_ENABLED === '1');
$mailTransportLabel = $mailTransportActive ? (((string) MAIL_USERNAME !== '' || (string) MAIL_PASSWORD !== '') ? 'SMTP delivery is enabled.' : 'PHP mail delivery is enabled.') : 'Email delivery is disabled. Activation emails are only logged.';
$sql = 'SELECT DISTINCT st.*, sec.section_name, (SELECT COUNT(*) FROM account_activation_tokens aat WHERE aat.student_id = st.id AND aat.used_at IS NULL AND aat.expires_at > NOW()) AS open_invites FROM students st JOIN sections sec ON sec.id = st.section_id JOIN section_subjects ss ON ss.section_id = sec.id JOIN subjects subj ON subj.id = ss.subject_id WHERE subj.teacher_id = ? ORDER BY FIELD(st.account_status, "pending","active","view_only","inactive","archived"), st.full_name';
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacherId]);
$students = $stmt->fetchAll();
$title = 'Teacher Students';
$subtitle = 'Bulk import your class list, preview it, then let students activate their own accounts with secure links.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<style>
#teacher-student-roster .table-enhancement-right .table-search { display:none; }
.teacher-live-search-row { align-items:center; gap:12px; }
.teacher-live-search-row input { flex: 1 1 320px; }
@media (max-width: 760px) { .teacher-live-search-row { align-items:stretch; } }
</style>
<div class="card teacher-workflow-card teacher-workflow-card--full">
  <div class="split-header"><div><h3 class="section-title">Interactive teacher workflow</h3><div class="muted small">Use the bulk onboarding modal for imports, then review the preview and roster in one clean flow.</div></div><div class="table-head-actions"><button class="btn" type="button" data-open-modal="teacher-student-import"><i class="bi bi-cloud-upload"></i> Bulk onboarding</button><button class="btn btn-outline" type="button" data-open-modal="teacher-workflow-guide">Open full guide</button></div></div>
  <div class="workflow-step-grid">
    <button type="button" class="workflow-step-card" data-open-modal="teacher-student-import">
      <span class="workflow-step-number">1</span>
      <strong>Download and upload</strong>
      <span class="muted small">Open the import modal, download the template, and upload your class file.</span>
    </button>
    <button type="button" class="workflow-step-card" data-scroll-target="#teacher-import-preview">
      <span class="workflow-step-number">2</span>
      <strong>Review the preview</strong>
      <span class="muted small">Jump to the preview table and fix blocked rows before confirming.</span>
    </button>
    <button type="button" class="workflow-step-card" data-scroll-target="#teacher-student-roster">
      <span class="workflow-step-number">3</span>
      <strong>Check roster status</strong>
      <span class="muted small">Verify who is pending, active, or needs a fresh activation link.</span>
    </button>
  </div>
  <div class="callout teacher-workflow-note">
    <strong>Recommendation</strong>
    <div class="muted small">Use manual add only for exceptions. Your normal section onboarding should always start from the bulk onboarding modal.</div>
  </div>
</div>

<div class="modal-backdrop" data-modal="teacher-student-import" aria-hidden="true">
  <div class="modal-card teacher-workflow-modal-card" role="dialog" aria-modal="true" aria-labelledby="teacher-import-title">
    <div class="modal-head">
      <div>
        <span class="pill soft">Workspace modal</span>
        <h3 id="teacher-import-title">Bulk onboarding workspace</h3>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
    </div>
    <p class="muted">Download the right template, upload the class list, and preview the result before any student is created.</p>
    <?php if (!$excelImportAvailable): ?>
      <div class="callout is-warning" style="margin-bottom:16px;">
        <strong>Excel import needs one server setting</strong>
        <div class="muted small">This XAMPP setup does not have the PHP Zip extension enabled yet, so .xlsx import is unavailable for now. CSV import is ready now. To enable Excel later, turn on <code>extension=zip</code> in your <code>php.ini</code> and restart Apache.</div>
      </div>
    <?php endif; ?>
    <div class="workflow-modal-actions">
      <?php if ($excelImportAvailable): ?>
      <form method="post" class="inline quick-action-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="download_template">
        <input type="hidden" name="template_type" value="xlsx">
        <button class="btn btn-secondary" type="submit">Download Excel template</button>
      </form>
      <?php endif; ?>
      <form method="post" class="inline quick-action-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="download_template">
        <input type="hidden" name="template_type" value="csv">
        <button class="btn btn-outline" type="submit">Download CSV template</button>
      </form>
      <a class="btn btn-secondary" href="<?= h(url('student/')) ?>" target="_blank">Open student portal</a>
    </div>
    <div class="callout teacher-mail-callout <?= $mailTransportActive ? 'is-success' : 'is-warning' ?>" style="margin-bottom:16px;">
      <strong>Email delivery</strong>
      <div class="muted small"><?= h($mailTransportLabel) ?></div>
    </div>
    <form id="teacher-import-form" method="post" enctype="multipart/form-data" class="stack teacher-import-modal-form">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="preview_import">
      <div class="import-dropzone">
        <strong>Upload class list</strong>
        <div class="muted small">Accepted file types: .xlsx or .csv. Required columns: Student ID, Full Name, Email, Section.</div>
        <input type="file" name="student_file" accept=".xlsx,.csv" required>
      </div>
    </form>
    <div class="modal-footer">
      <button class="btn" type="submit" form="teacher-import-form">Preview import</button>
      <span class="muted small">Nothing is saved until you confirm the preview.</span>
    </div>
  </div>
</div>

<div class="modal-backdrop" data-modal="teacher-workflow-guide" aria-hidden="true">
  <div class="modal-card teacher-workflow-modal-card" role="dialog" aria-modal="true" aria-labelledby="teacher-workflow-title">
    <div class="modal-head">
      <div>
        <span class="pill soft">Workflow guide</span>
        <h3 id="teacher-workflow-title">Best workflow for teachers</h3>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
    </div>
    <div class="timeline-list interactive-timeline-list">
      <div class="timeline-item interactive-timeline-item"><strong>1. Download the template</strong><p>Use the built-in format so the import reads your student IDs, emails, and section names cleanly.</p><button class="btn btn-outline" type="button" data-open-modal="teacher-student-import">Open bulk onboarding</button></div>
      <div class="timeline-item interactive-timeline-item"><strong>2. Upload and preview</strong><p>The system checks duplicates, missing values, invalid emails, and whether the section belongs to your teaching scope.</p><button class="btn btn-outline" type="button" data-scroll-target="#teacher-import-preview">Jump to preview area</button></div>
      <div class="timeline-item interactive-timeline-item"><strong>3. Confirm import</strong><p>Pending accounts are created or refreshed, then activation links are sent automatically.</p><button class="btn btn-outline" type="button" data-scroll-target="#teacher-student-roster">Open roster status</button></div>
    </div>
  </div>
</div>
<?php if ($preview && !empty($preview['rows'])): ?>
<div class="card" id="teacher-import-preview" style="margin-top:18px;">
  <div class="split-header">
    <div>
      <h3 class="section-title">Import preview</h3>
      <div class="muted small">Review every row before it is committed. Errors stay blocked until the file is corrected and uploaded again.</div>
    </div>
    <div class="action-row">
      <form method="post" class="inline">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="clear_preview">
        <button class="btn btn-outline" type="submit">Clear preview</button>
      </form>
      <?php if ((int) $preview['importable_count'] > 0): ?>
      <form method="post" class="inline">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="confirm_import">
        <button class="btn" type="submit">Confirm import</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="quick-grid teacher-import-stats" style="margin-bottom:16px;">
    <div class="subject-chip"><strong><?= (int) $preview['total_rows'] ?></strong><span class="muted small">Rows found</span></div>
    <div class="subject-chip"><strong><?= (int) $preview['importable_count'] ?></strong><span class="muted small">Ready to import</span></div>
    <div class="subject-chip"><strong><?= (int) $preview['error_count'] ?></strong><span class="muted small">Rows with issues</span></div>
  </div>
  <div class="table-wrap">
    <table class="table-redesign">
      <thead><tr><th>Row</th><th>Student</th><th>Section</th><th>Result</th><th>Notes</th></tr></thead>
      <tbody>
      <?php foreach ($preview['rows'] as $row): ?>
        <tr>
          <td>#<?= (int) $row['row_number'] ?></td>
          <td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?> · <?= h($row['email']) ?></div></td>
          <td><?= h($row['section_name']) ?></td>
          <td><?= $row['status'] === 'ready' ? '<span class="status active">Ready</span>' : '<span class="status error">Fix row</span>' ?></td>
          <td>
            <?php if (!empty($row['errors'])): ?>
              <div class="table-note" style="color:#b91c1c;"><?= h(implode(' • ', $row['errors'])) ?></div>
            <?php endif; ?>
            <?php if (!empty($row['notes'])): ?>
              <div class="table-note"><?= h(implode(' ', $row['notes'])) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card" id="teacher-student-roster" style="margin-top:18px; scroll-margin-top: 24px;">
  <div class="split-header"><div><h3 class="section-title">Students in your teaching scope</h3><div class="muted small">Search by student ID, name, or email to resend activation links or review access status.</div></div><div class="table-head-actions"><button class="btn" type="button" data-open-modal="teacher-add-student">Add student</button></div></div>
    <div class="filter-row teacher-live-search-row">
      <input id="teacher-live-search" type="search" placeholder="Search by student ID, name, email" value="<?= h($initialLiveSearch) ?>" autocomplete="off">
      <span class="muted small">Live search updates the table instantly while you type.</span>
    </div>
    <div class="table-wrap teacher-student-roster-wrap"><table class="teacher-student-roster table-redesign"><thead><tr><th>Student</th><th>Section</th><th>Status</th><th>Access</th><th>Invite</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($students as $row): ?>
      <tr>
        <td data-label="Student">
          <div class="roster-student-cell">
            <div class="student-avatar-badge" aria-hidden="true"><?= h(strtoupper(substr($row['full_name'], 0, 1))) ?></div>
            <div>
              <strong><?= h($row['full_name']) ?></strong>
              <div class="muted small"><?= h($row['student_id']) ?> · <?= h($row['email']) ?></div>
            </div>
          </div>
        </td>
        <td data-label="Section"><span class="pill soft"><?= h($row['section_name']) ?></span></td>
        <td data-label="Status"><?= status_badge($row['account_status']) ?></td>
        <td data-label="Access"><span class="access-indicator <?= (int) $row['can_submit'] ? 'is-open' : 'is-restricted' ?>"><?= (int) $row['can_submit'] ? 'Can submit' : 'Restricted' ?></span></td>
        <td data-label="Invite"><?= (int) $row['open_invites'] > 0 ? '<span class="pill">Pending invite</span>' : '<span class="muted small">No open invite</span>' ?></td>
        <td data-label="Actions">
          <div class="table-actions icon-only-actions"><?php if ($row['account_status'] === 'pending'): ?><form method="post" class="inline-icon-form"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="resend"><input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>"><input type="hidden" name="return_anchor" value="teacher-student-roster"><button class="icon-action" type="submit" title="resend invite" aria-label="resend invite"><i class="bi bi-send"></i></button></form><?php else: ?><span class="muted small">active onboarding completed</span><?php endif; ?></div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?><tr><td colspan="6" class="empty-state">No students found under your assigned sections yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<div class="modal-backdrop" data-modal="teacher-add-student" aria-hidden="true">
  <div class="modal-card teacher-workflow-modal-card" role="dialog" aria-modal="true" aria-labelledby="teacher-add-student-title">
    <div class="modal-head">
      <div><span class="pill soft">Teacher modal</span><h3 id="teacher-add-student-title">Add student</h3><p class="muted">Use this only for late joiners or one-off exceptions. Bulk import stays the primary workflow.</p></div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
    </div>
    <div class="modal-body">
      <form id="teacher-add-student-form" method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="invite">
        <input type="hidden" name="return_anchor" value="teacher-student-roster">
        <div><label>Student ID</label><input name="student_id" required placeholder="2025-0005"></div>
        <div><label>Full name</label><input name="full_name" required></div>
        <div><label>Email address</label><input type="email" name="email" required></div>
        <div><label>Section</label><select name="section_id" required><option value="">Select your section</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>"><?= h($section['section_name']) ?></option><?php endforeach; ?></select></div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn" type="submit" form="teacher-add-student-form">Send manual invitation</button>
      <button class="btn btn-outline" type="button" data-close-modal>Cancel</button>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var liveInput = document.getElementById('teacher-live-search');
  var rosterWrap = document.querySelector('#teacher-student-roster .table-wrap');
  if (!liveInput || !rosterWrap) return;

  var toolbarInput = rosterWrap.parentElement.querySelector('.table-enhancement .table-search');
  if (!toolbarInput) return;

  toolbarInput.value = liveInput.value || '';
  toolbarInput.dispatchEvent(new Event('input', { bubbles: true }));

  liveInput.addEventListener('input', function () {
    toolbarInput.value = liveInput.value;
    toolbarInput.dispatchEvent(new Event('input', { bubbles: true }));
  });

  toolbarInput.addEventListener('input', function () {
    if (liveInput.value !== toolbarInput.value) liveInput.value = toolbarInput.value;
  });

  if (window.history && window.history.replaceState && window.location.search.indexOf('q=') !== -1) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});
</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
