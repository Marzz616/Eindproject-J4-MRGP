# Voedselbank Maaskantje — back-office

Webapplicatie voor het eindproject: klanten, producten, leveranciers,
voedselpakketten, uitgifte, rapportages en gebruikersbeheer.

| Laag | Techniek |
|---|---|
| Frontend | **Vue 3** (Composition API, `<script setup>`), Vue Router, Pinia, gebouwd met Vite |
| Backend | **PHP 8.3** REST-API met front controller, router, controllers, models en services (MVC) |
| Database | MySQL 8 (InnoDB, foreign keys, views) |
| Omgeving | Docker Compose: web (Apache), db (MySQL), phpMyAdmin |

---

## 1. Starten met Docker (aanbevolen)

Je hebt alleen **Docker Desktop** nodig.

```bash
cd voedselbank
docker compose up -d --build     # eerste keer 2-3 minuten (Vue wordt in de container gebouwd)
docker compose ps                # drie containers: vb_web, vb_db (healthy), vb_pma
```

| Wat | Adres |
|---|---|
| De applicatie | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |
| MySQL van buitenaf (Workbench) | `127.0.0.1` poort `3307`, gebruiker `vbuser`, wachtwoord `vbpass` |

De database wordt bij de **eerste** start gevuld met `database/01-schema.sql`
en `database/02-seed.sql`. Opnieuw beginnen met verse testdata:

```bash
docker compose down -v           # -v gooit ook de database weg
docker compose up -d --build
```

Stoppen zonder data kwijt te raken: `docker compose down`.

**Tijdzone.** De containers staan op `Europe/Amsterdam` (`TZ` en `APP_TZ` in
`docker-compose.yml`). Zonder die instelling draait een container op UTC en
loopt "vandaag" een of twee uur achter — rond middernacht gaat dat mis bij de
weekcyclus en de eerstvolgende leverdatum.

### Inloggen

Wachtwoord van alle testaccounts: **`voedselbank`**

| Gebruikersnaam | Rol |
|---|---|
| `p.abraham` | Directie (mag alles) |
| `m.snel` | Administratie |
| `r.veenstra` | Magazijnmedewerker |
| `a.degroot` | Vrijwilliger |
| `k.degraaf` | Vrijwilliger — geblokkeerd, om te laten zien dat blokkeren werkt |

### Veelvoorkomende problemen

| Probleem | Oplossing |
|---|---|
| `port is already allocated` | Iets anders gebruikt poort 8080/8081/3307. Pas de linkerkant van `ports:` in `docker-compose.yml` aan. |
| Witte pagina of foutmelding | `docker compose logs web` — daar staat de echte fout. |
| Database leeg of oud | Het volume bestond al. `docker compose down -v` en opnieuw `up`. |
| Wijziging in Vue niet zichtbaar | De frontend wordt bij het bouwen van de image gemaakt: `docker compose up -d --build`. (PHP-wijzigingen zijn direct zichtbaar, `backend/` is gekoppeld.) |

---

## 2. Ontwikkelen (met live herladen)

Tijdens het ontwikkelen draai je de Vue-app los, zodat elke wijziging direct
in de browser staat. De database en de PHP-API blijven in Docker.

```bash
docker compose up -d             # database + API op :8080
cd frontend
npm install                      # eenmalig
npm run dev                      # Vue op http://localhost:5173
```

Vite stuurt alle `/api`-verzoeken door naar `http://localhost:8080`
(zie `frontend/vite.config.js`), dus inloggen en de sessie werken gewoon.

Zonder Docker kan het ook, met een lokale MySQL/MariaDB en PHP 8.3:

```bash
DB_HOST=127.0.0.1 php -S localhost:8000 backend/public/index.php   # API
VITE_API_URL=http://localhost:8000 npm run dev                       # in frontend/
```

---

## 3. Mappenstructuur

