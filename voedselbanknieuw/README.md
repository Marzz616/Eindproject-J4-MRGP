# Voedselbank Maaskantje — back-office (Laravel)

Back-office voor Stichting Voedselbank Maaskantje: ontvangst van producten in
het magazijn, klantadministratie, voedselpakketten samenstellen, uitgifte op
vrijdag en rapportages voor de directie.

Eindproject Software Developer niveau 4 — team Pheadon, Rick, Mario en Guillian.

## Techniek

| Onderdeel | Keuze |
|---|---|
| Framework | Laravel 13 (PHP 8.4), draait met Laravel Herd |
| Frontend | Vue 3 + Vue Router + Pinia, gebouwd met Vite |
| Database | MySQL 8 / MariaDB, schema uit het ERD van de casus |
| Opmaak | Eigen CSS, opgesplitst per onderwerp |

## Snel starten

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

Daarna `http://voedselbanknieuw.test` (Herd) of `php artisan serve`.
Inloggen: **p.abraham** / **voedselbank** (directie).

Lukt er iets niet? De stap-voor-stapuitleg en de veelvoorkomende fouten staan in
**[DOCUMENTATIE.md](DOCUMENTATIE.md)**.

## Documentatie

| Bestand | Inhoud |
|---|---|
| [DOCUMENTATIE.md](DOCUMENTATIE.md) | De site draaiend krijgen, hoe de code in elkaar zit en hoe je hem aanpast |
| [FUNCTIES.md](FUNCTIES.md) | Elke klasse en functie apart uitgelegd |
| [LARAVEL-HERD.md](LARAVEL-HERD.md) | Installeren met Herd, korte versie |

## Mappen

```
app/Http/Controllers   wat er gebeurt bij een verzoek
app/Http/Middleware    rechtencontrole vóór de controller
app/Models             alle SQL
app/Services           regels van de voedselbank (inloggen, dieetcontrole, weekcyclus)
app/Exceptions         nette foutmeldingen (401, 403, 404, 422)
app/Support            CSV-download en het uitvoeren van een SQL-bestand
resources/js           de Vue-app
routes/api.php         de API, met per route de benodigde permissie
routes/web.php         de pagina's (altijd dezelfde Blade-view, Vue doet de rest)
database/schema        het SQL-schema en de testdata
tests                  API-test, vergelijking met versie 2, volledige testronde
```

## Testen

```bash
node tests/api/api-test.js                 # 28 controles op de API
node tests/api/vergelijk-met-v2.js         # geeft Laravel dezelfde gegevens als versie 2?
node tests/testrapport/run.js              # alle 113 testcases (Playwright nodig)
```

## Rollen in de applicatie

| Rol | Mag |
|---|---|
| Directie | alles |
| Magazijnmedewerker | producten, voorraad en leveranciers |
| Vrijwilliger | pakketten samenstellen en uitgeven, voorraad corrigeren |
| Administratie | klanten, rapportages en accounts |
