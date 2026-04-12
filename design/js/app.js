// Snapshot of frontend JS for design/testing

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

// (truncated snapshot) — full interactive behavior lives in backend/assets/app.js