```
voedselbank/
├── backend/                       PHP-API
│   ├── public/index.php           front controller: elk /api-verzoek komt hier binnen
│   ├── routes/api.php             ALLE routes op één plek, met de vereiste permissie
│   ├── config/database.php        databaseverbinding (uit omgevingsvariabelen)
│   └── src/
│       ├── bootstrap.php          tijdzone + autoloader (PSR-4: App\ → src/)
│       ├── Core/                  het "framework": Router, Request, Response,
│       │                          Database, HttpException, ValidationException, Settings
│       ├── Auth/                  Auth (wie is ingelogd, wat mag hij), Session, Pages
│       ├── Controllers/           één controller per onderdeel: HTTP in, JSON uit,
│       │                          validatie van de invoer
│       ├── Models/                alle SQL, één model per tabel/onderwerp
│       └── Services/              bedrijfsregels die meerdere controllers delen:
│                                  DieetControle, Weekcyclus, Wachtwoord
│
├── frontend/                      Vue 3-app
│   ├── index.html, vite.config.js, package.json
│   └── src/
│       ├── main.js, App.vue       opstarten: Pinia, router, stijlen
│       ├── router/                pagina's + routebewaking per rol
│       ├── stores/auth.js         Pinia-store: wie is ingelogd, mag(), magPagina()
│       ├── services/              API-aanroepen, één bestand per onderdeel (http.js = basis)
│       ├── composables/           herbruikbare logica: useToast, useDialog,
│       │                          useFormErrors, useUitloggen, usePakketSamenstellen
│       ├── views/<onderdeel>/     de pagina's (één map per onderdeel)
│       ├── components/
│       │   ├── layout/            AppLayout, AppSidebar, AppTopbar
│       │   ├── ui/                BaseModal, SideDrawer, FormField, SearchInput, ...
│       │   ├── charts/            BarChart, LineChart (zonder externe bibliotheek)
│       │   └── <onderdeel>/       onderdelen van één pagina (KlantDrawer, VoorraadKolom, ...)
│       ├── utils/format.js        datums, bedragen, getallen
│       └── assets/styles/         CSS, opgesplitst per onderwerp
│
├── database/                      01-schema.sql (tabellen) en 02-seed.sql (testdata)
├── docker/apache.conf             /api → PHP, al het andere → Vue-app
├── tests/
│   ├── api-test.js                snelle regressietest (28 controles)
│   └── testrapport/               volledige testuitvoering (110 testcases) + resultaten
├── Dockerfile                     stap 1 bouwt Vue, stap 2 = PHP + Apache
└── docker-compose.yml
```

**Waarom deze indeling?** Elk bestand heeft één taak, en aan de naam en de map
zie je welke. Zoek je iets over klanten, dan staat het in `KlantController.php`,
`Models/Klant.php`, `services/klantenApi.js`, `views/klanten/` en
`components/klanten/`. Zo kunnen vier teamleden tegelijk aan verschillende
onderdelen werken zonder elkaars bestanden te raken.

---

## 4. Hoe een verzoek door de applicatie loopt

Voorbeeld: de directie verwijdert een klant.

```
KlantRij.vue  ──klik──>  KlantenView.vue  ──>  klantenApi.verwijderen(3)
                                                  │  DELETE /api/klanten/3
                                                  ▼
backend/public/index.php  (front controller)
  └─ Router::dispatch()
       ├─ zoekt in routes/api.php:  DELETE /klanten/{id}  →  KlantController@destroy, vereist 'klant_beheren'
       ├─ Auth::requirePermission('klant_beheren')        →  anders 401 / 403
       └─ KlantController::destroy($request)
             ├─ Klant::vind(3)                            (Model: SQL)
             ├─ Klant::aantalPakketten(3) > 0 ?           →  HttpException 409 "heeft_historie"
             └─ Klant::verwijder(3)                       →  ['ok' => true, 'melding' => ...]
  └─ Response::send()   JSON terug naar de browser
                                                  │
KlantenView.vue  <──────────────────────────────── ┘
  409 + reden heeft_historie?  → dialoog "Dit gezin kan niet weg" + aanbod "op non-actief"
  200?                         → toast + lijst opnieuw laden
```

