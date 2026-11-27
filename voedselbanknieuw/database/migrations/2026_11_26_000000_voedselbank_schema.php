<?php

use App\Support\SqlBestand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zet het databaseschema van de voedselbank klaar.
 *
 * De tabellen komen uit het ERD van de casus en staan in
 * database/schema/01-schema.sql. We voeren dat bestand hier uit, zodat
 * "php artisan migrate" precies dezelfde database maakt als de vorige versie
 * en het SQL-bestand ook los in phpMyAdmin of TablePlus te gebruiken blijft.
 */
return new class extends Migration
{
    public function up(): void
    {
        SqlBestand::voerUit(database_path('schema/01-schema.sql'));
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::statement('DROP VIEW IF EXISTS vw_Gezinslid, vw_Klant');
        DB::statement('DROP TABLE IF EXISTS Product_has_Pakket, Pakket, Levering_has_Product, Levering,
            Afvoer, Product, Categorie, Leverancier, Gezinslid_has_Wens, Gezinslid, Klant_has_Wens,
            Wens, Klant, Rol_has_Permissie, Permissie, Gebruiker, Rol, Instelling');
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
