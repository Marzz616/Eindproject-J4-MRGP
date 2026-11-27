# Installeren met Laravel Herd

Deze versie draait helemaal op Laravel. Er is geen Apache-configuratie en geen
Docker meer nodig: Laravel vangt elk verzoek op en bepaalt zelf of het een
pagina is (Blade + Vue) of een API-aanroep.

## 1. Benodigdheden

* **Laravel Herd** (gratis versie is genoeg) — https://herd.laravel.com
  Herd bevat PHP en Composer. Deze app vraagt **PHP 8.4 of nieuwer**.
* **Node 20 of nieuwer** (voor het bouwen van de Vue-frontend).
* **MySQL 8** of **MariaDB 10.6+**. In Herd Pro zit een databaseserver; met de
  gratis versie kun je DBngin, MAMP of XAMPP gebruiken.

## 2. Project klaarzetten

Zet de map `voedselbanknieuw` in de map die Herd in de gaten houdt
(standaard `~/Herd`). Herd maakt daar vanzelf een adres van:

```
http://voedselbanknieuw.test
```

Daarna in de terminal, in de projectmap:

```bash
composer install          # PHP-pakketten (map vendor)
npm install               # JavaScript-pakketten (map node_modules)
cp .env.example .env      # instellingen
php artisan key:generate  # beveiligingssleutel voor de sessies
npm run build             # bouwt de Vue-app naar public/build
```

> Werkt `composer` of `php` niet in de terminal? Open Herd en zet onder
> *Settings → PHP* de shell-integratie aan, of gebruik `herd php` en
> `herd composer`.

## 3. Database

Maak een lege database met de naam `voedselbank` en zet in `.env` je eigen
gegevens:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voedselbank
DB_USERNAME=root
DB_PASSWORD=
```

Daarna de tabellen en de testdata erin:

```bash
php artisan migrate --seed
```

* `migrate` voert `database/schema/01-schema.sql` uit — hetzelfde schema uit
  het ERD als in de vorige versie.
* `--seed` voert `database/schema/02-seed.sql` uit: 11 gezinnen, 20 producten,
  5 accounts en voorbeeldpakketten.

Opnieuw beginnen met verse testdata:

```bash
php artisan migrate:fresh --seed
```

## 4. Openen

Open `http://voedselbanknieuw.test` en log in. Het wachtwoord van alle
testaccounts is `voedselbank`:

| Gebruikersnaam | Rol |
|---|---|
| `p.abraham` | Directie (mag alles) |
| `r.veenstra` | Magazijnmedewerker |
| `a.degroot` | Vrijwilliger |
| `m.snel` | Administratie |

### Zonder Herd

Het werkt ook met de ingebouwde server van Laravel:

```bash
php artisan serve        # http://127.0.0.1:8000
```

### Tijdens het ontwikkelen

Laat Vite meelopen, dan zie je wijzigingen in de Vue-bestanden meteen:

```bash
npm run dev
```

Vergeet niet vóór het inleveren of presenteren weer `npm run build` te doen,
anders zoekt de pagina naar de ontwikkelserver van Vite.

## 5. Wat is er veranderd ten opzichte van versie 2?

| Versie 2 | Deze versie (Laravel) |
|---|---|
| Apache stuurde `/api/...` naar `backend/public/index.php` en de rest naar `index.html` | Laravel verdeelt zelf: `routes/api.php` voor de API, `routes/web.php` voor de pagina's |
| Eigen router (`Core/Router.php`) met rechtencontrole | Routes van Laravel met de middleware `ingelogd` en `mag:` |
| Eigen `Request`/`Response`-klassen | `Illuminate\Http\Request` en de JSON-antwoorden van Laravel |
| Eigen PDO-verbinding (`Core/Database.php`) | De databaselaag van Laravel (`DB`), ook met prepared statements |
| Eigen sessieklasse | De sessie van Laravel (`config/session.php`) |
| Docker (Apache + MySQL + phpMyAdmin) | Herd of `php artisan serve`; database los |
| Frontend in `frontend/`, apart gebouwd met een proxy naar de API | Frontend in `resources/js`, gebouwd met Vite via de laravel-plugin |

De database, de schermen en alle functionaliteit zijn hetzelfde gebleven. Dat
is ook getest: zie `tests/api/vergelijk-met-v2.js`.

## 6. Testen

```bash
node tests/api/api-test.js        # 28 controles op de API (app moet draaien)
```

Voor de volledige testronde (113 testcases, met screenshots) is Playwright
nodig:

```bash
npm install -D playwright
npx playwright install chromium
VB_URL=http://voedselbanknieuw.test node tests/testrapport/run.js
```

Let op: die testronde **leegt de database en zet de testdata terug** voor elk
onderdeel. Draai hem dus niet op gegevens die je wilt bewaren.