**Backend-afspraken**

* Controllers geven een array terug (wordt JSON, status 200) of een `Response`
  (bijv. 201 of CSV). Fouten zijn exceptions: `HttpException(409, 'melding', [...])`
  of `ValidationException(['veld' => 'melding'])` (422). De Router zet ze om naar JSON;
  er gaat nooit een stacktrace naar de browser.
* Alle SQL staat in `Models/` en gebruikt prepared statements (geen SQL-injectie).
* Werk dat uit meerdere stappen bestaat (pakket opslaan, levering boeken) loopt via
  `Database::transaction()`: lukt één stap niet, dan wordt alles teruggedraaid.

**Frontend-afspraken**

* Een pagina (`views/`) haalt data op via `services/` en geeft die aan componenten.
* Formulieren gebruiken `useFormErrors()`: een 422 van de server komt automatisch
  onder het juiste veld te staan (`<FormField :fout="fouten.email">`).
* Vragen aan de gebruiker gaan via `useDialog()` (`await dialog.bevestig({...})`),
  meldingen via `useToast()`.
* Tekst van gebruikers wordt altijd als tekst getoond (`{{ }}`), nooit als HTML —
  daarmee is cross-site scripting uitgesloten.

### De API in het kort

| Onderdeel | Routes |
|---|---|
| Inloggen | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`, `GET /auth/rechten`, `PUT /auth/wachtwoord` |
| Dashboard | `GET /dashboard` |
| Gebruikers | `GET/POST /gebruikers`, `PUT /gebruikers/{id}`, `PATCH /gebruikers/{id}/actief`, `PUT /gebruikers/{id}/wachtwoord` |
| Klanten | `GET/POST /klanten`, `PUT/DELETE /klanten/{id}`, `PATCH /klanten/{id}/status`, `GET /klanten/{id}/pakketten`, `GET/POST /wensen` |
| Leveranciers | `GET/POST /leveranciers`, `PUT/DELETE /leveranciers/{id}`, `PATCH /leveranciers/{id}/actief`, `POST /leveringen` |
| Producten | `GET/POST /producten`, `PUT/DELETE /producten/{id}`, `PATCH /producten/{id}/voorraad`, `POST /producten/{id}/afvoer` |
| Categorieën | `GET/POST /categorieen`, `PUT/DELETE /categorieen/{id}`, `POST /categorieen/{id}/verplaatsen` |
| Pakketten | `GET /pakketten/samenstellen`, `GET /pakketten/klant/{id}`, `GET/POST /pakketten`, `GET/PUT/DELETE /pakketten/{id}` |
| Uitgifte | `GET /uitgifte/wachtrij`, `GET /uitgifte/zoek?q=`, `GET /uitgifte/{id}`, `POST /uitgifte/{id}/identiteit`, `/uitgeven`, `/niet-opgehaald` |
| Rapportages | `GET /rapportages`, `GET /rapportages/maand?soort=&maand=&jaar=`, `GET /rapportages/export/{rapport}` |

Welke rol welke route mag gebruiken staat in `backend/routes/api.php`.

---

## 5. Hoe de database in elkaar zit

18 tabellen en 2 views, allemaal InnoDB met echte foreign keys.

### Rollen en rechten

```
Rol ──< Rol_has_Permissie >── Permissie
 │
 └──< Gebruiker
