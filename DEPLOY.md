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

7) SFTP key-based upload (pro zálohování přímo z aplikace)

- Doporučení: pokud nechcete používat hesla pro vzdálené SFTP nahrávání záloh, použijte SSH klíč.
- Umístěte soukromý klíč bezpečně na server (např. `/home/www/.ssh/backup_uploader`) s právy `600` a vlastníkem běžícím uživatelem webu.
- Do `authorized_keys` cílového účtu přidejte odpovídající veřejný klíč.
- Ve vaší aplikaci můžete použít novou třídu `BackupApp\Service\SftpKeyUploader`, která přijímá obsah privátního klíče (ne heslo). Příklad použití v PHP:

```php
use BackupApp\Service\SftpKeyUploader;
use BackupApp\Model\BackupModel;

$privateKey = file_get_contents('/home/www/.ssh/backup_uploader');
$uploader = new SftpKeyUploader($privateKey, null); // druhý parametr = passphrase pokud je potřeba
$model = new BackupModel(null, $uploader);
$result = $model->runBackup($dataArray);
```

- Alternativa: pokud aplikace běží v CI (GitHub Actions) a chcete, aby CI provádělo nahrání, uložte privátní klíč do GitHub Secret `DEPLOY_KEY` (nebo `SFTP_PRIVATE_KEY`) a použijte ho v runneru.

8) Poznámky k bezpečnosti klíčů

- Nikdy necommitujte soukromý klíč do repozitáře. Vždy používejte `Secrets` v GitHubu nebo soukromé soubory s přístupovými právy 600 na serveru.
- Pokud je klíč ochranný frází, uložte frázi do separátního secretu (např. `SFTP_KEY_PASSPHRASE`).
