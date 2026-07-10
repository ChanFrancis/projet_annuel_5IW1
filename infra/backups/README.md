# CoPot — Backups (3-2-1)

Implements the **3-2-1** rule:

- **3** copies — live PostgreSQL + local dump + off-site (Backblaze B2)
- **2** media — local disk + cloud
- **1** off-site — B2

## Scripts

| File | Purpose |
|---|---|
| `backup.sh` | `pg_dump -Fc` + Redis snapshot → local rotation (7 daily, 4 weekly) → `rclone copy` to B2 |
| `restore.sh` | streams a `.dump` back into Postgres (`pg_restore --clean --if-exists`) |
| `rclone.conf.example` | B2 remote template → copy to `rclone.conf` (git-ignored) |

## Run

```bash
# ad-hoc backup (normally run by the nightly cron installed by Ansible)
sudo -u copot /opt/copot/infra/backups/backup.sh

# restore (destructive — prompts for the DB name to confirm)
sudo -u copot /opt/copot/infra/backups/restore.sh /var/backups/copot/copot-20260101-030000.dump
```

Or from the repo root: `make backup` / `make restore FILE=/var/backups/copot/copot-*.dump`.

## Cron

Ansible installs a nightly job (03:17) as user `copot`, logging to
`/var/log/copot-backup.log`. The cron line:

```
17 3 * * * /opt/copot/infra/backups/backup.sh >> /var/log/copot-backup.log 2>&1
```

## Env vars (override on the VPS if needed)

| Var | Default | Meaning |
|---|---|---|
| `DEPLOY_ROOT` | `/opt/copot` | compose project dir |
| `BACKUP_DIR` | `/var/backups/copot` | local dump dir |
| `RCLONE_REMOTE` | `backblaze` | rclone remote name |
| `RCLONE_PATH` | `copot-backups` | B2 bucket/folder |
| `KEEP_DAILY` / `KEEP_WEEKLY` | `7` / `4` | local retention |