```

Rechten hangen aan de **rol**, niet aan de persoon. Geef iemand een andere rol
en hij krijgt direct andere rechten. De tien permissies:

`pakket_beheren`, `pakket_uitgeven`, `klant_inzien`, `klant_beheren`,
`product_beheren`, `voorraad_corrigeren`, `leverancier_beheren`,
`levering_boeken`, `rapportage_export`, `gebruiker_beheren`.

### Klanten en dieetwensen

```
Klant ──< Gezinslid ──< Gezinslid_has_Wens >── Wens
```

Belangrijk: een dieetwens hangt aan het **gezinslid**, niet aan het gezin.
Anders kun je niet vastleggen dat alleen Bart glutenvrij moet eten.

`Gezinslid.Type` (baby/kind/volwassene) wordt **niet opgeslagen** maar berekend
in de view `vw_Gezinslid`, uit de geboortedatum. Een baby wordt vanzelf een kind.
`vw_Klant` telt de gezinsgrootte, de laatste uitgifte en het klantnummer (`K-001`).

`Klant.Opmerkingen` is een vrij tekstveld van de directie: voorkeuren en
afspraken die geen dieetwens zijn ("liever rijst dan pasta, geen koffie").
De vrijwilliger ziet die tekst bij het samenstellen en op de pakbon, maar kan
hem niet aanpassen — voorkeuren vastleggen hoort bij de klantadministratie.

### Hoe streng is een dieetwens?

`Wens.Streng` bepaalt wat er bij het samenstellen gebeurt:

| `Streng` | Wensen | Wat de applicatie doet |
|---|---|---|
| `1` | Notenallergie, Pinda-allergie, Schaaldierallergie | het product kan **niet** worden toegevoegd; ook de server weigert het, ook met `forceer` |
| `0` | Halal, Geen varkensvlees, Glutenvrij, Lactose-intolerant, Vegetarisch, Veganistisch | mag wel, maar alleen na een expliciete bevestiging |

De reden voor dat onderscheid: bij een ernstige allergie mag het product het
huis niet in, ook niet voor de andere gezinsleden. Bij de overige wensen kan
het gezin het onderling verdelen — als één van de vijf lactose-intolerant is,
kunnen de andere vier de melk prima drinken. Daarom staat er bij elke
waarschuwing om hoeveel personen het gaat, met hun namen.

**Uitzondering: het gezin wil het tóch.** Soms klopt de harde blokkade niet
voor dit gezin — de vader eet geen noten, de kinderen wel. De directie kan dat
per gezinslid en per wens vastleggen in `Gezinslid_has_Wens`:

| Kolom | Betekenis |
|---|---|
| `Toch_toestaan` | het gezin heeft laten weten dat het product welkom is |
| `Toelichting` | **verplicht**: waarom, in gewone taal |

Zonder toelichting telt de uitzondering niet — de backend zet `Toch_toestaan`
alleen op 1 als er ook een reden bij staat. Een vastgelegde uitzondering haalt
de harde blokkade weg voor dat gezin; de **waarschuwing blijft**, met de reden
erbij, zowel in het samenstelscherm als op de pakbon. Zo verdwijnt de
informatie nooit stilletjes: je ziet dat er bewust van is afgeweken, en waarom.

### Producten en voorraad

```
Categorie ──< Product ──< Levering_has_Product >── Levering >── Leverancier
                   │
                   ├──< Product_has_Pakket >── Pakket >── Klant
                   └──< Afvoer
