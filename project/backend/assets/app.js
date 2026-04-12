const body = document.body;

const setDataLabels = (table) => {
  const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
  table.querySelectorAll('tbody tr').forEach((row) => {
    row.querySelectorAll('td').forEach((cell, index) => {
      if (!cell.getAttribute('data-label')) {
        cell.setAttribute('data-label', headers[index] || 'Details');
      }
    });
  });
};

document.querySelectorAll('[data-multistep]').forEach((form) => {
  const panels = Array.from(form.querySelectorAll('.step-panel'));
  const badges = Array.from(form.querySelectorAll('.stepper div'));
  let index = 0;
  const refresh = () => {
    panels.forEach((panel, i) => panel.classList.toggle('active', i === index));
    badges.forEach((badge, i) => badge.classList.toggle('active', i === index));
  };
  form.querySelectorAll('[data-next]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (index < panels.length - 1) { index += 1; refresh(); }
    });
  });
  form.querySelectorAll('[data-prev]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (index > 0) { index -= 1; refresh(); }
    });
  });
  refresh();
});

const getSidebarStateKey = () => `psms-sidebar-${document.body.className.match(/role-([a-z]+)/)?.[1] || 'guest'}`;
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');

const closeAllDropdowns = () => {
  document.querySelectorAll('[data-dropdown-shell].open, .notification-menu.open').forEach((menu) => {
    menu.classList.remove('open');
    const toggle = menu.querySelector('[data-dropdown-toggle], [data-toggle-menu]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  });
};

const applySidebarState = (isOpen) => {
  const mobile = window.innerWidth <= 1024;
  body.classList.toggle('sidebar-open', !!isOpen);
  if (!mobile) {
    body.classList.toggle('sidebar-collapsed', !isOpen);
  } else {
    body.classList.remove('sidebar-collapsed');
  }
  if (sidebarToggle) {
    sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }
};

const syncSidebarState = () => {
  if (!sidebarToggle) return;
  const mobile = window.innerWidth <= 1024;
  let isOpen = true;
  if (mobile) {
    isOpen = body.classList.contains('sidebar-open');
  } else {
    isOpen = localStorage.getItem(getSidebarStateKey()) !== 'collapsed';
  }
  applySidebarState(isOpen);
};

if (sidebarToggle) {
  syncSidebarState();
  sidebarToggle.addEventListener('click', () => {
    const mobile = window.innerWidth <= 1024;
    const currentlyOpen = mobile ? body.classList.contains('sidebar-open') : !body.classList.contains('sidebar-collapsed');
    const nextOpen = !currentlyOpen;
    if (!mobile) {
      localStorage.setItem(getSidebarStateKey(), nextOpen ? 'open' : 'collapsed');
    }
    applySidebarState(nextOpen);
  });
}

sidebarOverlay?.addEventListener('click', () => {
  body.classList.remove('sidebar-open');
});

window.addEventListener('resize', syncSidebarState);

if (sidebarToggle) {
  const updateSidebarToggleTitle = () => {
    const mobile = window.innerWidth <= 1024;
    const expanded = mobile ? body.classList.contains('sidebar-open') : !body.classList.contains('sidebar-collapsed');
    sidebarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    sidebarToggle.setAttribute('title', expanded ? 'Collapse navigation' : 'Expand navigation');
  };
  updateSidebarToggleTitle();
  const syncAndLabel = () => { syncSidebarState(); updateSidebarToggleTitle(); };
  window.removeEventListener('resize', syncSidebarState);
  window.addEventListener('resize', syncAndLabel);
  sidebarToggle.addEventListener('click', () => { window.setTimeout(updateSidebarToggleTitle, 0); });
}

document.querySelectorAll('[data-notification-toggle]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    const wrapper = button.closest('.notification-menu');
    if (!wrapper) return;
    const willOpen = !wrapper.classList.contains('open');
    closeAllDropdowns();
    wrapper.classList.toggle('open', willOpen);
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });
});

document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation();
    const wrapper = button.closest('[data-dropdown-shell]');
    const willOpen = !wrapper?.classList.contains('open');
    closeAllDropdowns();
    wrapper?.classList.toggle('open', willOpen);
    button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });
});

document.addEventListener('click', () => {
  closeAllDropdowns();
  if (window.innerWidth <= 1024) {
    body.classList.remove('sidebar-open');
  }
});

const MODAL_ANIMATION_MS = 260;
let lastModalOpener = null;

const focusFirstModalField = (modal) => {
  const target = modal?.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])');
  target?.focus();
};

const closeModal = (modal) => {
  if (!modal) return;
  modal.classList.remove('open');
  modal.classList.add('is-closing');
  modal.setAttribute('aria-hidden', 'true');
  window.setTimeout(() => {
    modal.classList.remove('is-closing');
    if (!document.querySelector('.modal-backdrop.open')) {
      body.classList.remove('modal-open');
      lastModalOpener?.focus?.();
    }
  }, MODAL_ANIMATION_MS);
};

const openModalByKey = (key, opener = null) => {
  const modal = document.querySelector(`[data-modal="${key}"]`);
  if (!modal) return;
  lastModalOpener = opener || document.activeElement;
  modal.classList.remove('is-closing');
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  body.classList.add('modal-open');
  window.setTimeout(() => focusFirstModalField(modal), 20);
};

document.querySelectorAll('[data-open-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    openModalByKey(button.getAttribute('data-open-modal'), button);
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
});

