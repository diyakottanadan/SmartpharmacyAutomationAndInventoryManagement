<?php

session_start();
include("db_connect.php");


/* -----------------------------------
   Login Check
----------------------------------- */

if (
    !isset($_SESSION['admin']) &&
    !isset($_SESSION['pharmacist'])
) {
    header("Location: login.php");
    exit();
}


/* -----------------------------------
   Safe Output Function
----------------------------------- */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* -----------------------------------
   Get Purchase ID
----------------------------------- */

/*
 * view_purchase.php uses:
 * purchase_details.php?PurchaseID=...
 */

if (
    !isset($_GET['PurchaseID']) ||
    !ctype_digit($_GET['PurchaseID']) ||
    (int)$_GET['PurchaseID'] <= 0
) {

    die("Invalid Purchase ID.");

}

$purchaseID = (int)$_GET['PurchaseID'];


/* -----------------------------------
   Get Purchase Information
----------------------------------- */

$purchaseSQL = "
    SELECT
        p.Purchase_Id,
        p.SupplierID,
        p.PharmacistID,
        p.PurchaseDate,
        p.TotalAmount,
        p.Status,

        s.SupplierName,

        ph.Name AS PharmacistName

    FROM purchase p

    LEFT JOIN supplier s
        ON p.SupplierID = s.SupplierID

    LEFT JOIN pharmacist ph
        ON p.PharmacistID = ph.PharmacistID

    WHERE p.Purchase_Id = ?

    LIMIT 1
";


$purchaseStmt = mysqli_prepare(
    $conn,
    $purchaseSQL
);


if (!$purchaseStmt) {

    die("Unable to load purchase details.");

}


mysqli_stmt_bind_param(
    $purchaseStmt,
    "i",
    $purchaseID
);


mysqli_stmt_execute(
    $purchaseStmt
);


$purchaseResult =
    mysqli_stmt_get_result(
        $purchaseStmt
    );


$purchase =
    mysqli_fetch_assoc(
        $purchaseResult
    );


if (!$purchase) {

    die("Purchase not found.");

}


/* -----------------------------------
   Get Purchase Items
----------------------------------- */

$itemSQL = "
    SELECT
        pi.PurchaseID,
        pi.MedicineID,
        pi.Quantity,
        pi.PurchasePrice,
        pi.SubTotal,

        m.MedicineName

    FROM purchase_items pi

    INNER JOIN medicine m
        ON pi.MedicineID = m.MedicineID

    WHERE pi.PurchaseID = ?

    ORDER BY pi.MedicineID
";


$itemStmt = mysqli_prepare(
    $conn,
    $itemSQL
);


if (!$itemStmt) {

    die("Unable to load purchase items.");

}


mysqli_stmt_bind_param(
    $itemStmt,
    "i",
    $purchaseID
);


mysqli_stmt_execute(
    $itemStmt
);


