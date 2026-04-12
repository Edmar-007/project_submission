</main>
</div>
<div class="modal-backdrop" data-modal="confirm-action" aria-hidden="true">
  <div class="modal-overlay"></div>
  <div class="modal-container">
    <div class="modal-card modal-confirm" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
      <div class="modal-head">
        <div>
          <span class="pill soft confirm-pill">Confirm action</span>
          <h3 id="confirm-action-title" data-confirm-title>Delete this item?</h3>
          <p class="muted confirm-copy" data-confirm-message>This action is intended as a safety check before you continue.</p>
        </div>
        <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close confirmation dialog">✕</button>
      </div>
      <div class="modal-body">
        <div class="callout confirm-callout">
          <strong>Heads up</strong>
          <div class="muted small">Review the action before continuing. This confirmation is shown for destructive actions such as delete, archive, deny, or deactivate.</div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="form-actions modal-actions">
          <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
          <button type="button" class="btn btn-danger" data-confirm-submit>Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= h(asset_url('app.js')) ?>"></script>
<script type="module" src="<?= h(asset_url('notification-manager.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Open modals
  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.getAttribute('data-open-modal');
      const modal = document.querySelector(`[data-modal="${id}"]`);
      if (modal) modal.style.display = 'grid';
    });
  });

  // Close modals
  document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => {
      const modal = button.closest('.ui-modal');
      if (modal) modal.style.display = 'none';
    });
  });

  // Click backdrop to close
  document.querySelectorAll('.ui-modal').forEach(modal => {
    modal.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });
  });

  // ESC key to close
  document.addEventListener('keydown', e => {
    if (e.key === "Escape") {
      document.querySelectorAll('.ui-modal').forEach(modal => {
        modal.style.display = 'none';
      });
    }
  });
});
</script>
</body>
</html>
