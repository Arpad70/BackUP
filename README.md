# Backup MVC — jednoduchá app

Mini aplikace, která provede:
- databázový dump (mysqldump)
- ZIP celé pracovního webu (adresář)
- odeslání dumpu i ZIPu přes SFTP do cílového adresáře

Požadavky:
- PHP s `zip` rozšířením
- `mysqldump` v PATH
- doporučeně Composer (`phpseclib` bude použita pro SFTP) nebo `ssh2` rozšíření

Instalace:
```
cd backup_app
composer install
```

Nastavení HTTP Basic auth (doporučeno):
```
# Linux / macOS
export BACKUP_USER=backup
export BACKUP_PASS='silne-heslo'

# Windows (PowerShell)
$env:BACKUP_USER = 'backup'; $env:BACKUP_PASS = 'silne-heslo'
```

Poznámka: pokud env proměnné nenastavíte, stránka bude chráněna výchozím účtem `backup`/`changeme` — nezapomeňte změnit před nasazením.

Spuštění (lokálně pomocí PHP vestavěného serveru):
```
cd backup_app/public
php -S 0.0.0.0:8000
# otevřít http://localhost:8000
```

Bezpečnost: nenechávejte tento nástroj vystavený veřejně bez ochrany; používá hesla v POST datech.

Deployment note (Apache)
- Pokud máte možnost, nastavte `DocumentRoot` serveru na `.../backup_app/public` — je to bezpečnější a přehlednější než přepisovat v root `.htaccess`.
- Ujistěte se, že Apache má povolené moduly `mod_rewrite` a `mod_headers` a že `AllowOverride` umožňuje `FileInfo`/`Indexes`/`AuthConfig` pro `.htaccess`.

Pokud nasazujete na hostovaném prostředí bez přístupu k serverové konfiguraci, ponechte root `.htaccess` (je v repu) — já opravil `public/.htaccess` tak, aby blokoval dotfiles spolehlivě.

Jak otestovat `.htaccess` a nasazení
----------------------------------

Níže jsou kroky, které prověří, že `backup_app/.htaccess` a `backup_app/public/.htaccess` fungují správně na Apache serveru.

1) Základní ověření modulů a konfigurace Apache

```bash
# zkontrolujte, že máte mod_rewrite a mod_headers
apachectl -M | grep -E "rewrite|headers" || sudo a2enmod rewrite headers

# ujistěte se, že AllowOverride v konfiguraci VirtualHost umožňuje .htaccess (např. AllowOverride All)
```

2) Ověření přesměrování do `public/`

```bash
# Pošlete požadavek na kořen (nahraďte host testovací adresou nebo použijte lokální host)
curl -sI http://localhost/ | head -n 20
# Očekávejte 200 a v těle stránky /public/index.php se zobrazí titul nebo text formuláře
curl -s http://localhost/ | grep -i "Backup — DB dump" || curl -s http://localhost/public/ | head
```

3) Ověření blokování citlivých souborů a dotfiles

```bash
# Požadavek na `.env` by měl vrátit 403 nebo 404
curl -sI http://localhost/.env

# Dotfile (např. .gitignore) také nesmí být přístupný
curl -sI http://localhost/.gitignore
```

4) Ověření služeb v `public/`

```bash
# Vytvořte testovací soubor
echo test > public/test.txt
curl -sI http://localhost/test.txt    # měl by vrátit 200 a obsah
curl -s http://localhost/test.txt
# Odstraňte po testu
rm public/test.txt
```

5) Kontrola bezpečnostních hlaviček

```bash
curl -sI http://localhost/ | egrep -i "X-Frame-Options|X-Content-Type-Options|Referrer-Policy"
```

6) Pokud něco nefunguje

- Zkontrolujte Apache error log (`/var/log/apache2/error.log` nebo konfigurace hostitele).
- Ujistěte se, že `AllowOverride` v `VirtualHost` povoluje `.htaccess` (např. `AllowOverride All`).
- Rozmyslete nastavení `DocumentRoot` na `.../backup_app/public` pro jednodušší a bezpečnější nasazení.

Tyto kroky jsou určeny k manuálnímu ověření. Pokud chceš, můžu vytvořit jednoduchý skript `tests/check_http.sh`, který spustí tyto kontroly automaticky (pokud máš přístup k serveru). 
