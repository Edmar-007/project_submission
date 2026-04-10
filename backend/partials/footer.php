</main>
</div>
<div class="modal-backdrop" data-modal="confirm-action" aria-hidden="true">
  <div class="modal-card modal-confirm" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
    <div class="modal-head">
      <div>
        <span class="pill soft confirm-pill">Confirm action</span>
        <h3 id="confirm-action-title" data-confirm-title>Delete this item?</h3>
        <p class="muted confirm-copy" data-confirm-message>This action is intended as a safety check before you continue.</p>
      </div>
      <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close confirmation dialog">✕</button>
    </div>
    <div class="callout confirm-callout">
      <strong>Heads up</strong>
      <div class="muted small">Review the action before continuing. This confirmation is shown for destructive actions such as delete, archive, deny, or deactivate.</div>
    </div>
    <div class="form-actions modal-actions">
      <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
      <button type="button" class="btn btn-danger" data-confirm-submit>Delete</button>
    </div>
  </div>
</div>
<script src="<?= h(asset_url('app.js')) ?>"></script>
</body>
</html>