document.querySelectorAll('[data-modal-form="subject"]').forEach((form) => {
  const validateSubjectModal = () => {
    const code = (form.querySelector('input[name="subject_code"]')?.value || '').trim();
    const name = (form.querySelector('input[name="subject_name"]')?.value || '').trim();
    const checkedSections = form.querySelectorAll('input[name="section_ids[]"]:checked').length;

    if (!code || !name) {
      window.alert('Subject code and subject name are required.');
      return false;
    }

    if (!checkedSections) {
      window.alert('Select at least one section for this subject.');
      return false;
    }

    return true;
  };

  form.addEventListener('submit', (event) => {
    if (!validateSubjectModal()) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('.modal-backdrop').forEach((modal) => {
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  const openModals = Array.from(document.querySelectorAll('.modal-backdrop.open'));
  const topModal = openModals[openModals.length - 1];
  if (topModal) closeModal(topModal);
});

document.querySelectorAll('.notification-dropdown, .profile-dropdown').forEach((panel) => {
  panel.addEventListener('click', (event) => event.stopPropagation());
});

const confirmModal = document.querySelector('[data-modal="confirm-action"]');
const confirmTitle = confirmModal?.querySelector('[data-confirm-title]');
const confirmMessage = confirmModal?.querySelector('[data-confirm-message]');
const confirmSubmit = confirmModal?.querySelector('[data-confirm-submit]');
let pendingConfirmAction = null;

const cleanLabel = (value) => (value || '').replace(/\s+/g, ' ').trim();
const sentenceCase = (value) => {
  const label = cleanLabel(value);
  if (!label) return 'Continue';
  return label.charAt(0).toUpperCase() + label.slice(1);
};
const titleCase = (value) => {
  const label = cleanLabel(value).toLowerCase();
  if (!label) return 'Confirm action';
  return label.replace(/\b\w/g, (char) => char.toUpperCase());
};
const getFormActionValue = (form) => {
  if (!form) return '';
  const actionInput = form.querySelector('input[name="action"]');
  return cleanLabel(actionInput?.value || '');
};
const getCandidateLabel = (trigger) => {
  if (!trigger) return '';
  return cleanLabel(
    trigger.dataset.confirmAction
    || trigger.dataset.confirmCta
    || trigger.getAttribute('aria-label')
    || trigger.getAttribute('title')
    || (trigger.tagName === 'INPUT' ? trigger.value : trigger.textContent)
    || getFormActionValue(trigger.form)
  );
};

const destructivePattern = /(delete|archive|remove|deny|deactivate|discard|block|restrict)/i;

const buildConfirmMeta = (trigger) => {
  if (!trigger || trigger.dataset.confirmBypass === '1' || trigger.hasAttribute('data-close-modal')) return null;

  const explicitTitle = cleanLabel(trigger.dataset.confirmTitle);
  const explicitMessage = cleanLabel(trigger.dataset.confirmMessage);
  const explicitCta = cleanLabel(trigger.dataset.confirmCta);
  const explicitTone = cleanLabel(trigger.dataset.confirmTone);

  const label = getCandidateLabel(trigger);
  const formAction = getFormActionValue(trigger.form);
  const attrMatch = destructivePattern.test(label)
    || destructivePattern.test(formAction)
    || destructivePattern.test(trigger.className || '')
    || trigger.classList.contains('btn-danger')
    || trigger.hasAttribute('data-confirm');

  if (!explicitTitle && !explicitMessage && !attrMatch) return null;

  const normalizedLabel = cleanLabel(explicitCta || label || formAction || 'Continue');
  const title = explicitTitle || `${titleCase(normalizedLabel)}?`;
  const message = explicitMessage || `Please confirm before you ${normalizedLabel.toLowerCase()}. This helps prevent accidental destructive actions.`;

  return {
    title,
    message,
    cta: sentenceCase(explicitCta || normalizedLabel || 'Continue'),
    tone: explicitTone || (trigger.classList.contains('btn-danger') || /delete|deny|deactivate|remove|discard|block/i.test(normalizedLabel) ? 'danger' : 'default')
  };
};

const openConfirmModal = (meta) => {
  if (!confirmModal || !meta) return;
  if (confirmTitle) confirmTitle.textContent = meta.title;
  if (confirmMessage) confirmMessage.textContent = meta.message;
  if (confirmSubmit) {
    confirmSubmit.textContent = meta.cta;
    confirmSubmit.classList.toggle('btn-danger', meta.tone !== 'default');
    confirmSubmit.classList.toggle('btn-secondary', meta.tone === 'default');
  }
  confirmModal.classList.add('open');
  confirmModal.setAttribute('aria-hidden', 'false');
  body.classList.add('modal-open');
};

const runPendingConfirmAction = () => {
  if (!pendingConfirmAction) return;
  const action = pendingConfirmAction;
  pendingConfirmAction = null;
  closeModal(confirmModal);

  if (action.type === 'form') {
    if (action.submitter) action.submitter.dataset.confirmBypass = '1';
    if (action.form.requestSubmit && action.submitter) {
      action.form.requestSubmit(action.submitter);
    } else if (action.form.requestSubmit) {
      action.form.requestSubmit();
    } else {
      action.form.submit();
    }
    window.setTimeout(() => {
      if (action.submitter) delete action.submitter.dataset.confirmBypass;
    }, 800);
    return;
  }

  if (action.type === 'link' && action.href) {
    if (action.target === '_blank') {
      window.open(action.href, '_blank', 'noopener');
    } else {
      window.location.href = action.href;
    }
  }
};

if (confirmSubmit) {
  confirmSubmit.addEventListener('click', runPendingConfirmAction);
}

document.addEventListener('click', (event) => {
  if (!confirmModal) return;
  const trigger = event.target.closest('button, input[type="submit"], a');
  if (!trigger || trigger.closest('[data-modal="confirm-action"]')) return;

  const meta = buildConfirmMeta(trigger);
  if (!meta) return;

  if (trigger.tagName === 'A') {
    const href = trigger.getAttribute('href');
    if (!href || href === '#' || href.startsWith('javascript:')) return;
    event.preventDefault();
    pendingConfirmAction = { type: 'link', href, target: trigger.getAttribute('target') || '' };
    openConfirmModal(meta);
    return;
  }

  const isSubmitControl = trigger.matches('button[type="submit"], input[type="submit"]') || (trigger.tagName === 'BUTTON' && !trigger.getAttribute('type'));
  if (!isSubmitControl || !trigger.form) return;

  event.preventDefault();
  pendingConfirmAction = { type: 'form', form: trigger.form, submitter: trigger };
  openConfirmModal(meta);
});

document.querySelectorAll('[data-copy-text]').forEach((button) => {
  button.addEventListener('click', async () => {
    const text = button.getAttribute('data-copy-text') || '';
    if (!text) return;
    const original = button.textContent;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
      } else {
        const temp = document.createElement('textarea');
        temp.value = text;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        temp.remove();
      }
      button.textContent = 'Copied';
      if (typeof showToast === 'function') showToast('Copied to clipboard', 'success', 'The value is ready to paste.');
      window.setTimeout(() => { button.textContent = original; }, 1200);
    } catch (error) {
      button.textContent = 'Copy failed';
      if (typeof showToast === 'function') showToast('Copy failed', 'error', 'Your browser blocked clipboard access.');
      window.setTimeout(() => { button.textContent = original; }, 1400);
    }
  });
});

document.querySelectorAll('[data-toggle-secret]').forEach((button) => {
  button.addEventListener('click', () => {
    const id = button.getAttribute('data-toggle-secret');
    const target = id ? document.getElementById(id) : null;
    if (!target) return;
    const secret = target.getAttribute('data-secret') || '';
    const showing = target.getAttribute('data-visible') === '1';
    if (showing) {
      target.textContent = '•'.repeat(Math.max(8, secret.length || 8));
      target.setAttribute('data-visible', '0');
      button.textContent = 'Show';
    } else {
      target.textContent = secret || '—';
      target.setAttribute('data-visible', '1');
      button.textContent = 'Hide';
    }
  });
});

const createGeneratedModal = ({ key, title, lead, contentNode, maxWidth = '' }) => {
  if (!contentNode) return null;
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop generated-modal';
  backdrop.dataset.modal = key;
  backdrop.setAttribute('aria-hidden', 'true');

  const card = document.createElement('div');
  card.className = 'modal-card';
  if (maxWidth) card.style.maxWidth = maxWidth;
  card.setAttribute('role', 'dialog');
  card.setAttribute('aria-modal', 'true');

  const head = document.createElement('div');
  head.className = 'modal-head';
  head.innerHTML = `
    <div>
      <span class="pill soft">Workspace modal</span>
      <h3>${title}</h3>
      ${lead ? `<p class="muted">${lead}</p>` : ''}
    </div>
    <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
  `;

  const bodyWrap = document.createElement('div');
  bodyWrap.className = 'generated-modal-form';
  bodyWrap.appendChild(contentNode);

  card.appendChild(head);
  card.appendChild(bodyWrap);
  backdrop.appendChild(card);
  document.body.appendChild(backdrop);

  head.querySelector('[data-close-modal]')?.addEventListener('click', () => closeModal(backdrop));
  backdrop.addEventListener('click', (event) => {
    if (event.target === backdrop) closeModal(backdrop);
  });
  return backdrop;
};


const ensureAjaxDetailModal = () => {
  let modal = document.querySelector('[data-modal="ajax-detail"]');
  if (modal) return modal;

  modal = document.createElement('div');
  modal.className = 'modal-backdrop ajax-detail-backdrop';
  modal.dataset.modal = 'ajax-detail';
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="modal-card modal-card-xl ajax-detail-card" role="dialog" aria-modal="true" aria-labelledby="ajax-detail-title">
      <div class="modal-head">
        <div>
          <span class="pill soft">Quick view</span>
          <h3 id="ajax-detail-title">Record preview</h3>
          <p class="muted" data-ajax-detail-lead>Open record details without leaving the current page.</p>
        </div>
        <button type="button" class="icon-btn modal-close" data-close-modal aria-label="Close dialog">✕</button>
      </div>
      <div class="ajax-detail-body" data-ajax-detail-body></div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.querySelector('[data-close-modal]')?.addEventListener('click', () => closeModal(modal));
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal(modal);
  });
  return modal;
};

