# Documentatie — hoe de code werkt en hoe je hem aanpast

Voor wie de code overneemt of wil wijzigen. Een losse uitleg van elke functie
staat in **[FUNCTIES.md](FUNCTIES.md)**.

---

## 1. De site draaiend krijgen

### 1.1 Wat je nodig hebt

| Nodig | Waarom | Controleren met |
|---|---|---|
| **PHP 8.4 of nieuwer** | Laravel 13 draait niet op een oudere versie | `php -v` |
| **Composer** | installeert de PHP-pakketten | `composer -V` |
| **Node 20 of nieuwer** | bouwt de Vue-frontend | `node -v` |
| **MySQL 8 of MariaDB 10.6+** | de database | `mysql --version` |

PHP en Composer zitten allebei in **Laravel Herd** (gratis, herd.laravel.com).
Node haal je los op bij nodejs.org. Voor de database kun je Herd Pro, DBngin,
MAMP of XAMPP gebruiken.

### 1.2 Stap voor stap

**Stap 1 — project neerzetten.**
Pak de map `voedselbanknieuw` uit in de map die Herd in de gaten houdt,
standaard `~/Herd`. Herd maakt daar vanzelf een webadres van:
`http://voedselbanknieuw.test`. Gebruik je geen Herd, dan kan de map overal
staan.

**Stap 2 — pakketten installeren.** Open een terminal in de projectmap:

```bash
composer install     # vult de map vendor (PHP-pakketten)
npm install          # vult de map node_modules (JavaScript-pakketten)
```

**Stap 3 — instellingen.**

```bash
cp .env.example .env        # Windows: copy .env.example .env
php artisan key:generate
```

`.env` is het enige bestand met instellingen die per computer verschillen.
Zet daar je databasegegevens in:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voedselbank
DB_USERNAME=root
DB_PASSWORD=
```

> `.env` staat in `.gitignore` en gaat dus **niet** mee in Git. Nieuwe
> instellingen die iedereen nodig heeft, zet je ook in `.env.example`.

**Stap 4 — database vullen.**
Maak eerst een lege database met de naam `voedselbank` (in TablePlus,
phpMyAdmin of met `CREATE DATABASE voedselbank;`). Daarna:

```bash
php artisan migrate --seed
```

* `migrate` voert `database/schema/01-schema.sql` uit: alle tabellen uit het ERD.
* `--seed` voert `database/schema/02-seed.sql` uit: de testdata.

**Stap 5 — frontend bouwen.**

```bash
npm run build
```

**Stap 6 — openen.** Ga naar `http://voedselbanknieuw.test`.
Zonder Herd:

```bash
php artisan serve        # http://127.0.0.1:8000
```

Inloggen (wachtwoord van álle accounts is `voedselbank`):

| Gebruikersnaam | Rol | Ziet |
|---|---|---|
| `p.abraham` | Directie | alles |
| `r.veenstra` | Magazijnmedewerker | producten, voorraad, leveranciers |
| `a.degroot` | Vrijwilliger | pakketten samenstellen en uitgeven |
| `m.snel` | Administratie | klanten, rapportages, accounts |

### 1.3 Tijdens het werken aan de code

Laat Vite meelopen in een tweede terminal. Je ziet wijzigingen in de
Vue-bestanden dan meteen in de browser, zonder opnieuw te bouwen:

```bash
npm run dev
```

**Belangrijk:** zet dit weer stop en draai `npm run build` voordat je het
project inlevert of laat zien. Anders zoekt de pagina naar de ontwikkelserver
van Vite en krijg je een wit scherm.

Wijzigingen in PHP werken meteen; daar hoef je niets voor te bouwen.

### 1.4 Opnieuw beginnen met verse gegevens

```bash
php artisan migrate:fresh --seed
```

Dit gooit alle tabellen weg en bouwt ze opnieuw op met de testdata. Handig
vóór een demo of als je tijdens het testen de gegevens hebt vervuild.

### 1.5 Het werkt niet — wat nu?