```

`Product.Productnaam` en `Product.EAN` zijn `UNIQUE` (US-VRD-09 en US-VRD-10).
`Product.Houdbaar_tot` mag leeg zijn (luiers, shampoo). Er is **één** THT per
product, niet per partij — zie *Bekende beperkingen* onderaan.
`Product.Waarde` is de winkelwaarde per stuk; daarmee rekent het rapport de
gemiddelde pakketwaarde uit.

### Historie beschermen

Dit is de kern van "historische gegevens mogen niet verloren gaan":

| Relatie | Regel | Gevolg |
|---|---|---|
| `Product_has_Pakket → Product` | `ON DELETE RESTRICT` | een product dat ooit in een pakket zat kán de database niet verwijderen |
| `Levering_has_Product → Product` | `ON DELETE RESTRICT` | idem voor de leveringshistorie |
| `Afvoer → Product` | `ON DELETE RESTRICT` | idem voor afgevoerde producten |
| `Gezinslid → Klant` | `ON DELETE CASCADE` | gezinsleden horen bij het gezin en hebben los geen betekenis |
| `Pakket → Klant` | geen cascade | een klant met pakketten kan niet zomaar weg; de backend weigert `DELETE /api/klanten/{id}` met 409 en biedt "op non-actief" aan |
| `Gebruiker` | wordt nooit verwijderd, alleen `Actief = 0` | "samengesteld door Ank" blijft kloppen |

De backend vangt dit netjes af: je krijgt geen databasefout maar een
uitleg met een alternatief ("zet de voorraad op 0").

### Afwijkingen ten opzichte van de originele ERD

Alles hieronder is toegevoegd omdat een user story anders niet te bouwen was:

1. `Permissie` + `Rol_has_Permissie` — nodig voor US-GBR-05 t/m US-GBR-09.
2. `Gezinslid_has_Wens` — wensen per persoon in plaats van per gezin (US-KLT-06/07).
3. `Gezinslid.Relatie` los van het berekende `Type`.
4. `Afvoer` — zonder deze tabel is het verspillingspercentage (US-RAP-04) niet te berekenen.
5. `Product.Waarde` — nodig voor de gemiddelde pakketwaarde (US-RAP-02).
6. `Categorie.Kort` — korte naam voor de filterknoppen.
7. Datatypes rechtgezet: datums zijn `DATE`, aantallen `INT`, ja/nee `BOOLEAN`.
8. `E-mail` hernoemd naar `Email` (een streepje mag niet in een MySQL-kolomnaam).
9. `Wens.Streng` en `Wens.Conflict_tag` — zonder die twee kan de applicatie een
   ernstige allergie niet onderscheiden van een voorkeur (US-KLT-06/07, US-PAK-09).
10. `Klant.Opmerkingen` — voorkeuren die geen dieetwens zijn, zodat de
    vrijwilliger weet wat dit gezin nodig heeft (US-PAK-03).
11. `Gezinslid_has_Wens.Toch_toestaan` + `.Toelichting` — een vastgelegde
    uitzondering met reden, zie *Hoe streng is een dieetwens?*.

---

## 6. Inloggen, sessies en rechten

### Wachtwoorden

Wachtwoorden staan **nooit** leesbaar in de database. Ze worden gehasht met
bcrypt (cost 12, zie `backend/src/Services/Wachtwoord.php`) en gecontroleerd met
`password_verify()`.

Eisen aan een nieuw wachtwoord: minimaal 8 tekens, met letters én cijfers,
en anders dan het huidige.

### Inloggen

1. Gebruikersnaam **of** e-mailadres + wachtwoord.
2. Bestaat de gebruiker niet, of klopt het wachtwoord niet? Dan krijg je
   exact dezelfde melding. Zo kun je niet raden welke accounts bestaan.
3. Bij een fout wachtwoord wacht de server 0,3 seconde en telt hij
   `Mislukte_pogingen` op. Na 10 pogingen gaat het account op slot.
4. Bij een geslaagde login krijgt de browser een nieuwe sessie-ID als hij al een
   sessie had (`session_regenerate_id(true)`, tegen session fixation) en wordt
   `Laatste_login` bijgewerkt. Per login gaat er precies één sessiecookie mee.

### De sessie

In de sessie zit **alleen** het gebruikers-id. Rol en rechten worden bij elk
verzoek opnieuw uit de database gehaald. Word je geblokkeerd of krijg je een
andere rol, dan merk je dat meteen — ook met een open tabblad.

De cookie is `HttpOnly` (niet leesbaar vanuit JavaScript) en `SameSite=Strict`
(beschermt tegen CSRF). Na een uur zonder activiteit word je uitgelogd.

### Inloggen en uitloggen in beeld

Op het inlogscherm staat onder het formulier één regel: *"Nog geen account?
Vraag de directie om er een voor je aan te maken."* Er is bewust **geen**
registratiepagina. Een back-office met privacygevoelige klantgegevens mag geen
zelfregistratie hebben — dat zou Broken Access Control uit de OWASP Top 10 zijn.
Accounts worden alleen door de directie aangemaakt (US-GBR-06).

Uitloggen kan vanuit de zijbalk (knop met het woord "Uitloggen" onder je naam)
en vanaf Mijn account. Beide vragen eerst om bevestiging. In de eerste versie
was het alleen een pijltje-icoontje; tijdens het testen bleek dat niemand het
vond, dus is het een knop met tekst geworden.

### Rechten — drie lagen

| Laag | Wat het doet | Waar |
|---|---|---|
| Navigatie | pagina's waar je geen recht op hebt staan niet in het menu | `frontend/src/components/layout/AppSidebar.vue` |
| Pagina | open je zo'n pagina toch via de URL, dan krijg je "geen toegang" | routebewaking in `frontend/src/router/index.js` |
| **Server** | elke route heeft een vereiste permissie; de Router controleert die vóór de controller draait | `backend/routes/api.php` + `backend/src/Core/Router.php` |

De eerste twee zijn gemak. De derde is de echte beveiliging: knoppen verbergen
in de frontend is geen beveiliging, want de API is ook met `curl` te benaderen.

### Privacygevoelige klantgegevens

Adres, postcode, telefoonnummer en e-mailadres van klanten worden **door de
server weggelaten** uit het antwoord als je het recht `klant_beheren` niet hebt.
Een vrijwilliger ziet dus wél de gezinssamenstelling en de dieetwensen (die
heeft hij nodig om een pakket samen te stellen), maar niet de contactgegevens.
Het staat ook niet verstopt in de HTML: het is er gewoon niet.

### Een dashboard per rol

Iedereen komt na het inloggen op het dashboard (`/`), maar niemand ziet hetzelfde.
`DashboardController` bouwt het dashboard op uit **blokken die per permissie
worden toegevoegd** — dezelfde `Auth::can(...)`-controle als elk ander endpoint. Wat
je niet mag inzien zit niet in het antwoord, dus ook niet in de pagina.

| Rol | Cijfers | Lijsten | Takenlijst bovenaan |
|---|---|---|---|
| Directie | alles | alles | alles wat openstaat |
| Magazijnmedewerker | voorraad, onder minimum, THT, eerstvolgende levering | bij te bestellen producten, houdbaarheid, verwachte en recente leveringen | afvoeren en bijbestellen |
| Vrijwilliger | THT, pakketten deze week, nog samen te stellen, klaar voor uitgifte, klanten | houdbaarheid, gezinnen zonder pakket, klaar voor uitgifte, eigen concepten | gezinnen die nog wachten, pakketten om uit te geven |
| Administratie | klanten, medewerkers | wachtende aanvragen, weekgrafiek | aanvragen beoordelen |

De vrijwilliger ziet wel de houdbaarheid (dat bepaalt wat hij in een pakket mag
doen) maar niet de magazijncijfers: hij mag de voorraad corrigeren tijdens het
samenstellen, maar het magazijn beheren is niet zijn werk.

De takenlijst bovenaan is geen decoratie — het is letterlijk "wat moet ik nu
doen", met een link naar de pagina waar het kan.
---

## 7. De weekcyclus

```
maandag ──────────────────────────────> vrijdag
 leveringen inboeken (Leveranciers)      uitgifte (Uitgiftepunt)
 voorraad bijwerken (Producten)          legitimatie controleren
 pakketten samenstellen (Pakketten)      pakket uitgeven
