<?php

session_start();
include("db_connect.php");

/* =========================================
   LOGIN CHECK
   ========================================= */

if (
    !isset($_SESSION['admin']) &&
    !isset($_SESSION['pharmacist'])
) {
    header("Location: login.php");
    exit();
}


/* =========================================
   SAFE OUTPUT
   ========================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/* =========================================
   CHECK PURCHASE ID
   ========================================= */

$purchaseID = filter_input(
    INPUT_GET,
    'PurchaseID',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($purchaseID === false || $purchaseID === null) {
    die("Invalid Purchase ID.");
}


/* =========================================
   GET PURCHASE DETAILS
   ========================================= */

$purchaseSQL = "

    SELECT
        purchase.Purchase_Id,
        purchase.PurchaseDate,
        purchase.TotalAmount,
        purchase.Status,

        supplier.SupplierID,
        supplier.SupplierName,

        pharmacist.PharmacistID,
        pharmacist.Name AS PharmacistName

    FROM purchase

    LEFT JOIN supplier
        ON purchase.SupplierID = supplier.SupplierID

    LEFT JOIN pharmacist
        ON purchase.PharmacistID = pharmacist.PharmacistID

    WHERE purchase.Purchase_Id = ?

";


$stmt = mysqli_prepare($conn, $purchaseSQL);

if (!$stmt) {
    die("Unable to load purchase details.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $purchaseID
);

mysqli_stmt_execute($stmt);

$purchaseResult = mysqli_stmt_get_result($stmt);

if (!$purchaseResult) {
    die("Unable to load purchase details.");
}

if (mysqli_num_rows($purchaseResult) === 0) {
    die("Purchase not found.");
}

$purchase = mysqli_fetch_assoc($purchaseResult);

mysqli_stmt_close($stmt);


/* =========================================
   GET PURCHASE ITEMS
   ========================================= */

$itemSQL = "

    SELECT
        purchase_items.Quantity,
        purchase_items.PurchasePrice,
        purchase_items.SubTotal,

        medicine.MedicineID,
        medicine.MedicineName

    FROM purchase_items

    INNER JOIN medicine
        ON purchase_items.MedicineID = medicine.MedicineID

    WHERE purchase_items.PurchaseID = ?

";


$stmt = mysqli_prepare($conn, $itemSQL);

if (!$stmt) {
    die("Unable to load purchase items.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $purchaseID
);

mysqli_stmt_execute($stmt);

$itemResult = mysqli_stmt_get_result($stmt);

if (!$itemResult) {
    die("Unable to load purchase items.");
}

mysqli_stmt_close($stmt);


/* =========================================
   DASHBOARD
   ========================================= */

if (isset($_SESSION['admin'])) {

    $dashboard = "admin_dashboard.php";

} else {

    $dashboard = "pharmacist_dashboard.php";

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Purchase Invoice</title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .container {
            width: 850px;
            max-width: 95%;
            margin: 40px auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 0 12px #bbb;
            border-radius: 10px;
        }

        .invoice-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .invoice-header h1 {
            margin: 0;
            font-size: 32px;
        }

        .invoice-header p {
            margin: 8px 0;
        }

        .invoice-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .invoice-title h2 {
            margin: 0;
        }

        .invoice-number {
            font-size: 18px;
            font-weight: bold;
        }

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }

        .info-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .info-box h3 {
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }

        .info-box p {
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #333;
            color: white;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 12px;
            text-align: center;
        }

        .total-section {
            margin-top: 25px;
            text-align: right;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .buttons {
            margin-top: 30px;
            text-align: center;
        }

        .button {
            display: inline-block;
            padding: 12px 20px;
            margin: 5px;
            text-decoration: none;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        .print-button {
            background-color: green;
        }

        .back-button {
            background-color: #555;
        }

        @media print {

            body {
                background: white;
            }

            .container {
                box-shadow: none;
                width: 100%;
                margin: 0;
                max-width: 100%;
            }

            .buttons {
                display: none;
            }
        }

        @media screen and (max-width: 700px) {

            .info-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .container {
                padding: 20px;
            }

            table {
                font-size: 13px;
            }
        }

    </style>

</head>


<body>


<div class="container">


    <!-- PHARMACY HEADER -->

    <div class="invoice-header">

        <h1>
            SMART PHARMACY
        </h1>

        <p>
            Pharmacy Automation
            and Inventory Management System
        </p>

        <p>
            Purchase Invoice
        </p>

    </div>


    <!-- INVOICE TITLE -->

    <div class="invoice-title">

        <h2>
            PURCHASE INVOICE
        </h2>

        <div class="invoice-number">

            Invoice No:
            PUR-<?php
                echo str_pad(
                    (int)$purchase['Purchase_Id'],
                    5,
                    "0",
                    STR_PAD_LEFT
                );
            ?>

        </div>

    </div>


    <!-- SUPPLIER AND PURCHASE DETAILS -->

    <div class="info-section">


        <!-- SUPPLIER DETAILS -->

        <div class="info-box">

            <h3>
                Supplier Details
            </h3>

            <p>
                <strong>Supplier ID:</strong>

                <?php
                echo e($purchase['SupplierID']);
                ?>
            </p>

            <p>
                <strong>Supplier Name:</strong>

                <?php
                echo e($purchase['SupplierName']);
                ?>
            </p>

        </div>


        <!-- PURCHASE DETAILS -->

        <div class="info-box">

            <h3>
                Purchase Details
            </h3>

            <p>
                <strong>Purchase ID:</strong>

                <?php
                echo e($purchase['Purchase_Id']);
                ?>
            </p>

            <p>
                <strong>Date:</strong>

                <?php

                echo e(
                    date(
                        "d-m-Y",
                        strtotime($purchase['PurchaseDate'])
                    )
                );

                ?>

            </p>

            <p>
                <strong>Pharmacist:</strong>

                <?php
                echo e($purchase['PharmacistName']);
                ?>
            </p>

            <p>
                <strong>Status:</strong>

                <?php
                echo e($purchase['Status']);
                ?>
            </p>

        </div>

    </div>


    <!-- PURCHASE ITEMS -->

    <table>

        <tr>

            <th>#</th>

            <th>Medicine</th>

            <th>Quantity</th>

            <th>Purchase Price</th>

            <th>Subtotal</th>

        </tr>


        <?php

        $count = 1;

        if (mysqli_num_rows($itemResult) > 0) {

            while ($item = mysqli_fetch_assoc($itemResult)) {

        ?>

        <tr>

            <td>
                <?php echo $count; ?>
            </td>

            <td>
                <?php
                echo e($item['MedicineName']);
                ?>
            </td>

            <td>
                <?php
                echo (int)$item['Quantity'];
                ?>
            </td>

            <td>
                ₹
                <?php
                echo number_format(
                    (float)$item['PurchasePrice'],
                    2
                );
                ?>
            </td>

            <td>
                ₹
                <?php
                echo number_format(
                    (float)$item['SubTotal'],
                    2
                );
                ?>
            </td>

        </tr>

        <?php

                $count++;
            }

        } else {

        ?>

        <tr>

            <td colspan="5">
                No medicines found for this purchase.
            </td>

        </tr>

        <?php

        }

        ?>

    </table>


    <!-- TOTAL -->

    <div class="total-section">

        <h2>

            Total Purchase Amount:

            ₹

            <?php

            echo number_format(
                (float)$purchase['TotalAmount'],
                2
            );

            ?>

        </h2>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        <h3>
            Purchase Record
        </h3>

        <p>
            This is a computer-generated purchase invoice.
        </p>

    </div>


    <!-- BUTTONS -->

    <div class="buttons">

        <button
            type="button"
            class="button print-button"
            onclick="window.print()"
        >
            Print Invoice
        </button>

        <a
            href="<?php echo e($dashboard); ?>"
            class="button back-button"
        >
            Back to Dashboard
        </a>

    </div>


</div>


</body>

</html>