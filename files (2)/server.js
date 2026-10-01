require('dotenv').config();
const path = require('path');
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

const MAX_CAPACITEIT = Number(process.env.MAX_CAPACITEIT) || 6500;

// ------------------------------------------------------------
// Database connectie
// dateStrings: DATE-kolommen komen als "2026-08-14" terug, zonder
// tijdzone-verschuiving (anders schuift een datum in NL een dag op).
// ------------------------------------------------------------
const pool = mysql.createPool({
  host: process.env.DB_HOST || '127.0.0.1',
  port: process.env.DB_PORT || 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'voorraadbeheer',
  waitForConnections: true,
  connectionLimit: 10,
  dateStrings: true,
});

// ------------------------------------------------------------
// Vertaalt de ERD-kolommen naar de veldnamen die de pagina gebruikt
// CAST: werkt ook als Aantal_voorraad in jouw database nog VARCHAR is.
// ------------------------------------------------------------
const PRODUCT_SELECT = `
  SELECT p.idProduct                       AS id,
         p.Productnaam                     AS naam,
         p.EAN                             AS ean,
         CAST(p.Aantal_voorraad AS SIGNED) AS voorraad,
         p.THT                             AS tht,
         p.Locatie                         AS locatie,
         p.Categorie_idCategorie           AS categorieId,
         c.Naam                            AS categorie
  FROM Product p
  JOIN Categorie c ON c.idCategorie = p.Categorie_idCategorie`;

async function haalProduct(id) {
  const [rijen] = await pool.query(`${PRODUCT_SELECT} WHERE p.idProduct = ?`, [id]);
  return rijen[0] || null;
}

// ------------------------------------------------------------
// Validatie: geeft { fouten, waarden } terug
// ------------------------------------------------------------
function valideerProduct(body) {
  const fouten = [];
  const naam = typeof body.naam === 'string' ? body.naam.trim() : '';
  const ean = typeof body.ean === 'string' ? body.ean.trim() : '';
  const locatie = typeof body.locatie === 'string' ? body.locatie.trim() : '';
  const tht = body.tht ? String(body.tht).slice(0, 10) : '';
  const voorraad = Number(body.voorraad);
  const categorieId = Number(body.categorieId);

  if (!naam) fouten.push('Productnaam is verplicht');
  if (naam.length > 45) fouten.push('Productnaam mag maximaal 45 tekens zijn');
  if (ean.length > 45) fouten.push('EAN mag maximaal 45 tekens zijn');
  if (locatie.length > 45) fouten.push('Locatie mag maximaal 45 tekens zijn');
  if (!Number.isInteger(categorieId) || categorieId < 1) fouten.push('Kies een categorie');
  if (body.voorraad === undefined || body.voorraad === '' || !Number.isInteger(voorraad) || voorraad < 0) {
    fouten.push('Voorraad moet een geheel getal van 0 of hoger zijn');
  }
  if (tht && (!/^\d{4}-\d{2}-\d{2}$/.test(tht) || isNaN(Date.parse(tht)))) {
    fouten.push('THT moet een geldige datum zijn');
  }

  return {
    fouten,
    waarden: {
      naam,
      ean: ean || null,
      voorraad,
      categorieId,
      tht: tht || null,
      locatie: locatie || null,
    },
  };
}

// Vertaalt bekende MySQL-fouten naar een duidelijke melding
function dbFout(err, res, standaardMelding) {
  if (err.code === 'ER_NO_REFERENCED_ROW_2') {
    return res.status(400).json({ error: 'Deze categorie bestaat niet' });
  }
  if (err.code === 'ER_ROW_IS_REFERENCED_2') {
    return res.status(409).json({
      error: 'Dit product komt voor in een pakket of levering en kan daarom niet worden gewijzigd of verwijderd.',
    });
  }
  console.error(err);
  return res.status(500).json({ error: standaardMelding });
}

// ------------------------------------------------------------
// GET /api/categorieen
// ------------------------------------------------------------
app.get('/api/categorieen', async (req, res) => {
  try {
    const [rijen] = await pool.query(
      'SELECT idCategorie AS id, Naam AS naam, Omschrijving AS omschrijving FROM Categorie ORDER BY idCategorie'
    );
    res.json(rijen);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Kon categorieën niet ophalen' });
  }
});

