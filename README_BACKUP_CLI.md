Backup CLI — bezpečné použití v CI

Tento dokument ukazuje, jak bezpečně používat `bin/backup-cli.php` v CI (např. GitHub Actions) s privátním klíčem uloženým jako Secret.

Doporučení:
- Nikdy necommitujte privátní klíč do repozitáře.
- Uložte obsah privátního klíče do GitHub Secret s názvem `SFTP_PRIVATE_KEY`.
- Pokud má klíč passphrase, použijte secret `SFTP_KEY_PASSPHRASE`.

Příklad GitHub Actions jobu (spustí backup a pošle na SFTP):

```yaml
name: Run backup

on:
  workflow_dispatch:

jobs:
  backup:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install deps
        run: composer install --no-progress --no-suggest --prefer-dist
      - name: Run backup-cli
        env:
          DB_HOST: ${{ secrets.DB_HOST }}
          DB_PORT: ${{ secrets.DB_PORT }}
          DB_USER: ${{ secrets.DB_USER }}
          DB_PASS: ${{ secrets.DB_PASS }}
          DB_NAME: ${{ secrets.DB_NAME }}

          SFTP_HOST: ${{ secrets.SFTP_HOST }}
          SFTP_PORT: ${{ secrets.SFTP_PORT }}
          SFTP_USER: ${{ secrets.SFTP_USER }}
          SFTP_REMOTE: ${{ secrets.SFTP_REMOTE }}

          # private key content (multi-line) stored as secret
          SFTP_PRIVATE_KEY: ${{ secrets.SFTP_PRIVATE_KEY }}
          SFTP_KEY_PASSPHRASE: ${{ secrets.SFTP_KEY_PASSPHRASE }}
        run: php bin/backup-cli.php
```

Jak to funguje:
- `bin/backup-cli.php` načte hodnoty z environment proměnných a pokud najde `SFTP_PRIVATE_KEY`, použije `SftpKeyUploader`.
- Privátní klíč zůstane pouze v paměti runneru a není zapisován do logů ani do repozitáře.

Bezpečnostní tipy:
- Omezte práva účtu, který má přijímat nahrávky na serveru (např. uživatel pouze pro uploady).
- Použijte key with forced command nebo restrictující `authorized_keys` options pokud je vhodné.
- Pokud chcete, můžete použít GitHub Actions `ssh-agent` + `actions/setup-ssh@v1` místo vkládání klíče do env, ale pro jednoduché CI je secret s klíčem běžné řešení.