```

* "Deze week" = vanaf maandag van de huidige week.
* Eén pakket per gezin per week (US-PAK-06); de server weigert een tweede.
* Een pakket doorloopt `Concept → Klaar → Uitgegeven`.
* Zodra een product in een pakket gaat, gaat het van de voorraad af.
  Haal je het er weer af, dan komt het terug.
* Een product dat over de THT is, verdwijnt uit de keuzelijst bij het samenstellen
  en kan ook via de API niet in een pakket worden gezet.
* Een product dat binnen drie dagen verloopt krijgt een label, zodat het juist
  als eerste wordt weggegeven.
* Een uitgegeven pakket is historie: niet meer te wijzigen en niet te verwijderen.
* Niet opgehaald? Dan boek je het pakket terug en staan de producten weer op voorraad.
---

## 8. Testen

### Snelle regressietest

`tests/api-test.js` benadert de API als vier rollen en doet 28 controles:
rechten per route, afgeschermde contactgegevens, beschermde historie, de
allergieblokkade, het dashboard per rol en de foutafhandeling. Alleen Node 18+ nodig.

```bash
node tests/api-test.js                                # tegen Docker (localhost:8080)
VB_URL=http://localhost:5173 node tests/api-test.js   # tegen de dev-server
```

Alles goed: `28 geslaagd, 0 mislukt` en afsluitcode 0.

### Volledige testuitvoering (testrapport)

`tests/testrapport/` bevat de 110 testcases uit het testplan, per functie
afzonderlijk, met testdata, stappen, verwacht resultaat en eis-ID's.
`run.js` voert ze uit in de browser (Playwright) en via de API, zet per module
de testdata terug en schrijft `resultaten.json` en screenshots weg.

```bash
npm install -g playwright && npx playwright install chromium   # eenmalig
VB_URL=http://localhost:5173 node tests/testrapport/run.js
```

Het testrapport (Word/PDF) is gemaakt op basis van deze uitkomsten.

### Zelf met curl kijken

```bash
curl -s -c v.txt -X POST -H 'Content-Type: application/json' \
  -d '{"gebruikersnaam":"a.degroot","wachtwoord":"voedselbank"}' http://localhost:8080/api/auth/login
