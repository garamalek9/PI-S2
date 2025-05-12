<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_formateur']) && $_SESSION['is_formateur'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_formation'])) {
    $formation_id = $_POST['id_formation'];
    $apprenant_id = $_SESSION['user_id'];

    try {
        // Vérifier si l'apprenant a déjà un panier
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE apprenant_id = ?");
        $stmt->execute([$apprenant_id]);
        $panier = $stmt->fetch();

        if (!$panier) {
            // Créer un nouveau panier si inexistant
            $stmt = $pdo->prepare("INSERT INTO panier (apprenant_id) VALUES (?)");
            $stmt->execute([$apprenant_id]);
            $panier_id = $pdo->lastInsertId();
        } else {
            $panier_id = $panier['id_panier'];
        }

        // Vérifier si la formation est déjà dans le panier
        $stmt = $pdo->prepare("SELECT * FROM panier_formation WHERE id_panier = ? AND id_formation = ?");
        $stmt->execute([$panier_id, $formation_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cette formation est déjà dans votre panier']);
            exit();
        }

        // Ajouter la formation au panier
        $stmt = $pdo->prepare("INSERT INTO panier_formation (id_panier, id_formation) VALUES (?, ?)");
        $stmt->execute([$panier_id, $formation_id]);

        // Compter le nombre d'items dans le panier
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM panier_formation WHERE id_panier = ?");
        $stmt->execute([$panier_id]);
        $count = $stmt->fetch()['count'];

        echo json_encode(['success' => true, 'count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
}
?>