<?php
require 'db.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Директория для загрузки изображений
$upload_dir = __DIR__ . '/uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $quantity = $_POST['quantity'];
        $category = $_POST['category'];

        // Обработка загрузки файла
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $image = $_FILES['image']['name'];
            $target_file = $upload_dir . basename($image);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Сохранение продукта в базу данных
                $stmt = $pdo->prepare('INSERT INTO products (name, description, price, quantity, category, image) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $description, $price, $quantity, $category, 'uploads/' . basename($image)]);
            } else {
                echo 'Ошибка при загрузке файла.';
            }
        }
    }

    if (isset($_POST['delete_product'])) {
        $product_id = $_POST['product_id'];

        // Удаляем все записи из order_items, связанные с этим продуктом
        $stmt = $pdo->prepare('DELETE FROM order_items WHERE product_id = ?');
        $stmt->execute([$product_id]);

        // Удаляем продукт
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
    }

    if (isset($_POST['delete_order'])) {
        $order_id = $_POST['order_id'];

        // Удаляем все записи из order_items, связанные с этим заказом
        $stmt = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
        $stmt->execute([$order_id]);

        // Удаляем заказ
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->execute([$order_id]);
    }

    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
}

$categories = ['Кирпич', 'Цемент', 'Гипсокартон', 'Плитка', 'Краска'];
$products_by_category = [];

// Получение продуктов по категориям
foreach ($categories as $category) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category = ?');
    $stmt->execute([$category]);
    $products_by_category[$category] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Заполнение order_number значениями, если они пусты
$stmt = $pdo->prepare('SELECT id FROM orders WHERE order_number IS NULL');
$stmt->execute();
$orders_without_number = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($orders_without_number)) {
    $stmt = $pdo->prepare('SELECT MAX(order_number) AS max_order_number FROM orders');
    $stmt->execute();
    $max_order_number = $stmt->fetch()['max_order_number'];
    if (is_null($max_order_number)) {
        $max_order_number = 0;
    }
    $stmt = $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?');
    foreach ($orders_without_number as $order) {
        $max_order_number++;
        $stmt->execute([$max_order_number, $order['id']]);
    }
}

