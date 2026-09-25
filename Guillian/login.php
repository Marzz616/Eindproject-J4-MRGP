<?php
session_start();

// Verbinding met de database
$host = 'mysql';
$db   = 'mydb';
$user = 'student';
$pass = 'veiligwachtwoord';

$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$foutmelding = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam = trim($_POST['gebruikersnaam']);
    $wachtwoord     = $_POST['wachtwoord'];

    $stmt = $pdo->prepare(
        "SELECT Gebruiker.idGebruiker, Gebruiker.Naam, Gebruiker.Gebruikersnaam,
                Gebruiker.Wachtwoord_hash, Gebruiker.Actief, Rol.Naam AS RolNaam
         FROM Gebruiker
         JOIN Rol ON Gebruiker.Rol_idRol = Rol.idRol
         WHERE Gebruiker.Gebruikersnaam = :gebruikersnaam"
    );
    $stmt->execute([':gebruikersnaam' => $gebruikersnaam]);
    $gebruiker = $stmt->fetch();

    if (!$gebruiker) {
        $foutmelding = 'Onjuiste gebruikersnaam of wachtwoord.';
    } elseif (!$gebruiker['Actief']) {
        $foutmelding = 'Dit account is niet actief.';
    } elseif (!password_verify($wachtwoord, $gebruiker['Wachtwoord_hash'])) {
        $foutmelding = 'Onjuiste gebruikersnaam of wachtwoord.';
    } else {
        // Login geslaagd: gegevens in de sessie zetten
        session_regenerate_id(true);
        $_SESSION['idGebruiker']    = $gebruiker['idGebruiker'];
        $_SESSION['naam']           = $gebruiker['Naam'];
        $_SESSION['gebruikersnaam'] = $gebruiker['Gebruikersnaam'];
        $_SESSION['rol']            = $gebruiker['RolNaam'];

        header('Location: Leveranciers.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Inloggen</title>
</head>
<body>

<h1>Inloggen</h1>

<?php if ($foutmelding): ?>
    <p style="color: red; font-weight: bold;"><?= htmlspecialchars($foutmelding) ?></p>
<?php endif; ?>

<form method="POST">
    <label>Gebruikersnaam:</label><br>
    <input type="text" name="gebruikersnaam" required><br><br>

    <label>Wachtwoord:</label><br>
    <input type="password" name="wachtwoord" required><br><br>

    <button type="submit">Inloggen</button>
</form>

</body>
</html>