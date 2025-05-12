<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_formateur']) || !$_SESSION['is_formateur']) {
    header("Location: login.php?error=2");
    exit();
}

// Gestion de la déconnexion
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.html");
    exit();
}

$nom = $_SESSION['nom'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';
$email = $_SESSION['email'] ?? '';

// Connexion à la base de données
$host = 'localhost';
$db   = 'learnupp';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Récupérer les formations du formateur connecté
$id_formateur = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM formation WHERE formateur_id = ?");
$stmt->execute([$id_formateur]);
$formations = $stmt->fetchAll();

// Récupérer les avis (par exemple les 10 plus récents)
$stmtAvis = $pdo->query("SELECT * FROM avis ORDER BY date DESC LIMIT 10");
$avis = $stmtAvis->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil Formateur - LearnUp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(120deg, #f5f7ff 60%, #e0e7ff 100%);
        color: #222;
        margin: 0;
    }
    header {
        background: #fff;
        box-shadow: 0 2px 10px rgba(67,97,238,0.07);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 0;
    }
    .logo {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #4361ee;
        font-size: 1.7rem;
        font-weight: 700;
    }
    .logo i {
        margin-right: 10px;
        font-size: 2rem;
    }
    nav {
        display: flex;
        align-items: center;
    }
    .user-greeting {
        margin-right: 18px;
        color: #4361ee;
        font-weight: 500;
    }
    nav a, .btn {
        margin-left: 22px;
        text-decoration: none;
        color: #222;
        font-weight: 500;
        transition: color 0.3s, background 0.3s;
        border-radius: 8px;
        padding: 8px 18px;
    }
    nav a:hover, .btn:hover {
        color: #fff;
        background: linear-gradient(90deg, #4361ee 60%, #4cc9f0 100%);
    }
    .btn-outline {
        border: 2px solid #4361ee;
        color: #4361ee;
        background: transparent;
    }
    .btn-outline:hover {
        background: #4361ee;
        color: #fff;
    }
    main {
        padding: 40px 0 0 0;
    }
    .formations-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 35px;
        margin-top: 30px;
    }
    .formation-card {
        background: linear-gradient(135deg, #f8f9fa 60%, #e0e7ff 100%);
        border-radius: 18px;
        box-shadow: 0 8px 32px rgba(67, 97, 238, 0.10);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.25s, box-shadow 0.25s;
        border: 1px solid #e0e7ff;
        position: relative;
    }
    .formation-card:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 16px 40px rgba(67, 97, 238, 0.18);
    }
    .formation-image img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-top-left-radius: 18px;
        border-top-right-radius: 18px;
        transition: filter 0.3s;
    }
    .formation-card:hover .formation-image img {
        filter: brightness(0.95) saturate(1.2);
    }
    .formation-content {
        padding: 22px 18px 18px 18px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .formation-content h3 {
        margin-bottom: 10px;
        color: #4361ee;
        font-size: 1.25rem;
        font-weight: 600;
    }
    .formation-content p {
        flex: 1;
        margin-bottom: 18px;
        color: #333;
        font-size: 1rem;
        line-height: 1.5;
    }
    .formation-content .btn {
        align-self: flex-start;
        margin-top: 8px;
        font-size: 1rem;
        border-radius: 6px;
        padding: 8px 22px;
        background: linear-gradient(90deg, #4361ee 60%, #4cc9f0 100%);
        border: none;
        color: #fff;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(67, 97, 238, 0.10);
        transition: background 0.2s;
    }
    .formation-content .btn:hover {
        background: linear-gradient(90deg, #3f37c9 60%, #4361ee 100%);
    }
    .formation-actions {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }
    .btn-quiz {
        background: linear-gradient(90deg, #28a745 60%, #4bb543 100%);
        color: #fff;
    }
    .btn-quiz:hover {
        background: linear-gradient(90deg, #218838 60%, #28a745 100%);
    }
    .avis {
        margin-top: 60px;
    }
    .avis-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 30px;
    }
    .avis-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        padding: 20px;
    }
    .avis-meta {
        font-size: 0.9em;
        color: #888;
        margin-top: 10px;
        display: flex;
        justify-content: space-between;
    }
    .section-title {
        text-align: center;
        font-size: 2rem;
        margin-bottom: 40px;
        color: #222;
        font-weight: 600;
        letter-spacing: 1px;
    }
    footer {
        background: #222;
        color: #fff;
        margin-top: 60px;
        padding: 30px 0 10px 0;
        font-size: 1rem;
        text-align: center;
        border-top-left-radius: 30px;
        border-top-right-radius: 30px;
    }
    @media (max-width: 768px) {
        .formations-list {
            grid-template-columns: 1fr;
        }
        .formation-actions {
            flex-direction: column;
            gap: 8px;
        }
        .formation-content .btn {
            width: 100%;
            text-align: center;
        }
    }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="acceuil_formateur.php" class="logo">
                    <i class="fas fa-graduation-cap"></i> LearnUp
                </a>
                <nav>
                    <span class="user-greeting">Bonjour, <?php echo htmlspecialchars($prenom); ?>!</span>
                    <a href="acceuil_formateur.php">Accueil</a>
                    <a href="formation.php">Gérer mes formations</a>
                    <a href="contact.html">Contact</a>
                    <a href="profil.php">Mon Profil</a>
                    <a href="login.html" class="btn btn-outline">Déconnexion</a>
                </nav>
            </div>
        </div>
    </header>
    <main>
        <div class="container">
            <div class="formations-list">
                <?php foreach ($formations as $f): ?>
                    <div class="formation-card">
                        <div class="formation-image">
                            <?php
                            $imagePath = !empty($f['image']) ? (file_exists($f['image']) ? $f['image'] : 'Uploads/' . $f['image']) : 'images/default-formation.jpg';
                            ?>
                            <img src="<?php echo !empty($f['image']) ? 'Uploads/' . htmlspecialchars($f['image']) : 'images/default-formation.jpg'; ?>" alt="<?php echo htmlspecialchars($f['nom_formation'] ?? $f['nom']); ?>">
                        </div>
                        <div class="formation-content">
                            <h3><?php echo htmlspecialchars($f['nom_formation'] ?? $f['nom']); ?></h3>
                            <p><?php echo htmlspecialchars($f['description']); ?></p>
                            <div class="formation-actions">
                                <a href="formation.php?id=<?php echo $f['id_formation']; ?>" class="btn btn-primary">Voir la formation</a>
                                <a href="quiz_gestion.php?formation_id=<?php echo $f['id_formation']; ?>" class="btn btn-quiz">Gérer Quiz</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <section class="avis">
                <h2 class="section-title">Avis des apprenants</h2>
                <div class="avis-list">
                    <?php foreach ($avis as $a): ?>
                        <div class="avis-card">
                            <p class="commentaire"><?php echo htmlspecialchars($a['commentaire']); ?></p>
                            <div class="avis-meta">
                                <span class="auteur">Auteur ID: <?php echo $a['auteur_id']; ?></span>
                                <span class="date"><?php echo $a['date']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>
    <footer style="text-align:center; padding:20px 0; color:#888;">
        © <?php echo date('Y'); ?> LearnUp. Tous droits réservés.
    </footer>
</body>
</html>