const openAjaxDetailModal = async (trigger) => {
  const href = trigger?.getAttribute('href') || '';
  if (!href) return;
  const modal = ensureAjaxDetailModal();
  const bodyHost = modal.querySelector('[data-ajax-detail-body]');
  const titleHost = modal.querySelector('#ajax-detail-title');
  const leadHost = modal.querySelector('[data-ajax-detail-lead]');
  if (!bodyHost || !titleHost) return;

  titleHost.textContent = trigger.getAttribute('data-modal-title') || trigger.getAttribute('aria-label') || trigger.getAttribute('title') || 'Record preview';
  if (leadHost) leadHost.textContent = trigger.getAttribute('data-modal-lead') || 'Open record details without leaving the current page.';
  bodyHost.innerHTML = '<div class="ajax-detail-loading"><div class="ajax-detail-spinner" aria-hidden="true"></div><div><strong>Loading preview…</strong><div class="muted small">Fetching the latest details for this record.</div></div></div>';
  openModalByKey('ajax-detail');

  try {
    const response = await fetch(href, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html'
      },
      credentials: 'same-origin'
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    bodyHost.innerHTML = await response.text();
  } catch (error) {
    bodyHost.innerHTML = '<div class="empty-state"><strong>Preview unavailable.</strong><div class="muted small">The record could not be loaded in a modal right now. Please open the full page instead.</div></div>';
  }
};

const bindAjaxDetailModalTriggers = () => {
  document.querySelectorAll('a[data-ajax-modal="1"][href]').forEach((link) => {
    if (link.dataset.ajaxModalBound === '1') return;
    link.addEventListener('click', (event) => {
      event.preventDefault();
      openAjaxDetailModal(link);
    });
    link.dataset.ajaxModalBound = '1';
  });
};

const registerModalLauncher = (label, key, tone = 'secondary') => {
  const host = document.querySelector('[data-page-actions]');
  if (!host) return null;
  const button = document.createElement('button');
  button.type = 'button';
  button.className = `btn btn-${tone === 'primary' ? '' : tone} modal-launch-btn`.trim();
  if (tone === 'primary') button.className = 'btn modal-launch-btn';
  button.textContent = label;
  button.dataset.openModal = key;
  button.addEventListener('click', () => openModalByKey(key));
  host.appendChild(button);
  return button;
};

const convertDetailsToModals = () => {
  document.querySelectorAll('details').forEach((details, index) => {
    const summary = details.querySelector('summary');
    if (!summary || details.dataset.modalized === '1' || details.closest('.notification-dropdown')) return;
    const label = cleanLabel(summary.textContent) || 'Open details';
    const contentNodes = Array.from(details.childNodes).filter((node) => node !== summary && !(node.nodeType === 3 && !node.textContent.trim()));
    if (!contentNodes.length) return;

    const fragment = document.createElement('div');
    fragment.className = 'quick-modal-grid';
    contentNodes.forEach((node) => fragment.appendChild(node));

    const key = `details-modal-${index + 1}`;
    createGeneratedModal({ key, title: label, lead: 'Complete the action in a focused modal instead of inside the table row.', contentNode: fragment });

    const button = document.createElement('button');
    button.type = 'button';
    button.className = summary.className || 'btn btn-outline';
    button.textContent = label;
    button.dataset.openModal = key;
    button.addEventListener('click', () => openModalByKey(key));
    details.replaceWith(button);
    details.dataset.modalized = '1';
  });
};

const convertPrimaryFormsToModals = () => {
  const shouldConvertCard = (card) => {
    const heading = card.querySelector('h3, .section-title');
    const form = card.querySelector('form');
    const hasTable = card.querySelector('.table-wrap');
    const text = cleanLabel(heading?.textContent || '');
    if (!heading || !form || hasTable || card.dataset.modalized === '1') return false;
    return /^(add|create|invite|bulk)/i.test(text);
  };

  document.querySelectorAll('.card').forEach((card, index) => {
    if (!shouldConvertCard(card)) return;
    const heading = card.querySelector('h3, .section-title');
    const key = `card-modal-${index + 1}`;
    const title = cleanLabel(heading?.textContent || 'Open form');
    const lead = cleanLabel(card.querySelector('.muted')?.textContent || 'Open this form in a modal to keep the page cleaner.');

    const clone = card.cloneNode(true);
    clone.classList.remove('card');
    clone.style.padding = '0';
    clone.style.border = '0';
    clone.style.boxShadow = 'none';
    createGeneratedModal({ key, title, lead, contentNode: clone, maxWidth: '880px' });
    registerModalLauncher(title, key, 'primary');

    const teaser = document.createElement('div');
    teaser.className = 'card';
    teaser.innerHTML = `
      <div class="split-header">
        <div>
          <h3 class="section-title">${title}</h3>
          <div class="muted small">This create form now opens in a modal so the workspace stays focused.</div>
        </div>
        <span class="pill">Modal</span>
      </div>
      <div class="callout modal-inline-note">
        <strong>Quick access</strong>
        <div class="muted small">Use the page action button in the upper-right area to open the form.</div>
      </div>
    `;
    card.replaceWith(teaser);
    card.dataset.modalized = '1';
  });
};


