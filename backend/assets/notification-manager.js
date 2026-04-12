export class NotificationManager {
  constructor() {
    this.endpoint = document.querySelector('meta[name="ajax-mark-notif-read"]')?.content || '';
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_csrf"]')?.value || '';
    this.init();
  }

  init() {
    if (!this.endpoint || !this.csrfToken) return;
    this.bindBellPreviewLinks();
    this.bindMarkReadButtons();
    this.bindMarkAllButtons();
    this.bindUnreadCardClicks();
  }

  async requestMark({ notificationId = 0, markAll = false }) {
    const formData = new FormData();
    formData.append('_csrf', this.csrfToken);
    if (markAll) {
      formData.append('mark_all', '1');
    } else {
      formData.append('notification_id', String(notificationId));
    }

    const response = await fetch(this.endpoint, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });
    if (!response.ok) return null;
    const data = await response.json();
    return data && data.ok ? Number(data.unread_count || 0) : null;
  }

  syncBadge(unreadCount) {
    const button = document.querySelector('[data-notification-toggle]');
    if (!button) return;

    let badge = button.querySelector('.badge-count');
    const count = Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0;
    const subtitle = document.querySelector('.notification-dropdown-head .muted');

    if (count <= 0) {
      badge?.remove();
      button.setAttribute('aria-label', 'Toggle notifications');
      if (subtitle) subtitle.textContent = 'Everything caught up';
      return;
    }

    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge-count';
      button.appendChild(badge);
    }
    badge.textContent = String(count);
    button.setAttribute('aria-label', `Toggle notifications (${count} unread)`);
    if (subtitle) subtitle.textContent = `${count} unread`;
  }

  markCardReadUI(notificationId) {
    const card = document.getElementById(`notification-${notificationId}`);
    if (!card) return;
    card.classList.remove('unread', 'is-unread');
    card.dataset.notifRead = '1';
    card.querySelector('form')?.remove();
  }

  async markSingle(notificationId, optimistic = true) {
    const parsedId = parseInt(String(notificationId), 10);
    if (!parsedId) return null;

    if (optimistic) {
      this.markCardReadUI(parsedId);
      const current = parseInt(document.querySelector('.badge-count')?.textContent || '0', 10);
      this.syncBadge(Math.max(0, current - 1));
    }

    const newCount = await this.requestMark({ notificationId: parsedId });
    if (newCount !== null) this.syncBadge(newCount);
    return newCount;
  }

  bindBellPreviewLinks() {
    document.addEventListener('click', async (event) => {
      const link = event.target.closest('.notification-item-link[data-notif-id]');
      if (!link) return;
      if (String(link.dataset.notifRead || '1') !== '0') return;

      event.preventDefault();
      const href = link.getAttribute('href') || '';
      const notifId = link.dataset.notifId || '';
      link.classList.remove('unread');
      link.dataset.notifRead = '1';

      await this.markSingle(notifId, true);
      if (href) window.location.href = href;
    });
  }

  bindMarkReadButtons() {
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-notification-mark]');
      if (!button) return;

      event.preventDefault();
      event.stopPropagation();
      const notificationId = button.getAttribute('data-notification-mark') || '';
      await this.markSingle(notificationId, true);
    });
  }

  bindMarkAllButtons() {
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-notification-mark-all]');
      if (!button) return;

      event.preventDefault();
      event.stopPropagation();

      document.querySelectorAll('.timeline-item.unread, .student-notification-card.is-unread').forEach((item) => {
        item.classList.remove('unread', 'is-unread');
        item.dataset.notifRead = '1';
        item.querySelector('form')?.remove();
      });
      this.syncBadge(0);

      const newCount = await this.requestMark({ markAll: true });
      if (newCount !== null) this.syncBadge(newCount);
    });
  }

  bindUnreadCardClicks() {
    document.addEventListener('click', async (event) => {
      const card = event.target.closest('.timeline-item.unread[id^="notification-"], .student-notification-card.is-unread[id^="notification-"]');
      if (!card) return;
      if (event.target.closest('button, a, form, input, select, textarea')) return;

      const notificationId = (card.id || '').replace('notification-', '');
      await this.markSingle(notificationId, true);
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => { new NotificationManager(); });
} else {
  new NotificationManager();
}