| Wat je ziet | Wat er aan de hand is | Oplossing |
|---|---|---|
| `Your Composer dependencies require a PHP version ">= 8.4.1"` | PHP is te oud | PHP 8.4 installeren (zit in Herd) |
| Wit scherm, in de console `Failed to load module` | de frontend is niet gebouwd | `npm run build` |
| Wit scherm terwijl `npm run dev` eerder aanstond | de pagina zoekt nog naar Vite | `npm run build` of `npm run dev` opnieuw starten |
| `SQLSTATE[HY000] [1045] Access denied` | verkeerde gebruiker of wachtwoord in `.env` | `.env` nakijken, daarna `php artisan config:clear` |
| `SQLSTATE[HY000] [1049] Unknown database` | de database bestaat nog niet | eerst `CREATE DATABASE voedselbank;` |
| `No application encryption key has been specified` | er staat geen `APP_KEY` in `.env` | `php artisan key:generate` |
| `419 Page Expired` of je wordt steeds uitgelogd | sessie-instellingen staan niet goed | `SESSION_DRIVER=file` in `.env` en `php artisan config:clear` |
| `The /storage directory must be present` / schrijffouten | map niet schrijfbaar | `chmod -R 775 storage bootstrap/cache` |
| Wijziging in `.env` doet niets | de instellingen staan in de cache | `php artisan config:clear` |
| `404` op een pagina die wel bestaat | de route staat niet in `routes/` | `php artisan route:list` |
| `500` en je ziet niet waarom | de details staan in het logboek | `storage/logs/laravel.log` openen (onderaan) |

**Alles schoonmaken** als er iets raars gebeurt:

```bash
php artisan optimize:clear      # config, routes, views en cache leeg
```

---

## 2. Hoe de applicatie in elkaar zit

### 2.1 De weg die een verzoek aflegt

```
browser
   │
   ├─ gewone URL (/klanten) ──────► routes/web.php ──► resources/views/app.blade.php
   │                                                        │
   │                                                   Vue neemt het over
   │                                                   (resources/js/router)
   │
   └─ API-aanroep (/api/klanten) ─► routes/api.php
                                       │
                                  middleware        ingelogd? mag je dit?
                                       │
                                   controller       invoer controleren, antwoord bepalen
                                       │
                                     model          de SQL
                                       │
                                     MySQL
```

Alles komt binnen via `public/index.php`. Dat is het enige PHP-bestand dat de
browser kan bereiken; de rest van de code staat buiten de map `public`.

### 2.2 Eén pagina, veel schermen

De schermen zitten in Vue. Laravel stuurt voor **elke** gewone URL dezelfde
pagina terug (`resources/views/app.blade.php`), en de Vue-router bepaalt
daarna welk scherm je ziet. Daarom werkt F5 ook op `/klanten` of `/uitgifte`.

De regel die dat doet staat in `routes/web.php`:

```php
Route::view('/{pad?}', 'app')->where('pad', '^(?!api|build|up).*$');
```

Alles wat met `api`, `build` of `up` begint valt daar buiten: dat zijn de API,
de gebouwde frontend-bestanden en de statuscheck van Laravel.

### 2.3 Lagen

| Laag | Map | Verantwoordelijk voor |
|---|---|---|
| Route | `routes/` | welke URL bij welke methode hoort + welk recht nodig is |
| Middleware | `app/Http/Middleware` | ben je ingelogd, mag je dit |
| Controller | `app/Http/Controllers` | invoer controleren, bepalen wat er terug moet |
| Service | `app/Services` | regels van de voedselbank die op meerdere plekken gelden |
| Model | `app/Models` | alle SQL |
| Frontend | `resources/js` | de schermen |

**De regel:** een controller bevat geen SQL en een model bevat geen
beslissingen over het antwoord. Zoek je waar een query staat, kijk dan in
`app/Models`.

---

## 3. Mappen

```
voedselbanknieuw/
├─ app/
│  ├─ Exceptions/        HttpException en ValidationException (nette foutmeldingen)
│  ├─ Http/
│  │  ├─ Controllers/    één controller per onderdeel
│  │  └─ Middleware/     Ingelogd.php en Permissie.php
│  ├─ Models/            alle SQL, Basis.php is de basisklasse
│  ├─ Providers/         AppServiceProvider.php (macro's en databasetijdzone)
│  ├─ Services/          AuthService, DieetControle, Weekcyclus, Wachtwoord, Settings, Pages, Sessie
│  └─ Support/           Csv.php en SqlBestand.php
├─ bootstrap/app.php     instellingen van Laravel: routes, middleware, foutafhandeling
├─ config/               database.php, session.php, app.php
├─ database/
│  ├─ migrations/        één migratie die het SQL-schema uitvoert
│  ├─ schema/            01-schema.sql (tabellen) en 02-seed.sql (testdata)
│  └─ seeders/           DatabaseSeeder.php
├─ public/               index.php, favicon en de gebouwde frontend (build/)
├─ resources/
│  ├─ js/                de Vue-app
│  └─ views/app.blade.php   de enige HTML-pagina
├─ routes/
│  ├─ api.php            alle API-routes met hun rechten
│  └─ web.php            de pagina's
├─ storage/logs/         laravel.log — hier staan de serverfouten
└─ tests/                api-test, vergelijking met versie 2, volledige testronde
```

De Vue-app van binnen:

