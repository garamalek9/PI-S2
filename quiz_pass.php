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

// Vérifier que l'apprenant a accès à ce quiz
try {
    $stmt = $pdo->prepare("
        SELECT q.*, f.id_formation 
        FROM quiz q
        JOIN formation f ON q.formation_id = f.id_formation
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

// Traitement de la soumission du quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reponses = $_POST['reponses'] ?? [];
    $score = 0;

    // Récupérer les questions et bonnes réponses
    $stmt = $pdo->prepare("SELECT id_question, bonne_reponse FROM question WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_questions = count($questions);

    // Calculer le score
    foreach ($questions as $index => $question) {
        $question_id = $question['id_question'];
        if (isset($reponses[$question_id]) && $reponses[$question_id] === $question['bonne_reponse']) {
            $score++;
        }
    }

    // Calculer le pourcentage
    $pourcentage = $total_questions > 0 ? ($score / $total_questions) * 100 : 0;

    // Enregistrer le résultat
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_results (user_id, course_id, score, date_attempt)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$apprenant_id, $quiz['formation_id'], round($pourcentage)]);
    } catch (PDOException $e) {
        // Log error if needed, but don't block the redirect
    }

    header("Location: quiz_resultat.php?quiz_id=" . $quiz_id . "&score=" . round($pourcentage));
    exit();
}

// Récupérer les questions du quiz
$stmt = $pdo->prepare("SELECT * FROM question WHERE quiz_id = ?");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz - <?php echo htmlspecialchars($quiz['titre']); ?> - LearnUp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361EE;
            --secondary-color: #EF476F;
            --dark-color: #2D3436;
            --background-color: #F5F7FF;
            --card-bg: #FFFFFF;
            --accent-color: #F8A602;
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

        .quiz-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .quiz-container:hover {
            transform: translateY(-5px);
        }

        .quiz-container h1 {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            position: relative;
        }

        .quiz-container h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 80px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .question-block {
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

        .options-list {
            list-style: none;
            padding: 0;
        }

        .option-item {
            margin: 10px 0;
        }

        .option-item label {
            display: block;
            padding: 12px;
            border: 2px solid #E8ECEF;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .option-item label:hover {
            background: #F0F4FF;
            border-color: var(--primary-color);
        }

        .option-item input[type="radio"] {
            margin-right: 10px;
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

        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: var(--shadow);
            z-index: 1000;
        }

        .timer.warning {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
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

            .quiz-container {
                padding: 20px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .timer {
                top: 10px;
                right: 10px;
                font-size: 0.9rem;
            }
        }

        button:hover {
            transform: translateY(-2px);
        }

        button:active {
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
            <div class="quiz-container">
                <h1><?php echo htmlspecialchars($quiz['titre']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars($quiz['description'])); ?></p>
                <p><strong>Durée:</strong> <?php echo htmlspecialchars($quiz['durée']); ?> minutes</p>
                <p><strong>Niveau:</strong> <?php echo htmlspecialchars($quiz['niveau']); ?></p>

                <div id="timer" class="timer">00:00</div>

                <form method="POST" id="quizForm">
                    <?php foreach ($questions as $question): ?>
                        <div class="question-block">
                            <h3>Question <?php echo htmlspecialchars($question['id_question']); ?></h3>
                            <p><?php echo htmlspecialchars($question['texte']); ?></p>
                            
                            <ul class="options-list">
                                <?php 
                                $options = [
                                    'A' => $question['reponse_A'],
                                    'B' => $question['reponse_B'],
                                    'C' => $question['reponse_C'],
                                    'D' => $question['reponse_D']
                                ];
                                foreach ($options as $key => $option): 
                                ?>
                                    <li class="option-item">
                                        <label>
                                            <input type="radio" 
                                                   name="reponses[<?php echo $question['id_question']; ?>]" 
                                                   value="<?php echo $key; ?>" 
                                                   required>
                                            <?php echo htmlspecialchars($option); ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary">Soumettre le Quiz</button>

                </form>
            </div>
        </div>
    </main>

    <script>
        function startTimer(duration, display) {
            let timer = duration * 60; // Convertir minutes en secondes
            let minutes, seconds;

            let countdown = setInterval(function () {
                minutes = Math.floor(timer / 60);
                seconds = timer % 60;

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = minutes + ":" + seconds;

                // Ajouter classe warning si moins de 1 minute restante
                if (timer <= 60) {
                    display.classList.add('warning');
                }

                if (--timer < 0) {
                    clearInterval(countdown);
                    document.getElementById('quizForm').submit();
                }
            }, 1000);
        }

        window.onload = function () {
            let duration = <?php echo json_encode($quiz['durée']); ?>;
            let display = document.querySelector('#timer');
            startTimer(duration, display);
        };
    </script>
</body>
</html>