<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_formateur']) && $_SESSION['is_formateur'])) {
    header("Location: login.php");
    exit();
}

$apprenant_id = $_SESSION['user_id'];
$nom = $_SESSION['nom'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';

// Récupérer les formations achetées par l'apprenant
try {
    $stmt = $pdo->prepare("SELECT f.*, c.date_commande, c.statut 
                          FROM commande c
                          JOIN formation f ON c.id_formation = f.id_formation
                          WHERE c.apprenant_id = ?
                          ORDER BY c.date_commande DESC");
    $stmt->execute([$apprenant_id]);
    $formations = $stmt->fetchAll();
} catch (PDOException $e) {
    $formations = [];
    $error = "Erreur lors de la récupération des formations";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations - LearnUp</title>
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
            --warning-color: #ffc107;
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
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }

        nav {
            display: flex;
            align-items: center;
        }

        .user-greeting {
            margin-right: 15px;
            color: var(--primary-color);
            font-weight: 500;
        }

        nav a {
            margin-left: 25px;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav a:hover {
            color: var(--primary-color);
        }

        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        /* Main Content */
        .main-content {
            padding: 40px 0;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-en-cours {
            background-color: var(--warning-color);
            color: #000;
        }

        .status-termine {
            background-color: var(--success-color);
            color: white;
        }

        .status-annule {
            background-color: var(--error-color);
            color: white;
        }

        /* Formations List */
        .formations-container {
            margin-top: 30px;
        }

        .formations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .formation-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #e0e7ff;
        }

        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .formation-image {
            height: 180px;
            overflow: hidden;
        }

        .formation-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .formation-card:hover .formation-image img {
            transform: scale(1.05);
        }

        .formation-details {
            padding: 20px;
        }

        .formation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .formation-title {
            font-size: 1.25rem;
            color: var(--primary-color);
            font-weight: 600;
            margin-right: 10px;
        }

        .formation-date {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 10px;
        }

        .formation-description {
            color: #555;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .formation-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .formation-price {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .formation-actions {
            display: flex;
            gap: 10px;
        }

        .btn-access {
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }

        .btn-access:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .no-formations {
            text-align: center;
            padding: 50px 0;
            color: #666;
            font-size: 1.1rem;
        }

        .no-formations i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .formations-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            nav {
                margin-top: 15px;
                width: 100%;
                justify-content: space-between;
            }
            
            nav a {
                margin-left: 0;
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
                    <a href="mes_formations.php" class="active">Mes Formations</a>
                    <a href="contact.html">Contact</a>
                    <a href="profil.php">Mon Profil</a>
                    <a href="login.html" class="btn btn-outline">Déconnexion</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Mes Formations</h1>
            </div>

            <div class="formations-container">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php elseif (empty($formations)): ?>
                    <div class="no-formations">
                        <i class="fas fa-book-open"></i>
                        <p>Vous n'avez aucune formation pour le moment.</p>
                        <a href="catalogue.php" class="btn btn-primary">Parcourir le catalogue</a>
                    </div>
                <?php else: ?>
                    <div class="formations-grid">
                        <?php foreach ($formations as $formation): ?>
                            <?php
                            // Gestion de l'image
                            $image = 'images/default-formation.jpg';
                            if (!empty($formation['image'])) {
                                if (file_exists($formation['image'])) {
                                    $image = $formation['image'];
                                } elseif (file_exists('uploads/' . $formation['image'])) {
                                    $image = 'uploads/' . $formation['image'];
                                }
                            }
                            
                            // Formatage de la date
                            $date_commande = date('d/m/Y', strtotime($formation['date_commande']));
                            
                            // Statut de la formation
                            $status_class = '';
                            switch ($formation['statut']) {
                                case 'terminé':
                                    $status_class = 'status-termine';
                                    break;
                                case 'annulé':
                                    $status_class = 'status-annule';
                                    break;
                                default:
                                    $status_class = 'status-en-cours';
                            }
                            ?>
                            <div class="formation-card">
                                <div class="formation-image">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($formation['nom']); ?>">
                                </div>
                                <div class="formation-details">
                                    <div class="formation-header">
                                        <h3 class="formation-title"><?php echo htmlspecialchars($formation['nom']); ?></h3>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($formation['statut']); ?></span>
                                    </div>
                                    <div class="formation-date">Achetée le: <?php echo $date_commande; ?></div>
                                    <p class="formation-description">
                                        <?php echo htmlspecialchars(substr($formation['description'], 0, 100)); ?>
                                        <?php if (strlen($formation['description']) > 100) echo '...'; ?>
                                    </p>
                                    <div class="formation-footer">
                                        <div class="formation-price"><?php echo number_format($formation['prix'], 2); ?> €</div>
                                        <div class="formation-actions">
                                            <?php if ($formation['statut'] === 'en cours'): ?>
                                                <a href="formation_detail.php?id=<?php echo $formation['id_formation']; ?>" class="btn-access">
                                                    <i class="fas fa-play"></i> Continuer
                                                </a>
                                            <?php else: ?>
                                                <a href="formation_detail.php?id=<?php echo $formation['id_formation']; ?>" class="btn-access">
                                                    <i class="fas fa-eye"></i> Voir
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer style="text-align:center; padding:20px 0; color:#888; margin-top:50px;">
        &copy; <?php echo date('Y'); ?> LearnUp. Tous droits réservés.
    </footer>

    <script>
        // Script pour gérer les interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Vous pouvez ajouter ici des fonctionnalités JavaScript si nécessaire
            console.log('Page "Mes Formations" chargée');
        });
    </script>
</body>
</html>