// ------------------------------------------------------------
// GET /api/capaciteit
// ------------------------------------------------------------
app.get('/api/capaciteit', async (req, res) => {
  try {
    const [[{ totaal }]] = await pool.query(
      'SELECT COALESCE(SUM(CAST(Aantal_voorraad AS SIGNED)), 0) AS totaal FROM Product'
    );
    const totaalNr = Number(totaal);
    res.json({
      totaal: totaalNr,
      maxCapaciteit: MAX_CAPACITEIT,
      percentage: Math.round((totaalNr / MAX_CAPACITEIT) * 100),
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Kon capaciteit niet ophalen' });
  }
});

// ------------------------------------------------------------
// GET /api/producten?categorie=<id>&zoek=<tekst>   (Read)
// ------------------------------------------------------------
app.get('/api/producten', async (req, res) => {
  try {
    const { categorie, zoek } = req.query;
    const condities = [];
    const params = [];

    if (categorie && categorie !== 'Alles') {
      condities.push('p.Categorie_idCategorie = ?');
      params.push(Number(categorie));
    }
    if (zoek) {
      condities.push('(p.Productnaam LIKE ? OR p.EAN LIKE ?)');
      params.push(`%${zoek}%`, `%${zoek}%`);
    }

    const where = condities.length ? `WHERE ${condities.join(' AND ')}` : '';
    const [rijen] = await pool.query(
      `${PRODUCT_SELECT} ${where} ORDER BY p.THT IS NULL, p.THT ASC, p.Productnaam ASC`,
      params
    );
    res.json(rijen);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Kon producten niet ophalen' });
  }
});

app.get('/api/producten/:id', async (req, res) => {
  try {
    const product = await haalProduct(req.params.id);
    if (!product) return res.status(404).json({ error: 'Product niet gevonden' });
    res.json(product);
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: 'Kon product niet ophalen' });
  }
});

// ------------------------------------------------------------
// POST /api/producten   (Create)
// ------------------------------------------------------------
app.post('/api/producten', async (req, res) => {
  const { fouten, waarden: w } = valideerProduct(req.body);
  if (fouten.length) return res.status(400).json({ error: fouten.join('. ') });

  try {
    const [resultaat] = await pool.query(
      `INSERT INTO Product (Productnaam, EAN, Aantal_voorraad, Categorie_idCategorie, THT, Locatie)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [w.naam, w.ean, w.voorraad, w.categorieId, w.tht, w.locatie]
    );
    res.status(201).json(await haalProduct(resultaat.insertId));
  } catch (err) {
    dbFout(err, res, 'Kon product niet aanmaken');
  }
});

// ------------------------------------------------------------
// PUT /api/producten/:id   (Update)
// ------------------------------------------------------------
app.put('/api/producten/:id', async (req, res) => {
  const { fouten, waarden: w } = valideerProduct(req.body);
  if (fouten.length) return res.status(400).json({ error: fouten.join('. ') });

  try {
    const [resultaat] = await pool.query(
      `UPDATE Product
          SET Productnaam = ?, EAN = ?, Aantal_voorraad = ?,
              Categorie_idCategorie = ?, THT = ?, Locatie = ?
        WHERE idProduct = ?`,
      [w.naam, w.ean, w.voorraad, w.categorieId, w.tht, w.locatie, req.params.id]
    );
    if (resultaat.affectedRows === 0) return res.status(404).json({ error: 'Product niet gevonden' });
    res.json(await haalProduct(req.params.id));
  } catch (err) {
    dbFout(err, res, 'Kon product niet bijwerken');
  }
});

// ------------------------------------------------------------
// DELETE /api/producten/:id
// ------------------------------------------------------------
app.delete('/api/producten/:id', async (req, res) => {
  try {
    const [resultaat] = await pool.query('DELETE FROM Product WHERE idProduct = ?', [req.params.id]);
    if (resultaat.affectedRows === 0) return res.status(404).json({ error: 'Product niet gevonden' });
    res.status(204).send();
  } catch (err) {
    dbFout(err, res, 'Kon product niet verwijderen');
  }
});

const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
  console.log(`Voorraadbeheer server draait op http://localhost:${PORT}`);
});
