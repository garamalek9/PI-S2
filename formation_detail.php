<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_formateur']) && $_SESSION['is_formateur'])) {
    header("Location: login.php");
    exit();
}

// Vérifier si l'ID de formation est présent
if (!isset($_GET['id'])) {
    header("Location: mes_formations.php");
    exit();
}

$formation_id = (int)$_GET['id'];
$apprenant_id = $_SESSION['user_id'];

// Vérifier que l'apprenant a bien acheté cette formation
try {
    $stmt = $pdo->prepare("SELECT f.*, c.statut 
                          FROM commande c
                          JOIN formation f ON c.id_formation = f.id_formation
                          WHERE c.apprenant_id = ? AND c.id_formation = ?");
    $stmt->execute([$apprenant_id, $formation_id]);
    $formation = $stmt->fetch();

    if (!$formation) {
        header("Location: mes_formations.php?error=unauthorized");
        exit();
    }
} catch (PDOException $e) {
    header("Location: mes_formations.php?error=database");
    exit();
}

// Récupérer les informations de l'utilisateur
$nom = $_SESSION['nom'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';

// Récupérer les chapitres de la formation
try {
    $stmtChapters = $pdo->prepare("SELECT * FROM chapitre WHERE formation_id = ? ORDER BY id_chapitre");
    $stmtChapters->execute([$formation_id]);
    $chapters = $stmtChapters->fetchAll();
} catch (PDOException $e) {
    $chapters = []; // Fallback si la table chapitre n'existe pas ou erreur
}

// Afficher le quiz associé à la formation
$quiz = null;
$quiz_result = null;
try {
    $stmtQuiz = $pdo->prepare("SELECT * FROM quiz WHERE formation_id = ?");
    $stmtQuiz->execute([$formation_id]);
    $quiz = $stmtQuiz->fetch();

    if ($quiz) {
        // Vérifier si l'apprenant a déjà passé le quiz
        $stmtResult = $pdo->prepare("SELECT score FROM quiz_results WHERE id_quiz = ? AND id_apprenant = ?");
        $stmtResult->execute([$quiz['id_quiz'], $apprenant_id]);
        $quiz_result = $stmtResult->fetch();
    }
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération du quiz : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($formation['nom']); ?> - LearnUp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
            font-size: 2rem;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        nav a {
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        nav a:hover {
            color: var(--primary-color);
        }

        .user-greeting {
            color: var(--dark-color);
            font-weight: 500;
        }

        .main-content {
            padding: 40px 0;
        }

        .formation-detail {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: fadeIn 1s ease-in-out;
        }

        .formation-detail h1 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 2rem;
        }

        .formation-detail p {
            color: var(--dark-color);
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .formation-content {
            line-height: 1.8;
            color: var(--dark-color);
            font-size: 1rem;
            margin-bottom: 30px;
        }

        .formation-modules {
            margin-top: 30px;
        }

        .formation-modules h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .chapter {
            background: var(--light-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .chapter:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .chapter h3 {
            color: var(--dark-color);
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .chapter p {
            color: #666;
        }

        .quiz-section {
            margin-top: 40px;
            padding: 20px;
            background: var(--light-color);
            border-radius: 10px;
        }

        .quiz-section h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .quiz-section p {
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            border: none;
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #3d9a36;
            transform: translateY(-2px);
        }

        .error-message {
            background-color: var(--error-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        footer {
            background-color: white;
            padding: 20px 0;
            margin-top: 50px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }

            nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .formation-detail {
                padding: 20px;
            }

            .chapter {
                padding: 15px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0 15px;
            }

            .formation-detail h1 {
                font-size: 1.5rem;
            }

            .formation-modules h2,
            .quiz-section h2 {
                font-size: 1.3rem;
            }

            nav {
                gap: 10px;
            }
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
                    <span class="user-greeting">Bonjour, <?php echo htmlspecialchars($prenom); ?>!</span>
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
            <div class="formation-detail">
                <h1><?php echo htmlspecialchars($formation['nom']); ?></h1>
                <p><strong>Statut:</strong> <?php echo htmlspecialchars($formation['statut']); ?></p>
                <div class="formation-content">
                    <?php echo nl2br(htmlspecialchars($formation['description'])); ?>
                </div>

                <div class="formation-modules">
                    <h2>Modules de la formation</h2>
                    <?php if (!empty($chapters)): ?>
                        <?php foreach ($chapters as $chapter): ?>
                            <div class="chapter">
                                <h3><?php echo htmlspecialchars($chapter['titre']); ?></h3>
                                <p><?php echo nl2br(htmlspecialchars($chapter['contenu'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aucun module disponible pour cette formation. Veuillez contacter le formateur pour plus d'informations.</p>
                    <?php endif; ?>
                </div>

                <div class="quiz-section">
                    <?php if (isset($error)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($quiz): ?>
                        <h2>Quiz : <?php echo htmlspecialchars($quiz['titre']); ?></h2>
                        <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                        <?php if ($quiz_result): ?>
                            <p style="color: <?php echo $quiz_result['score'] >= 50 ? 'green' : 'red'; ?>;">
                                <strong>Votre score : <?php echo htmlspecialchars($quiz_result['score']); ?>%</strong>
                            </p>
                            <a href="quiz_pass.php?quiz_id=<?php echo $quiz['id_quiz']; ?>" class="btn btn-outline">Repasser le Quiz</a>
                        <?php else: ?>
                            <a href="quiz_pass.php?quiz_id=<?php echo $quiz['id_quiz']; ?>" class="btn btn-success">Passer le Quiz</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Aucun quiz n'est associé à cette formation pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            © <?php echo date('Y'); ?> LearnUp. Tous droits réservés.
        </div>
    </footer>
</body>
</html>