const actionIconSvg = {
  view: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1.5 12s3.8-7 10.5-7 10.5 7 10.5 7-3.8 7-10.5 7S1.5 12 1.5 12Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
  edit: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m16.5 3.5 4 4L8 20l-5 1 1-5 12.5-12.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  archive: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5h18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 7.5h14V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 3h8l1 4.5H7L8 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  delete: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M9 3h6l1 4H8l1-4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M10 11v5M14 11v5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  lock: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7.5a4 4 0 1 1 8 0V10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  download: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m7.5 11.5 4.5 4.5 4.5-4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  copy: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="4" y="4" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
  grade: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.2 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  approve: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  send: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 21 4l-6.2 16-3.6-6.7L3 11.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M21 4 11.2 13.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
  file: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h6l5 5v12a1 1 0 0 1-1 1H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 3v5h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
  link: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13.5 8.5 15a3.5 3.5 0 0 1-5-5L7 6.5a3.5 3.5 0 0 1 5 5L10.5 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 10.5 15.5 9a3.5 3.5 0 1 1 5 5L17 17.5a3.5 3.5 0 0 1-5-5L13.5 11" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  more: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></svg>'
};

const inferActionIcon = (label) => {
  const value = cleanLabel(label).toLowerCase();
  if (!value) return 'more';
  if (/\b(view|open|review|preview|details?)\b/.test(value)) return 'view';
  if (/\b(print)\b/.test(value)) return 'view';
  if (/\b(edit|quick edit|update|manage)\b/.test(value)) return 'edit';
  if (/\b(grade)\b/.test(value)) return 'grade';
  if (/\b(copy|duplicate)\b/.test(value)) return 'copy';
  if (/\b(export|download)\b/.test(value)) return 'download';
  if (/\b(resend|invite|email|mail|send)\b/.test(value)) return 'send';
  if (/\b(add|new|create)\b/.test(value)) return 'plus';
  if (/\b(file|resource|attachment|document)\b/.test(value)) return 'file';
  if (/\b(project|video|url|link|open link)\b/.test(value)) return 'link';
  if (/\b(archive)\b/.test(value)) return 'archive';
  if (/\b(delete|remove|trash)\b/.test(value)) return 'delete';
  if (/\b(deactivate|restrict|block|deny|reject|lock)\b/.test(value)) return 'lock';
  if (/\b(approve|accept|confirm|activate|reactivate|save)\b/.test(value)) return 'approve';
  return 'more';
};

const iconizeActionControl = (action, options = {}) => {
  if (!action || action.dataset.iconized === '1') return;
  const { iconOnly = false } = options;
  const label = cleanLabel(action.textContent || action.getAttribute('aria-label') || action.getAttribute('title') || 'Action');
  const iconKey = action.dataset.icon || inferActionIcon(label);
  const labelText = iconOnly ? ((label || 'Action').toLowerCase()) : (label || 'Action');

  const iconSpan = document.createElement('span');
  iconSpan.className = 'btn-action-icon';
  iconSpan.setAttribute('aria-hidden', 'true');
  iconSpan.innerHTML = actionIconSvg[iconKey] || actionIconSvg.more;

  action.textContent = '';
  action.append(iconSpan);

  if (iconOnly) {
    action.classList.remove('btn', 'btn-outline', 'btn-secondary', 'btn-danger', 'table-text-action');
    action.classList.add('icon-action');
  } else {
    const labelSpan = document.createElement('span');
    labelSpan.className = 'btn-action-label';
    labelSpan.textContent = labelText;
    action.append(labelSpan);
    action.classList.add('btn-action');
  }

  action.setAttribute('title', labelText);
  if (!action.getAttribute('aria-label')) action.setAttribute('aria-label', labelText);
  action.dataset.iconized = '1';
};

const markGenericTableActionCells = () => {
  if (window.location.pathname.includes('print_')) return;
  document.querySelectorAll('.table-wrap table, .table-responsive table').forEach((table) => {
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => cleanLabel(th.textContent).toLowerCase());
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    if (!headers.length || !rows.length) return;

    const actionIndices = new Set();
    headers.forEach((header, index) => {
      if (/^(actions?|decision|controls?)$/.test(header) || /\b(actions?|decision|controls?)\b/.test(header)) actionIndices.add(index);
      if (/\b(invite|links?|file|resource)\b/.test(header)) actionIndices.add(index);
    });

    rows.forEach((row) => {
      const cells = Array.from(row.children);
      cells.forEach((cell, index) => {
        const controls = cell.querySelectorAll('a, button, summary');
        const hasControls = controls.length > 0;
        const compactText = cleanLabel(cell.textContent);
        const looksLikeActionCell = hasControls && (actionIndices.has(index) || (index === cells.length - 1 && controls.length <= 3) || (controls.length >= 2 && compactText.length <= 60));
        if (!looksLikeActionCell) return;
        cell.classList.add('table-cell-actions');
        const wrapper = cell.querySelector('.table-actions') || cell.querySelector('.icon-action-group') || cell.querySelector('.form-actions');
        if (wrapper) wrapper.classList.add('table-actions');
      });
    });
  });
};

const enhanceTableActionIcons = () => {
  markGenericTableActionCells();
  document.querySelectorAll('.table-actions a, .table-actions button, .table-actions summary, td.table-cell-actions > a, td.table-cell-actions > button, td.table-cell-actions > summary, td.table-cell-actions .inline > button, td.table-cell-actions .inline > a, td.table-cell-actions .muted-link').forEach((action) => {
    if (action.closest('.table-pagination')) return;
    if (action.classList.contains('icon-action')) {
      const labelText = cleanLabel(action.getAttribute('aria-label') || action.getAttribute('title') || action.textContent || 'Action').toLowerCase();
      action.setAttribute('title', labelText);
      if (!action.getAttribute('aria-label')) action.setAttribute('aria-label', labelText);
      action.dataset.iconized = '1';
      return;
    }
    iconizeActionControl(action, { iconOnly: true });
  });
};

const registerTableActionLinkModals = () => {
  document.querySelectorAll('td.table-cell-actions a.icon-action[data-modal-link="1"][href]:not([target="_blank"]):not([download])').forEach((link, index) => {
    if (link.dataset.modalBound === '1') return;
    const href = link.getAttribute('href') || '';
    if (!href || href === '#' || href.startsWith('javascript:')) return;
    const url = new URL(href, window.location.href);
    if (url.origin !== window.location.origin) return;

    const labelText = cleanLabel(link.getAttribute('aria-label') || link.getAttribute('title') || 'Open record');
    const key = `table-action-link-${index + 1}`;
    const frameWrap = document.createElement('div');
    frameWrap.className = 'remote-modal-shell';
    frameWrap.innerHTML = `<iframe class="remote-modal-frame" src="${url.href}" loading="lazy" title="${labelText}"></iframe>`;
    createGeneratedModal({
      key,
      title: labelText,
      lead: 'This record opens in a focused modal so you can stay on the table.',
      contentNode: frameWrap,
      maxWidth: 'min(1240px, 96vw)'
    });

    link.addEventListener('click', (event) => {
      event.preventDefault();
      openModalByKey(key);
    });
    link.dataset.modalBound = '1';
  });
};

