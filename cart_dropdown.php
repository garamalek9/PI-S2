<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="cart-empty-message">
            <i class="fas fa-lock"></i>
            <p>Veuillez vous connecter pour accéder à votre panier</p>
            <a href="login.php" class="btn btn-primary">Se connecter</a>
          </div>';
    exit();
}

$apprenant_id = $_SESSION['user_id'];

try {
    // Récupérer le panier et ses items
    $stmt = $pdo->prepare("SELECT f.id_formation, f.nom, f.prix, f.image 
                          FROM panier p
                          JOIN panier_formation pf ON p.id_panier = pf.id_panier
                          JOIN formation f ON pf.id_formation = f.id_formation
                          WHERE p.apprenant_id = ?");
    $stmt->execute([$apprenant_id]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        echo '<div class="cart-empty-message">
                <i class="fas fa-shopping-cart"></i>
                <p>Votre panier est vide</p>
                <a href="formation.php" class="btn btn-primary">Découvrir nos formations</a>
              </div>';
    } else {
        $total = 0;
        echo '<div class="cart-header">
                <h3>Votre Panier</h3>
                <span class="cart-count">' . count($items) . ' formation(s)</span>
              </div>';
        
        echo '<div class="cart-items-container">';
        foreach ($items as $item) {
            $total += $item['prix'];
            $image = !empty($item['image']) ? (file_exists($item['image']) ? $item['image'] : 'uploads/' . $item['image']) : 'images/default-formation.jpg';
            
            echo '<div class="cart-item">
                    <img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($item['nom']) . '">
                    <div class="cart-item-details">
                        <h4 class="cart-item-title">' . htmlspecialchars($item['nom']) . '</h4>
                        <div class="cart-item-price">' . number_format($item['prix'], 2) . ' €</div>
                    </div>
                    <button class="remove-item" data-id="' . $item['id_formation'] . '" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                  </div>';
        }
        echo '</div>';
        
        echo '<div class="cart-footer">
                <div class="cart-total">
                    <span>Total</span>
                    <span class="total-amount">' . number_format($total, 2) . ' €</span>
                </div>
                <button id="confirm-cart" class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    Confirmer la commande
                </button>
              </div>';
    }
} catch (PDOException $e) {
    echo '<div class="cart-error">
            <i class="fas fa-exclamation-circle"></i>
            <p>Une erreur est survenue lors du chargement du panier</p>
          </div>';
}
?>