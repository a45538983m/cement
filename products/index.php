<?php

require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Добавление товара
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $unit = trim($_POST['unit'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    if ($name === '') {
        $error = 'Введите название товара.';
    } elseif (!in_array($type, ['product', 'material'], true)) {
        $error = 'Выберите правильный тип.';
    } elseif ($unit === '') {
        $error = 'Введите единицу измерения.';
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO products
                (name, type, unit, price, stock)
                VALUES
                (?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $name,
                $type,
                $unit,
                $price
            ]);

            header('Location: index.php?success=added');
            exit;

        } catch (PDOException $e) {

            $error = 'Ошибка при добавлении товара.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Удаление товара
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            header('Location: index.php?success=deleted');
            exit;

        } catch (PDOException $e) {

            $error = 'Нельзя удалить этот товар. Возможно, он уже используется.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Сообщения
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $message = 'Товар успешно добавлен.';
    }

    if ($_GET['success'] === 'deleted') {
        $message = 'Товар удалён.';
    }
}


/*
|--------------------------------------------------------------------------
| Получаем товары
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT *
    FROM products
    ORDER BY id DESC
");

$products = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Товары — Цементные блоки</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 27px;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #777;
            font-size: 14px;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 11px 17px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .content-panel {
            background: white;
            border: 1px solid #e7e9ed;
            border-radius: 12px;
            padding: 20px;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .add-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 13px;
            color: #555;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 0 10px;
            font-size: 14px;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            background: #f8fafc;
            color: #555;
            font-size: 13px;
            font-weight: 600;
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 14px 13px;
            border-bottom: 1px solid #eef0f2;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-product {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-material {
            background: #fef3c7;
            color: #92400e;
        }

        .stock {
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #777;
        }

        @media (max-width: 1000px) {

            .add-form {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 600px) {

            .add-form {
                grid-template-columns: 1fr;
            }

            .page-header {
                align-items: flex-start;
                gap: 15px;
                flex-direction: column;
            }

        }

    </style>

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">

            <div class="logo-icon">
                CB
            </div>

            <div>
                <strong>Цементные</strong>
                <span>Блоки</span>
            </div>

        </div>


        <nav>

            <a href="../dashboard.php" class="menu-item">
                <span>🏠</span>
                Главная
            </a>

            <a href="index.php" class="menu-item active">
                <span>📦</span>
                Товары
            </a>

            <a href="../recipe/index.php" class="menu-item">
                <span>📋</span>
                Техкарты
            </a>

            <a href="../production/" class="menu-item">
                <span>🏭</span>
                Производство
            </a>

            <a href="../sales/" class="menu-item">
                <span>🧾</span>
                Продажи
            </a>

            <a href="../invoices/index.php" class="menu-item">
                <span>📋</span>
                Накладные
            </a>

            <a href="../expenses/" class="menu-item">
                <span>💰</span>
                Расходы
            </a>

            <a href="../customers/" class="menu-item">
                <span>👥</span>
                Клиенты
            </a>

            <a href="../reports/" class="menu-item">
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


    <!-- MAIN -->

    <main class="main">

        <div class="page-header">

            <div>

                <h1>Товары</h1>

                <p>
                    Продукция и материалы
                </p>

            </div>

        </div>


        <?php if ($message): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="content-panel">


            <!-- ФОРМА ДОБАВЛЕНИЯ -->

            <form method="POST" class="add-form">

                <div class="form-group">

                    <label>
                        Название
                    </label>

                    <input
                        type="text"
                        name="name"
                        placeholder="Например: Блок 20×20×40"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Тип
                    </label>

                    <select name="type" required>

                        <option value="product">
                            Готовая продукция
                        </option>

                        <option value="material">
                            Материал
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Единица
                    </label>

                    <select name="unit" required>

                        <option value="шт.">
                            шт.
                        </option>

                        <option value="кг">
                            кг
                        </option>

                        <option value="т">
                            тонна
                        </option>

                        <option value="л">
                            литр
                        </option>

                        <option value="мешок">
                            мешок
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Цена
                    </label>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        value="0"
                    >

                </div>


                <button
                    type="submit"
                    name="add_product"
                    class="btn btn-primary"
                >
                    + Добавить
                </button>

            </form>


            <!-- ТАБЛИЦА -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Название
                            </th>

                            <th>
                                Тип
                            </th>

                            <th>
                                Единица
                            </th>

                            <th>
                                Цена
                            </th>

                            <th>
                                Остаток
                            </th>

                            <th>
                                Действия
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($products)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty"
                            >
                                Пока нет товаров.
                                Добавьте первый товар выше.
                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>
                                    <?= (int)$product['id'] ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </strong>
                                </td>

                                <td>

                                    <?php if ($product['type'] === 'product'): ?>

                                        <span class="badge badge-product">
                                            Продукция
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-material">
                                            Материал
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($product['unit']) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$product['price'],
                                        2,
                                        '.',
                                        ' '
                                    ) ?>
                                    сомони
                                </td>

                                <td class="stock">

                                    <?= number_format(
                                        (float)$product['stock'],
                                        3,
                                        '.',
                                        ' '
                                    ) ?>

                                    <?= htmlspecialchars($product['unit']) ?>

                                </td>

                                <td>

                                    <div class="actions">

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Удалить этот товар?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$product['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_product"
                                                class="btn btn-danger"
                                            >
                                                Удалить
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>

</html>