```
resources/js/
├─ app.js                startpunt: Vue, Pinia en de router
├─ App.vue               de buitenste component
├─ router/index.js       welke URL bij welk scherm hoort + rechtencontrole
├─ views/<onderdeel>/    de schermen (KlantenView.vue, ProductenView.vue, …)
├─ components/
│  ├─ <onderdeel>/       onderdelen van één scherm (KlantDrawer, PakketKolom, …)
│  ├─ ui/                bouwstenen die overal terugkomen (FormField, SideDrawer, …)
│  ├─ layout/            zijbalk, bovenbalk, de omlijsting
│  ├─ dashboard/         de blokken van het dashboard
│  └─ charts/            de twee grafiektypes
├─ composables/          herbruikbare logica (meldingen, dialogen, pakket samenstellen)
├─ services/             per onderdeel één bestand met de API-aanroepen
├─ stores/auth.js        wie is er ingelogd (Pinia)
├─ utils/format.js       datums, getallen en bedragen netjes tonen
└─ assets/styles/        de opmaak, opgesplitst per onderwerp
```

---

## 4. Afspraken in de code

1. **Nederlands.** Namen van functies, variabelen en commentaar zijn
   Nederlands, net als de meldingen naar de gebruiker.
2. **Geen Eloquent.** De modellen erven van `App\Models\Basis` en schrijven
   gewone SQL. De database komt uit het ERD van de casus en heeft
   kolomnamen als `idKlant` en `Gezinsnaam`; die passen niet bij de
   verwachtingen van Eloquent.
3. **Rechten staan bij de route**, in `routes/api.php`. Nooit alleen een knop
   verbergen in de frontend: wie de API rechtstreeks aanroept moet een 403
   krijgen.
4. **Eén controller per onderdeel**, met de vaste namen `index`, `store`,
   `update`, `destroy` en daarnaast eigen namen voor bijzondere acties
   (`afvoeren`, `uitgeven`, `verplaatsen`).
5. **Alle SQL in een model.** Prepared statements met `?`, nooit een waarde
   in de querytekst plakken.
6. **Fouten gooien, niet teruggeven.** `throw new HttpException(404, '…')` of
   `ValidationException::throwIf($fouten)`. `bootstrap/app.php` maakt er JSON
   van. Zo staat de afhandeling op één plek.
7. **Meerdere tabellen tegelijk wijzigen? In een transactie**
   (`DB::transaction(...)`), zodat het nooit half gebeurt.

---

## 5. Recepten — iets aanpassen

### 5.1 Een veld toevoegen (bijvoorbeeld "opmerking" bij een klant)

1. **Database** — zet de kolom in `database/schema/01-schema.sql` bij de tabel
   `Klant`, en vul hem eventueel in `02-seed.sql`. Daarna
   `php artisan migrate:fresh --seed`.
2. **Model** — `app/Models/Klant.php`: neem de kolom op in de `SELECT` van
   `lijst()` / `vind()` en in de `INSERT`/`UPDATE` van `maak()` en
   `wijzigContact()`.
3. **Controller** — `app/Http/Controllers/KlantController.php`: lees het veld
   uit het verzoek (`$request->tekst('opmerking')`), controleer het zo nodig
   in `valideer()` en zet het in het antwoord dat naar de frontend gaat.
4. **Frontend** — `resources/js/components/klanten/KlantDrawer.vue`: voeg een
   `<FormField>` toe en neem het veld mee in het object dat wordt opgeslagen.
5. Testen: opslaan, herladen, en kijken of het in de database staat.

### 5.2 Een API-route toevoegen

In `routes/api.php`:

```php
Route::get('/klanten/{id}/notities', [KlantController::class, 'notities'])
    ->middleware('mag:klant_inzien');
```

En in de controller:

```php
/** GET /klanten/{id}/notities */
public function notities(Request $request): array
{
    return Klant::notities($request->routeId());
}
```

Een array die je teruggeeft wordt automatisch JSON. Wil je een andere
statuscode, gebruik dan `return response()->json([...], 201);`.

Controleer daarna met `php artisan route:list --path=api` of de route er staat.

### 5.3 Een pagina toevoegen

1. Maak het scherm: `resources/js/views/<onderdeel>/MijnView.vue`.
2. Zet de route erbij in `resources/js/router/index.js`, binnen de lijst
   `children`:
   ```js
   { path: 'mijnpagina', name: 'mijnpagina',
     component: () => import('@/views/mijn/MijnView.vue'),
     meta: { pagina: 'klanten', titel: 'Mijn pagina' } },
   ```
   `meta.pagina` zegt welk recht nodig is; die namen staan in
   `app/Services/Pages.php`.
3. Zet hem in het menu: `resources/js/components/layout/AppSidebar.vue`.
4. In Laravel hoeft niets: `routes/web.php` stuurt elke URL al naar de Vue-app.

### 5.4 Rechten van een rol wijzigen

