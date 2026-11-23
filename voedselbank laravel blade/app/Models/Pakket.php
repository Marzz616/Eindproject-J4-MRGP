<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Voedselpakket Model (US-PAK-01 t/m US-PAK-12)
 */
class Pakket extends Model
{
    protected $table = 'pakketten';

    protected $fillable = [
        'pakketnummer',        // US-PAK-04: Automatisch uniek pakketnummer
        'klant_id',            // US-PAK-06: Gekoppeld aan één specifieke klant
        'samenstellingsdatum', // US-PAK-05: Datum van samenstelling
        'uitgiftedatum',       // US-PAK-09 & US-PAK-12: Datum van uitgifte
        'status',              // samengesteld, uitgegeven
    ];

    protected static function boot()
    {
        parent::boot();

        // US-PAK-04 & US-PAK-05: Automatisch uniek pakketnummer en samenstellingsdatum registreren
        static::creating(function ($pakket) {
            if (empty($pakket->pakketnummer)) {
                $pakket->pakketnummer = 'PAK-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            }
            if (empty($pakket->samenstellingsdatum)) {
                $pakket->samenstellingsdatum = now()->toDateString();
            }
        });
    }

    public function klant()
    {
        return $this->belongsTo(Klant::class, 'klant_id');
    }

    // US-PAK-07: Geregistreerde producten en aantallen in het pakket
    public function producten()
    {
        return $this->belongsToMany(Product::class, 'pakket_product', 'pakket_id', 'product_id')
                    ->withPivot('aantal');
    }
}