const registerTableActionConfirmations = () => {
  document.querySelectorAll('td.table-cell-actions button[type="submit"], td.table-cell-actions form button, .icon-action-group form .icon-action').forEach((button) => {
    if (button.dataset.confirmTitle) return;
    const labelText = cleanLabel(button.getAttribute('aria-label') || button.getAttribute('title') || button.textContent || 'Confirm action').toLowerCase();
    if (!/(archive|delete|remove|trash|deactivate|deny)/.test(labelText)) return;
    button.dataset.confirmTitle = `${labelText}?`;
    button.dataset.confirmMessage = 'Please confirm this table action before continuing.';
  });
};

const enhanceTables = () => {
  if (window.location.pathname.includes('print_')) return;
  document.querySelectorAll('.table-wrap table').forEach((table, tableIndex) => {
    if (table.dataset.enhanced === '1') return;
    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody?.querySelectorAll('tr') || []);
    if (!tbody || allRows.length === 0) return;

    setDataLabels(table);
    const headers = Array.from(table.querySelectorAll('thead th')).map((th) => cleanLabel(th.textContent));
    const hasRealRows = allRows.some((row) => row.querySelectorAll('td').length > 1 || !row.textContent.toLowerCase().includes('no '));
    if (!hasRealRows) return;

    const originalRows = allRows.filter((row) => !row.querySelector('.empty-state'));
    const emptyTemplate = allRows.find((row) => row.querySelector('.empty-state')) || null;
    if (!originalRows.length) return;

    const wrap = table.closest('.table-wrap');
    const toolbar = document.createElement('div');
    toolbar.className = 'table-enhancement';
    toolbar.innerHTML = `
      <div class="table-enhancement-left">
        <span class="pill soft">Rows <span class="table-inline-count">${originalRows.length}</span></span>
        <span class="table-meta-note">Improved table view with client-side search and pagination.</span>
      </div>
      <div class="table-enhancement-right">
        <input class="table-search" type="search" placeholder="Search this table">
        <select class="table-page-size" aria-label="Rows per page">
          <option value="5">5 rows</option>
          <option value="8" selected>8 rows</option>
          <option value="12">12 rows</option>
          <option value="20">20 rows</option>
        </select>
      </div>
    `;

    const footer = document.createElement('div');
    footer.className = 'table-footer';
    footer.innerHTML = `
      <div class="table-meta-note" data-range>Showing 1-${Math.min(8, originalRows.length)} of ${originalRows.length}</div>
      <div class="table-pagination" data-pagination></div>
    `;

    wrap.prepend(toolbar);
    wrap.appendChild(footer);

    const searchInput = toolbar.querySelector('.table-search');
    const pageSizeSelect = toolbar.querySelector('.table-page-size');
    const rangeLabel = footer.querySelector('[data-range]');
    const paginationHost = footer.querySelector('[data-pagination]');
    const responsiveDefault = window.innerWidth < 760 ? 5 : 8;
    pageSizeSelect.value = String(responsiveDefault);

    let query = '';
    let page = 1;
    let pageSize = Number(pageSizeSelect.value || responsiveDefault);
    let emptyRow = null;

    const rowMatches = (row) => {
      if (!query) return true;
      return row.textContent.toLowerCase().includes(query);
    };

    const render = () => {
      const filtered = originalRows.filter(rowMatches);
      const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
      if (page > totalPages) page = totalPages;
      const start = (page - 1) * pageSize;
      const end = start + pageSize;

      originalRows.forEach((row) => {
        row.dataset.hidden = '1';
      });

      filtered.slice(start, end).forEach((row) => {
        row.dataset.hidden = '0';
      });

      if (emptyTemplate) emptyTemplate.style.display = filtered.length ? 'none' : '';

      if (!filtered.length) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.className = 'table-empty-search';
          const cell = document.createElement('td');
          cell.colSpan = headers.length || 1;
          cell.textContent = 'No rows matched this page search.';
          emptyRow.appendChild(cell);
          tbody.appendChild(emptyRow);
        }
        emptyRow.style.display = '';
      } else if (emptyRow) {
        emptyRow.style.display = 'none';
      }

      const visibleStart = filtered.length ? start + 1 : 0;
      const visibleEnd = filtered.length ? Math.min(end, filtered.length) : 0;
      rangeLabel.textContent = `Showing ${visibleStart}-${visibleEnd} of ${filtered.length}`;
      toolbar.querySelector('.table-inline-count').textContent = String(filtered.length);

      paginationHost.innerHTML = '';
      const makeButton = (label, nextPage, disabled = false, active = false) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'table-page-btn' + (active ? ' active' : '');
        btn.textContent = label;
        btn.disabled = disabled;
        btn.addEventListener('click', () => {
          page = nextPage;
          render();
        });
        paginationHost.appendChild(btn);
      };

      makeButton('‹', Math.max(1, page - 1), page === 1);
      const pagesToShow = [];
      for (let p = 1; p <= totalPages; p += 1) {
        if (p === 1 || p === totalPages || Math.abs(p - page) <= 1) pagesToShow.push(p);
      }
      let lastRendered = 0;
      pagesToShow.forEach((p) => {
        if (p - lastRendered > 1) {
          const dots = document.createElement('span');
          dots.className = 'table-meta-note';
          dots.textContent = '…';
          paginationHost.appendChild(dots);
        }
        makeButton(String(p), p, false, p === page);
        lastRendered = p;
      });
      makeButton('›', Math.min(totalPages, page + 1), page === totalPages);
    };

    searchInput.addEventListener('input', () => {
      query = searchInput.value.trim().toLowerCase();
      page = 1;
      render();
    });
    pageSizeSelect.addEventListener('change', () => {
      pageSize = Number(pageSizeSelect.value || 8);
      page = 1;
      render();
    });

    render();
    table.dataset.enhanced = '1';
    wrap.dataset.enhanced = `table-${tableIndex + 1}`;
  });
};

convertDetailsToModals();
convertPrimaryFormsToModals();
enhanceTables();
enhanceTableActionIcons();
registerTableActionConfirmations();
bindAjaxDetailModalTriggers();

const applyStoredStudentAvatar = () => {};

const initStudentAvatarControls = () => {};