De rollen en rechten staan in de database, niet in de code:
`database/schema/02-seed.sql`, de tabellen `Rol`, `Permissie` en
`Rol_has_Permissie`. Pas daar de koppeling aan en draai
`php artisan migrate:fresh --seed`.

Wil je een heel **nieuw** recht, dan komt dat er ook bij in
`app/Services/Pages.php` (welke pagina's je ermee mag openen) en bij de routes
die het nodig hebben (`->middleware('mag:nieuw_recht')`).

### 5.5 Een melding of tekst aanpassen

* Meldingen van de server staan in de controllers, bij de plek waar ze
  ontstaan (bijvoorbeeld `'Klantgegevens opgeslagen.'`).
* Teksten op het scherm staan in het Vue-bestand van dat scherm.
* Kleuren, lettertypes en afstanden staan in `resources/js/assets/styles/`.
  `base.css` bevat de kleuren als CSS-variabelen; pas die aan en het werkt
  overal door.

### 5.6 Een nieuwe controle op invoer

Elke controller heeft onderaan een eigen `valideer()`-methode. Het patroon:

```php
$fouten = [];
if ($naam === '') {
    $fouten['naam'] = 'Vul een naam in.';
}
ValidationException::throwIf($fouten);
```

De frontend zet die melding automatisch onder het juiste invoerveld, omdat de
sleutel (`naam`) gelijk is aan de naam van het veld.

---

## 6. De database

* Het schema staat in **`database/schema/01-schema.sql`** en komt uit het ERD
  van de casus. De migratie `2026_11_26_000000_voedselbank_schema.php` voert
  dat bestand uit; er zijn geen losse Laravel-migraties per tabel.
* De testdata staat in **`database/schema/02-seed.sql`** en wordt uitgevoerd
  door `database/seeders/DatabaseSeeder.php`.
* Beide bestanden kun je ook gewoon in phpMyAdmin of TablePlus importeren.
* De belangrijkste tabellen: `Gebruiker`, `Rol`, `Permissie`,
  `Rol_has_Permissie`, `Klant`, `Gezinslid`, `Wens`, `Leverancier`,
  `Levering`, `Product`, `Categorie`, `Pakket`, `Product_has_Pakket`,
  `Afvoer` en `Instelling`.
* Twee views maken het rekenwerk makkelijker: `vw_Gezinslid` (leeftijd en type
  uit de geboortedatum) en `vw_Klant` (gezinsgrootte en laatste uitgifte).

Twee instellingen in `config/database.php` zijn bewust aangepast:

1. **`strict` staat uit, met een eigen `modes`-lijst.** Die lijst is gelijk aan
   de strenge stand van Laravel, maar zonder `ONLY_FULL_GROUP_BY`. Onze
   rapportagequery's groeperen op een alias uit de `SELECT`
   (`... AS D ... GROUP BY D`). MySQL accepteert dat, MariaDB niet.
2. **De tijdzone van MySQL wordt gelijkgezet aan die van PHP**
   (`AppServiceProvider::zetDatabaseTijdzone`). Anders klopt `CURDATE()` in de
   query's niet met "vandaag" in de applicatie.

---

## 7. Testen

```bash
node tests/api/api-test.js             # 28 controles op de API (app moet draaien)
node tests/api/vergelijk-met-v2.js     # vergelijkt met versie 2 (beide moeten draaien)
node tests/testrapport/run.js          # alle 113 testcases, met screenshots
```

Voor de laatste is Playwright nodig:

```bash
npm install -D playwright
npx playwright install chromium
```

Draait de app op een ander adres, geef dat dan mee:

```bash
VB_URL=http://voedselbanknieuw.test node tests/api/api-test.js
```

> De volledige testronde **leegt de database en zet de testdata terug** vóór
> elk onderdeel. Draai hem niet op gegevens die je wilt bewaren.

De uitkomsten komen in `tests/testrapport/resultaten.json` en de screenshots in
`tests/testrapport/screenshots/`.

---

## 8. Handige commando's

| Commando | Wat het doet |
|---|---|
| `php artisan serve` | start de app op `http://127.0.0.1:8000` |
| `php artisan route:list` | laat alle routes zien met hun middleware |
| `php artisan migrate --seed` | tabellen aanmaken en vullen |
| `php artisan migrate:fresh --seed` | alles weggooien en opnieuw opbouwen |
| `php artisan optimize:clear` | alle caches leeg (na een wijziging in `.env`) |
| `php artisan tinker` | losse PHP-code uitproberen met de database erbij |
| `npm run dev` | Vite laten meelopen tijdens het ontwikkelen |
| `npm run build` | de frontend bouwen voor de oplevering |
| `./vendor/bin/pint` | de PHP-code netjes opmaken volgens de stijl van Laravel |
