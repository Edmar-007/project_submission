<?php
/**
 * Reusable Modal Component
 * Usage: 
 * <?php render_modal('unique-key', 'Modal Title', 'Optional lead text', $contentHTML); ?>
 * 
 * $contentHTML = form/any HTML for modal body
 */
function render_modal(string $modalKey, string $title, string $lead = '', string $contentHTML = '', array $attributes = []): void {
    $id = 'modal-' . $modalKey;
    $classes = ['modal-backdrop'];
    $footerHTML = (string) ($attributes['footer_html'] ?? '');
    $showDefaultActions = (($attributes['show_default_actions'] ?? '0') === '1');
    unset($attributes['footer_html'], $attributes['show_default_actions']);
    if (isset($attributes['class'])) {
        $classes[] = $attributes['class'];
        unset($attributes['class']);
    }
    $ariaLabelledby = $attributes['aria-labelledby'] ?? $id . '-title';
    ?>
    
    <div 
        data-modal="<?= h($modalKey) ?>" 
        class="<?= implode(' ', $classes) ?>" 
        aria-hidden="true"
        aria-modal="true"
        role="dialog"
        aria-labelledby="<?= h($ariaLabelledby) ?>"
        <?= array_reduce(
            $attributes, 
            fn($carry, $value, $key) => $carry .= ' ' . h($key) . '="' . h($value) . '"', 
            ''
        ) ?>
    >
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-card">
                <div class="modal-head">
                    <div>
                        <span class="pill soft"><?= h($attributes['pill'] ?? 'Dialog') ?></span>
                        <h3 id="<?= h($id) ?>-title"><?= h($title) ?></h3>
                        <?php if ($lead): ?><p class="muted"><?= h($lead) ?></p><?php endif; ?>
                    </div>
                    <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close modal">
                        <span aria-hidden="true">✕</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?= $contentHTML ?>
                </div>
                <?php if ($footerHTML !== '' || $showDefaultActions): ?>
                    <div class="modal-footer">
                        <?php if ($footerHTML !== ''): ?>
                            <?= $footerHTML ?>
                        <?php else: ?>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                                <button type="button" class="btn" data-primary-action>Save</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php
}
?>