const initStudentSubjectFilters = () => {
  const grid = document.querySelector('[data-student-subject-grid]');
  const searchInput = document.querySelector('[data-student-search]');
  const stateSelect = document.querySelector('[data-student-state]');
  if (!grid || !searchInput || !stateSelect) return;

  const cards = Array.from(grid.querySelectorAll('[data-status][data-search]'));
  const refresh = () => {
    const query = searchInput.value.trim().toLowerCase();
    const state = stateSelect.value.trim().toLowerCase();
    let visible = 0;
    cards.forEach((card) => {
      const matchesQuery = !query || (card.dataset.search || '').includes(query);
      const matchesState = !state || (card.dataset.status || '').toLowerCase() === state;
      const show = matchesQuery && matchesState;
      card.style.display = show ? '' : 'none';
      if (show) visible += 1;
    });

    let empty = grid.querySelector('.student-filter-empty');
    if (!visible) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'card empty-state student-filter-empty';
        empty.textContent = 'No subjects matched your current search or filter.';
        grid.appendChild(empty);
      }
    } else if (empty) {
      empty.remove();
    }
  };

  searchInput.addEventListener('input', refresh);
  stateSelect.addEventListener('change', refresh);
  refresh();
};

initStudentSubjectFilters();


const initScrollTargetButtons = () => {
  document.querySelectorAll('[data-scroll-target]').forEach((button) => {
    if (button.dataset.boundScrollTarget === '1') return;
    button.dataset.boundScrollTarget = '1';
    button.addEventListener('click', () => {
      const selector = button.getAttribute('data-scroll-target');
      const target = selector ? document.querySelector(selector) : null;
      if (!target) return;
      document.querySelectorAll('.modal-backdrop.open').forEach(closeModal);
      window.setTimeout(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 80);
    });
  });
};

initScrollTargetButtons();


document.querySelectorAll('[data-settings-tabs]').forEach((shell) => {
  const buttons = Array.from(shell.querySelectorAll('[data-settings-target]'));
  const panels = Array.from(shell.querySelectorAll('.settings-tab-panel'));
  const activate = (targetId) => {
    buttons.forEach((button) => {
      const active = button.getAttribute('data-settings-target') === targetId;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => {
      const active = panel.id === targetId;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  };
  buttons.forEach((button) => {
    button.addEventListener('click', () => activate(button.getAttribute('data-settings-target')));
  });
  const hash = (window.location.hash || '').replace(/^#/, '').toLowerCase();
  const hashMap = { profile: buttons[0]?.getAttribute('data-settings-target') || '', security: buttons[1]?.getAttribute('data-settings-target') || '', preferences: buttons[2]?.getAttribute('data-settings-target') || '' };
  const initial = hashMap[hash] || buttons.find((button) => button.classList.contains('is-active'))?.getAttribute('data-settings-target') || panels[0]?.id;
  if (initial) activate(initial);
});


const ensureToastStack = () => {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  return stack;
};

const showToast = (message, tone = 'info', meta = '') => {
  const stack = ensureToastStack();
  const toast = document.createElement('div');
  toast.className = `toast ${tone}`;
  toast.innerHTML = `<strong>${message}</strong>${meta ? `<small>${meta}</small>` : ''}`;
  stack.appendChild(toast);
  window.setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(8px) scale(.98)';
    window.setTimeout(() => toast.remove(), 220);
  }, 2200);
};

const enhanceFlashMessages = () => {
  document.querySelectorAll('.flash').forEach((flash) => {
    const tone = flash.classList.contains('error') ? 'error' : flash.classList.contains('success') ? 'success' : 'info';
    flash.setAttribute('role', 'status');
    flash.setAttribute('aria-live', 'polite');
    if (!flash.querySelector('[data-flash-dismiss]')) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'icon-btn';
      btn.style.cssText = 'margin-left:auto;width:36px;height:36px;font-size:14px;box-shadow:none;';
      btn.setAttribute('aria-label', 'Dismiss message');
      btn.setAttribute('data-flash-dismiss', '1');
      btn.textContent = '✕';
      flash.style.display = 'flex';
      flash.style.alignItems = 'center';
      flash.style.gap = '12px';
      flash.appendChild(btn);
      btn.addEventListener('click', () => flash.remove());
    }
    flash.dataset.flashTone = tone;
  });
};

const enhanceEmptyStates = () => {
  document.querySelectorAll('.table-wrap table').forEach((table) => {
    const bodyRows = table.querySelectorAll('tbody tr');
    if (bodyRows.length !== 1) return;
    const cells = bodyRows[0].querySelectorAll('td');
    if (cells.length !== 1) return;
    const cell = cells[0];
    if ((cell.getAttribute('colspan') || '1') === '1') return;
    if (cell.querySelector('.empty-state')) return;
    const message = cleanLabel(cell.textContent) || 'No records available yet.';
    cell.innerHTML = `<div class="empty-state"><strong>${message}</strong><div class="muted small">New records will appear here once data is available.</div></div>`;
  });
};

const enhanceInteractiveCards = () => {
  document.querySelectorAll('.student-project-card-modern, .student-project-item, .portal-card, .portal-split-card, .subject-chip, .timeline-item').forEach((card) => {
    if (card.dataset.hoverReady === '1') return;
    card.dataset.hoverReady = '1';
    card.addEventListener('mousemove', (event) => {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      const rect = card.getBoundingClientRect();
      const x = ((event.clientX - rect.left) / rect.width - 0.5) * 4;
      const y = ((event.clientY - rect.top) / rect.height - 0.5) * -4;
      card.style.transform = `translateY(-3px) rotateX(${y}deg) rotateY(${x}deg)`;
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
    });
  });
};

const enhanceFilterRows = () => {
  document.querySelectorAll('.filter-row').forEach((row) => {
    row.classList.add('saas-filter-row');
  });
};

enhanceFlashMessages();
enhanceEmptyStates();
enhanceInteractiveCards();
enhanceFilterRows();


document.querySelectorAll('[data-workspace-tabs]').forEach((shell) => {
  const buttons = Array.from(shell.querySelectorAll('[data-workspace-target]'));
  const panels = Array.from(shell.querySelectorAll('.workspace-panel'));
  const activate = (targetId) => {
    buttons.forEach((button) => {
      const active = button.getAttribute('data-workspace-target') === targetId;
      button.classList.toggle('is-active', active);
    });
    panels.forEach((panel) => {
      const active = panel.id === targetId;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
  };
  buttons.forEach((button) => button.addEventListener('click', () => activate(button.getAttribute('data-workspace-target'))));
  const initial = buttons.find((button) => button.classList.contains('is-active'))?.getAttribute('data-workspace-target') || panels[0]?.id;
  if (initial) activate(initial);
});

/* ============================================================
   ENHANCEMENT LAYER — UI/UX JavaScript Upgrades
   ============================================================ */

// 1. Inject Google Font: Figtree
(function injectFigtree() {
  if (document.querySelector('link[data-font="figtree"]')) return;
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.setAttribute('data-font', 'figtree');
  link.href = 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap';
  document.head.appendChild(link);
})();

// 2. Button loading state on form submit
document.querySelectorAll('form:not([data-no-loading])').forEach((form) => {
  form.addEventListener('submit', (e) => {
    const submitBtn = form.querySelector('[type="submit"]:not([data-no-loading])');
    if (submitBtn && !submitBtn.closest('[data-confirm-submit]')) {
      const original = submitBtn.textContent.trim();
      submitBtn.setAttribute('data-loading', '1');
      submitBtn.setAttribute('data-original-text', original);
      // safety fallback: remove after 8s in case page doesn't reload
      setTimeout(() => {
        submitBtn.removeAttribute('data-loading');
      }, 8000);
    }
  });
});

// 3. Confirm-action modal: inject action type label
document.querySelectorAll('[data-confirm-title]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const modal = document.querySelector('[data-modal="confirm-action"]');
    if (!modal) return;
    const submitBtn = modal.querySelector('[data-confirm-submit]');
    const action = btn.getAttribute('data-confirm-action') || 'confirm';
    const isDanger = ['archive', 'delete', 'deny', 'deactivate', 'block'].includes(action.toLowerCase());
    if (submitBtn) {
      submitBtn.className = isDanger ? 'btn btn-danger' : 'btn';
      submitBtn.textContent = action || 'Confirm';
    }
  });
});

