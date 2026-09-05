
<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| Удаление накладной
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_invoice'])
) {

    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $password = $_POST['delete_password'] ?? '';

    if ($invoice_id <= 0) {

        $error = 'Накладная не найдена.';

    } elseif (!hash_equals(
        DELETE_INVOICE_PASSWORD,
        $password
    )) {

        $error = 'Неверный пароль. Накладная не удалена.';

    } else {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Получаем товары накладной
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    product_id,
                    quantity
                FROM invoice_items
                WHERE invoice_id = ?
            ");

            $stmt->execute([
                $invoice_id
            ]);

            $items = $stmt->fetchAll();


            /*
            |--------------------------------------------------------------------------
            | Проверяем существование накладной
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM invoices
                WHERE id = ?
                AND type = 'income'
                LIMIT 1
            ");

            $stmt->execute([
                $invoice_id
            ]);

            $invoice = $stmt->fetch();


            if (!$invoice) {

                throw new Exception(
                    'Накладная не найдена.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Возвращаем склад назад
            |--------------------------------------------------------------------------
            |
            | Например:
            |
            | Было 1000 кг
            | В накладной +500 кг
            |
            | После удаления:
            | 1000 - 500 = 500 кг
            |
            */

            $stmtStock = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
            ");


            foreach ($items as $item) {

                $stmtStock->execute([
                    $item['quantity'],
                    $item['product_id']
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Удаляем позиции накладной
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM invoice_items
                WHERE invoice_id = ?
            ");

            $stmt->execute([
                $invoice_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Удаляем саму накладную
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM invoices
                WHERE id = ?
                AND type = 'income'
            ");

            $stmt->execute([
                $invoice_id
            ]);


            $pdo->commit();


            header(
                'Location: index.php?success=deleted'
            );

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
| Создание приходной накладной
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_invoice'])
) {

    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];


    if (empty($product_ids)) {

        $error = 'Добавьте хотя бы один товар.';

    } else {

        try {

            $pdo->beginTransaction();

            $items = [];

            $grandTotal = 0;


            /*
            |--------------------------------------------------------------------------
            | Проверяем товары
            |--------------------------------------------------------------------------
            */

            foreach (
                $product_ids as $index => $product_id
            ) {

                $product_id = (int)$product_id;

                $quantity = (float)(
                    $quantities[$index] ?? 0
                );

                $price = (float)(
                    $prices[$index] ?? 0
                );


                if (
                    $product_id <= 0
                    || $quantity <= 0
                ) {
                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Проверяем существование товара
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        name,
                        unit
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $product_id
                ]);

                $product = $stmt->fetch();


                if (!$product) {
                    continue;
                }


                $total =
                    $quantity * $price;


                $grandTotal += $total;


                $items[] = [

                    'product_id' =>
                        $product_id,

                    'quantity' =>
                        $quantity,

                    'price' =>
                        $price,

                    'total' =>
                        $total
                ];
            }


            if (empty($items)) {

                throw new Exception(
                    'Добавьте товары с правильным количеством.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Создаём накладную
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO invoices
                (
                    type,
                    customer_name,
                    total
                )
                VALUES
                (
                    'income',
                    'Поставщик',
                    ?
                )
            ");

            $stmt->execute([
                $grandTotal
            ]);


            $invoice_id =
                $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Добавляем товары накладной
            |--------------------------------------------------------------------------
            */

            $stmtItem = $pdo->prepare("
                INSERT INTO invoice_items
                (
                    invoice_id,
                    product_id,
                    quantity,
                    price,
                    total
                )
                VALUES
                (?, ?, ?, ?, ?)
            ");


            /*
            |--------------------------------------------------------------------------
            | Обновляем остаток
            |--------------------------------------------------------------------------
            */

            $stmtStock = $pdo->prepare("
                UPDATE products
                SET stock = stock + ?
                WHERE id = ?
            ");


            foreach ($items as $item) {

                $stmtItem->execute([

                    $invoice_id,

                    $item['product_id'],

                    $item['quantity'],

                    $item['price'],

                    $item['total']
                ]);


                $stmtStock->execute([

                    $item['quantity'],

                    $item['product_id']
                ]);
            }


            $pdo->commit();


            header(
                'Location: index.php?success=created'
            );

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
| Сообщения
|--------------------------------------------------------------------------
*/

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'created') {

        $message =
            'Приходная накладная успешно создана.';
    }


    if ($_GET['success'] === 'deleted') {

        $message =
            'Накладная удалена, остатки склада восстановлены.';
    }
}


/*
|--------------------------------------------------------------------------
| Получаем товары
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        name,
        type,
        unit,
        price,
        stock
    FROM products
    ORDER BY name
");

$products = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| История накладных
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        total,
        created_at
    FROM invoices
    WHERE type = 'income'
    ORDER BY id DESC
");

$invoices = $stmt->fetchAll();

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
        Накладные — Цементные блоки
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


        .description {

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


        .invoice-row {

            display: grid;

            grid-template-columns:
                2fr
                140px
                140px
                140px
                45px;

            gap: 10px;

            margin-bottom: 10px;
        }


        .invoice-row select,
        .invoice-row input {

            height: 40px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            padding: 0 10px;

            font-size: 14px;

            background: white;
        }


        .invoice-row select:focus,
        .invoice-row input:focus {

            outline: none;

            border-color: #2563eb;
        }


        .remove-row {

            border: none;

            border-radius: 7px;

            background: #fee2e2;

            color: #dc2626;

            cursor: pointer;

            font-size: 18px;
        }


        .remove-row:hover {

            background: #fecaca;
        }


        .form-actions {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-top: 20px;

            padding-top: 20px;

            border-top: 1px solid #eee;
        }


        .buttons {

            display: flex;

            gap: 10px;
        }


        .btn {

            display: inline-block;

            border: none;

            border-radius: 8px;

            padding: 11px 17px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;

            text-decoration: none;
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


        .total-box {

            font-size: 16px;

            font-weight: 600;
        }


        .total-box span {

            color: #2563eb;

            font-size: 20px;
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

            padding: 13px;

            border-bottom: 1px solid #e5e7eb;
        }


        td {

            padding: 14px 13px;

            border-bottom: 1px solid #eef0f2;

            font-size: 14px;
        }


        .invoice-number {

            font-weight: 600;

            color: #2563eb;
        }


        .empty {

            text-align: center;

            padding: 35px;

            color: #777;
        }


        /* =========================================================
           МОДАЛЬНОЕ ОКНО УДАЛЕНИЯ
        ========================================================= */

        .modal {

            display: none;

            position: fixed;

            z-index: 9999;

            left: 0;

            top: 0;

            width: 100%;

            height: 100%;

            background: rgba(0, 0, 0, 0.45);

            align-items: center;

            justify-content: center;
        }


        .modal.show {

            display: flex;
        }


        .modal-box {

            width: 400px;

            max-width: calc(100% - 30px);

            background: white;

            border-radius: 12px;

            padding: 25px;

            box-shadow:
                0 15px 40px
                rgba(0, 0, 0, 0.20);
        }


        .modal-box h3 {

            margin: 0 0 8px;

            font-size: 19px;
        }


        .modal-box p {

            color: #777;

            font-size: 13px;

            margin-bottom: 20px;
        }


        .modal-box label {

            display: block;

            font-size: 13px;

            font-weight: 600;

            color: #555;

            margin-bottom: 6px;
        }


        .modal-box input {

            width: 100%;

            height: 42px;

            box-sizing: border-box;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            padding: 0 11px;

            font-size: 14px;

            margin-bottom: 18px;
        }


        .modal-box input:focus {

            outline: none;

            border-color: #2563eb;
        }


        .modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;
        }


        @media (max-width: 900px) {

            .invoice-row {

                grid-template-columns:
                    1fr
                    1fr;
            }


            .form-actions {

                align-items: flex-start;

                gap: 15px;

                flex-direction: column;
            }

        }


        @media (max-width: 700px) {

            .page-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }

        }

    </style>

</head>


<body>


<div class="app">


    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">


        <div class="logo">


            <div class="logo-icon">
                CB
            </div>


            <div>

                <strong>
                    Цементные
                </strong>

                <span>
                    Блоки
                </span>

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
                href="../recipes/"
                class="menu-item"
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
                href="index.php"
                class="menu-item active"
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


    <!-- =========================================================
         MAIN
    ========================================================== -->

    <main class="main">


        <div class="page-header">


            <div>

                <h1>
                    Накладные
                </h1>

                <p>
                    Приход товаров и материалов
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


        <!-- =====================================================
             НОВАЯ НАКЛАДНАЯ
        ====================================================== -->

        <div class="content-panel">


            <h2>
                Новая приходная накладная
            </h2>


            <p class="description">

                Добавьте материалы или готовую продукцию,
                которые поступили на склад.

            </p>


            <?php if (empty($products)): ?>


                <div class="empty">

                    Сначала добавьте товары
                    в разделе «Товары».

                </div>


            <?php else: ?>


                <form
                    method="POST"
                    id="invoiceForm"
                >


                    <div id="invoiceRows">


                        <div class="invoice-row">


                            <select
                                name="product_id[]"
                                required
                            >

                                <option value="">
                                    Выберите товар
                                </option>


                                <?php foreach (
                                    $products as $product
                                ): ?>


                                    <option
                                        value="<?= (int)$product['id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $product['name']
                                        ) ?>

                                        —

                                        <?= htmlspecialchars(
                                            $product['unit']
                                        ) ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                            <input
                                type="number"
                                name="quantity[]"
                                min="0.001"
                                step="0.001"
                                placeholder="Количество"
                                required
                            >


                            <input
                                type="number"
                                name="price[]"
                                min="0"
                                step="0.01"
                                placeholder="Цена"
                                required
                            >


                            <input
                                type="text"
                                class="row-total"
                                placeholder="Сумма"
                                readonly
                            >


                            <button
                                type="button"
                                class="remove-row"
                                onclick="removeRow(this)"
                            >
                                ×
                            </button>


                        </div>


                    </div>


                    <div class="form-actions">


                        <div class="buttons">


                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="addRow()"
                            >
                                + Добавить товар
                            </button>


                            <button
                                type="submit"
                                name="create_invoice"
                                class="btn btn-primary"
                            >
                                Создать накладную
                            </button>


                        </div>


                        <div class="total-box">

                            Итого:

                            <span id="grandTotal">
                                0.00
                            </span>

                            сомони

                        </div>


                    </div>


                </form>


            <?php endif; ?>


        </div>


        <!-- =====================================================
             ИСТОРИЯ НАКЛАДНЫХ
        ====================================================== -->

        <div class="content-panel">


            <h2>
                История накладных
            </h2>


            <p class="description">
                Все приходные накладные.
            </p>


            <?php if (empty($invoices)): ?>


                <div class="empty">

                    Накладных пока нет.

                </div>


            <?php else: ?>


                <div style="overflow-x:auto;">


                    <table>


                        <thead>


                            <tr>

                                <th>
                                    №
                                </th>

                                <th>
                                    Дата
                                </th>

                                <th>
                                    Тип
                                </th>

                                <th>
                                    Сумма
                                </th>

                                <th>
                                    Действия
                                </th>

                            </tr>


                        </thead>


                        <tbody>


                        <?php foreach (
                            $invoices as $invoice
                        ): ?>


                            <tr>


                                <td class="invoice-number">

                                    №<?= (int)$invoice['id'] ?>

                                </td>


                                <td>

                                    <?= date(
                                        'd.m.Y H:i',
                                        strtotime(
                                            $invoice['created_at']
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    Приход

                                </td>


                                <td>

                                    <?= number_format(
                                        (float)$invoice['total'],
                                        2,
                                        '.',
                                        ' '
                                    ) ?>

                                    сомони

                                </td>


                                <td>


                                    <a
                                        href="view.php?id=<?= (int)$invoice['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        Открыть
                                    </a>


                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        onclick="openDeleteModal(
                                            <?= (int)$invoice['id'] ?>
                                        )"
                                    >
                                        Удалить
                                    </button>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </div>


    </main>


</div>


<!-- =========================================================
     МОДАЛЬНОЕ ОКНО УДАЛЕНИЯ
========================================================== -->

<div
    id="deleteModal"
    class="modal"
    onclick="closeModalOutside(event)"
>


    <div class="modal-box">


        <h3>
            Удаление накладной
        </h3>


        <p>

            Вы действительно хотите удалить
            накладную №<strong id="deleteInvoiceNumber"></strong>?

            Остаток товара будет автоматически
            возвращён назад.

        </p>


        <form
            method="POST"
        >


            <input
                type="hidden"
                name="invoice_id"
                id="deleteInvoiceId"
            >


            <label>
                Пароль для удаления
            </label>


            <input
                type="password"
                name="delete_password"
                placeholder="Введите пароль"
                autocomplete="off"
                required
            >


            <div class="modal-actions">


                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeDeleteModal()"
                >
                    Отмена
                </button>


                <button
                    type="submit"
                    name="delete_invoice"
                    class="btn btn-danger"
                >
                    Удалить
                </button>


            </div>


        </form>


    </div>


</div>


<script>


/*
|--------------------------------------------------------------------------
| Добавить строку товара
|--------------------------------------------------------------------------
*/

function addRow() {

    const container =
        document.getElementById(
            'invoiceRows'
        );


    const firstRow =
        container.querySelector(
            '.invoice-row'
        );


    const newRow =
        firstRow.cloneNode(true);


    newRow
        .querySelectorAll('input')
        .forEach(function(input) {

            input.value = '';

        });


    newRow.querySelector(
        'select'
    ).value = '';


    container.appendChild(
        newRow
    );


    updateTotal();
}


/*
|--------------------------------------------------------------------------
| Удалить строку товара
|--------------------------------------------------------------------------
*/

function removeRow(button) {

    const container =
        document.getElementById(
            'invoiceRows'
        );


    const rows =
        container.querySelectorAll(
            '.invoice-row'
        );


    if (rows.length <= 1) {

        alert(
            'Должен остаться хотя бы один товар.'
        );

        return;
    }


    button
        .parentElement
        .remove();


    updateTotal();
}


/*
|--------------------------------------------------------------------------
| Расчёт общей суммы
|--------------------------------------------------------------------------
*/

function updateTotal() {

    let grandTotal = 0;


    document
        .querySelectorAll(
            '.invoice-row'
        )
        .forEach(function(row) {


            const quantity =
                parseFloat(
                    row.querySelector(
                        '[name="quantity[]"]'
                    ).value
                ) || 0;


            const price =
                parseFloat(
                    row.querySelector(
                        '[name="price[]"]'
                    ).value
                ) || 0;


            const total =
                quantity * price;


            row.querySelector(
                '.row-total'
            ).value =
                total.toFixed(2);


            grandTotal += total;

        });


    document.getElementById(
        'grandTotal'
    ).textContent =
        grandTotal.toFixed(2);
}


/*
|--------------------------------------------------------------------------
| Изменение количества / цены
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'input',
    function(event) {


        if (

            event.target.name ===
                'quantity[]'

            ||

            event.target.name ===
                'price[]'

        ) {

            updateTotal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| Открыть окно удаления
|--------------------------------------------------------------------------
*/

function openDeleteModal(invoiceId) {

    document.getElementById(
        'deleteInvoiceId'
    ).value = invoiceId;


    document.getElementById(
        'deleteInvoiceNumber'
    ).textContent = invoiceId;


    document.getElementById(
        'deleteModal'
    ).classList.add('show');


    document.querySelector(
        '#deleteModal input[type="password"]'
    ).value = '';


    setTimeout(function() {

        document.querySelector(
            '#deleteModal input[type="password"]'
        ).focus();

    }, 100);

}


/*
|--------------------------------------------------------------------------
| Закрыть окно
|--------------------------------------------------------------------------
*/

function closeDeleteModal() {

    document.getElementById(
        'deleteModal'
    ).classList.remove('show');
}


/*
|--------------------------------------------------------------------------
| Закрытие по клику вне окна
|--------------------------------------------------------------------------
*/

function closeModalOutside(event) {

    if (
        event.target.id ===
        'deleteModal'
    ) {

        closeDeleteModal();

    }

}


/*
|--------------------------------------------------------------------------
| ESC закрывает окно
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeDeleteModal();

        }

    }
);

</script>


</body>

</html>

