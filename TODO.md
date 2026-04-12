# Project Refactor: Notifications + Modal System
Status: ✅ Plan approved | 📋 In progress | ⏳ Pending | ✅ Done

## 1. Create TODO.md [✅ Done]

## 2. Create reusable modal partial (backend/partials/modal.php) [✅ Done]
- ✅ Added modal-backdrop/overlay/container/card/head/body/footer
- ✅ Scrollable .modal-body + sticky .modal-footer
- ✅ render_modal() helper function

## 3. Enhance app.js ModalManager [✅ Done]
- ✅ Full tab trap (Shift+Tab cycling, modal stack)
- ✅ Body scroll lock (CSS var + padding preservation)
- ✅ Modal body scroll only + sticky footer (CSS updated)
- ✅ chainNextModal via data-chain-next-modal attr
- ✅ Auto-scan/bind all data-open-modal triggers (existing + dynamic)
- ✅ Long form scroll reset + validation hooks

## 4. NotificationManager in app.js
- [ ] AJAX markRead(id) endpoint integration
- [ ] Click handlers: dropdown items + notifications.php cards
- [ ] Real-time badge update in header.php
- [ ] CSRF-safe POST for mark-read/mark-all-read

## 5. app.css Modal Fixes [✅ Done]
- ✅ .modal-backdrop/overlay/container/card/head/body/footer standards
- ✅ Body scroll lock + scrollbar width preservation
- ✅ .modal-body scroll only + .modal-footer sticky
- ✅ Responsive viewport fit (95vw/95vh, mobile full)

## 6. Update Notifications Flow
- [ ] header.php: data-notification-mark on dropdown links
- [ ] notifications.php x3: data-notification-mark on timeline-item wrappers + card click
- [ ] auth.php: /api/mark-read.php AJAX endpoint (query/mark_notification_read)

## 7. Scan+Fix Inconsistent Modals (58 found)
- [ ] Replace legacy class="modal" → data-modal
- [ ] Add .modal-body wrappers to long forms (subjects.php priority)
- [ ] Standardize triggers to data-open-modal

## 8. Validation + Testing
- [ ] PHP lint: php -l **/*.php
- [ ] JS console: no errors/warnings
- [ ] Test notifications: click/badge sync/all-read
- [ ] Test modals: scroll/trap/Esc/nested/mobile/long forms
- [ ] Cross-browser: Chrome/Firefox/Safari

## 9. attempt_completion

**Progress: 1/9 (11%)**

