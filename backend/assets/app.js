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

document.querySelectorAll('[data-toggle-menu]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation();
    const wrapper = button.closest('.notification-menu');
    closeAllDropdowns();
    wrapper?.classList.toggle('open');
    button.setAttribute('aria-expanded', wrapper?.classList.contains('open') ? 'true' : 'false');
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

const closeModal = (modal) => {
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
  if (!document.querySelector('.modal-backdrop.open')) {
    body.classList.remove('modal-open');
  }
};

const openModalByKey = (key) => {
  const modal = document.querySelector(`[data-modal="${key}"]`);
  if (!modal) return;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
  body.classList.add('modal-open');
};

document.querySelectorAll('[data-open-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    openModalByKey(button.getAttribute('data-open-modal'));
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
});

document.querySelectorAll('.modal-backdrop').forEach((modal) => {
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal(modal);
  });
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('.modal-backdrop.open').forEach(closeModal);
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
      window.setTimeout(() => { button.textContent = original; }, 1200);
    } catch (error) {
      button.textContent = 'Copy failed';
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
  if (/\b(archive)\b/.test(value)) return 'archive';
  if (/\b(delete|remove|trash)\b/.test(value)) return 'delete';
  if (/\b(deactivate|restrict|block|deny|reject|lock)\b/.test(value)) return 'lock';
  if (/\b(approve|accept|confirm|activate|reactivate|save)\b/.test(value)) return 'approve';
  return 'more';
};

const enhanceTableActionIcons = () => {
  document.querySelectorAll('.table-actions a, .table-actions button, .table-actions summary').forEach((action) => {
    if (action.dataset.iconized === '1') return;
    const label = cleanLabel(action.textContent || action.getAttribute('aria-label') || action.getAttribute('title') || 'Action');
    const iconKey = action.dataset.icon || inferActionIcon(label);
    const labelText = label || 'Action';
    const labelSpan = document.createElement('span');
    labelSpan.className = 'btn-action-label';
    labelSpan.textContent = labelText;

    const iconSpan = document.createElement('span');
    iconSpan.className = 'btn-action-icon';
    iconSpan.setAttribute('aria-hidden', 'true');
    iconSpan.innerHTML = actionIconSvg[iconKey] || actionIconSvg.more;

    action.textContent = '';
    action.append(iconSpan, labelSpan);
    action.classList.add('btn-action');
    action.setAttribute('title', labelText);
    if (!action.getAttribute('aria-label')) action.setAttribute('aria-label', labelText);
    action.dataset.iconized = '1';
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

const applyStoredStudentAvatar = () => {
  const stored = window.localStorage ? localStorage.getItem('studentAvatarData') : '';
  document.querySelectorAll('[data-student-avatar], [data-student-avatar-preview]').forEach((node) => {
    const initial = node.getAttribute('data-avatar-initial') || 'U';
    if (stored) {
      node.style.backgroundImage = `url(${stored})`;
      node.classList.add('has-image');
      node.textContent = initial;
    } else {
      node.style.backgroundImage = '';
      node.classList.remove('has-image');
      node.textContent = initial;
    }
  });
};

const initStudentAvatarControls = () => {
  const input = document.querySelector('[data-student-avatar-input]');
  const saveBtn = document.querySelector('[data-student-avatar-save]');
  const resetBtn = document.querySelector('[data-student-avatar-reset]');
  if (!input || !saveBtn) {
    applyStoredStudentAvatar();
    return;
  }

  let pendingData = window.localStorage ? localStorage.getItem('studentAvatarData') || '' : '';
  const previewNodes = document.querySelectorAll('[data-student-avatar-preview]');

  const paintPreview = (data) => {
    previewNodes.forEach((node) => {
      const initial = node.getAttribute('data-avatar-initial') || 'U';
      if (data) {
        node.style.backgroundImage = `url(${data})`;
        node.classList.add('has-image');
        node.textContent = initial;
      } else {
        node.style.backgroundImage = '';
        node.classList.remove('has-image');
        node.textContent = initial;
      }
    });
  };

  applyStoredStudentAvatar();
  paintPreview(pendingData);

  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      pendingData = String(reader.result || '');
      paintPreview(pendingData);
    };
    reader.readAsDataURL(file);
  });

  saveBtn.addEventListener('click', () => {
    if (window.localStorage) {
      if (pendingData) {
        localStorage.setItem('studentAvatarData', pendingData);
      } else {
        localStorage.removeItem('studentAvatarData');
      }
    }
    applyStoredStudentAvatar();
    closeModal(document.querySelector('[data-modal="student-avatar-modal"]'));
  });

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      pendingData = '';
      input.value = '';
      paintPreview('');
    });
  }
};

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

applyStoredStudentAvatar();
initStudentAvatarControls();
initStudentSubjectFilters();
