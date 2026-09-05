<?php
require_once __DIR__ . '/config/database.php';

//Получаем количество товаров 
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE type = 'product'
");

$productCount = $stmt -> fetchColumn();

//Получаем количество товаров

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM products
    WHERE type = 'material'
");
$materialCount = $stmt->fetchColumn();

//Продажа за сегодня
$stmt = $pdo->query("
    SELECT COALESCE(SUM(total),0)
    FROM sales
    WHERE DATE(created_at) = CURDATE()
");
$todaySales = $stmt->fetchColumn();

//Расходи на сегодня
$stmt = $pdo->query("
    SELECT COALESCE(SUM(amount),0)
    FROM expenses
    WHERE DATE(created_at) = CURDATE()
");
$todayExpenses = $stmt->fetchColumn();

//Количество клиентов
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM customers
");
$customerCount = $stmt->fetchColumn();

//Долги клиентов
$stmt = $pdo->query("
    SELECT COALESCE(SUM(debt),0)
    FROM customers
");
$totalDebt = $stmt->fetchColumn();

$todayProfit = $todaySales - $todayExpenses;
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Цементные блоки</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    
    <div class="app">

    <!-- Блоки меню -->
     <aside class="sidebar">
        <div class="logo">
            <div class="logo-icon">CB</div>

            <div>
                <strong>Цементные</strong>
                <span>Блоки</span>
            </div>
        </div>

        <nav>
            <a href="dashboard.php" class="menu-item active">
                <span>🏠</span>
                Главная
            </a>

            <a href="products/index.php" class="menu-item">
                <span>📦</span>
                Товары
            </a>

            <a href="recipe/index.php" class="menu-item">
                <span>📋</span>
                Техкарты
            </a>

            <a href="#" class="menu-item">
                <span>🏭</span>
                Производства
            </a>

            <a href="#" class="menu-item">
                <span>🧾</span>
                Продажи
            </a>

            <a href="invoices/index.php" class="menu-item">
                <span>📋</span>
                Накладные
            </a>

            <a href="#" class="menu-item">
                <span>💰</span>
                Расходы
            </a>

            <a href="#" class="menu-item">
                <span>👥</span>
                Клиенты
            </a>

            <a href="#" class="menu-item">
                <span>📊</span>
                Отчёты
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="#" class="menu-item">
                <span>⚙️</span>
                Настройки
            </a>

        </div>

    </aside>


    <!-- Основная часть -->
    <main class="main">

        <header class="topbar">

            <div>
                <h1>Главная</h1>
                <p>Обзор работы предприятия</p>
            </div>

            <div class="date">
                <?= date('d.m.Y') ?>
            </div>

        </header>


        <!-- Карточки -->
        <section class="cards">

            <div class="card">

                <div class="card-icon">💵</div>

                <div>
                    <p>Продажи сегодня</p>

                    <h2>
                        <?= number_format($todaySales, 2, '.', ' ') ?>
                        <small>сомони</small>
                    </h2>
                </div>

            </div>


            <div class="card">

                <div class="card-icon">💸</div>

                <div>
                    <p>Расходы сегодня</p>

                    <h2>
                        <?= number_format($todayExpenses, 2, '.', ' ') ?>
                        <small>сомони</small>
                    </h2>
                </div>

            </div>


            <div class="card">

                <div class="card-icon">📈</div>

                <div>
                    <p>Результат сегодня</p>

                    <h2>
                        <?= number_format($todayProfit, 2, '.', ' ') ?>
                        <small>сомони</small>
                    </h2>
                </div>

            </div>


            <div class="card">

                <div class="card-icon">👥</div>

                <div>
                    <p>Клиенты</p>

                    <h2>
                        <?= $customerCount ?>
                    </h2>
                </div>

            </div>

        </section>


        <!-- Нижние блоки -->
        <section class="dashboard-grid">


            <!-- Склад -->
            <div class="panel">

                <div class="panel-header">

                    <div>
                        <h3>Склад</h3>
                        <p>Основная информация</p>
                    </div>

                    <span class="panel-icon">📦</span>

                </div>

                <div class="warehouse-info">

                    <div class="warehouse-row">
                        <span>Готовая продукция</span>
                        <strong><?= $productCount ?></strong>
                    </div>

                    <div class="warehouse-row">
                        <span>Материалы</span>
                        <strong><?= $materialCount ?></strong>
                    </div>

                </div>

            </div>


            <!-- Долги -->
            <div class="panel">

                <div class="panel-header">

                    <div>
                        <h3>Задолженность</h3>
                        <p>Долги клиентов</p>
                    </div>

                    <span class="panel-icon">💳</span>

                </div>

                <div class="debt">

                    <strong>
                        <?= number_format($totalDebt, 2, '.', ' ') ?>
                    </strong>

                    <span>сомони</span>

                </div>

            </div>


        </section>


        <!-- Быстрые действия -->
        <section class="quick-actions">

            <h3>Быстрые действия</h3>

            <div class="action-buttons">

                <a href="#" class="action">
                    <span>🧾</span>
                    <strong>Новая продажа</strong>
                </a>

                <a href="#" class="action">
                    <span>🏭</span>
                    <strong>Производство</strong>
                </a>

                <a href="#" class="action">
                    <span>📋</span>
                    <strong>Новая накладная</strong>
                </a>

                <a href="#" class="action">
                    <span>💰</span>
                    <strong>Добавить расход</strong>
                </a>

            </div>

        </section>

    </main>

</div>

</body>
</html>

        </nav>
     </aside>
    </div>


</body>
</html>