curl -s -o /dev/null -w '%{http_code}\n' -b v.txt http://localhost:8080/api/gebruikers   # 403
curl -s -b v.txt http://localhost:8080/api/klanten        # 200, zonder adres/telefoon/e-mail
```

---

## 9. Bekende beperkingen

Dingen die we bewust niet hebben gebouwd, met de reden erbij.

**Eén THT-datum per product, niet per partij.** In het echt komt hetzelfde
product in meerdere leveringen binnen, elk met een eigen houdbaarheidsdatum.
Ons model heeft één `Houdbaar_tot` per product. Dat is genoeg om te waarschuwen
en om verlopen producten te blokkeren, maar niet om partijen uit elkaar te
houden. Een volgende versie zou een tabel `Partij` krijgen tussen `Levering` en
`Product`, met de voorraad per partij. Dat raakt de voorraadadministratie overal,
dus daar is dit project te kort voor.

**Geen magazijncapaciteit.** De casus stelt geen maximum aan de voorraad, dus
er is geen capaciteitsgrens meer. Het productenscherm toont alleen hoeveel er
ligt, over hoeveel producten dat verdeeld is, en wat er onder de minimumvoorraad
zit of over de datum is.

**Geen zelfregistratie.** Accounts worden alleen door de directie aangemaakt
(US-GBR-06). Een open registratiepagina zou Broken Access Control zijn: iedereen
met de URL kon dan bij privacygevoelige klantgegevens.

**Niet-opgehaalde pakketten worden verwijderd.** De producten gaan terug naar de
voorraad, maar er blijft niet staan dát een gezin niet is komen opdagen. Voor een
volgende versie zou een status `Niet opgehaald` beter zijn dan verwijderen.

**Een gezin opslaan vervangt alle gezinsleden.** `PUT /api/klanten/{id}`
gooit de gezinsleden weg en zet ze opnieuw neer, inclusief hun wensen en
uitzonderingen. Het eindresultaat klopt — wat op het scherm staat, staat daarna
in de database — maar de gezinsleden krijgen wel nieuwe id's. Omdat alleen
`Gezinslid_has_Wens` naar `Gezinslid` verwijst (en die rijen worden in dezelfde
transactie opnieuw geschreven) gaat er niets verloren. Netter zou zijn: per lid
kijken of het nieuw, gewijzigd of verwijderd is. Dat is een goed verbetervoorstel.
