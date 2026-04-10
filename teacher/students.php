<?php
require_once __DIR__ . '/../backend/helpers/query.php';
require_role('teacher');
$pdo = pdo();
$teacher = current_user();
$teacherId = (int) $teacher['id'];
$sections = teacher_sections($teacherId);
$search = trim($_GET['q'] ?? '');
$previewSessionKey = 'teacher_student_import_preview_' . $teacherId;

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
        $rows[] = array_map(static fn($value) => trim((string) $value), $data);
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

if (($_GET['download_template'] ?? '') !== '') {
    verify_csrf();
    teacher_download_template($_GET['download_template'] === 'csv' ? 'csv' : 'xlsx');
}

if (($_GET['clear_preview'] ?? '') === '1') {
    verify_csrf();
    unset($_SESSION[$previewSessionKey]);
    set_flash('success', 'Import preview cleared.');
    redirect_to('teacher/students.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'invite';
    if ($action === 'invite') {
        $studentId = trim($_POST['student_id'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $allowed = array_column($sections, 'id');
        if (!$studentId || !$fullName || !$email || !$sectionId || !in_array($sectionId, $allowed, true)) {
            set_flash('error', 'Complete the manual add form using one of your assigned sections.');
            redirect_to('teacher/students.php');
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
        redirect_to('teacher/students.php');
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
        redirect_to('teacher/students.php');
    }

    if ($action === 'confirm_import') {
        $preview = $_SESSION[$previewSessionKey] ?? null;
        if (!$preview || empty($preview['rows'])) {
            set_flash('error', 'Upload a file and review the preview before importing.');
            redirect_to('teacher/students.php');
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
        redirect_to('teacher/students.php');
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
        redirect_to('teacher/students.php');
    }
}

$preview = $_SESSION[$previewSessionKey] ?? null;
$sql = 'SELECT DISTINCT st.*, sec.section_name, (SELECT COUNT(*) FROM account_activation_tokens aat WHERE aat.student_id = st.id AND aat.used_at IS NULL AND aat.expires_at > NOW()) AS open_invites FROM students st JOIN sections sec ON sec.id = st.section_id JOIN section_subjects ss ON ss.section_id = sec.id JOIN subjects subj ON subj.id = ss.subject_id WHERE subj.teacher_id = ?';
$params = [$teacherId];
if ($search !== '') {
    $sql .= ' AND (st.student_id LIKE ? OR st.full_name LIKE ? OR st.email LIKE ?)';
    $like = "%{$search}%";
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY FIELD(st.account_status, "pending","active","view_only","inactive","archived"), st.full_name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$title = 'Teacher Students';
$subtitle = 'Bulk import your class list, preview it, then let students activate their own accounts with secure links.';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<div class="grid cols-2">
  <div class="card highlight-card teacher-import-card">
    <div class="split-header"><div><h3 class="section-title">Bulk onboarding</h3><div class="muted small">Download the template, fill it from your class list, upload it, and let the system create pending student accounts in one pass.</div></div><span class="pill">Recommended</span></div>
    <div class="quick-grid" style="margin-bottom:16px;">
      <a class="btn btn-secondary" href="<?= h(url('teacher/students.php?download_template=xlsx&_csrf=' . urlencode(csrf_token()))) ?>">Download Excel template</a>
      <a class="btn btn-outline" href="<?= h(url('teacher/students.php?download_template=csv&_csrf=' . urlencode(csrf_token()))) ?>">Download CSV template</a>
      <a class="btn btn-secondary" href="<?= h(url('student/')) ?>" target="_blank">Open student portal</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="preview_import">
      <div class="import-dropzone">
        <strong>Upload class list</strong>
        <div class="muted small">Accepted file types: .xlsx or .csv. Required columns: Student ID, Full Name, Email, Section.</div>
        <input type="file" name="student_file" accept=".xlsx,.csv" required>
      </div>
      <div class="form-actions"><button class="btn" type="submit">Preview import</button><span class="muted small">Nothing is saved until you confirm the preview.</span></div>
    </form>
  </div>
  <div class="card">
    <h3 class="section-title">Best workflow for teachers</h3>
    <div class="timeline-list">
      <div class="timeline-item"><strong>1. Download the template</strong><p>Use the built-in format so the import reads your student IDs, emails, and section names cleanly.</p></div>
      <div class="timeline-item"><strong>2. Upload and preview</strong><p>The system checks duplicates, missing values, invalid emails, and whether the section belongs to your teaching scope.</p></div>
      <div class="timeline-item"><strong>3. Confirm import</strong><p>Pending accounts are created or refreshed, then activation links are sent automatically.</p></div>
    </div>
    <div class="callout" style="margin-top:14px;">
      <strong>Recommendation</strong>
      <div class="muted small">Keep manual one-by-one invites only for late adds or special cases. Bulk import should be your main section onboarding flow.</div>
    </div>
  </div>
</div>

<?php if ($preview && !empty($preview['rows'])): ?>
<div class="card" style="margin-top:18px;">
  <div class="split-header">
    <div>
      <h3 class="section-title">Import preview</h3>
      <div class="muted small">Review every row before it is committed. Errors stay blocked until the file is corrected and uploaded again.</div>
    </div>
    <div class="action-row">
      <a class="btn btn-outline" href="<?= h(url('teacher/students.php?clear_preview=1&_csrf=' . urlencode(csrf_token()))) ?>">Clear preview</a>
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
    <table>
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

<div class="grid cols-2" style="margin-top:18px;">
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Students in your teaching scope</h3><div class="muted small">Search by student ID, name, or email to resend activation links or review access status.</div></div></div>
    <form method="get" class="filter-row">
      <input name="q" placeholder="Search by student ID, name, email" value="<?= h($search) ?>">
      <button class="btn btn-secondary" type="submit">Search</button>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Section</th><th>Status</th><th>Access</th><th>Invite</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($students as $row): ?>
      <tr>
        <td><strong><?= h($row['full_name']) ?></strong><div class="muted small"><?= h($row['student_id']) ?> · <?= h($row['email']) ?></div></td>
        <td><?= h($row['section_name']) ?></td>
        <td><?= status_badge($row['account_status']) ?></td>
        <td><?= (int) $row['can_submit'] ? 'Can submit' : 'Restricted' ?></td>
        <td><?= (int) $row['open_invites'] > 0 ? '<span class="pill">Pending invite</span>' : '<span class="muted small">No open invite</span>' ?></td>
        <td><div class="table-actions"><?php if ($row['account_status'] === 'pending'): ?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="resend"><input type="hidden" name="student_pk" value="<?= (int) $row['id'] ?>"><button class="btn btn-outline" type="submit">Resend invite</button></form><?php else: ?><span class="muted small">Active onboarding completed</span><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?><tr><td colspan="6" class="empty-state">No students found under your assigned sections yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <div class="split-header"><div><h3 class="section-title">Manual add for exceptions</h3><div class="muted small">Use this only when one student joins late or needs to be corrected outside the bulk file.</div></div><span class="pill">Secondary</span></div>
    <form method="post" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="invite">
      <div><label>Student ID</label><input name="student_id" required placeholder="2025-0005"></div>
      <div><label>Full name</label><input name="full_name" required></div>
      <div><label>Email address</label><input type="email" name="email" required></div>
      <div><label>Section</label><select name="section_id" required><option value="">Select your section</option><?php foreach ($sections as $section): ?><option value="<?= (int) $section['id'] ?>"><?= h($section['section_name']) ?></option><?php endforeach; ?></select></div>
      <div class="full form-actions"><button class="btn" type="submit">Send manual invitation</button></div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
