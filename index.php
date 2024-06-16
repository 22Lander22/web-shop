<?php
session_start();
require 'db.php';

$categories = ['Кирпич', 'Цемент', 'Гипсокартон', 'Плитка', 'Краска'];
$products_by_category = [];

// Получение продуктов по категориям
foreach ($categories as $category) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category = ?');
    $stmt->execute([$category]);
    $products_by_category[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Инициализация корзины
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Обработка добавления товара в корзину
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];

    // Проверка наличия товара в корзине
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

// Обработка удаления товара из корзины
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_from_cart'])) {
    $product_id = $_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
}

// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $total_price = 0;
    $phone_number = $_POST['phone_number'];

    // Вычисление общей суммы заказа
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $pdo->prepare('SELECT price, quantity FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            if ($product['quantity'] < $quantity) {
                echo 'Недостаточно товара на складе для заказа.';
                exit();
            }
            $total_price += $product['price'] * $quantity;
        }
    }

    try {
        // Вставка заказа в таблицу orders
        $stmt = $pdo->prepare('INSERT INTO orders (total_price, phone_number) VALUES (?, ?)');
        $stmt->execute([$total_price, $phone_number]);
        $order_id = $pdo->lastInsertId();

        // Вставка товаров в таблицу order_items и обновление количества товаров
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);

            // Обновление количества товара в базе данных
            $stmt_update = $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?');
            $stmt_update->execute([$quantity, $product_id]);

            // Обновление количества товара в массиве products_by_category
            foreach ($products_by_category as &$products) {
                foreach ($products as &$prod) {
                    if ($prod['id'] == $product_id) {
                        $prod['quantity'] -= $quantity;
                    }
                }
            }
        }

        // Очистка корзины
        $_SESSION['cart'] = [];
        $order_success = true;

        // Путь к изображению QR-кода
        $qrCodePath = 'orders/qr_code.png';
    } catch (PDOException $e) {
        echo 'Ошибка при оформлении заказа: ' . $e->getMessage();
    }
}

// Получение данных о товарах в корзине
$cart_items = [];
if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Магазин Строительных Материалов</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { width: 80%; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; }
        .header h1 { margin: 0; }
        .header a { text-decoration: none; color: #007bff; }
        .header a:hover { text-decoration: underline; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tabs .tab-button { padding: 10px; border: 1px solid #ccc; background-color: #f4f4f4; cursor: pointer; flex: 1; text-align: center; }
        .tabs .tab-button.active { background-color: #fff; border-bottom: none; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .product-list { list-style: none; padding: 0; }
        .product-list li { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); margin-bottom: 10px; }
        .product-list li h3 { margin-top: 0; }
        .product-list li img { max-width: 100px; }
    </style>
    <script>
        function showTab(tabId) {
            var tabs = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabId).classList.add('active');

            var tabButtons = document.querySelectorAll('.tabs .tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            document.querySelector(`.tab-button[data-tab="${tabId}"]`).classList.add('active');
        }

        function moveToCheckout() {
            // Перемещение товаров из корзины в оформление заказа
            var cartItems = document.querySelectorAll('#tab-cart .product-list li');
            var checkoutList = document.querySelector('#tab-checkout .product-list');
            checkoutList.innerHTML = '';
            cartItems.forEach(function(item) {
                checkoutList.appendChild(item.cloneNode(true));
            });
            showTab('tab-checkout');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Магазин Строительных Материалов</h1>
            <a href="admin_dashboard.php">Админ панель</a>
        </div>
        <div class="tabs">
            <button class="tab-button" data-tab="tab-cart" onclick="showTab('tab-cart')">Корзина</button>
            <button class="tab-button" data-tab="tab-checkout" onclick="showTab('tab-checkout')">Оформление заказа</button>
        </div>
        <div class="tabs">
            <?php foreach ($categories as $index => $category): ?>
                <button class="tab-button" data-tab="tab-<?= $index ?>" onclick="showTab('tab-<?= $index ?>')" class="<?= $index === 0 ? 'active' : '' ?>"><?= htmlspecialchars($category) ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($categories as $index => $category): ?>
            <div id="tab-<?= $index ?>" class="tab-content <?= $index === 0 ? 'active' : '' ?>">
                <ul class="product-list">
                    <?php foreach ($products_by_category[$category] as $product): ?>
                        <li>
                            <h3><?= htmlspecialchars($product['name']) ?></h3>
                            <p><?= htmlspecialchars($product['description']) ?></p>
                            <p>Цена: ₽<?= htmlspecialchars($product['price']) ?></p>
                            <p>Количество: <?= htmlspecialchars($product['quantity']) ?></p>
                            <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            <form action="index.php" method="post">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <label for="quantity">Количество:</label>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= $product['quantity'] ?>">
                                <button type="submit" name="add_to_cart">Добавить в корзину</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <div id="tab-cart" class="tab-content">
            <h2>Корзина</h2>
            <?php if (empty($cart_items)): ?>
                <p>Ваша корзина пуста.</p>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach ($cart_items as $item): ?>
                        <li>
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p>Количество: <?= $_SESSION['cart'][$item['id']] ?></p>
                            <p>Цена: ₽<?= htmlspecialchars($item['price']) ?></p>
                            <p>Итого: ₽<?= htmlspecialchars($item['price'] * $_SESSION['cart'][$item['id']]) ?></p>
                            <form action="index.php" method="post">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="remove_from_cart">Удалить из корзины</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form action="index.php" method="post">
                    <label for="phone_number">Номер телефона:</label>
                    <input type="text" id="phone_number" name="phone_number" required>
                    <button type="submit" name="checkout">Оформить заказ</button>
                </form>
            <?php endif; ?>
        </div>
        <div id="tab-checkout" class="tab-content">
            <h2>Оформление заказа</h2>
            <?php if (isset($order_success) && $order_success): ?>
                <p>Ваш заказ был успешно оформлен! Пожалуйста, оплатите с помощью QR-кода ниже.</p>
                <img src="<?= htmlspecialchars($qrCodePath) ?>" alt="QR код для оплаты" style="max-width: 200px;">
            <?php else: ?>
                <p>Пожалуйста, добавьте товары в корзину для оформления заказа.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
