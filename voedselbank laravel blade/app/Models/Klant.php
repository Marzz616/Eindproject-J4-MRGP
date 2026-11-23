<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Klant Model (US-KLT-01 t/m US-KLT-14, US-WRK-01, US-SEC-02)
 */
class Klant extends Model
{
    protected $table = 'klanten';

    protected $fillable = [
        'gezinsnaam',         // US-KLT-04
        'adres',              // US-KLT-04
        'postcode',           // US-RAP-04 & US-RAP-05
        'telefoonnummer',     // US-KLT-04
        'emailadres',         // US-KLT-04
        'aantal_volwassenen', // US-KLT-05 & US-KLT-06
        'aantal_kinderen',    // US-KLT-05 & US-KLT-06
        'aantal_babys',       // US-KLT-05 & US-KLT-06
        'wensen',             // US-KLT-07, US-KLT-08, US-KLT-09
        'eigen_wens',         // US-KLT-10
        'identificatie_type', // US-WRK-01: paspoort of rijbewijs
        'identificatie_nr',   // US-WRK-01 & US-SEC-02: Privacygevoelig gegeven
        'geaccepteerd',       // US-KLT-12
        'aanmelddatum',       // US-KLT-11 & US-WRK-03
    ];

    protected $casts = [
        'wensen' => 'array',
        'geaccepteerd' => 'boolean',
    ];

    protected $appends = ['totaal_gezinsleden'];

    // US-KLT-06: Bereken automatisch het totale aantal gezinsleden (volwassenen + kinderen + baby's)
    public function getTotaalGezinsledenAttribute(): int
    {
        return (int) $this->aantal_volwassenen + (int) $this->aantal_kinderen + (int) $this->aantal_babys;
    }

    // US-KLT-13 & US-KLT-14: Relatie met afgenomen voedselpakketten
    public function pakketten()
    {
        return $this->hasMany(Pakket::class, 'klant_id');
    }
}