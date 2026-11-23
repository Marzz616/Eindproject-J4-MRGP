# Voedselbank Maaskantje – Laravel (Herd)

## Wat is omgezet

1. **HTML → Blade**  
   Alle pagina’s staan in `resources/views/pages/*.blade.php` met layout `layouts/app.blade.php`.  
   Routes in `routes/web.php` (`/login`, `/`, `/klanten`, …).

2. **API PHP → Laravel**  
   - `AuthController` – volledige poort van `auth.php` (`?actie=login|ik|logout|rechten|wachtwoord`).  
   - Overige endpoints (`dashboard.php`, `klanten.php`, …) draaien via `LegacyApiController` + `app/Legacy/` (zelfde SQL/responses als de originele API).  
   - Frontend blijft: `api('auth.php?actie=login', { body: { gebruikersnaam, wachtwoord } })`.

3. **Sessie**  
   Laravel-session op API-routes zodat `credentials: 'same-origin'` werkt (Herd).

## Database

Gebruik het originele schema:

```bash
# .env: DB_DATABASE=voedselbank, DB_USERNAME=..., DB_PASSWORD=...
mysql -u root -p voedselbank < db/01-schema.sql
mysql -u root -p voedselbank < db/02-seed.sql
```

Of importeer via TablePlus / Herd database UI.

Wachtwoord testaccounts: **voedselbank**  
(p.abraham = Directie, m.snel = Administratie, r.veenstra = Magazijn, a.degroot = Vrijwilliger)

## Starten (Herd)

1. Site in Herd koppelen aan de projectmap (`public` als document root).
2. `composer install` (als vendor ontbreekt).
3. `.env` kopiëren van `.env.example`, `APP_KEY` genereren: `php artisan key:generate`.
4. Database importeren (schema + seed).
5. Open `https://voedselbank.test/login` (of jouw Herd-URL).

## Structuur

```
app/Http/Controllers/Api/AuthController.php   ← auth
app/Http/Controllers/Api/LegacyApiController.php
app/Services/AuthService.php
app/Legacy/helpers.php + api/*.php            ← overige API-logica
resources/views/pages/*.blade.php
public/assets/app.js + app.css
routes/web.php + routes/api.php
```
