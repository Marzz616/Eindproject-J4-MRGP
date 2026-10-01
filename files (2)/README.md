# Producten & Voorraad

Voorraadpagina met een Node.js/Express-backend die met `mysql2` op jouw
MySQL-database praat. De database volgt jouw ERD (13 tabellen). De pagina
zelf werkt met **Product** en **Categorie**; de overige tabellen (Klant,
Pakket, Levering, ...) zijn aangemaakt en klaar voor uitbreiding.

## 1. Database aanmaken in MySQL Workbench

1. Maak verbinding met je server.
2. Open `schema.sql` (File > Open SQL Script) en voer het uit (bliksem-icoon).
   Dit maakt database `voorraadbeheer` met alle tabellen uit het ERD en
   zet er 5 categorieën en 3 voorbeeldproducten in.

Het script verwijdert niets en kan dus opnieuw worden uitgevoerd.

### Afwijkingen van het ERD

| Wat | Waarom |
|---|---|
| `Product.THT` (DATE) en `Product.Locatie` toegevoegd | De pagina toont deze kolommen, maar ze stonden niet in het ERD |
| `Product.Aantal_voorraad` is INT i.p.v. VARCHAR(45) | Nodig om te kunnen optellen (capaciteitsbalk) |
| FK's naar `Product` hebben `ON UPDATE CASCADE` | Categorie zit in de primary key van Product; zonder cascade kun je de categorie van een product in een pakket/levering niet wijzigen |
| `Gebruiker.Wachtwoord_hash` is VARCHAR(255) | Een hash past niet in 45 tekens |

### Heb je de database al uit je eigen ERD gemaakt?

Dan hoef je `schema.sql` niet te draaien. Voeg alleen de twee kolommen toe:

```sql
ALTER TABLE Product
  ADD COLUMN THT DATE NULL,
  ADD COLUMN Locatie VARCHAR(45) NULL;
```

Zet `DB_NAME` in `.env` op de naam van jouw database. De server werkt ook als
`Aantal_voorraad` bij jou nog VARCHAR is. Zorg wel dat de tabel `Categorie`
minstens één rij heeft, anders kun je geen product aanmaken.

## 2. Project starten

Node.js 18+ is nodig.

```bash
cd voorraadbeheer
npm install
cp .env.example .env     # vul je MySQL-gegevens in
npm start
```

Open [http://localhost:3001](http://localhost:3001).

## Hoe de pagina de tabellen gebruikt

- **Categorie-pills en keuzelijst** worden uit de tabel `Categorie` geladen.
  Een nieuwe categorie toevoegen? Voeg een rij toe in `Categorie` en herlaad de pagina.
- **Read**: `Product` gekoppeld aan `Categorie`, met filter en zoeken op
  productnaam of EAN.
- **Create / Update / Delete**: schrijven direct naar `Product`.
- **Verwijderen of categorie wijzigen** van een product dat in
  `Product_has_Pakket` of `Levering_has_Product` voorkomt, wordt door de
  database tegengehouden. De pagina toont dan een duidelijke melding.
- **Capaciteitsbalk**: som van `Aantal_voorraad` t.o.v. `MAX_CAPACITEIT` uit `.env`.
- **THT-kleuren**: rood (VERLOPEN) bij een datum in het verleden, oranje
  (BIJNA VERLOPEN) binnen 30 dagen.

## API

| Methode | Endpoint | Werking |
|---|---|---|
| GET | `/api/categorieen` | alle categorieën |
| GET | `/api/producten` | lijst, optioneel `?categorie=<id>&zoek=` |
| GET | `/api/producten/:id` | één product |
| POST | `/api/producten` | aanmaken |
| PUT | `/api/producten/:id` | bijwerken |
| DELETE | `/api/producten/:id` | verwijderen |
| GET | `/api/capaciteit` | totale voorraad + maximum |

## Problemen oplossen

- **"Kon producten niet laden"**: draait `npm start` nog, en kloppen de
  gegevens in `.env`?
- **Access denied for user**: wachtwoord of rechten in `.env` kloppen niet.
- **Unknown column 'THT'**: voer de `ALTER TABLE` hierboven uit.
- **Table 'voorraadbeheer.product' doesn't exist** op Linux: tabelnamen zijn
  daar hoofdlettergevoelig. Gebruik de namen uit `schema.sql`.
