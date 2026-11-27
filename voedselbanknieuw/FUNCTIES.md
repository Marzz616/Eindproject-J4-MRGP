# Alle functies in de code

Naslagwerk: elke klasse met al zijn functies, wat ze doen en wat ze
teruggeven. Hoe je de code aanpast en hoe je de site draaiend krijgt, staat in
**[DOCUMENTATIE.md](DOCUMENTATIE.md)**.

**Inhoud**

1. [Controllers](#1-controllers-apphttpcontrollers) — wat er gebeurt bij een verzoek
2. [Middleware](#2-middleware-apphttpmiddleware) — de rechtencontrole
3. [Services](#3-services-appservices) — regels van de voedselbank
4. [Models](#4-models-appmodels) — alle SQL
5. [Exceptions en hulpklassen](#5-exceptions-en-hulpklassen)
6. [Instellen van Laravel](#6-instellen-van-laravel)
7. [De frontend](#7-de-frontend-resourcesjs)

> **Lezen:** `naam(parameters): wat het teruggeeft` — daarachter de uitleg.
> `?array` betekent "een array of `null`". `void` betekent dat de functie niets
> teruggeeft.

---

## 1. Controllers (`app/Http/Controllers`)

Een controller leest het verzoek, controleert de invoer en bepaalt wat er
teruggaat. Geeft hij een array terug, dan maakt Laravel daar JSON van.
Elke controller heeft onderaan een eigen `valideer()`-methode (privé) die alle
veldfouten tegelijk verzamelt.

### AuthController — inloggen en eigen account

| Functie | Doet |
|---|---|
| `me(Request $request): array` | Wie is er ingelogd? Geeft `{ingelogd: false}` of `{ingelogd: true, gebruiker: {...}}`. De Vue-app vraagt dit bij het opstarten. |
| `login(Request $request): array` | Controleert gebruikersnaam en wachtwoord. Bij een onbekende naam én bij een fout wachtwoord komt bewust dezelfde melding, anders kun je uitproberen welke accounts bestaan. Een geblokkeerd account krijgt 403, na 10 mislukte pogingen volgt 429. Daarna wordt de sessie gestart. |
| `logout(Request $request): array` | Maakt de sessie leeg. |
| `rechten(Request $request): array` | De eigen rechten met leesbare namen, voor het scherm "Mijn account". |
| `wachtwoord(Request $request): array` | Eigen wachtwoord wijzigen (US-GBR-04). Het huidige moet kloppen, het nieuwe moet aan de regels voldoen, niet gelijk zijn aan het oude en twee keer hetzelfde zijn ingevuld. |

### DashboardController — het startscherm

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Bouwt het dashboard voor de rol van de ingelogde gebruiker: cijferkaarten, lijsten, grafieken en een takenlijst met links naar het werk dat openstaat. Wat iemand niet mag zien komt ook niet uit de server (US-SEC-02). |

### GebruikerController — accounts (US-GBR)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle gebruikers, de rollen en welke rechten bij welke rol horen. |
| `store(Request $request): JsonResponse` | Nieuw account aanmaken. Gebruikersnaam en e-mail moeten uniek zijn; het wachtwoord wordt gehasht. Status 201. |
| `update(Request $request): array` | Naam, gebruikersnaam, e-mail of rol wijzigen. |
| `actief(Request $request): array` | Blokkeren of weer vrijgeven. Je kunt jezelf niet blokkeren en het laatste actieve directie-account blijft bestaan. |
| `reset(Request $request): array` | De beheerder zet een nieuw wachtwoord; de teller van mislukte pogingen gaat terug naar 0. |

### KlantController — gezinnen (US-KLT)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle klanten met gezinsgrootte, dieetwensen, status en of er deze week al een pakket is. Voor een vrijwilliger worden de contactgegevens afgeschermd (AVG, TEC-4). |
| `store(Request $request): JsonResponse` | Nieuw gezin registreren, inclusief gezinsleden en hun wensen. Status 201. |
| `update(Request $request): array` | Gezin wijzigen. De directie wijzigt alles; een vrijwilliger alleen de gezinssamenstelling en de dieetwensen. |
| `destroy(Request $request): array` | Gezin verwijderen — alleen als er geen pakkethistorie is, anders 409 met reden `heeft_historie`. |
| `status(Request $request): array` | Een aanmelding accepteren of afwijzen (US-KLT-12). `null` = wacht nog op beoordeling. |
| `pakketten(Request $request): array` | De pakketgeschiedenis van dit gezin, met de inhoud per pakket (US-KLT-13/14). |
| `wensen(Request $request): array` | De keuzelijst met dieetwensen en allergieën. |
| `wensToevoegen(Request $request): JsonResponse` | Een eigen wens toevoegen (US-KLT-10). Bestaat de naam al, dan komt die bestaande terug in plaats van een dubbele. |

### LeverancierController — leveranciers en leveringen (US-LEV)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle leveranciers met hun leveringsgeschiedenis en de eerstvolgende geplande levering. |
| `store(Request $request): JsonResponse` | Leverancier toevoegen. Naam moet uniek zijn, e-mail geldig. Status 201. |
| `update(Request $request): array` | Gegevens wijzigen, inclusief de volgende leverdatum en -tijd (LEV-4). |
| `actief(Request $request): array` | Op non-actief zetten of weer activeren: de zachte variant van verwijderen, voor leveranciers met historie. |
| `destroy(Request $request): array` | Verwijderen — alleen zonder leveringshistorie, anders 409 met het advies "zet hem op non-actief". |
| `levering(Request $request): JsonResponse` | Een levering inboeken. De voorraad gaat automatisch omhoog en de geplande leverdatum wordt opgeschoond. Alles in één transactie. Status 201. |

### ProductController — voorraad (US-VRD)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle producten met categorie, voorraad, houdbaarheidsdatum en waarschuwingen (bijna over datum, onder minimum). Ook de vrijwilliger mag dit zien. |
| `store(Request $request): JsonResponse` | Product toevoegen. Controleert op een unieke naam en een geldige, unieke EAN-13 (US-VRD-09/10). Status 201. |
| `update(Request $request): array` | Productgegevens wijzigen. |
| `voorraad(Request $request): array` | Het aantal corrigeren: een vast getal (`aantal`) of erbij/eraf (`delta`). Nooit onder nul. |
| `afvoeren(Request $request): array` | Producten afvoeren met een reden; de regel blijft bewaard voor het verspillingsrapport (US-RAP-04). |
| `destroy(Request $request): array` | Verwijderen — alleen als het product nog nooit in een pakket, levering of afvoer zat (US-VRD-05), anders 409. |

### CategorieController — productcategorieën (US-VRD-11)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle categorieën met het aantal producten erin. |
| `store(Request $request): JsonResponse` | Categorie toevoegen. Status 201. |
| `update(Request $request): array` | Naam, afkorting of omschrijving wijzigen. |
| `destroy(Request $request): array` | Verwijderen — alleen als er geen producten meer in zitten, anders 409 met reden `heeft_producten`. |
| `verplaatsen(Request $request): array` | Alle producten naar een andere categorie zetten, zodat de oude leeg is en weg kan. |

### PakketController — pakketten samenstellen (US-PAK)

| Functie | Doet |
|---|---|
| `samenstellen(Request $request): array` | Het startscherm: welke gezinnen deze week nog een pakket nodig hebben plus de hele voorraad per categorie. |
| `klant(Request $request): array` | Eén gezin: samenstelling, dieetprofiel, adviesaantal producten en het pakket waar al aan gewerkt wordt (US-PAK-02/03). |
| `index(Request $request): array` | Overzicht van alle pakketten met hun status. |
| `show(Request $request): array` | Eén pakket met de inhoud; ook gebruikt voor de pakbon en de sticker (US-PAK-10/11). |
| `store(Request $request): JsonResponse` | Nieuw pakket opslaan als concept of klaarzetten. Status 201. |
| `update(Request $request): array` | Een bestaand concept of klaargezet pakket wijzigen. Een uitgegeven pakket kan niet meer gewijzigd worden. |
| `destroy(Request $request): array` | Pakket verwijderen; de producten gaan terug in de voorraad. |

Bij opslaan en wijzigen gebeurt er het meeste van de applicatie:

* producten die botsen met een **strenge** wens (allergie) of die **over de
  houdbaarheidsdatum** zijn kunnen er niet in — ook niet met `forceer`
  (antwoord 409, reden `geblokkeerd`);
* producten die botsen met een **gewone** wens geven 409 met reden `conflict`;
  de gebruiker kan dan bewust bevestigen met `forceer: true`;
* alleen het **verschil** met de oude inhoud moet nog op voorraad zijn;
* het pakketnummer en de datum worden automatisch gezet (US-PAK-04/05);
* alles gebeurt in één **transactie**, zodat pakket en voorraad nooit uit
  elkaar lopen.

### UitgifteController — de vrijdag (US-WRK)

| Functie | Doet |
|---|---|
| `wachtrij(Request $request): array` | Alle pakketten van deze week, gesplitst in concept, klaar en uitgegeven. |
| `zoek(Request $request): array` | Zoekt een gezin op klantnummer of naam ("scan het pasje of typ de cliënt-ID"). Niets gevonden: 404 met een nette melding. |
| `show(Request $request): array` | De pakketkaart van één pakket. |
| `identiteit(Request $request): array` | Legt vast met welk document is gelegitimeerd: paspoort, rijbewijs of ID-kaart (US-WRK-01). Het documentnummer wordt bewust niet opgeslagen (AVG). |
| `uitgeven(Request $request): array` | Het pakket uitgeven. Zonder vastgelegde legitimatie kan dat niet. Datum, tijd en wie het uitgaf worden vastgelegd. |
| `nietOpgehaald(Request $request): array` | Het pakket is niet opgehaald: alles terug in de voorraad. |

### RapportageController — managementinformatie (US-RAP)

| Functie | Doet |
|---|---|
| `index(Request $request): array` | Alle kerncijfers en grafieken voor het rapportagescherm: uitgegeven pakketten, waarde, verspilling, top-10 producten, gezinsgrootteverdeling. |
| `maand(Request $request): array` | Het maandoverzicht, te filteren op maand en jaar: `soort=categorie` geeft per productcategorie, product en leverancier (RAP-1), `soort=postcode` geeft per postcode en categorie (RAP-2). |
| `export(Request $request): Response` | Dezelfde cijfers als CSV-bestand voor Excel (US-RAP-06). |

---

## 2. Middleware (`app/Http/Middleware`)

| Klasse | Functie | Doet |
|---|---|---|
| `Ingelogd` | `handle($request, $next)` | Laat alleen ingelogde gebruikers door, anders 401 met `login_nodig: true`. In de routes heet hij `ingelogd`. |
| `Permissie` | `handle($request, $next, ...$permissies)` | Controleert of de gebruiker minstens één van de genoemde rechten heeft, anders 403. In de routes: `mag:klant_inzien` of `mag:product_beheren,voorraad_corrigeren`. |

---

## 3. Services (`app/Services`)

### AuthService — wie is ingelogd en wat mag die

| Functie | Doet |
|---|---|
| `user(): ?array` | De ingelogde gebruiker met zijn permissies, of `null`. Leest rol en rechten **bij elk verzoek opnieuw** uit de database, zodat blokkeren of een rolwijziging meteen werkt. Is het account intussen geblokkeerd of verwijderd, dan wordt de sessie vernietigd. |
| `id(): int` | Het id van de ingelogde gebruiker, of 0. |
| `can(string $permissie): bool` | Heeft de ingelogde gebruiker dit recht? Handig in een controller om een deel van het antwoord weg te laten. |
| `requireLogin(): array` | Geeft de gebruiker terug, of gooit een 401. |
| `requirePermission(string ...$permissies): array` | Minstens één van deze rechten is nodig, anders 403. |
| `refresh(): void` | Vergeet de onthouden gebruiker; nodig na inloggen en uitloggen. |
| `publicProfile(array $g): array` | Wat de frontend over de gebruiker mag weten: id, naam, rol, rechten en toegestane pagina's — nooit de wachtwoordhash. |

### Sessie — de sessie van Laravel

| Functie | Doet |
|---|---|
| `gebruikerId(): ?int` | Het gebruikers-id uit de sessie, of `null`. |
| `login(int $gebruikerId): void` | Zet het id in de sessie en geeft de sessie een **nieuw ID** (bescherming tegen session fixation). |
| `destroy(): void` | Maakt de sessie leeg en geeft een nieuw, leeg sessie-ID. |

### Pages — welke pagina's bij welke rechten horen

| Functie | Doet |
|---|---|
| `allowedFor(array $permissies): array` | Geeft de namen van de pagina's die iemand met deze rechten mag openen. Daarmee bouwt de Vue-app het menu en bewaakt de Vue-router de pagina's. |

### DieetControle — allergieën en wensen (de kern van US-PAK-03)

| Functie | Doet |
|---|---|
| `profiel(int $klantId): array` | Bouwt het dieetprofiel van een gezin, gegroepeerd per conflict-tag (`noten`, `gluten`, `varkensvlees`, …). Per tag: welke wens, of hij **streng** is (allergie) of gewoon, wie in het gezin het betreft, en of er een uitzondering met reden is vastgelegd. |
| `botsingen(?string $allergenen, array $profiel): array` | Welke wensen uit het profiel botsen met de kenmerken van dit product? |
| `isVerlopen(?string $tht): bool` | Is de houdbaarheidsdatum verstreken? |
| `controleer(array $producten, array $profiel): array` | Controleert alle producten van een pakket in één keer en geeft twee lijsten terug: `geblokkeerd` (mag echt niet: strenge allergie zonder uitzondering, of over datum) en `waarschuwing` (gewone wens, mag met bevestiging). |

### Weekcyclus — de week van de voedselbank

| Functie | Doet |
|---|---|
| `weekStart(): string` | De maandag van deze week als `jjjj-mm-dd`. Pakketten gaan per week: samenstellen in de week, uitgifte op vrijdag. |
| `advies(int $gezinsgrootte): int` | Het adviesaantal producten: 8 voor één persoon, plus 4 per extra gezinslid (US-PAK-04). |

### Wachtwoord — wachtwoordregels op één plek

| Functie | Doet |
|---|---|
| `voldoet(string $wachtwoord): bool` | Voldoet het wachtwoord aan de eisen? De eis zelf staat in de constante `Wachtwoord::EIS`, zodat de melding en de controle nooit uit elkaar lopen. |
| `hash(string $wachtwoord): string` | Hasht met bcrypt (cost 12): traag genoeg om raden duur te maken. |

### Settings — instellingen uit de database

| Functie | Doet |
|---|---|
| `get(string $sleutel, string $standaard = ''): string` | Haalt een waarde uit de tabel `Instelling` en onthoudt hem binnen het verzoek. |
| `int(string $sleutel, int $standaard): int` | Hetzelfde, maar als getal. Bijvoorbeeld `tht_waarschuwing_dagen`. |

---

## 4. Models (`app/Models`)

Alle SQL staat hier. De modellen erven van `Basis`.

### Basis — de basisklasse (beschermd, voor de modellen zelf)

| Functie | Doet |
|---|---|
| `all(string $sql, array $params = []): array` | Alle rijen van een query, als arrays. |
| `one(string $sql, array $params = []): ?array` | De eerste rij, of `null`. |
| `value(string $sql, array $params = []): mixed` | Eén waarde: de eerste kolom van de eerste rij. |
| `column(string $sql, array $params = []): array` | Eén kolom als lijst. |
| `execute(string $sql, array $params = []): int` | INSERT, UPDATE of DELETE; geeft het aantal geraakte rijen. |
| `lastId(): int` | Het id van de zojuist toegevoegde rij. |
| `plaatshouders(array $waarden): string` | Maakt `?,?,?` voor een `IN (...)`-lijst. |

De waarden gaan altijd apart mee als `?` (prepared statements), dus
SQL-injectie is niet mogelijk.

### Gebruiker

| Functie | Doet |
|---|---|
| `metRol(int $id): ?array` | Eén gebruiker met de naam van zijn rol. |
| `voorLogin(string $naam): ?array` | Zoekt op gebruikersnaam óf e-mailadres, voor het inloggen. |
| `permissiesVanRol(int $rolId): array` | De permissie-codes van een rol. |
| `permissieNamen(array $codes): array` | De leesbare namen bij die codes. |
| `lijst(): array` | Alle gebruikers voor het beheerscherm. |
| `rollen(): array` / `rolNaam(int $rolId): ?string` | De rollen, en de naam van één rol. |
| `permissies(): array` / `koppelingRolPermissie(): array` | Alle rechten en welke bij welke rol horen. |
| `dubbel(string $gnaam, string $email, int $behalveId): array` | Bestaande accounts met dezelfde gebruikersnaam of e-mail. |
| `andereActieveDirectie(int $behalveId): int` | Hoeveel actieve directieleden er nog zijn naast deze — zo blijft er altijd één over. |
| `maak(...): int` / `wijzig(...): void` | Account aanmaken of wijzigen. |
| `hash(int $id): string` / `zetWachtwoord(int $id, string $hash): bool` | De hash ophalen en een nieuw wachtwoord zetten; de teller mislukte pogingen gaat naar 0. |
| `zetActief(int $id, bool $actief): void` | Blokkeren of vrijgeven. |
| `mislukteLogin(int $id): void` / `geslaagdeLogin(int $id): void` | Tellen de pogingen en leggen de laatste login vast. |
| `aantal(bool $actief): int` | Aantal actieve of geblokkeerde accounts, voor het dashboard. |

### Klant

| Functie | Doet |
|---|---|
| `lijst(): array` | Alle klanten met gezinsgrootte, laatste uitgifte en of er deze week al een pakket is. |
| `vind(int $id): ?array` | Eén klant. |
| `ledenVan(array $klantIds): array` | De gezinsleden van meerdere klanten in één query (in plaats van één query per klant). |
| `wensenVan(array $klantIds): array` | De dieetwensen per gezinslid, ook voor meerdere klanten tegelijk. |
| `leden(int $klantId): array` | De gezinsleden van één klant, met leeftijd en type (volwassene, kind, baby). |
| `gezinsgrootte(int $klantId): int` | Het aantal personen. |
| `wensNamen(int $klantId): array` | Alle wensnamen in het gezin, zonder dubbelen. |
| `aantalPakketten(int $klantId): int` | Hoeveel pakketten dit gezin heeft gehad. |
| `maak(array $v): int` / `wijzigContact(int $id, array $v): void` | Gezin aanmaken of de contactgegevens wijzigen. |
| `vervangLeden(int $klantId, array $leden): void` | Vervangt de hele gezinssamenstelling inclusief wensen. Een uitzondering ("het gezin wil dit tóch") telt alleen mee als er een reden bij staat. |
| `zetStatus(int $id, ?bool $geaccepteerd): void` | `null` = wacht op beoordeling, `true` = geaccepteerd, `false` = afgewezen. |
| `verwijder(int $id): void` | Gezinsleden en wensen gaan mee via `ON DELETE CASCADE`. |
| `pakketten(int $klantId): array` | De pakketgeschiedenis, nieuwste eerst. |
| `aantalMetStatus(?bool $geaccepteerd): int` | Aantallen voor het dashboard. |
| `wachtendOpBeoordeling(int $limiet): array` | Aanvragen die nog beoordeeld moeten worden, oudste eerst. |

### Leverancier

| Functie | Doet |
|---|---|
| `lijst(): array` / `vind(int $id): ?array` | Overzicht en één leverancier. |
| `leveringen(): array` | Alle leveringen met hun inhoud in één query. |
| `naamBestaat(string $naam, int $behalveId): bool` | Controle op een dubbele naam. |
| `maak(array $v): int` / `wijzig(int $id, array $v): void` | Toevoegen of wijzigen. |
| `zetActief(int $id, bool $actief): int` | Op non-actief zetten of activeren. |
| `aantalLeveringen(int $id): int` | Of er historie is (bepaalt of verwijderen mag). |
| `verwijder(int $id): void` | Verwijderen. |
| `boekLevering(int $leverancierId, string $datum, ?string $tijd, int $gebruikerId, array $regels): int` | Boekt de levering, hoogt per regel de voorraad op en schoont de geplande leverdatum op. Moet binnen een transactie worden aangeroepen. |
| `verwacht(int $limiet): array` | Leveranciers met een geplande levering vanaf vandaag (voor het dashboard). |
| `recenteLeveringen(int $limiet): array` | De laatste leveringen met het grootste product erin. |

### Product

| Functie | Doet |
|---|---|
| `lijst(): array` | Alle producten met categorie, voorraad en houdbaarheidsdatum. |
| `voorSamenstellen(): array` | De producten voor het samenstelscherm, gegroepeerd op categorie. |
| `vind(int $id): ?array` / `vindMeerdere(array $ids): array` | Eén product, of meerdere in één query. |
| `totaleVoorraad(): int` / `aantalSoorten(): int` | Cijfers voor het dashboard. |
| `dubbel(string $naam, string $ean, int $behalveId): array` | Producten met dezelfde naam of EAN (US-VRD-09/10). |
| `maak(array $v): int` / `wijzig(int $id, array $v): void` | Toevoegen of wijzigen. |
| `zetVoorraad(int $id, int $aantal): void` | De voorraad op een vast getal zetten. |
| `wijzigVoorraad(int $id, int $delta): void` | De voorraad ophogen (+) of verlagen (−). |
| `voorraadVoorUpdate(int $id): ?array` | De voorraad ophalen met een rij-lock binnen een transactie, zodat twee vrijwilligers niet tegelijk hetzelfde product kunnen uitgeven. |
| `historie(int $id): array` | In hoeveel pakketten, leveringen en afvoerregels dit product voorkomt — bepaalt of verwijderen mag. |
| `verwijder(int $id): void` | Verwijderen. |
| `voerAf(int $id, int $aantal, string $reden, ?string $toelichting, int $gebruikerId): void` | Afvoeren; de regel blijft bewaard voor het verspillingsrapport. |
| `bijnaOverDatum(int $dagen, int $limiet)` · `aantalBijnaOverDatum(int $dagen)` · `overDatum(int $limiet)` · `aantalOverDatum()` · `onderMinimum(int $limiet)` · `aantalOnderMinimum()` | De signaleringen voor het dashboard en het productoverzicht. |

### Categorie

| Functie | Doet |
|---|---|
| `lijst(): array` | Alle categorieën met het aantal producten. |
| `namen(): array` | Alleen de namen, voor keuzelijsten. |
| `vind(int $id): ?array` / `naamBestaat(string $naam, int $behalveId): bool` | Opzoeken en controleren op dubbele namen. |
| `maak(...)` · `wijzig(...)` · `verwijder(int $id)` | Toevoegen, wijzigen, verwijderen. |
| `verplaatsProducten(int $van, int $naar): int` | Zet alle producten naar een andere categorie en geeft het aantal terug. |

### Pakket

| Functie | Doet |
|---|---|
| `klantenVoorWeek(string $maandag): array` | Geaccepteerde klanten met de stand van deze week, voor het samenstelscherm. |
| `vanDezeWeek(int $klantId, string $maandag): ?array` | Het pakket van dit gezin in deze week, of `null`. |
| `anderPakketDezeWeek(int $klantId, string $maandag, int $behalveId): bool` | Heeft dit gezin deze week al een ánder pakket? (US-PAK-06) |
| `lijst(): array` / `vind(int $id): ?array` / `status(int $id): ?string` | Overzicht, één pakket met klant en samensteller, en alleen de status. |
| `inhoud(int $id): array` | De inhoud van één pakket, op categorie gesorteerd. |
| `aantallen(int $id): array` | De inhoud als `productId => aantal`, voor het rekenwerk met de voorraad. |
| `regelsVan(array $pakketIds): array` | De regels van meerdere pakketten tegelijk. |
| `maak(int $klantId, string $status, int $gebruikerId): int` | Nieuw pakket; het nummer loopt automatisch door. |
| `zetStatus(int $id, string $status): void` | Concept ⇄ Klaar. |
| `vervangInhoud(int $id, array $oud, array $nieuw): void` | Vervangt de inhoud en verwerkt het verschil in de voorraad: het oude gaat terug, het nieuwe gaat eraf (US-PAK-07/08). |
| `verwijderEnTerugboeken(int $id): void` | Pakket weg, producten terug in de voorraad. |
| `wachtrij(string $maandag): array` | Alle pakketten van deze week voor het uitgiftescherm. |
| `zoekKlanten(string $term): array` | Zoekt op naam of klantnummer; `8`, `K-008`, `k008` en `El Hassani` werken allemaal. |
| `relevantVoorUitgifte(int $klantId, string $maandag): ?int` | Welk pakket de balie moet zien: eerst Klaar, dan Uitgegeven, dan Concept. |
| `legitimeer(int $id, string $soort): bool` | Legt de legitimatie vast; lukt alleen bij een pakket dat nog niet is uitgegeven. |
| `geefUit(int $id, int $gebruikerId): void` | Zet de status op Uitgegeven met datum, tijd en wie het deed. |
| `aantalVanaf(...)` · `aantalMetStatus(...)` · `gezinnenZonderPakket(...)` · `conceptenVan(...)` · `klaarVoorUitgifte(...)` | De cijfers en lijstjes voor het dashboard. |

### Wens — dieetwensen en allergieën

| Functie | Doet |
|---|---|
| `lijst(): array` | Alle wensen voor de keuzelijst. |
| `opNaam(string $naam): ?array` | Zoekt een wens op naam, om dubbelen te voorkomen. |
| `maak(string $naam, bool $streng, ?string $tag): int` | Nieuwe wens. Zonder conflict-tag is de wens alleen informatief en geeft hij nergens een waarschuwing. |
| `conflictenVanGezin(int $klantId): array` | Alle wensen van een gezin die met producten kunnen botsen, per conflict-tag. `DieetControle` maakt daar het profiel van. |
| `perLid(int $klantId): array` | De wensnamen per gezinslid. |

### Rapportage — alleen tellen en optellen

| Functie | Doet |
|---|---|
| `uitgegevenDitJaar(): int` · `uitgegevenVorigJaarTotNu(): int` | Uitgegeven pakketten dit jaar en dezelfde periode vorig jaar, voor de groei. |
| `pakketWaarde(): array` | Aantal uitgegeven pakketten en hun totale winkelwaarde (US-RAP-02). |
| `perWeek(): array` | Uitgegeven pakketten per week, laatste vijf weken (US-RAP-01). |
| `geleverdPerDag(): array` · `uitgegaanPerDag(): array` | De twee lijnen van de grafiek: wat er binnenkwam en wat eruit ging. |
| `stuksInUitgegevenPakketten(): int` · `afvoerPerReden(): array` | Voor het verspillingspercentage. |
| `gezinsgrootteVerdeling(): array` | Geaccepteerde gezinnen ingedeeld naar grootte (US-RAP-05). |
| `topProducten(int $limiet): array` | De meest uitgedeelde producten. |
| `geleverdInMaand(int $maand, int $jaar): array` | RAP-1: geleverd per categorie, product en leverancier in een maand. |
| `uitgegevenPerProductInMaand(int $maand, int $jaar): array` | Wat er in die maand per product is uitgegeven. |
| `uitgegevenPerPostcode(int $maand, int $jaar): array` · `postcodeTotalen(int $maand, int $jaar): array` | RAP-2: per postcode en categorie, plus de totalen per postcode. |

---

## 5. Exceptions en hulpklassen

### HttpException (`app/Exceptions`)

Een fout die als nette JSON naar de browser gaat.

```php
throw new HttpException(409, 'Dit pakket is al uitgegeven.', ['reden' => 'al_uitgegeven']);
```

| Functie | Doet |
|---|---|
| `__construct(int $status, string $melding, array $extra = [])` | De statuscode, de melding en eventueel extra velden. |
| `status(): int` | De HTTP-statuscode. |
| `body(): array` | Het antwoord: `{"fout": "…"}` plus de extra velden. |

### ValidationException (`app/Exceptions`)

Invoer klopt niet: status 422 met per veld een melding, die de frontend onder
het juiste invoerveld zet.

| Functie | Doet |
|---|---|
| `__construct(array $fouten)` | Een array `veldnaam => melding`. |
| `body(): array` | `{"fouten": {"email": "Vul een geldig e-mailadres in."}}` |
| `throwIf(array $fouten): void` | Gooit alleen als er echt fouten in staan. |

### Csv (`app/Support`)

| Functie | Doet |
|---|---|
| `download(string $bestandsnaam, array $kop, array $rijen): Response` | Maakt een CSV-download met puntkomma's als scheidingsteken en een BOM vooraan, zodat Excel in het Nederlands de kolommen goed splitst en de accenten goed toont. |

### SqlBestand (`app/Support`)

| Functie | Doet |
|---|---|
| `voerUit(string $pad): int` | Voert een `.sql`-bestand uit op de ingestelde database en geeft het aantal uitgevoerde statements terug. De regels `CREATE DATABASE` en `USE` worden overgeslagen, want welke database gebruikt wordt bepaalt `.env`. Gebruikt door de migratie en de seeder. |

---

## 6. Instellen van Laravel

### `bootstrap/app.php`

Hier staat hoe Laravel is ingesteld:

* **routes** — `routes/web.php` voor de pagina's, `routes/api.php` voor de API
  (met het voorvoegsel `/api`);
* **middleware** — de API krijgt de sessie-middleware, zodat `/api/...` weet
  wie er is ingelogd; `ingelogd` en `mag` krijgen hun korte naam;
* **foutafhandeling** — `HttpException` wordt JSON met de juiste status,
  404 en 405 krijgen een Nederlandse melding, en elke andere fout wordt
  `{"fout": "Er ging iets mis op de server."}` met status 500. De technische
  details gaan naar `storage/logs/laravel.log` en nooit naar de browser.
  Een 401, 403 of 422 is geen storing en komt niet in het logboek.

### `app/Providers/AppServiceProvider.php`

| Functie | Doet |
|---|---|
| `boot()` | Zet de databasetijdzone goed en voegt de macro's hieronder toe. |
| `zetDatabaseTijdzone()` (privé) | Zet de tijdzone van MySQL gelijk aan die van PHP (Europe/Amsterdam). Anders klopt `CURDATE()` in de query's niet met "vandaag" in de applicatie. |

De macro's breiden de `Request` van Laravel uit, zodat de controllers overal op
dezelfde manier waarden uitlezen:

| Macro | Doet |
|---|---|
| `$request->tekst('naam')` | Tekst, zonder spaties aan de randen. |
| `$request->getal('aantal')` | Heel getal. |
| `$request->vink('actief')` | Ja/nee. |
| `$request->lijst('regels')` | Een array (bijvoorbeeld de regels van een pakket). |
| `$request->routeId()` | Het `{id}` uit de URL, als getal. |
| `$request->routeWaarde('rapport')` | Een andere waarde uit de URL. |

---

## 7. De frontend (`resources/js`)

### `app.js`

Start Vue, koppelt Pinia (gedeelde gegevens), de router en de stijlen, en hangt
alles in `<div id="app">` van `resources/views/app.blade.php`.

### `router/index.js`

Welke URL bij welk scherm hoort. Elke route heeft `meta.pagina`: de naam van de
pagina waarvoor je rechten nodig hebt. Vóór elke navigatie controleert de
router of je bent ingelogd (anders naar `/login?terug=…`) en of je rol de
pagina mag openen (anders naar `/geen-toegang`). De server controleert dat
daarna nog eens per API-verzoek.

De routes: `login`, `dashboard` (`/`), `pakketten`, `uitgifte`, `producten`,
`leveranciers`, `klanten`, `rapportages`, `maandoverzicht`, `gebruikers`,
`account`, `pakbon/:id`, `geen-toegang` en `niet-gevonden`.

### `services/http.js` — alle communicatie met de API

| Functie | Doet |
|---|---|
| `http.get/post/put/patch/delete(pad, body)` | Stuurt het verzoek met de sessiecookie en geeft het JSON-antwoord terug. |
| `http.url(pad)` | De volledige URL, voor downloads via een gewone link (de CSV). |
| `query(params)` | Bouwt `?a=1&b=2`; lege waarden worden weggelaten. |
| `onUnauthorized(handler)` | De router geeft hier door wat er moet gebeuren als de sessie verlopen is. |
| `ApiError` | De fout die wordt gegooid bij een mislukt verzoek, met `status`, `fouten` (veldfouten uit een 422) en `reden` (bijvoorbeeld `heeft_historie`), zodat een scherm daar gericht op kan reageren. |

### `services/*Api.js` — per onderdeel één bestand

Elke functie hieronder is één regel die `http` aanroept. Ze staan los van de
schermen, zodat een URL maar op één plek staat.

| Bestand | Functies |
|---|---|
| `authApi` | `ik()`, `login(gebruikersnaam, wachtwoord)`, `logout()`, `rechten()`, `wachtwoord(huidig, nieuw, herhaal)` |
| `dashboardApi` | `ophalen()` |
| `gebruikersApi` | `lijst()`, `opslaan(g)`, `zetActief(id, actief)`, `resetWachtwoord(id, wachtwoord)` |
| `klantenApi` | `lijst()`, `opslaan(k)`, `verwijderen(id)`, `zetStatus(id, geaccepteerd)`, `pakketten(id)`, `wensen()`, `wensToevoegen(wens)` |
| `leveranciersApi` | `lijst()`, `opslaan(l)`, `zetActief(id, actief)`, `verwijderen(id)`, `boekLevering(levering)` |
| `productenApi` | `lijst()`, `opslaan(p)`, `verwijderen(id)`, `zetVoorraad(id, aantal)`, `afvoeren(id, afvoer)` |
| `categorieenApi` | `lijst()`, `opslaan(c)`, `verwijderen(id)`, `verplaatsProducten(van, naar)` |
| `pakkettenApi` | `samenstellen()`, `klant(id)`, `lijst()`, `ophalen(id)`, `opslaan(p)`, `verwijderen(id)` |
| `uitgifteApi` | `wachtrij()`, `zoek(q)`, `kaart(id)`, `legitimatie(id, legitimatie)`, `uitgeven(id)`, `nietOpgehaald(id)` |
| `rapportagesApi` | `overzicht()`, `maand(soort, maand, jaar)`, `csvUrl(rapport)` |

`opslaan()` kijkt zelf of er een `id` is: zonder id wordt het een POST (nieuw),
met id een PUT (wijzigen).

### `stores/auth.js` — wie is er ingelogd (Pinia)

| Onderdeel | Doet |
|---|---|
| `gebruiker` | De ingelogde gebruiker, of `null`. |
| `ingelogd` | Of er iemand is ingelogd. |
| `mag(permissie)` | Heeft de gebruiker dit recht? Hiermee verbergen de schermen knoppen. |
| `magPagina(pagina)` | Mag de gebruiker deze pagina openen? Hiermee bouwt de zijbalk het menu. |
| `laad(opnieuw = false)` | Haalt de ingelogde gebruiker op bij het opstarten. |
| `login(gebruikersnaam, wachtwoord)` | Logt in en onthoudt de gebruiker. |
| `logout()` | Logt uit bij de server en vergeet de gebruiker. |
| `vergeet()` | Vergeet de gebruiker zonder de server te bellen (als de sessie al verlopen is). |

### `composables/` — herbruikbare logica

| Bestand | Geeft terug |
|---|---|
| `useToast()` | `goed(tekst)`, `fout(tekst)`, `info(tekst)` — de meldingen rechtsonder in beeld. |
| `useDialog()` | `bevestig(opties)` geeft een `Promise<boolean>` (ja/nee-venster) en `melding(opties)` toont alleen een mededeling. |
| `useFormErrors()` | `fouten`, `verwerk(e)` en `wis()`: zet de veldfouten uit een 422-antwoord onder de juiste invoervelden. Daarnaast `meldFout(e)` voor een losse foutmelding. |
| `usePakketSamenstellen()` | De hele logica van het samenstelscherm: `kiesKlant`, `voegToe`, `wijzig`, `opslaan`, `verwijder`, plus `botst(product)` en `geblokkeerd(product)` die laten zien of een product botst met een wens of niet mag. |
| `useUitloggen()` | Uitloggen mét bevestigingsvenster; gebruikt door de zijbalk en "Mijn account". |
| `usePageSubtitle()` | De ondertitel in de bovenbalk per pagina. |

### `utils/format.js` — alles netjes tonen

| Functie | Doet |
|---|---|
| `datum(s)` / `datumKort(s)` | `2026-11-26` wordt `Vandaag`, `Gisteren`, `Morgen` of `26 nov 2026`; `datumKort` geeft altijd `26-11-2026`. |
| `tijdstip(s)` | Datum met tijd (`Vandaag, 14:05`), of `Nog nooit` als er niets is. |
| `geld(n)` | `12.5` wordt `€ 12,50`. |
| `getal(n)` | `4679` wordt `4.679`. |
| `klantnummer(id)` | `2` wordt `K-002`. |
| `personen(n)` | `1 persoon` of `5 personen`. |
| `initialen(naam)` | De eerste letter, voor het rondje bij een naam. |
| `vandaagIso()` | De datum van vandaag in Nederlandse tijd als `jjjj-mm-dd`. |
| `MAANDEN_VOL` | De maandnamen, voor de keuzelijst bij het maandoverzicht. |

### Componenten

| Map | Wat erin staat |
|---|---|
| `views/<onderdeel>/` | De schermen; één bestand per pagina. |
| `components/<onderdeel>/` | De onderdelen van dat scherm, bijvoorbeeld `KlantDrawer.vue` (het zijpaneel om een gezin te bewerken) en `PakketKolom.vue` (de inhoud van het pakket). |
| `components/ui/` | Bouwstenen die overal terugkomen: `FormField`, `SideDrawer`, `BaseModal`, `SearchInput`, `EmptyState`, `ExpandableRow`, `ToastHost`, `DialogHost`, `AppIcon`. |
| `components/layout/` | `AppLayout`, `AppSidebar` (het menu) en `AppTopbar` (de titel en de rol). |
| `components/dashboard/` | `StatCard` (een cijferkaart), `ListBlock` (een lijstje) en `TaskList` (de takenlijst). |
| `components/charts/` | `BarChart` en `LineChart`, met de hand getekend in SVG. |
