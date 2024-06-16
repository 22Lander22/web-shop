<?php
session_start();
require 'db.php';

$cart = $_SESSION['cart'] ?? [];

$total_price = 0;
?>

<h2>Корзина</h2>
<ul>
    <?php foreach ($cart as $product_id => $quantity): ?>
        <?php
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        $total_price += $product['price'] * $quantity;
        ?>
        <li>
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p>Количество: <?= $quantity ?></p>
            <p>Цена: $<?= htmlspecialchars($product['price']) ?></p>
            <p>Итого: $<?= htmlspecialchars($product['price'] * $quantity) ?></p>
        </li>
    <?php endforeach; ?>
</ul>
<h3>Общая сумма: $<?= $total_price ?></h3>
<a href="#" onclick="showTab('checkout')">Перейти к оформлению</a>
