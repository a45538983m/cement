<?php

require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Создание техкарты
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_recipe'])) {

    $product_id = (int)($_POST['product_id'] ?? 0);
    $recipe_quantity = (float)($_POST['recipe_quantity'] ?? 0);

    $material_ids = $_POST['material_id'] ?? [];
    $quantities = $_POST['material_quantity'] ?? [];

    if ($product_id <= 0) {

        $error = 'Выберите готовую продукцию.';

    } elseif ($recipe_quantity <= 0) {

        $error = 'Укажите количество продукции.';

    } elseif (empty($material_ids)) {

        $error = 'Добавьте хотя бы один материал.';

    } else {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Проверяем, нет ли уже техкарты
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM recipes
                WHERE product_id = ?
                LIMIT 1
            ");

            $stmt->execute([$product_id]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'Для этого товара техкарта уже существует.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Создаём техкарту
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO recipes
                (product_id, quantity)
                VALUES (?, ?)
            ");

            $stmt->execute([
                $product_id,
                $recipe_quantity
            ]);

            $recipe_id = $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Добавляем материалы
            |--------------------------------------------------------------------------
            */

            $stmtItem = $pdo->prepare("
                INSERT INTO recipe_items
                (recipe_id, material_id, quantity)
                VALUES (?, ?, ?)
            ");


            foreach ($material_ids as $index => $material_id) {

                $material_id = (int)$material_id;

                $quantity = (float)(
                    $quantities[$index] ?? 0
                );

                if ($material_id <= 0 || $quantity <= 0) {
                    continue;
                }

                $stmtItem->execute([
                    $recipe_id,
                    $material_id,
                    $quantity
                ]);
            }


            $pdo->commit();

            header('Location: index.php?success=created');

            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Ошибка базы данных.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Удаление техкарты
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_recipe'])
) {

    $recipe_id = (int)($_POST['recipe_id'] ?? 0);

    if ($recipe_id > 0) {

        try {

            $stmt = $pdo->prepare("
                DELETE FROM recipe_items
                WHERE recipe_id = ?
            ");

            $stmt->execute([$recipe_id]);


            $stmt = $pdo->prepare("
                DELETE FROM recipes
                WHERE id = ?
            ");

            $stmt->execute([$recipe_id]);


            header('Location: index.php?success=deleted');

            exit;

        } catch (PDOException $e) {

            $error = 'Не удалось удалить техкарту.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Сообщения
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'created') {
        $message = 'Техкарта успешно создана.';
    }

    if ($_GET['success'] === 'deleted') {
        $message = 'Техкарта удалена.';
    }
}


/*
|--------------------------------------------------------------------------
| Получаем готовую продукцию
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, name, unit, price
    FROM products
    WHERE type = 'product'
    ORDER BY name
");

$products = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Получаем материалы
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, name, unit, price
    FROM products
    WHERE type = 'material'
    ORDER BY name
");

$materials = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Получаем техкарты
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        r.id,
        r.quantity,
        p.name AS product_name,
        p.unit AS product_unit
    FROM recipes r
    INNER JOIN products p
        ON p.id = r.product_id
    ORDER BY r.id DESC
");

$recipes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Получаем материалы для каждой техкарты
|--------------------------------------------------------------------------
*/

$recipeItems = [];

if (!empty($recipes)) {

    $recipeIds = array_column($recipes, 'id');

    $placeholders = implode(
        ',',
        array_fill(0, count($recipeIds), '?')
    );

    $stmt = $pdo->prepare("
        SELECT
            ri.recipe_id,
            ri.quantity,
            p.name,
            p.unit
        FROM recipe_items ri
        INNER JOIN products p
            ON p.id = ri.material_id
        WHERE ri.recipe_id IN ($placeholders)
        ORDER BY ri.id
    ");

    $stmt->execute($recipeIds);

    $items = $stmt->fetchAll();

    foreach ($items as $item) {

        $recipeItems[$item['recipe_id']][] = $item;
    }
}

?>

<!DOCTYPE html>
<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Техкарты — Цементные блоки
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

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

        .content-panel {
            background: white;
            border: 1px solid #e7e9ed;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .content-panel h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .panel-description {
            color: #777;
            font-size: 13px;
            margin-bottom: 22px;
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 200px;
            gap: 15px;
            margin-bottom: 20px;
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
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 0 11px;
            font-size: 14px;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .materials-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .material-row {
            display: grid;
            grid-template-columns: 1fr 180px 45px;
            gap: 10px;
            margin-bottom: 10px;
        }

        .material-row select,
        .material-row input {
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 0 10px;
            font-size: 14px;
        }

        .remove-material {
            border: none;
            border-radius: 7px;
            background: #fee2e2;
            color: #dc2626;
            cursor: pointer;
            font-size: 18px;
        }

        .remove-material:hover {
            background: #fecaca;
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

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .recipe-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 14px;
        }

        .recipe-card:last-child {
            margin-bottom: 0;
        }

        .recipe-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .recipe-product {
            font-size: 16px;
            font-weight: 600;
        }

        .recipe-quantity {
            color: #2563eb;
            font-size: 14px;
            margin-top: 4px;
        }

        .recipe-materials {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .material-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
        }

        .material-card-name {
            font-size: 13px;
            color: #555;
            margin-bottom: 5px;
        }

        .material-card-value {
            font-size: 15px;
            font-weight: 600;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 35px;
        }

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .material-row {
                grid-template-columns: 1fr;
            }

            .recipe-materials {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 600px) {

            .recipe-materials {
                grid-template-columns: 1fr;
            }

            .recipe-header {
                align-items: flex-start;
                gap: 10px;
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

            <a
                href="../dashboard.php"
                class="menu-item"
            >
                <span>🏠</span>
                Главная
            </a>

            <a
                href="../products/"
                class="menu-item"
            >
                <span>📦</span>
                Товары
            </a>

            <a
                href="index.php"
                class="menu-item active"
            >
                <span>📋</span>
                Техкарты
            </a>

            <a
                href="../production/"
                class="menu-item"
            >
                <span>🏭</span>
                Производство
            </a>

            <a
                href="../sales/"
                class="menu-item"
            >
                <span>🧾</span>
                Продажи
            </a>

            <a
                href="../invoices/index.php"
                class="menu-item"
            >
                <span>📄</span>
                Накладные
            </a>

            <a
                href="../expenses/"
                class="menu-item"
            >
                <span>💰</span>
                Расходы
            </a>

            <a
                href="../customers/"
                class="menu-item"
            >
                <span>👥</span>
                Клиенты
            </a>

            <a
                href="../reports/"
                class="menu-item"
            >
                <span>📊</span>
                Отчёты
            </a>

        </nav>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <div class="page-header">

            <div>

                <h1>
                    Техкарты
                </h1>

                <p>
                    Рецептура производства продукции
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


        <!-- СОЗДАНИЕ ТЕХКАРТЫ -->

        <div class="content-panel">

            <h2>
                Новая техкарта
            </h2>

            <p class="panel-description">
                Укажите норму материалов для определённого количества продукции.
            </p>


            <?php if (empty($products)): ?>

                <div class="empty">

                    Сначала добавьте готовую продукцию
                    в разделе «Товары».

                </div>

            <?php elseif (empty($materials)): ?>

                <div class="empty">

                    Сначала добавьте материалы
                    в разделе «Товары».

                </div>

            <?php else: ?>


                <form
                    method="POST"
                    id="recipeForm"
                >

                    <div class="form-grid">

                        <div class="form-group">

                            <label>
                                Готовая продукция
                            </label>

                            <select
                                name="product_id"
                                required
                            >

                                <option value="">
                                    Выберите товар
                                </option>

                                <?php foreach ($products as $product): ?>

                                    <option
                                        value="<?= (int)$product['id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $product['name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Количество продукции
                            </label>

                            <input
                                type="number"
                                name="recipe_quantity"
                                min="0.001"
                                step="0.001"
                                placeholder="Например: 100"
                                required
                            >

                        </div>

                    </div>


                    <div class="materials-title">
                        Материалы
                    </div>


                    <div id="materialsContainer">

                        <div class="material-row">

                            <select
                                name="material_id[]"
                                required
                            >

                                <option value="">
                                    Выберите материал
                                </option>

                                <?php foreach ($materials as $material): ?>

                                    <option
                                        value="<?= (int)$material['id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $material['name']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <input
                                type="number"
                                name="material_quantity[]"
                                min="0.001"
                                step="0.001"
                                placeholder="Количество"
                                required
                            >


                            <button
                                type="button"
                                class="remove-material"
                                onclick="removeMaterial(this)"
                            >
                                ×
                            </button>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            onclick="addMaterial()"
                        >
                            + Добавить материал
                        </button>

                        <button
                            type="submit"
                            name="create_recipe"
                            class="btn btn-primary"
                        >
                            Сохранить техкарту
                        </button>

                    </div>

                </form>


            <?php endif; ?>

        </div>


        <!-- СПИСОК ТЕХКАРТ -->

        <div class="content-panel">

            <h2>
                Существующие техкарты
            </h2>

            <p class="panel-description">
                Сохранённые рецептуры производства.
            </p>


            <?php if (empty($recipes)): ?>

                <div class="empty">

                    Техкарт пока нет.

                </div>

            <?php else: ?>


                <?php foreach ($recipes as $recipe): ?>

                    <div class="recipe-card">


                        <div class="recipe-header">

                            <div>

                                <div class="recipe-product">

                                    <?= htmlspecialchars(
                                        $recipe['product_name']
                                    ) ?>

                                </div>

                                <div class="recipe-quantity">

                                    Норма на:

                                    <?= number_format(
                                        (float)$recipe['quantity'],
                                        3,
                                        '.',
                                        ' '
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $recipe['product_unit']
                                    ) ?>

                                </div>

                            </div>


                            <form
                                method="POST"
                                onsubmit="
                                    return confirm(
                                        'Удалить эту техкарту?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="recipe_id"
                                    value="<?= (int)$recipe['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_recipe"
                                    class="btn btn-danger"
                                >
                                    Удалить
                                </button>

                            </form>

                        </div>


                        <div class="recipe-materials">


                            <?php

                            $items =
                                $recipeItems[$recipe['id']]
                                ?? [];

                            ?>


                            <?php foreach ($items as $item): ?>

                                <div class="material-card">

                                    <div class="material-card-name">

                                        <?= htmlspecialchars(
                                            $item['name']
                                        ) ?>

                                    </div>

                                    <div class="material-card-value">

                                        <?= number_format(
                                            (float)$item['quantity'],
                                            3,
                                            '.',
                                            ' '
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $item['unit']
                                        ) ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>


                        </div>

                    </div>

                <?php endforeach; ?>


            <?php endif; ?>

        </div>

    </main>

</div>


<script>

function addMaterial() {

    const container =
        document.getElementById('materialsContainer');

    const firstRow =
        container.querySelector('.material-row');

    const newRow =
        firstRow.cloneNode(true);


    newRow.querySelector('select').value = '';

    newRow.querySelector('input').value = '';


    container.appendChild(newRow);
}


function removeMaterial(button) {

    const container =
        document.getElementById('materialsContainer');

    const rows =
        container.querySelectorAll('.material-row');


    if (rows.length <= 1) {

        alert('Должен остаться хотя бы один материал.');

        return;
    }


    button.parentElement.remove();
}

</script>

</body>

</html>