// 4. Table row count badge in table headers
document.querySelectorAll('.admin-compact-table-wrap, .table-wrap').forEach((wrap) => {
  const table = wrap.querySelector('table');
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr:not(.empty-state-row)');
  const header = wrap.closest('.card, .admin-students-main')?.querySelector('.admin-table-header .table-head-actions');
  if (header && rows.length) {
    const existing = header.querySelector('[data-row-count]');
    if (!existing) {
      const badge = document.createElement('span');
      badge.className = 'pill soft';
      badge.setAttribute('data-row-count', '1');
      badge.textContent = `${rows.length} record${rows.length !== 1 ? 's' : ''}`;
      header.prepend(badge);
    }
  }
});

// 5. Inline search filter for tables (client-side highlight)
document.querySelectorAll('.filter-row input[name="q"]').forEach((input) => {
  if (input.form) return; // server-side form, skip
  const tableWrap = input.closest('.card')?.querySelector('tbody');
  if (!tableWrap) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    tableWrap.querySelectorAll('tr').forEach((row) => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
});

// 6. Animate metric card numbers counting up
const countUp = (el) => {
  const target = parseInt(el.textContent.replace(/,/g, ''), 10);
  if (isNaN(target) || target === 0) return;
  const duration = Math.min(900, 400 + target * 0.5);
  let start = null;
  const step = (ts) => {
    if (!start) start = ts;
    const progress = Math.min((ts - start) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(ease * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = target.toLocaleString();
  };
  requestAnimationFrame(step);
};

const metricObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      const strong = entry.target.querySelector('strong, h3');
      if (strong && /^\d[\d,]*$/.test(strong.textContent.trim())) {
        countUp(strong);
      }
      metricObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.3 });

document.querySelectorAll('.metric-card, .compact-metric-card').forEach((card) => {
  metricObserver.observe(card);
});

// 7. Auto-dismiss flash messages after 5s
document.querySelectorAll('.flash').forEach((flash) => {
  const bar = document.createElement('div');
  bar.style.cssText = 'position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 14px 14px;width:100%;background:currentColor;opacity:.35;animation:flashTimer 5s linear forwards';
  flash.style.position = 'relative';
  flash.style.overflow = 'hidden';
  flash.appendChild(bar);
  setTimeout(() => {
    flash.style.transition = 'opacity .4s ease, transform .4s ease';
    flash.style.opacity = '0';
    flash.style.transform = 'translateY(-6px)';
    setTimeout(() => flash.remove(), 400);
  }, 5000);
});

const flashTimerStyle = document.createElement('style');
flashTimerStyle.textContent = '@keyframes flashTimer { from { width:100% } to { width:0 } }';
document.head.appendChild(flashTimerStyle);

// 8. Tooltip on icon-action buttons (use title attr)
document.querySelectorAll('.icon-action[title]').forEach((btn) => {
  let tip = null;
  const tooltipText = btn.getAttribute('title') || '';
  btn.addEventListener('mouseenter', (e) => {
    tip = document.createElement('div');
    tip.className = 'icon-action-tooltip';
    tip.textContent = tooltipText;
    tip.style.cssText = 'position:fixed;z-index:9999;background:#1a2a4a;color:#fff;font-size:11.5px;font-weight:700;padding:5px 10px;border-radius:8px;pointer-events:none;white-space:nowrap;letter-spacing:.01em;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:opacity .15s ease;opacity:0';
    document.body.appendChild(tip);
    const rect = btn.getBoundingClientRect();
    tip.style.left = `${rect.left + rect.width / 2 - tip.offsetWidth / 2}px`;
    tip.style.top = `${rect.top - tip.offsetHeight - 8}px`;
    requestAnimationFrame(() => { tip.style.opacity = '1'; });
  });
  btn.addEventListener('mouseleave', () => {
    if (tip) { tip.style.opacity = '0'; setTimeout(() => tip?.remove(), 150); tip = null; }
  });
  // Remove native tooltip
  btn.removeAttribute('title');
  btn.setAttribute('data-tip', tooltipText);
});

// 9. Sticky table header enhancement
document.querySelectorAll('.admin-compact-table-wrap table thead, .table-wrap table thead').forEach((thead) => {
  thead.style.position = 'sticky';
  thead.style.top = '0';
  thead.style.zIndex = '5';
  thead.style.background = 'rgba(248,250,255,.95)';
  thead.style.backdropFilter = 'blur(8px)';
});

// 10. Modal: trap focus inside
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Tab') return;
  const openModal = document.querySelector('.modal-backdrop.open');
  if (!openModal) return;
  const focusable = openModal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])');
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey ? document.activeElement === first : document.activeElement === last) {
    e.preventDefault();
    (e.shiftKey ? last : first)?.focus();
  }
});



document.querySelectorAll('[data-history-toggle]').forEach((button) => {
  const targetId = button.getAttribute('data-target');
  const panel = targetId ? document.getElementById(targetId) : null;
  if (!panel) return;
  button.addEventListener('click', () => {
    const expanded = button.getAttribute('aria-expanded') !== 'false';
    const nextExpanded = !expanded;
    button.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
    button.textContent = nextExpanded ? 'Hide history' : 'Show history';
    panel.hidden = !nextExpanded;
    panel.classList.toggle('is-open', nextExpanded);
  });
});

/* ============================================================
   NOTIFICATION BELL — AJAX mark-as-read on dropdown click
   ============================================================ */

const getAjaxMarkReadUrl = () =>
  document.querySelector('meta[name="ajax-mark-notif-read"]')?.content || '';
const getPageCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.content || '';

/**
 * POST to the mark-read AJAX endpoint.
 * Returns the new unread_count on success, or null on failure.
 */