$itemResult =
    mysqli_stmt_get_result(
        $itemStmt
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Purchase Details</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        h3 {
            margin-top: 30px;
        }

        .details {
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
            line-height: 2;
            margin-bottom: 30px;
        }

        .details-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .details-label {
            font-weight: bold;
            width: 180px;
        }

        .details-value {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #e9e9e9;
        }

        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: bold;
            font-size: 13px;
        }

        .completed {
            background-color: #d4edda;
            color: #155724;
        }

        .pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .amount {
            font-weight: bold;
        }

        .no-items {
            text-align: center;
            padding: 20px;
            color: #666;
            font-weight: bold;
        }

        .buttons {
            margin-top: 25px;
        }

        .button {
            display: inline-block;
            padding: 10px 18px;
            margin-right: 8px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back {
            background-color: #555;
        }

        .invoice {
            background-color: #007bff;
        }

        .button:hover {
            opacity: 0.85;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        @media (max-width: 600px) {

            .container {
                width: 95%;
                padding: 15px;
                margin: 20px auto;
            }

            .details-label {
                width: 100%;
            }

            .details-value {
                width: 100%;
            }

            .button {
                display: block;
                text-align: center;
                margin-bottom: 10px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h2>Purchase Details</h2>


    <!-- Purchase Information -->

    <div class="details">

        <div class="details-row">

            <div class="details-label">
                Purchase ID:
            </div>

            <div class="details-value">

                <?php
                echo e(
                    $purchase['Purchase_Id']
                );
                ?>

            </div>

        </div>


        <div class="details-row">

            <div class="details-label">
                Supplier:
            </div>

            <div class="details-value">

                <?php

                if (
                    !empty(
                        $purchase['SupplierName']
                    )
                ) {

                    echo e(
                        $purchase['SupplierName']
                    );

                } else {

                    echo "Supplier #"
                        . e(
                            $purchase['SupplierID']
                        );

                }

                ?>

            </div>

        </div>


        <div class="details-row">

            <div class="details-label">
                Pharmacist:
            </div>

            <div class="details-value">

                <?php

                if (
                    !empty(
                        $purchase['PharmacistName']
                    )
                ) {

                    echo e(
                        $purchase['PharmacistName']
                    );

                } else {

                    echo "Pharmacist #"
                        . e(
                            $purchase['PharmacistID']
                        );

                }

                ?>

            </div>

        </div>


        <div class="details-row">

            <div class="details-label">
                Purchase Date:
            </div>

            <div class="details-value">

                <?php

                if (
                    !empty(
                        $purchase['PurchaseDate']
                    )
                ) {

                    echo e(
                        date(
                            "d-m-Y",
                            strtotime(
                                $purchase['PurchaseDate']
                            )
                        )
                    );

                } else {

                    echo "-";

                }

                ?>

            </div>

        </div>


        <div class="details-row">

            <div class="details-label">
                Total Amount:
            </div>

            <div class="details-value amount">

                ₹<?php

                echo number_format(
                    (float)$purchase['TotalAmount'],
                    2
                );

                ?>

            </div>

        </div>


        <div class="details-row">

            <div class="details-label">
                Status:
            </div>

            <div class="details-value">

                <?php

                $status =
                    $purchase['Status'];

                $statusClass =
                    "pending";

                if (
                    strtolower($status)
                    === "completed"
                ) {

                    $statusClass =
                        "completed";

                } elseif (
                    strtolower($status)
                    === "cancelled"
                ) {

                    $statusClass =
                        "cancelled";

                }

                ?>

                <span
                    class="status <?php
                        echo e($statusClass);
                    ?>"
                >

                    <?php
                    echo e($status);
                    ?>

                </span>

            </div>

        </div>

    </div>


    <!-- Medicine Items -->

    <h3>Medicines Purchased</h3>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>Medicine</th>

                    <th>Quantity</th>

                    <th>Purchase Price</th>

                    <th>Subtotal</th>

                </tr>

            </thead>


            <tbody>

            <?php

            if (
                mysqli_num_rows(
                    $itemResult
                ) > 0
            ) {

                while (
                    $item =
                    mysqli_fetch_assoc(
                        $itemResult
                    )
                ) {

                    ?>

                    <tr>

                        <td>

                            <?php
                            echo e(
                                $item['MedicineName']
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo e(
                                $item['Quantity']
                            );
                            ?>

                        </td>


                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$item[
                                    'PurchasePrice'
                                ],
                                2
                            );

                            ?>

                        </td>


                        <td>

                            ₹<?php

                            echo number_format(
                                (float)$item[
                                    'SubTotal'
                                ],
                                2
                            );

                            ?>

                        </td>

                    </tr>

                    <?php

                }

            } else {

                ?>

                <tr>

                    <td
                        colspan="4"
                        class="no-items"
                    >

                        No medicine items found.

                    </td>

                </tr>

                <?php

            }

            ?>

            </tbody>

        </table>

    </div>


    <!-- Buttons -->

    <div class="buttons">

        <a
            href="view_purchase.php"
            class="button back"
        >

            Back to Purchases

        </a>


        <a
            href="purchase_invoice.php?PurchaseID=<?php
                echo urlencode(
                    $purchase['Purchase_Id']
                );
            ?>"
            class="button invoice"
        >

            View Invoice

        </a>

    </div>

</div>

</body>

</html>