# Backups

This package includes a ZIP backup flow that generates:
- `database.sql`
- `manifest.json`
- `uploads/`

Use `php backend/scripts/backup_run.php` for a cron-ready local backup run.
Google Drive settings are environment-based and documented in `.env.example`.
