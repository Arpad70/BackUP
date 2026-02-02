Automatické nasazení (GitHub Actions)

1) Cíl: po push do větve `main` se repozitář nasadí na server a soubory se zkopírují do adresáře, který hostí web `backup.eshopion.sk`.

2) Co je přidáno:
- `.github/workflows/deploy.yml` — workflow, který použije `rsync` přes SSH a spustí jednoduché remote příkazy (nastavení práv, vytvoření `index.html` pokud chybí).

3) Nutné GitHub Secrets (v repozitáři -> Settings -> Secrets):
- `DEPLOY_HOST` — hostname nebo IP serveru (např. shell.r2.websupport.sk nebo backup.eshopion.sk)
- `DEPLOY_PORT` — SSH port (např. `22`)
- `DEPLOY_USER` — uživatel na serveru (např. `uid1082847`)
- `DEPLOY_KEY` — soukromý SSH klíč (PEM) pro přístup bez hesla
- `DEPLOY_PATH` — absolutní cesta na serveru, kam se kopírují soubory (např. `/var/www/backup.eshopion.sk` nebo `/data/7/3/.../backup`)

4) Jak vytvořit a nahrát SSH klíč (lokálně):

```bash
mkdir -p ~/.ssh/deploy_keys
ssh-keygen -t ed25519 -f ~/.ssh/deploy_keys/github_deploy -N ""
cat ~/.ssh/deploy_keys/github_deploy # obsah, vložte do GitHub Secret DEPLOY_KEY
ssh-copy-id -i ~/.ssh/deploy_keys/github_deploy.pub -p 22 uid1082847@backup.eshopion.sk
```

Poznámka: pokud nemůžete použít `ssh-copy-id`, zkopírujte obsah `*.pub` do `~/.ssh/authorized_keys` na serveru.

5) Test: proveďte commit & push do `main`. Workflow se spustí a nasadí.

6) Bezpečnost: zajistěte, že uživatel má omezená práva a že cílový adresář neobsahuje citlivé soubory. Můžeme workflow upravit pro selektivní kopírování pouze `public` nebo jen artefakty.
