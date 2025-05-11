<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification et du rôle formateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_formateur']) || !$_SESSION['is_formateur']) {
    header("Location: login.php");
    exit();
}

$formateur_id = $_SESSION['user_id'];
$formation_id = isset($_GET['formation_id']) ? (int)$_GET['formation_id'] : null;

// Vérifier que formation_id est fourni et valide
if (!$formation_id) {
    header("Location: mes_formations.php?error=missing_formation_id");
    exit();
}

// Vérifier que la formation appartient bien au formateur
$stmt = $pdo->prepare("SELECT * FROM formation WHERE id_formation = ? AND formateur_id = ?");
$stmt->execute([$formation_id, $formateur_id]);
$formation = $stmt->fetch();

if (!$formation) {
    header("Location: mes_formations.php?error=invalid_formation");
    exit();
}

// Traitement de l'ajout/modification de quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $titre = $_POST['titre'] ?? '';
            $description = $_POST['description'] ?? '';
            $durée = $_POST['durée'] ?? '';
            $niveau = $_POST['niveau'] ?? '';
            $formation_id = $_POST['formation_id'] ?? null; // Récupéré du formulaire
            $questions = $_POST['questions'] ?? [];
            $reponses_A = $_POST['reponses_A'] ?? [];
            $reponses_B = $_POST['reponses_B'] ?? [];
            $reponses_C = $_POST['reponses_C'] ?? [];
            $reponses_D = $_POST['reponses_D'] ?? [];
            $bonnes_reponses = $_POST['bonnes_reponses'] ?? [];

            // Validation
            $valid = true;
            if (empty($titre) || empty($description) || empty($durée) || empty($niveau) || !$formation_id) {
                $valid = false;
            }
            for ($i = 0; $i < count($questions); $i++) {
                if (empty($questions[$i]) || empty($reponses_A[$i]) || empty($reponses_B[$i]) || 
                    empty($reponses_C[$i]) || empty($reponses_D[$i]) || 
                    !in_array($bonnes_reponses[$i], ['A', 'B', 'C', 'D'])) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                try {
                    if ($_POST['action'] === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO quiz (formation_id, formateur_id, titre, description, durée, niveau) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$formation_id, $formateur_id, $titre, $description, $durée, $niveau]);
                        $quiz_id = $pdo->lastInsertId();
                    } else {
                        $quiz_id = $_POST['quiz_id'];
                        $stmt = $pdo->prepare("UPDATE quiz SET titre = ?, description = ?, durée = ?, niveau = ? WHERE id_quiz = ? AND formateur_id = ? AND formation_id = ?");
                        $stmt->execute([$titre, $description, $durée, $niveau, $quiz_id, $formateur_id, $formation_id]);
                        
                        // Supprimer les anciennes questions
                        $stmt = $pdo->prepare("DELETE FROM question WHERE quiz_id = ?");
                        $stmt->execute([$quiz_id]);
                    }
                    
                    // Ajouter les questions
                    for ($i = 0; $i < count($questions); $i++) {
                        $stmt = $pdo->prepare("INSERT INTO question (quiz_id, texte, reponse_A, reponse_B, reponse_C, reponse_D, bonne_reponse) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$quiz_id, $questions[$i], $reponses_A[$i], $reponses_B[$i], $reponses_C[$i], $reponses_D[$i], $bonnes_reponses[$i]]);
                    }
                    
                    header("Location: quiz_gestion.php?formation_id=" . $formation_id . "&success=1");
                    exit();
                } catch (PDOException $e) {
                    $error = "Erreur lors de l'enregistrement du quiz : " . $e->getMessage();
                }
            } else {
                $error = "Veuillez remplir tous les champs correctement.";
            }
        } elseif ($_POST['action'] === 'delete') {
            $quiz_id = $_POST['quiz_id'];
            $stmt = $pdo->prepare("DELETE FROM quiz WHERE id_quiz = ? AND formation_id = ? AND formateur_id = ?");
            $stmt->execute([$quiz_id, $formation_id, $formateur_id]);
            
            header("Location: quiz_gestion.php?formation_id=" . $formation_id . "&success=2");
            exit();
        }
    }
}