// Получение данных о заказах
$stmt = $pdo->prepare('SELECT orders.*, GROUP_CONCAT(CONCAT(products.name, " (x", order_items.quantity, ")") SEPARATOR ", ") AS product_names 
                       FROM orders
                       LEFT JOIN order_items ON orders.id = order_items.order_id
                       LEFT JOIN products ON order_items.product_id = products.id
                       GROUP BY orders.id
                       ORDER BY orders.order_number');
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Админ панель</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            flex-grow: 1;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        .header h1 {
            margin: 0;
        }
        .header a, .header button {
            text-decoration: none;
            color: #007bff;
            background-color: transparent;
            border: none;
            cursor: pointer;
        }
        .header a:hover, .header button:hover {
            text-decoration: underline;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .form-container h2 {
            margin-top: 0;
        }
        .form-container form {
            display: flex;
            flex-wrap: wrap;
        }
        .form-container label {
            width: 100%;
            margin-bottom: 5px;
        }
        .form-container input,
        .form-container select,
        .form-container textarea {
            width: calc(50% - 10px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-container textarea {
            resize: vertical;
        }
        .form-container button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #218838;
        }
        .product-list {
            list-style: none;
            padding: 0;
        }
        .product-list li {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
        }
        .product-list li h3 {
            margin-top: 0;
        }
        .product-list li img {
            max-width: 100px;
        }
        .tabs {
            display: flex;
        }
        .tabs button {
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #f4f4f4;
            cursor: pointer;
            flex: 1;
        }
        .tabs button.active {
            background-color: #fff;
            border-bottom: none;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function showTab(tabId) {
            var tabs = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabId).classList.add('active');

            var tabButtons = document.querySelectorAll('.tabs button');
            tabButtons.forEach(button => button.classList.remove('active'));
            document.querySelector(`button[data-tab="${tabId}"]`).classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Админ панель</h1>
            <a href="index.php">На главную</a>
            <form action="admin_dashboard.php" method="post">
                <button type="submit" name="logout">Выйти</button>
            </form>
        </div>
        <div class="tabs">
            <button data-tab="tab-products" onclick="showTab('tab-products')" class="active">Продукты</button>
            <button data-tab="tab-orders" onclick="showTab('tab-orders')">Заказы</button>
        </div>
        <div id="tab-products" class="tab-content active">
            <div class="form-container">
                <h2>Добавить продукт</h2>
                <form action="admin_dashboard.php" method="post" enctype="multipart/form-data">
                    <label for="name">Название:</label>
                    <input type="text" id="name" name="name" required>
                    <label for="description">Описание:</label>
                    <textarea id="description" name="description" required></textarea>
                    <label for="price">Цена:</label>
                    <input type="number" id="price" name="price" step="0.01" required>
                    <label for="quantity">Количество:</label>
                    <input type="number" id="quantity" name="quantity" required>
                    <label for="category">Категория:</label>
                    <select id="category" name="category" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="image">Изображение:</label>
                    <input type="file" id="image" name="image" required>
                    <button type="submit" name="add_product">Добавить продукт</button>
                </form>
            </div>
            <div class="form-container">
                <h2>Удалить продукт</h2>
                <form action="admin_dashboard.php" method="post">
                    <label for="product_id">ID продукта:</label>
                    <input type="number" id="product_id" name="product_id" required>
                    <button type="submit" name="delete_product">Удалить продукт</button>
                </form>
            </div>
            <div class="form-container">
                <h2>Список продуктов</h2>
                <div class="tabs">
                    <?php foreach ($categories as $index => $category): ?>
                        <button data-tab="tab-<?= $index ?>" onclick="showTab('tab-<?= $index ?>')" class="<?= $index === 0 ? 'active' : '' ?>"><?= htmlspecialchars($category) ?></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($categories as $index => $category): ?>
                    <div id="tab-<?= $index ?>" class="tab-content <?= $index === 0 ? 'active' : '' ?>">
                        <ul class="product-list">
                            <?php if (!empty($products_by_category[$category])): ?>
                                <?php foreach ($products_by_category[$category] as $product): ?>
                                    <li>
                                        <strong>ID:</strong> <?= htmlspecialchars($product['id']) ?><br>
                                        <strong>Название:</strong> <?= htmlspecialchars($product['name']) ?><br>
                                        <strong>Описание:</strong> <?= htmlspecialchars($product['description']) ?><br>
                                        <strong>Цена:</strong> ₽<?= htmlspecialchars($product['price']) ?><br>
                                        <strong>Количество:</strong> <?= htmlspecialchars($product['quantity']) ?><br>
                                        <strong>Изображение:</strong> <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>"><br>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>Нет продуктов в этой категории</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="tab-orders" class="tab-content">
            <h2>Список заказов</h2>
            <div class="form-container">
                <h2>Удалить заказ</h2>
                <form action="admin_dashboard.php" method="post">
                    <label for="order_id">ID заказа:</label>
                    <input type="number" id="order_id" name="order_id" required>
                    <button type="submit" name="delete_order">Удалить заказ</button>
                </form>
            </div>
            <ul class="order-list">
                <?php foreach ($orders as $order): ?>
                    <li>
                        <strong>ID заказа:</strong> <?= htmlspecialchars($order['id']) ?><br>
                        <strong>Номер заказа:</strong> <?= htmlspecialchars($order['order_number']) ?><br>
                        <strong>Телефон:</strong> <?= htmlspecialchars($order['phone_number']) ?><br>
                        <strong>Состав заказа:</strong> <?= htmlspecialchars($order['product_names']) ?><br>
                        <strong>Общая стоимость:</strong> ₽<?= htmlspecialchars($order['total_price']) ?><br>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>
