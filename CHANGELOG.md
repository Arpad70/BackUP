# Changelog

Všechny významné změny v tomto repozitáři jsou zaznamenány v tomto souboru.

## v1.1.1 - 2026-02-02

- Lokalizace: přidány a doplněny překlady pro češtinu (`lang/cs.php`) a zajištěna parita v `lang/en.php` a `lang/sk.php`.
- Aplikace: přepojení UI a serverových textů na jednoduchý překladový systém (`Translator`), přeloženy `src/View/form.php` a `src/View/result.php`.
- Progress API: `public/progress.php` nyní podporuje lokalizované zprávy, CLI také používá překlady.
- Bezpečnost a chybové zprávy: vylepšené zpracování SFTP privátních klíčů a lokalizované chybové zprávy v modelu.
- CI/workflows: opraveny soubory v `.github/workflows/` tak, aby procházely yamllint a byly konzistentní pro CI.
- Další: menší úpravy dokumentace (`README.md`) a vytvořen tag `v1.1.1`.

## v1.1.0

- Předchozí stabilní tag.

---

Poznámka: tento changelog byl vygenerován automaticky z posledních změn; pokud chcete přidat detailnější poznámky k jednotlivým commitům, mohu je doplnit.