const ajaxMarkNotificationRead = async (notifId, markAll = false) => {
  const url = getAjaxMarkReadUrl();
  const csrf = getPageCsrfToken();
  if (!url || !csrf) return null;
  try {
    const fd = new FormData();
    fd.append('_csrf', csrf);
    if (markAll) {
      fd.append('mark_all', '1');
    } else {
      fd.append('notification_id', String(notifId));
    }
    const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
    if (!res.ok) return null;
    const data = await res.json();
    return data.ok ? data.unread_count : null;
  } catch (_) {
    return null;
  }
};

/**
 * Update the badge count element in the topbar.
 * Removes the badge when count reaches 0.
 */
const syncNotificationBadge = (newCount) => {
  const btn = document.querySelector('[data-notification-toggle]');
  if (!btn) return;
  let badge = btn.querySelector('.badge-count');
  const count = parseInt(newCount, 10);
  if (count <= 0) {
    badge?.remove();
    // Update aria-label
    btn.setAttribute('aria-label', 'Toggle notifications');
    // Update dropdown subtitle
    const sub = document.querySelector('.notification-dropdown-head .muted');
    if (sub) sub.textContent = 'Everything caught up';
  } else {
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge-count';
      btn.appendChild(badge);
    }
    badge.textContent = String(count);
    btn.setAttribute('aria-label', `Toggle notifications (${count} unread)`);
  }
};

/**
 * Bell dropdown notification item — intercept click on unread items,
 * mark as read via AJAX, update badge, then navigate.
 */
const initNotificationBellMarkRead = () => {
  document.querySelectorAll('.notification-item-link[data-notif-id]').forEach((link) => {
    link.addEventListener('click', async (e) => {
      const isUnread = link.dataset.notifRead === '0';
      if (!isUnread) return; // already read, let the browser navigate normally

      e.preventDefault();
      const dest = link.getAttribute('href');

      // Optimistic UI update immediately
      link.classList.remove('unread');
      link.dataset.notifRead = '1';
      const currentBadge = document.querySelector('.badge-count');
      const currentCount = currentBadge ? Math.max(0, parseInt(currentBadge.textContent, 10) - 1) : 0;
      syncNotificationBadge(currentCount);

      // Fire AJAX (don't block navigation)
      const newCount = await ajaxMarkNotificationRead(parseInt(link.dataset.notifId, 10));
      if (newCount !== null) syncNotificationBadge(newCount);

      window.location.href = dest;
    });
  });
};

initNotificationBellMarkRead();


/* ============================================================
   NOTIFICATION PAGE — whole card body click-to-mark-read
   ============================================================ */

const initNotificationCardMarkRead = () => {
  // Covers admin/teacher timeline-item cards and student cards
  const cards = document.querySelectorAll(
    '.timeline-item.unread[id^="notification-"], .student-notification-card.is-unread[id^="notification-"]'
  );
  if (!cards.length) return;

  cards.forEach((card) => {
    card.addEventListener('click', async (e) => {
      // Let inner buttons, links, and form submits work normally
      if (e.target.closest('button, a, form, input, select, textarea')) return;

      const notifId = parseInt((card.id || '').replace('notification-', ''), 10);
      if (!notifId) return;

      // Optimistic UI
      card.classList.remove('unread', 'is-unread');
      card.style.cursor = '';
      const inlineMarkBtn = card.querySelector('.btn-outline, .btn-ghost');
      inlineMarkBtn?.closest('form')?.remove();

      const currentBadge = document.querySelector('.badge-count');
      const currentCount = currentBadge ? Math.max(0, parseInt(currentBadge.textContent, 10) - 1) : 0;
      syncNotificationBadge(currentCount);

      const newCount = await ajaxMarkNotificationRead(notifId);
      if (newCount !== null) syncNotificationBadge(newCount);
    });
  });
};

initNotificationCardMarkRead();


/* ============================================================
   MODAL SYSTEM — structural improvements
   ============================================================ */

/**
 * Patch openModalByKey to reset modal body scroll position every time
 * a modal is opened (prevents stale scroll from previous open).
 */
const _patchModalScrollReset = () => {
  const origOpen = openModalByKey;
  // Reassign on the module scope by wrapping (can't reassign const, so we hook via event)
  // Instead we patch the post-open step inline here:
  document.addEventListener('modal:opened', (e) => {
    const modal = e.detail?.modal;
    if (!modal) return;
    const scrollable = modal.querySelector(
      '.modal-body, .modal-form-shell, form.form-grid, form.form-modal-grid, .generated-modal-form, .ajax-detail-body, .stack'
    );
    if (scrollable) scrollable.scrollTop = 0;
  });
};
_patchModalScrollReset();

// Dispatch modal:opened event — integrate with existing openModalByKey
// We monkey-patch by wrapping document-level logic (openModalByKey is a const above,
// so we use a MutationObserver to detect when a modal gains the 'open' class).
(() => {
  const openObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName !== 'class') return;
      const el = mutation.target;
      if (!el.classList.contains('modal-backdrop')) return;
      const wasOpen = mutation.oldValue?.includes('open') ?? false;
      const isOpen = el.classList.contains('open');
      if (!wasOpen && isOpen) {
        el.dispatchEvent(new CustomEvent('modal:opened', { bubbles: true, detail: { modal: el } }));
        // Reset scroll directly here too, no need to wait for event
        const scrollable = el.querySelector(
          '.modal-body, .modal-form-shell, form.form-grid, form.form-modal-grid, .generated-modal-form, .ajax-detail-body'
        );
        if (scrollable) scrollable.scrollTop = 0;
      }
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    openObserver.observe(backdrop, { attributes: true, attributeOldValue: true });
  });
})();


/**
 * hoistModalFormActions — for modals that still have their action buttons
 * trapped inside a scrollable form wrapper, auto-move them to a sticky
 * .modal-footer element appended to .modal-card. This is a progressive
 * enhancement that works alongside the CSS flex layout fix.
 */
const hoistModalFormActions = () => {
  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    const card = backdrop.querySelector('.modal-card');
    if (!card || card.querySelector('.modal-footer')) return;

    // Look for form-actions inside the scrollable shell
    const scrollable = card.querySelector(
      '.modal-form-shell, form.form-modal-grid, .modal-body, form.form-grid'
    );
    if (!scrollable) return;

    const actions =
      scrollable.querySelector('.modal-form-actions') ||
      scrollable.querySelector('.form-actions') ||
      scrollable.querySelector('.d-flex.justify-content-end') ||
      (scrollable.lastElementChild?.classList.contains('full') &&
        scrollable.lastElementChild.querySelector('.form-actions, button[type="submit"]')
        ? scrollable.lastElementChild
        : null);

    if (!actions) return;

    // Only hoist if the actions are genuinely at the bottom of the scrollable area
    const isAtBottom = actions === scrollable.lastElementChild ||
      actions.closest('.full') === scrollable.lastElementChild ||
      actions.parentElement === scrollable;

    if (!isAtBottom) return;

    const footer = document.createElement('div');
    footer.className = 'modal-footer';
    actions.parentNode.removeChild(actions);
    footer.appendChild(actions);
    card.appendChild(footer);
  });
};

hoistModalFormActions();
