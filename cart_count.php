<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$apprenant_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT COUNT(pf.id_formation) as count 
                          FROM panier p
                          JOIN panier_formation pf ON p.id_panier = pf.id_panier
                          WHERE p.apprenant_id = ?");
    $stmt->execute([$apprenant_id]);
    $result = $stmt->fetch();

    echo json_encode(['count' => $result['count'] ?? 0]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
?>