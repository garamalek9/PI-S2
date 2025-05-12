<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_formateur']) && $_SESSION['is_formateur'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$apprenant_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Récupérer le panier
    $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE apprenant_id = ?");
    $stmt->execute([$apprenant_id]);
    $panier = $stmt->fetch();

    if (!$panier) {
        throw new Exception("Panier vide");
    }

    $panier_id = $panier['id_panier'];

    // Récupérer les formations du panier
    $stmt = $pdo->prepare("SELECT id_formation FROM panier_formation WHERE id_panier = ?");
    $stmt->execute([$panier_id]);
    $formations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($formations)) {
        throw new Exception("Panier vide");
    }

    // Créer une commande pour chaque formation
    foreach ($formations as $formation_id) {
        $stmt = $pdo->prepare("INSERT INTO commande (apprenant_id, id_formation, date_commande, statut) 
                              VALUES (?, ?, NOW(), 'en cours')");
        $stmt->execute([$apprenant_id, $formation_id]);
    }

    // Vider le panier
    $stmt = $pdo->prepare("DELETE FROM panier_formation WHERE id_panier = ?");
    $stmt->execute([$panier_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Commande confirmée avec succès']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>