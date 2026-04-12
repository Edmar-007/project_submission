<?php
if (defined('FILE_STUDENT_SUBJECTS_PHP_LOADED')) { return; }
define('FILE_STUDENT_SUBJECTS_PHP_LOADED', true);

require_once __DIR__ . '/../backend/config/app.php';
require_role('student');
$student = current_user();
$subjects = student_subjects((int) $student['section_id']);
$activities = student_visible_activities((int) $student['id'], (int) $student['section_id']);
$subjectMap = [];
foreach ($subjects as $subjectRow) {
    $subjectMap[(int) $subjectRow['id']] = $subjectRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_subject_team') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        if (!isset($subjectMap[$subjectId])) {
            set_flash('error', 'Invalid subject selection.');
            redirect_to('student/subjects.php');
        }

        $subject = $subjectMap[$subjectId];
        $team = subject_team_for_leader((int) $student['id'], $subjectId);
        if (!$team) {
            $existingMembership = student_team_for_subject((int) $student['id'], $subjectId);
            if ($existingMembership && ($existingMembership['role'] ?? '') !== 'leader') {
                set_flash('error', 'Only the team leader can manage this subject team.');
                redirect_to('student/subjects.php');
            }
        }

        $rawMemberIds = array_values(array_unique(array_filter(array_map('intval', $_POST['member_ids'] ?? []))));
        $rawMemberIds = array_values(array_filter($rawMemberIds, static fn(int $id): bool => $id > 0 && $id !== (int) $student['id']));
        $selectedMembers = students_for_subject_team_ids($subjectId, $rawMemberIds);
        $selectedById = [];
        foreach ($selectedMembers as $member) {
            $selectedById[(int) $member['id']] = $member;
        }
        if (count($selectedById) !== count($rawMemberIds)) {
            set_flash('error', 'One or more selected teammates are not eligible for this subject.');
            redirect_to('student/subjects.php');
        }

        foreach (array_keys($selectedById) as $memberId) {
            $membershipStmt = pdo()->prepare('
                SELECT tm.id
                FROM team_members tm
                JOIN teams t ON t.id = tm.team_id
                WHERE tm.student_id = ?
                  AND t.subject_id = ?
                  AND t.activity_id IS NULL
                  AND t.status = "active"
                  AND (? = 0 OR t.id <> ?)
                LIMIT 1
            ');
            $teamId = (int) ($team['id'] ?? 0);
            $membershipStmt->execute([$memberId, $subjectId, $teamId, $teamId]);
            if ($membershipStmt->fetch()) {
                set_flash('error', 'One or more selected students already belong to another subject team.');
                redirect_to('student/subjects.php');
            }
        }

        try {
            pdo()->beginTransaction();
            if (!$team) {
                $teamName = $subject['subject_code'] . ' · ' . $student['student_id'] . ' Team';
                $insertTeam = pdo()->prepare('INSERT INTO teams (subject_id, activity_id, section_id, leader_student_id, team_name, status) VALUES (?, NULL, ?, ?, ?, "active")');
                $insertTeam->execute([$subjectId, (int) $student['section_id'], (int) $student['id'], $teamName]);
                $teamId = (int) pdo()->lastInsertId();
                $insertMember = pdo()->prepare('INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, ?)');
                $insertMember->execute([$teamId, (int) $student['id'], 'leader']);
            } else {
                $teamId = (int) $team['id'];
                $deleteMembers = pdo()->prepare('DELETE FROM team_members WHERE team_id = ? AND role <> "leader"');
                $deleteMembers->execute([$teamId]);
            }

            if ($selectedById) {
                $insertMember = pdo()->prepare('INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, "member")');
                foreach (array_keys($selectedById) as $memberId) {
                    $insertMember->execute([$teamId, $memberId]);
                }
            }
            pdo()->commit();
            set_flash('success', 'Subject team saved. Team-based submissions will now reuse this team automatically.');
        } catch (Throwable $e) {
            if (pdo()->inTransaction()) {
                pdo()->rollBack();
            }
            set_flash('error', 'Unable to save team right now. Please try again.');
        }
        redirect_to('student/subjects.php');
    }
}

