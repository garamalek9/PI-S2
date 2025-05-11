<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_formateur']) && $_SESSION['is_formateur'])) {
    header("Location: login.php");
    exit();
}

$apprenant_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

if (!$quiz_id) {
    header("Location: mes_formations.php");
    exit();
}

// Récupérer les informations du quiz
try {
    $stmt = $pdo->prepare("
        SELECT q.*, f.id_formation, f.nom AS formation_nom, u.prénom AS formateur_prenom, u.nom AS formateur_nom
        FROM quiz q
        JOIN formation f ON q.formation_id = f.id_formation
        JOIN formateur fm ON f.formateur_id = fm.ID_formateur
        JOIN utilisateur u ON fm.ID_formateur = u.ID_utilisateur
        JOIN commande c ON f.id_formation = c.id_formation
        WHERE q.id_quiz = ? AND c.apprenant_id = ? AND c.statut = 'en cours'
    ");
    $stmt->execute([$quiz_id, $apprenant_id]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        header("Location: mes_formations.php?error=access_denied");
        exit();
    }
} catch (PDOException $e) {
    header("Location: mes_formations.php?error=database");
    exit();
}

// Récupérer le dernier résultat du quiz pour cet apprenant
try {
    $stmt = $pdo->prepare("
        SELECT score, date_attempt
        FROM quiz_results
        WHERE user_id = ? AND course_id = ?
        ORDER BY date_attempt DESC
        LIMIT 1
    ");
    $stmt->execute([$apprenant_id, $quiz['formation_id']]);
    $result = $stmt->fetch();

    if (!$result) {
        header("Location: mes_formations.php?error=no_result");
        exit();
    }
} catch (PDOException $e) {
    header("Location: mes_formations.php?error=database");
    exit();
}

// Récupérer les informations de l'apprenant
$nom = $_SESSION['nom'] ?? 'Apprenant';
$prenom = $_SESSION['prenom'] ?? '';
$apprenant_name = trim($prenom . ' ' . $nom);

$score = $result['score'];
$date_attempt = $result['date_attempt'];
$pass_status = $score >= 50 ? "Réussi" : "Échoué";
$formateur_name = trim($quiz['formateur_prenom'] . ' ' . $quiz['formateur_nom']);

// Vérifier si l'apprenant a obtenu 100% pour générer le certificat
$show_certificate = ($score == 100);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du Quiz - <?php echo htmlspecialchars($quiz['titre']); ?> - LearnUp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361EE;
            --secondary-color: #EF476F;
            --dark-color: #2D3436;
            --background-color: #F5F7FF;
            --card-bg: #FFFFFF;
            --success-color: #4bb543;
            --error-color: #dc3545;
            --shadow: 0 5px 15px rgba(0,0,0,0.05);
            --border-radius: 10px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-color);
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .logo i {
            margin-right: 10px;
        }

        nav {
            display: flex;
            align-items: center;
        }

        nav a {
            color: var(--dark-color);
            text-decoration: none;
            margin-left: 25px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        nav a:hover {
            color: var(--primary-color);
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary-color);
            transition: width 0.3s;
        }

        nav a:hover::after {
            width: 100%;
        }

        .main-content {
            padding: 40px 0;
        }

        .result-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
        }

        .result-container:hover {
            transform: translateY(-5px);
        }

        .result-container h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            position: relative;
        }

        .result-container h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .result-box {
            background: #F8FAFD;
            border: 2px dashed #E8ECEF;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-box h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .result-box p {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .result-status-pass {
            color: var(--success-color);
            font-weight: 600;
        }

        .result-status-fail {
            color: var(--error-color);
            font-weight: 600;
        }

        .certificate-message {
            color: var(--success-color);
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            header .container {
                flex-direction: column;
                gap: 15px;
            }

            nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            nav a {
                margin: 10px;
            }

            .result-container {
                padding: 20px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        button:hover, a.btn:hover {
            transform: translateY(-2px);
        }

        button:active, a.btn:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="acceuil_apprenant.php" class="logo">
                    <i class="fas fa-graduation-cap"></i> LearnUp
                </a>
                <nav>
                    <a href="acceuil_apprenant.php">Accueil</a>
                    <a href="mes_formations.php">Mes Formations</a>
                    <a href="contact.html">Contact</a>
                    <a href="profil.php">Mon Profil</a>
                    <a href="login.html" class="btn btn-outline">Déconnexion</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="result-container">
                <h1>Résultat du Quiz : <?php echo htmlspecialchars($quiz['titre']); ?></h1>
                <div class="result-box">
                    <h2>Votre Résultat</h2>
                    <p><strong>Score :</strong> <?php echo htmlspecialchars($score); ?>%</p>
                    <p><strong>Statut :</strong> <span class="<?php echo $score >= 50 ? 'result-status-pass' : 'result-status-fail'; ?>"><?php echo htmlspecialchars($pass_status); ?></span></p>
                    <p><strong>Date de tentative :</strong> <?php echo htmlspecialchars($date_attempt); ?></p>
                    <p><strong>Formation :</strong> <?php echo htmlspecialchars($quiz['formation_nom']); ?></p>
                </div>

                <?php if ($show_certificate): ?>
                    <p class="certificate-message">Félicitations ! Vous avez obtenu un score parfait. Téléchargez votre certificat ci-dessous :</p>
                    <form method="POST" action="generate_certificate.php">
                        <input type="hidden" name="apprenant_name" value="<?php echo htmlspecialchars($apprenant_name); ?>">
                        <input type="hidden" name="formation_name" value="<?php echo htmlspecialchars($quiz['formation_nom']); ?>">
                        <input type="hidden" name="formateur_name" value="<?php echo htmlspecialchars($formateur_name); ?>">
                        <input type="hidden" name="score" value="<?php echo htmlspecialchars($score); ?>">
                        <input type="hidden" name="max_score" value="100">
                        <input type="hidden" name="date_attempt" value="<?php echo htmlspecialchars($date_attempt); ?>">
                        <button type="submit" class="btn btn-success">Télécharger le Certificat</button>
                    </form>
                <?php endif; ?>

                <a href="formation_detail.php?id=<?php echo $quiz['formation_id']; ?>" class="btn btn-primary">Retour à la Formation</a>
                <a href="quiz_pass.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-outline">Repasser le Quiz</a>
            </div>
        </div>
    </main>
</body>
</html>