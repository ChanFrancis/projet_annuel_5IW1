# CoPot — Ansible provisioning

Provisions a fresh **Ubuntu 24.04** VPS to run CoPot in production: base packages,
Docker, a `copot` deploy user, a locked-down UFW firewall (ports 22/80/443 only),
and the full Docker Compose stack.

## Setup

```bash
# 1. Host + secrets (git-ignored)
cp group_vars/all.example.yml group_vars/all.yml
$EDITOR group_vars/all.yml          # fill secrets, especially copot_deploy_public_key
ansible-vault encrypt group_vars/all.yml

cp inventory.yml inventory.local.yml
$EDITOR inventory.local.yml         # ansible_host / ansible_user

# 2. First run (as root, before the copot user exists)
ansible-playbook -i inventory.local.yml playbook.yml \
    --vault-password-file ~/.vault_pass \
    -e ansible_user=root
```

Re-runs are idempotent — you can later run as the `copot` user.

## What the playbook does

| Step | Effect |
|---|---|
| apt | `update_cache` + `dist` upgrade, `unattended-upgrades` |
| user | creates `copot` (sudo + docker), installs CI's authorized SSH key |
| Docker | official repo install of `docker-ce` + `docker-compose-plugin` |
| UFW | deny incoming by default; allow 22/80/443 only |
| GHCR | `docker login ghcr.io` with the deploy PAT |
| Compose | renders `.env.prod` + `backend/.env.prod.local`, pulls, migrates, `up -d` |
| Backup | installs `rclone`, renders B2 config, nightly cron |

## Files

- `playbook.yml` — the playbook (single file, no roles for simplicity)
- `inventory.yml` — template (copy to `inventory.local.yml`, git-ignored)
- `group_vars/all.example.yml` — variable template (copy to `all.yml`, vault-encrypt)
- `templates/` — `env.prod.local.j2`, `env.prod.j2`, `rclone.conf.j2`

## Verify after deploy

```bash
ssh copot@<vps>
sudo ufw status               # only 22/80/443
docker compose -f /opt/copot/docker-compose.prod.yml ps
curl -I https://<domain>/api/health
```