$activityMap = [];
foreach ($activities as $activity) {
    $activityMap[(int) $activity['subject_id']][] = $activity;
}
$teamMap = [];
$teamMembersMap = [];
foreach ($subjects as $subject) {
    $subjectTeam = student_team_for_subject((int) $student['id'], (int) $subject['id']);
    if ($subjectTeam) {
        $teamMap[(int) $subject['id']] = $subjectTeam;
        $teamMembersMap[(int) $subject['id']] = subject_team_members((int) $subjectTeam['id']);
    }
}
$title = 'My Subjects';
$subtitle = 'Browse subject containers and the submission activities published inside them';
require_once __DIR__ . '/../backend/partials/header.php';
?>
<section class="workspace-shell student-history-shell ui-section">
  <div class="workspace-head">
    <div>
      <div class="eyebrow">Student subjects</div>
      <h2>Assigned subject workspace</h2>
      <p class="muted">Subjects are now containers. Your teacher can publish multiple submission activities inside each subject with different sections, deadlines, and restrictions.</p>
    </div>
    <div class="student-history-actions ui-action-row"><a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php')) ?>">Open submit flow</a></div>
  </div>

  <div class="review-card-grid">
    <?php foreach ($subjects as $subject): ?>
      <?php $subjectActivities = $activityMap[(int) $subject['id']] ?? []; ?>
      <?php $subjectTeam = $teamMap[(int) $subject['id']] ?? null; ?>
      <?php $subjectTeamMembers = $teamMembersMap[(int) $subject['id']] ?? []; ?>
      <article class="card review-queue-card ui-subject-card">
        <div class="split-header ui-subject-card__top"><div><h3 class="section-title ui-subject-card__title"><?= h($subject['subject_name']) ?></h3><div class="muted small"><?= h($subject['subject_code']) ?> · <?= h($subject['teacher_name']) ?></div></div><?= status_badge($subject['status']) ?></div>
        <p class="muted"><?= h($subject['description'] ?: 'Assigned through your section.') ?></p>
        <div class="metric-chip ui-chip" style="margin-bottom:12px;"><span>Activities</span><strong><?= count($subjectActivities) ?></strong></div>
        <div class="timeline-list">
          <?php foreach (array_slice($subjectActivities, 0, 4) as $activity): ?>
            <div class="timeline-item">
              <strong><?= h($activity['title']) ?></strong>
              <p><?= h($activity['activity_window']['label'] ?? 'Open') ?></p>
              <div class="muted small"><?= h(ucfirst((string) $activity['submission_mode'])) ?> submission</div>
            </div>
          <?php endforeach; ?>
          <?php if (!$subjectActivities): ?><div class="empty-state ui-empty-state">No activity is published for this subject yet.</div><?php endif; ?>
        </div>
        <section class="card ui-panel-card" style="margin-top:12px;">
          <div class="split-header">
            <div>
              <h4 class="section-title">Subject team</h4>
              <div class="muted small">This team is reused automatically for team-based submissions.</div>
            </div>
            <?php if ($subjectTeam): ?>
              <span class="pill ui-chip <?= ($subjectTeam['role'] ?? '') === 'leader' ? 'soft' : '' ?>"><?= h(ucfirst((string) ($subjectTeam['role'] ?? 'member'))) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($subjectTeam): ?>
            <div class="info-list" style="margin-top:8px;">
              <div class="row"><span>Leader</span><strong><?= h($subjectTeam['leader_name'] ?? '—') ?></strong></div>
              <div class="row"><span>Members</span><strong><?= count($subjectTeamMembers) ?></strong></div>
            </div>
            <div class="timeline-list" style="margin-top:10px;">
              <?php foreach ($subjectTeamMembers as $member): ?>
                <div class="timeline-item">
                  <strong><?= h($member['full_name']) ?></strong>
                  <p><?= h($member['student_id']) ?> · <?= h(ucfirst((string) $member['role'])) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if (($subjectTeam['role'] ?? '') !== 'leader'): ?>
              <div class="callout" style="margin-top:12px;">
                <strong>Leader-only management</strong>
                <div class="muted small">Only your team leader can update members and submit team-based activities.</div>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="empty-state ui-empty-state" style="margin-top:8px;">No subject team yet. Create one here so you can submit team-based activities without rebuilding members later.</div>
          <?php endif; ?>

          <?php if (!$subjectTeam || ($subjectTeam['role'] ?? '') === 'leader'): ?>
            <?php
              $prefillMembers = [];
              foreach ($subjectTeamMembers as $member) {
                  if (($member['role'] ?? '') === 'member') {
                      $prefillMembers[] = $member;
                  }
              }
            ?>
            <form method="post" class="subject-team-builder" data-subject-id="<?= (int) $subject['id'] ?>" style="margin-top:12px;">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_subject_team">
              <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
              <div class="split-header">
                <label style="margin:0;">Members</label>
                <div class="muted small">Search by student ID or full name. Leader stays fixed.</div>
              </div>
              <div class="form-grid" style="grid-template-columns:1fr auto;align-items:start;">
                <div style="position:relative;">
                  <input class="ui-input" type="text" data-team-search placeholder="Search eligible classmates">
                  <div class="table-card" data-team-results hidden style="position:absolute;z-index:12;inset:auto 0 0 0;transform:translateY(100%);max-height:230px;overflow:auto;"></div>
                </div>
                <button type="button" class="btn btn-secondary ui-btn ui-btn--secondary" data-team-clear>Clear</button>
              </div>
              <div class="timeline-list" data-team-chip-list style="margin-top:10px;"></div>
              <div class="form-actions ui-action-row" style="margin-top:12px;">
                <button type="submit" class="btn ui-btn ui-btn--primary"><?= $subjectTeam ? 'Save team members' : 'Create subject team' ?></button>
              </div>
              <template data-initial-members><?= h(json_encode(array_map(static fn($m): array => ['id' => (int) $m['id'], 'full_name' => (string) $m['full_name'], 'student_id' => (string) $m['student_id'], 'section_name' => (string) ($m['section_name'] ?? ''), 'email' => (string) ($m['email'] ?? '')], $prefillMembers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></template>
            </form>
          <?php endif; ?>
        </section>
        <div class="form-actions ui-action-row" style="margin-top:12px;">
          <a class="btn btn-secondary ui-btn ui-btn--secondary" href="<?= h(url('student/subject_preview.php?subject_id=' . (int) $subject['id'])) ?>" data-ajax-modal="1" data-modal-title="Subject overview">Overview</a>
          <?php if ($subjectActivities): ?><a class="btn ui-btn ui-btn--primary" href="<?= h(url('student/submit.php?subject_id=' . (int) $subject['id'])) ?>">Choose activity</a><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$subjects): ?><div class="card empty-state ui-empty-state">No active subjects are assigned to your section yet.</div><?php endif; ?>
  </div>
</section>
<script>
(() => {
  const forms = Array.from(document.querySelectorAll('.subject-team-builder'));
  forms.forEach((form) => {
    const subjectId = form.getAttribute('data-subject-id');
    const searchInput = form.querySelector('[data-team-search]');
    const resultsBox = form.querySelector('[data-team-results]');
    const chipList = form.querySelector('[data-team-chip-list]');
    const clearBtn = form.querySelector('[data-team-clear]');
    const initialTemplate = form.querySelector('[data-initial-members]');
    if (!subjectId || !searchInput || !resultsBox || !chipList || !clearBtn || !initialTemplate) return;
    let initialMembers = [];
    try { initialMembers = JSON.parse(initialTemplate.textContent || '[]'); } catch (_) { initialMembers = []; }
    const selected = new Map(initialMembers.map((member) => [String(member.id), member]));
    let lastController = null;

    const syncHiddenInputs = () => {
      form.querySelectorAll('input[name="member_ids[]"]').forEach((el) => el.remove());
      selected.forEach((item) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'member_ids[]';
        hidden.value = String(item.id);
        form.appendChild(hidden);
      });
    };

    const renderSelected = () => {
      chipList.innerHTML = '';
      if (!selected.size) {
        chipList.innerHTML = '<div class="empty-state ui-empty-state">No additional members selected. The leader remains part of the team.</div>';
        syncHiddenInputs();
        return;
      }
      selected.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'timeline-item';
        row.innerHTML = `<div class="split-header"><div><strong>${item.full_name}</strong><div class="muted small">${item.student_id}${item.section_name ? ' · ' + item.section_name : ''}</div></div><button type="button" class="btn btn-danger btn-sm ui-btn ui-btn--danger">Remove</button></div>`;
        row.querySelector('button')?.addEventListener('click', () => {
          selected.delete(String(item.id));
          renderSelected();
        });
        chipList.appendChild(row);
      });
      syncHiddenInputs();
    };

    const renderResults = (items) => {
      resultsBox.innerHTML = '';
      if (!items.length) {
         resultsBox.innerHTML = '<div class="empty-state ui-empty-state" style="padding:12px;">No matching students found.</div>';
        resultsBox.hidden = false;
        return;
      }
      items.forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
         button.className = 'btn btn-ghost ui-btn ui-btn--ghost';
        button.style.width = '100%';
        button.style.justifyContent = 'flex-start';
        button.style.borderRadius = '0';
        button.innerHTML = `<div><strong>${item.full_name}</strong><div class="muted small">${item.student_id}${item.section_name ? ' · ' + item.section_name : ''}${item.email ? ' · ' + item.email : ''}</div></div>`;
        button.addEventListener('click', () => {
          selected.set(String(item.id), item);
          resultsBox.hidden = true;
          searchInput.value = '';
          renderSelected();
        });
        resultsBox.appendChild(button);
      });
      resultsBox.hidden = false;
    };

    const searchMembers = async () => {
      const q = searchInput.value.trim();
      if (q.length < 2) {
        resultsBox.hidden = true;
        return;
      }
      if (lastController) lastController.abort();
      lastController = new AbortController();
      const exclude = Array.from(selected.keys()).join(',');
      const response = await fetch(`member_search.php?subject_id=${encodeURIComponent(subjectId)}&q=${encodeURIComponent(q)}&exclude=${encodeURIComponent(exclude)}`, { signal: lastController.signal });
      if (!response.ok) {
        resultsBox.hidden = true;
        return;
      }
      const data = await response.json();
      renderResults(Array.isArray(data.items) ? data.items : []);
    };

    searchInput.addEventListener('input', () => {
      window.clearTimeout(searchInput._timer);
      searchInput._timer = window.setTimeout(() => { searchMembers().catch(() => {}); }, 220);
    });
    clearBtn.addEventListener('click', () => {
      selected.clear();
      renderSelected();
      resultsBox.hidden = true;
    });
    document.addEventListener('click', (event) => {
      if (!resultsBox.contains(event.target) && event.target !== searchInput) {
        resultsBox.hidden = true;
      }
    });
    renderSelected();
  });
})();
</script>
<?php require_once __DIR__ . '/../backend/partials/footer.php'; ?>
