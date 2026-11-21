<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Gebruiker Model (US-GBR-01 t/m US-GBR-09, US-SEC-05, TECH-02)
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'gebruikers';

    protected $fillable = [
        'naam',
        'gebruikersnaam',
        'email',
        'wachtwoord',
        'rol',            // directie, magazijnmedewerker, vrijwilliger
        'is_geblokkeerd', // US-GBR-03: Status voor geblokkeerde gebruikers
    ];

    // US-SEC-05: Verberg wachtwoord-hashes in alle JSON/API-antwoorden (AVG/beveiliging)
    protected $hidden = [
        'wachtwoord',
    ];

    protected $casts = [
        'is_geblokkeerd' => 'boolean',
    ];

    public function getAuthPassword()
    {
        return $this->wachtwoord;
    }
}