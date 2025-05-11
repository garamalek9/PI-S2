<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un formateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_formateur']) || !$_SESSION['is_formateur']) {
    header("Location: login.php?error=2");
    exit();
}

require_once 'db.php';

// Récupérer les infos utilisateur
$nom = $_SESSION['nom'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';
$email = $_SESSION['email'] ?? '';
$formateur_id = $_SESSION['user_id'];

// Gestion des actions (ajout, modification, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Ajouter une formation
                $nom_formation = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
                $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
                $prix = filter_input(INPUT_POST, 'prix', FILTER_VALIDATE_FLOAT);
                $est_publie = isset($_POST['est_publie']) ? 1 : 0;
                $image_name = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $image_name = uniqid('formation_', true) . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
                }
                if ($nom_formation && $description && $prix !== false && $image_name) {
                    $stmt = $pdo->prepare("INSERT INTO FORMATION (nom, description, prix, formateur_id, est_publie, image) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom_formation, $description, $prix, $formateur_id, $est_publie, $image_name]);
                    $success = "Formation ajoutée avec succès !";
                } else {
                    $error = "Veuillez remplir tous les champs correctement et ajouter une image.";
                }
            } elseif ($_POST['action'] === 'edit') {
                // Modifier une formation
                $id_formation = filter_input(INPUT_POST, 'id_formation', FILTER_VALIDATE_INT);
                $nom_formation = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
                $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
                $prix = filter_input(INPUT_POST, 'prix', FILTER_VALIDATE_FLOAT);
                $est_publie = isset($_POST['est_publie']) ? 1 : 0;
                $image_name = $edit_formation['image'] ?? null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $image_name = uniqid('formation_', true) . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
                }
                if ($id_formation && $nom_formation && $description && $prix !== false && $image_name) {
                    $stmt = $pdo->prepare("UPDATE FORMATION SET nom = ?, description = ?, prix = ?, est_publie = ?, image = ? WHERE id_formation = ? AND formateur_id = ?");
                    $stmt->execute([$nom_formation, $description, $prix, $est_publie, $image_name, $id_formation, $formateur_id]);
                    $success = "Formation modifiée avec succès !";
                } else {
                    $error = "Veuillez remplir tous les champs correctement et ajouter une image.";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Suppression d'une formation
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $id_formation = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id_formation) {
            $stmt = $pdo->prepare("DELETE FROM FORMATION WHERE id_formation = ? AND formateur_id = ?");
            $stmt->execute([$id_formation, $formateur_id]);
            $success = "Formation supprimée avec succès !";
        } else {
            $error = "ID de formation invalide.";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Récupérer les formations du formateur
try {
    $stmt = $pdo->prepare("SELECT * FROM FORMATION WHERE formateur_id = ? ORDER BY date_creation DESC");
    $stmt->execute([$formateur_id]);
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des formations : " . $e->getMessage();
}

// Récupérer les données pour modification si nécessaire
$edit_formation = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id_formation = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_formation) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM FORMATION WHERE id_formation = ? AND formateur_id = ?");
            $stmt->execute([$id_formation, $formateur_id]);
            $edit_formation = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur lors de la récupération de la formation : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Formations - LearnUp</title>
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

        /* Form Section */
        .form-section {
            padding: 60px 0;
        }

        .section-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 40px;
            color: var(--dark-color);
        }

        .formation-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .formation-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .formation-form input,
        .formation-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .formation-form textarea {
            height: 120px;
            resize: vertical;
        }

        .formation-form .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .formation-form .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }

        .success-message,
        .error-message {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success-message {
            background-color: var(--success-color);
            color: white;
        }

        .error-message {
            background-color: var(--error-color);
            color: white;
        }

        /* Table Section */
        .formations-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .formations-table th,
        .formations-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .formations-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .formations-table tr:hover {
            background-color: #f9fafd;
        }

        .formations-table .actions {
            display: flex;
            gap: 10px;
        }

        .btn-danger {
            background-color: var(--error-color);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .formations-table {
                display: block;
                overflow-x: auto;
            }

            nav {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .formation-form {
                padding: 20px;
            }

            .section-title {
                font-size: 1.8rem;
            }
        }

        .formations-innovantes {
            margin: 40px 0;
        }
        .formations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 32px;
        }
        .formation-card-innov {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(67, 97, 238, 0.10);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s, box-shadow 0.25s;
            border: 1px solid #e0e7ff;
            position: relative;
        }
        .formation-card-innov:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 16px 40px rgba(67, 97, 238, 0.18);
        }
        .formation-img-wrap {
            position: relative;
        }
        .formation-img-wrap img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            transition: filter 0.3s;
        }
        .formation-card-innov:hover .formation-img-wrap img {
            filter: brightness(0.95) saturate(1.2);
        }
        .badge {
            position: absolute;
            top: 16px;
            left: 16px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.10);
        }
        .badge-pub {
            background: linear-gradient(90deg, #4bb543 60%, #4361ee 100%);
        }
        .badge-brouillon {
            background: linear-gradient(90deg, #dc3545 60%, #4cc9f0 100%);
        }
        .formation-info {
            padding: 22px 18px 18px 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .formation-info h3 {
            margin-bottom: 10px;
            color: #4361ee;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .formation-info .desc {
            flex: 1;
            margin-bottom: 18px;
            color: #333;
            font-size: 1rem;
            line-height: 1.5;
        }
        .formation-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.98rem;
            color: #555;
        }
        .formation-meta i {
            margin-right: 5px;
            color: #4361ee;
        }
        .formation-actions {
            display: flex;
            gap: 10px;
        }
        .btn-edit, .btn-delete {
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s, color 0.2s;
        }
        .btn-edit {
            background: linear-gradient(90deg, #4361ee 60%, #4cc9f0 100%);
            color: #fff;
        }
        .btn-edit:hover {
            background: linear-gradient(90deg, #3f37c9 60%, #4361ee 100%);
        }
        .btn-delete {
            background: linear-gradient(90deg, #dc3545 60%, #ff6b6b 100%);
            color: #fff;
        }
        .btn-delete:hover {
            background: linear-gradient(90deg, #b71c1c 60%, #dc3545 100%);
        }
        .no-formations {
            text-align: center;
            color: #888;
            font-size: 1.1rem;
            padding: 40px 0;
        }
        @media (max-width: 768px) {
            .formations-grid {
                grid-template-columns: 1fr;
            }
        }

        .formation-form-innov {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(67, 97, 238, 0.10);
            max-width: 600px;
            margin: 0 auto 40px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .formation-form-innov label {
            font-weight: 600;
            color: #4361ee;
            margin-bottom: 6px;
        }
        .formation-form-innov input[type='text'],
        .formation-form-innov input[type='number'],
        .formation-form-innov textarea {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e0e7ff;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: border 0.2s;
        }
        .formation-form-innov input:focus,
        .formation-form-innov textarea:focus {
            border: 1.5px solid #4361ee;
            outline: none;
        }
        .formation-form-innov textarea {
            min-height: 100px;
            resize: vertical;
        }
        .dropzone {
            border: 2px dashed #4361ee;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            background: #f5f7ff;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .dropzone.dragover {
            border-color: #4cc9f0;
            background: #e0e7ff;
        }
        #dropzone-text {
            color: #888;
            font-size: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-primary {
            background: linear-gradient(90deg, #4361ee 60%, #4cc9f0 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #3f37c9 60%, #4361ee 100%);
        }
        @media (max-width: 600px) {
            .formation-form-innov {
                padding: 16px;
            }
        }
        footer {
            background: linear-gradient(90deg, #222 60%, #3f37c9 100%);
            color: #fff;
            margin-top: 60px;
            padding: 40px 0 10px 0;
            font-size: 1rem;
            text-align: center;
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 40px;
            margin-bottom: 30px;
        }
        .footer-column h4 {
            font-size: 1.1rem;
            margin-bottom: 18px;
            color: #4cc9f0;
        }
        .footer-column p, .footer-column ul {
            color: #bbb;
            font-size: 0.95rem;
        }
        .footer-column ul {
            list-style: none;
            padding: 0;
        }
        .footer-column ul li {
            margin-bottom: 8px;
        }
        .footer-column ul li a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s;
        }
        .footer-column ul li a:hover {
            color: #fff;
        }
        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .social-links a {
            color: #fff;
            background: #4361ee;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        .social-links a:hover {
            background: #4cc9f0;
        }
        .copyright {
            text-align: center;
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #bbb;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="accueil_formateur.php" class="logo">
                    <i class="fas fa-graduation-cap"></i> LearnUp
                </a>
                <nav>
                    <span class="user-greeting">Bonjour, <?php echo htmlspecialchars($prenom); ?>!</span>
                    <a href="accueil_formateur.php">Accueil</a>
                    <a href="formation.php">Formations</a>
                    <a href="contact.html">Contact</a>
                    <a href="profil.php">Mon Profil</a>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <a href="avis.php">Avis</a>
                    <?php endif; ?>
                    <a href="login.html" class="btn btn-outline">Déconnexion</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="form-section">
            <div class="container">
                <h3 class="section-title"><?php echo $edit_formation ? 'Modifier une Formation' : 'Ajouter une Formation'; ?></h3>
                
                <?php if (isset($success)): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form class="formation-form-innov" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $edit_formation ? 'edit' : 'add'; ?>">
                    <?php if ($edit_formation): ?>
                        <input type="hidden" name="id_formation" value="<?php echo $edit_formation['id_formation']; ?>">
                    <?php endif; ?>
                    
                    <label for="nom">Nom de la formation</label>
                    <input type="text" id="nom" name="nom" value="<?php echo $edit_formation ? htmlspecialchars($edit_formation['nom']) : ''; ?>" required>
                    
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?php echo $edit_formation ? htmlspecialchars($edit_formation['description']) : ''; ?></textarea>
                    
                    <label for="prix">Prix (Dinar)</label>
                    <input type="number" id="prix" name="prix" step="0.01" value="<?php echo $edit_formation ? htmlspecialchars($edit_formation['prix']) : ''; ?>" required>
                    
                    <label for="image">Image de la formation</label>
                    <div class="dropzone" id="dropzone">
                        <input type="file" id="image" name="image" accept="image/*" style="display:none;">
                        <div id="dropzone-text">Glissez-déposez une image ici ou cliquez pour sélectionner</div>
                        <img id="preview" src="#" alt="Aperçu" style="display:none;max-width:100%;margin-top:10px;border-radius:10px;">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="est_publie" name="est_publie" <?php echo $edit_formation && $edit_formation['est_publie'] ? 'checked' : ''; ?>>
                        <label for="est_publie">Publier la formation</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><?php echo $edit_formation ? 'Modifier' : 'Ajouter'; ?></button>
                </form>

                <h3 class="section-title">Vos Formations</h3>
                <div class="formations-innovantes">
                    <?php if (empty($formations)): ?>
                        <p class="no-formations">Aucune formation trouvée.</p>
                    <?php else: ?>
                        <div class="formations-grid">
                            <?php foreach ($formations as $f): ?>
                                <div class="formation-card-innov">
                                    <div class="formation-img-wrap">
                                        <?php
                                        $image = 'images/default-formation.jpg';
                                        if (!empty($f['image'])) {
                                            if (file_exists($f['image'])) {
                                                $image = $f['image'];
                                            } elseif (file_exists('uploads/' . $f['image'])) {
                                                $image = 'uploads/' . $f['image'];
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($f['nom']); ?>">
                                        <?php if ($f['est_publie']): ?>
                                            <span class="badge badge-pub">Publiée</span>
                                        <?php else: ?>
                                            <span class="badge badge-brouillon">Brouillon</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="formation-info">
                                        <h3><?php echo htmlspecialchars($f['nom']); ?></h3>
                                        <p class="desc"><?php echo htmlspecialchars(substr($f['description'], 0, 120)) . (strlen($f['description']) > 120 ? '...' : ''); ?></p>
                                        <div class="formation-meta">
                                            <span class="prix"><i class="fas fa-euro-sign"></i> <?php echo number_format($f['prix'], 2); ?></span>
                                            <span class="date"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($f['date_creation'])); ?></span>
                                        </div>
                                        <div class="formation-actions">
                                            <a href="formation.php?action=edit&id=<?php echo $f['id_formation']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Modifier</a>
                                            <a href="formation.php?action=delete&id=<?php echo $f['id_formation']; ?>" class="btn btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer cette formation ?');"><i class="fas fa-trash"></i> Supprimer</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h4>LearnUp</h4>
                    <p>La plateforme de formation en ligne qui vous accompagne dans l'acquisition de compétences professionnelles.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h4>Formations</h4>
                    <ul>
                        <li><a href="formation.php">Développement Web</a></li>
                        <li><a href="formation.php">Data Science</a></li>
                        <li><a href="formation.php">Marketing Digital</a></li>
                        <li><a href="formation.php">Design Graphique</a></li>
                        <li><a href="formation.php">Toutes les formations</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Entreprise</h4>
                    <ul>
                        <li><a href="about.php">À propos</a></li>
                        <li><a href="careers.php">Carrières</a></li>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="partners.php">Partenariats</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contact.html">Contact</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="help.php">Centre d'aide</a></li>
                        <li><a href="terms.php">Conditions d'utilisation</a></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 LearnUp. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('image');
    const preview = document.getElementById('preview');
    const dropzoneText = document.getElementById('dropzone-text');

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            showPreview(fileInput.files[0]);
        }
    });
    function showPreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            dropzoneText.style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
    </script>
</body>
</html>