// Récupérer les quiz existants
$quiz_list = [];
if ($formation_id) {
    $stmt = $pdo->prepare("SELECT * FROM quiz WHERE formation_id = ? AND formateur_id = ?");
    $stmt->execute([$formation_id, $formateur_id]);
    $quiz_list = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Quiz - LearnUp</title>
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

        .alert-success {
            background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
            color: #2E7D32;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease-in;
        }

        .alert-error {
            background: linear-gradient(135deg, #FFE6E6, #FFCCCC);
            color: #D00000;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .quiz-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #E8ECEF;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
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

        .btn-add-question {
            background: var(--accent-color);
            color: white;
            margin-bottom: 20px;
        }

        .btn-add-question:hover {
            background: #D99000;
            transform: translateY(-2px);
        }

        .btn-remove-question {
            background: #EF233C;
            color: white;
            padding: 8px 15px;
        }

        .btn-remove-question:hover {
            background: #D00000;
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

        .btn-danger {
            background: #EF233C;
            color: white;
        }

        .btn-danger:hover {
            background: #D00000;
        }

        .quiz-list {
            margin-top: 40px;
        }

        .quiz-list h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--dark-color);
            position: relative;
        }

        .quiz-list h2::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 60px;
            height: 2px;
            background-color: var(--primary-color);
        }

        .quiz-item {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .quiz-item:hover {
            transform: translateY(-5px);
        }

        .quiz-item h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        .quiz-item p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }

        .quiz-actions {
            display: flex;
            gap: 15px;
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
                <a href="acceuil_formateur.php" class="logo">
                    <i class="fas fa-graduation-cap"></i> LearnUp
                </a>
                <nav>
                    <a href="acceuil_formateur.php">Accueil</a>
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
                <h1>Gestion des Quiz - <?php echo htmlspecialchars($formation['nom']); ?></h1>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <?php if ($_GET['success'] == 1): ?>
                            Quiz enregistré avec succès !
                        <?php elseif ($_GET['success'] == 2): ?>
                            Quiz supprimé avec succès !
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="quiz-form" id="quizForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="quiz_id" id="quizId">
                    <input type="hidden" name="formation_id" value="<?php echo $formation_id; ?>">
                    
                    <div class="form-group">
                        <label for="titre">Titre du Quiz</label>
                        <input type="text" id="titre" name="titre" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="durée">Durée (en minutes)</label>
                        <input type="number" id="durée" name="durée" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="niveau">Niveau</label>
                        <select id="niveau" name="niveau" required>
                            <option value="Débutant">Débutant</option>
                            <option value="Intermédiaire">Intermédiaire</option>
                            <option value="Avancé">Avancé</option>
                        </select>
                    </div>
                    
                    <div id="questions-container">
                        <!-- Les questions seront ajoutées ici dynamiquement -->
                    </div>
                    
                    <button type="button" class="btn-add-question" onclick="addQuestion()">
                        Ajouter une question
                    </button>
                    
                    <button type="submit" class="btn btn-primary">Enregistrer le Quiz</button>
                </form>

                <div class="quiz-list">
                    <h2>Quiz existants</h2>
                    <?php foreach ($quiz_list as $quiz): ?>
                        <div class="quiz-item">
                            <h3><?php echo htmlspecialchars($quiz['titre']); ?></h3>
                            <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                            <p><strong>Durée:</strong> <?php echo htmlspecialchars($quiz['durée']); ?> minutes</p>
                            <p><strong>Niveau:</strong> <?php echo htmlspecialchars($quiz['niveau']); ?></p>
                            <div class="quiz-actions">
                                <button onclick="editQuiz(<?php echo $quiz['id_quiz']; ?>)" class="btn btn-outline">
                                    Modifier
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id_quiz']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce quiz ?')">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        let questionCount = 0;

        function addQuestion(question = '', reponse_A = '', reponse_B = '', reponse_C = '', reponse_D = '', bonne_reponse = '') {
            const container = document.getElementById('questions-container');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-block';
            questionDiv.innerHTML = `
                <div class="form-group">
                    <label>Question ${questionCount + 1}</label>
                    <input type="text" name="questions[]" value="${question}" required>
                </div>
                <div class="form-group">
                    <label>Réponse A</label>
                    <input type="text" name="reponses_A[]" value="${reponse_A}" required>
                </div>
                <div class="form-group">
                    <label>Réponse B</label>
                    <input type="text" name="reponses_B[]" value="${reponse_B}" required>
                </div>
                <div class="form-group">
                    <label>Réponse C</label>
                    <input type="text" name="reponses_C[]" value="${reponse_C}" required>
                </div>
                <div class="form-group">
                    <label>Réponse D</label>
                    <input type="text" name="reponses_D[]" value="${reponse_D}" required>
                </div>
                <div class="form-group">
                    <label>Bonne réponse</label>
                    <select name="bonnes_reponses[]" required>
                        <option value="A" ${bonne_reponse === 'A' ? 'selected' : ''}>A</option>
                        <option value="B" ${bonne_reponse === 'B' ? 'selected' : ''}>B</option>
                        <option value="C" ${bonne_reponse === 'C' ? 'selected' : ''}>C</option>
                        <option value="D" ${bonne_reponse === 'D' ? 'selected' : ''}>D</option>
                    </select>
                </div>
                <button type="button" class="btn-remove-question" onclick="this.parentElement.remove()">
                    Supprimer cette question
                </button>
            `;
            container.appendChild(questionDiv);
            questionCount++;
        }

        function editQuiz(quizId) {
            fetch('get_quiz.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'quiz_id=' + quizId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour le formulaire
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('quizId').value = quizId;
                    document.getElementById('titre').value = data.quiz.titre;
                    document.getElementById('description').value = data.quiz.description;
                    document.getElementById('durée').value = data.quiz.durée;
                    document.getElementById('niveau').value = data.quiz.niveau;

                    // Vider et remplir les questions
                    const container = document.getElementById('questions-container');
                    container.innerHTML = '';
                    questionCount = 0;
                    data.questions.forEach(q => {
                        addQuestion(q.texte, q.reponse_A, q.reponse_B, q.reponse_C, q.reponse_D, q.bonne_reponse);
                    });
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    alert('Erreur lors du chargement du quiz.');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        }

        // Ajouter une question par défaut au chargement
        addQuestion();
    </script>
</body>
</html>