
<?php

require_once __DIR__ . '/../config/database.php';

$invoice_id = (int)($_GET['id'] ?? 0);

if ($invoice_id <= 0) {
    die('Накладная не найдена.');
}


/*
|--------------------------------------------------------------------------
| Получаем накладную
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        type,
        customer_name,
        total,
        created_at
    FROM invoices
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$invoice_id]);

$invoice = $stmt->fetch();

if (!$invoice) {
    die('Накладная не найдена.');
}


/*
|--------------------------------------------------------------------------
| Получаем товары накладной
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ii.id,
        ii.quantity,
        ii.price,
        ii.total,
        p.name,
        p.unit
    FROM invoice_items ii
    INNER JOIN products p
        ON p.id = ii.product_id
    WHERE ii.invoice_id = ?
    ORDER BY ii.id
");

$stmt->execute([$invoice_id]);

$items = $stmt->fetchAll();

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
        Накладная №<?= (int)$invoice['id'] ?>
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
            margin-bottom: 6px;
        }

        .page-header p {
            color: #777;
            font-size: 14px;
        }

        .back-button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        .back-button:hover {
            background: #e2e8f0;
        }

        .invoice-box {
            background: white;
            border: 1px solid #e7e9ed;
            border-radius: 12px;
            padding: 25px;
        }

        .invoice-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-box {
            background: #f8fafc;
            border-radius: 9px;
            padding: 15px;
        }

        .info-box span {
            display: block;
            color: #777;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-box strong {
            font-size: 15px;
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

        .total-row td {
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
        }

        .total-value {
            color: #2563eb;
            font-size: 19px;
        }

        .print-button {
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            padding: 11px 18px;
            background: #2563eb;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .print-button:hover {
            background: #1d4ed8;
        }

        @media (max-width: 700px) {

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .invoice-info {
                grid-template-columns: 1fr;
            }

        }

        @media print {

            .sidebar,
            .back-button,
            .print-button {
                display: none !important;
            }

            .main {
                padding: 0;
                max-width: none;
            }

            .invoice-box {
                border: none;
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


    <!-- MAIN -->

    <main class="main">

        <div class="page-header">

            <div>

                <h1>
                    Накладная №<?= (int)$invoice['id'] ?>
                </h1>

                <p>
                    Просмотр накладной
                </p>

            </div>


            <a
                href="index.php"
                class="back-button"
            >
                ← Назад к накладным
            </a>

        </div>


        <div class="invoice-box">


            <!-- ИНФОРМАЦИЯ -->

            <div class="invoice-info">

                <div class="info-box">

                    <span>
                        Номер накладной
                    </span>

                    <strong>
                        №<?= (int)$invoice['id'] ?>
                    </strong>

                </div>


                <div class="info-box">

                    <span>
                        Дата
                    </span>

                    <strong>

                        <?= date(
                            'd.m.Y H:i',
                            strtotime(
                                $invoice['created_at']
                            )
                        ) ?>

                    </strong>

                </div>


                <div class="info-box">

                    <span>
                        Тип
                    </span>

                    <strong>
                        Приход
                    </strong>

                </div>

            </div>


            <!-- ТОВАРЫ -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Товар
                            </th>

                            <th>
                                Количество
                            </th>

                            <th>
                                Цена
                            </th>

                            <th>
                                Сумма
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($items as $index => $item): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $item['name']
                                    ) ?>
                                </strong>
                            </td>

                            <td>

                                <?= number_format(
                                    (float)$item['quantity'],
                                    3,
                                    '.',
                                    ' '
                                ) ?>

                                <?= htmlspecialchars(
                                    $item['unit']
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    (float)$item['price'],
                                    2,
                                    '.',
                                    ' '
                                ) ?>

                                сомони

                            </td>

                            <td>

                                <?= number_format(
                                    (float)$item['total'],
                                    2,
                                    '.',
                                    ' '
                                ) ?>

                                сомони

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    <tr class="total-row">

                        <td colspan="4">
                            Итого:
                        </td>

                        <td class="total-value">

                            <?= number_format(
                                (float)$invoice['total'],
                                2,
                                '.',
                                ' '
                            ) ?>

                            сомони

                        </td>

                    </tr>


                    </tbody>

                </table>

            </div>


            <button
                class="print-button"
                onclick="window.print()"
            >
                🖨 Печать
            </button>


        </div>

    </main>

</div>

</body>

</html>
```
