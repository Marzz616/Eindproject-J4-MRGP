<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Leverancier Model (US-LEV-01 t/m US-LEV-06)
 */
class Leverancier extends Model
{
    protected $table = 'leveranciers';

    // US-LEV-01 & US-LEV-05: Bedrijfsnaam, adres, contactpersoon, e-mailadres, telefoonnummer en eerstvolgende levering
    protected $fillable = [
        'bedrijfsnaam',
        'adres',
        'contactpersoon',
        'emailadres',
        'telefoonnummer',
        'volgende_levering',
    ];

    // US-LEV-06 & US-WRK-02: Relatie met geplande en binnengekomen leveringen
    public function leveringen()
    {
        return $this->hasMany(Levering::class, 'leverancier_id');
    }
}