# Voedselbank Maaskantje — werkafspraken in deze code

Lees eerst `DOCUMENTATIE.md` (draaien, structuur, aanpassen) en `FUNCTIES.md`
(elke functie apart).

- Laravel 13 op PHP 8.4, draait met Laravel Herd. Geen Apache-config, geen Docker.
- De database komt uit het ERD van de casus en staat in `database/schema/`.
  Gebruik geen Eloquent-migraties voor die tabellen; het schema blijft SQL.
- De modellen in `app/Models` erven van `Basis` en bevatten alleen SQL
  (prepared statements via de DB-facade van Laravel). Geen Eloquent-modellen.
- Rechten staan bij de route in `routes/api.php` (`ingelogd` en `mag:`), nooit
  alleen in de frontend.
- Inloggen gaat via `app/Services/AuthService.php`, niet via de ingebouwde
  auth van Laravel: de tabellen Gebruiker/Rol/Permissie komen uit de casus.
- Foutmeldingen zijn Nederlands en gaan als JSON terug; de afhandeling staat
  in `bootstrap/app.php`.
- Commentaar en namen in de code zijn Nederlands.
