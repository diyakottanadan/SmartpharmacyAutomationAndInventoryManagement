<?php

session_start();

include("db_connect.php");


/* =========================================
   CHECK LOGIN
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
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   VALIDATE SALE ID
   ========================================= */

$saleID = filter_input(
    INPUT_GET,
    'SaleID',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);


if (
    $saleID === false ||
    $saleID === null
) {

    die("Invalid Sale ID.");

}


/* =========================================
   GET SALE DETAILS
   ========================================= */

$saleSQL = "

    SELECT

        sale.SaleID,
        sale.SaleDate,
        sale.TotalAmount,
        sale.PaymentMethod,

        customer.CustomerID,
        customer.CustomerName,
        customer.Phone AS CustomerPhone,

        pharmacist.PharmacistID,
        pharmacist.Name AS PharmacistName

    FROM sale

    LEFT JOIN customer
        ON sale.CustomerID =
           customer.CustomerID

    LEFT JOIN pharmacist
        ON sale.PharmacistID =
           pharmacist.PharmacistID

    WHERE sale.SaleID = ?

";


$saleStmt = mysqli_prepare(
    $conn,
    $saleSQL
);


if (!$saleStmt) {

    die("Unable to load invoice.");

}


mysqli_stmt_bind_param(
    $saleStmt,
    "i",
    $saleID
);


mysqli_stmt_execute(
    $saleStmt
);


$saleResult =
    mysqli_stmt_get_result(
        $saleStmt
    );


if (!$saleResult) {

    mysqli_stmt_close($saleStmt);

    die("Unable to load invoice.");

}


if (
    mysqli_num_rows(
        $saleResult
    ) === 0
) {

    mysqli_stmt_close($saleStmt);

    die("Sale not found.");

}


$sale =
    mysqli_fetch_assoc(
        $saleResult
    );


mysqli_stmt_close(
    $saleStmt
);


/* =========================================
   GET SALE ITEMS
   ========================================= */

$itemSQL = "

    SELECT

        sale_items.Quantity,
        sale_items.SellingPrice,
        sale_items.SubTotal,

        medicine.MedicineID,
        medicine.MedicineName

    FROM sale_items

    INNER JOIN medicine
        ON sale_items.MedicineID =
           medicine.MedicineID

    WHERE sale_items.SaleID = ?

    ORDER BY sale_items.SaleItemID ASC

";


$itemStmt = mysqli_prepare(
    $conn,
    $itemSQL
);


if (!$itemStmt) {

    die("Unable to load invoice items.");

}


mysqli_stmt_bind_param(
    $itemStmt,
    "i",
    $saleID
);


mysqli_stmt_execute(
    $itemStmt
);


$itemResult =
    mysqli_stmt_get_result(
        $itemStmt
    );


if (!$itemResult) {

    mysqli_stmt_close($itemStmt);

    die("Unable to load invoice items.");

}


mysqli_stmt_close(
    $itemStmt
);


/* =========================================
   DETERMINE DASHBOARD
   ========================================= */

if (isset($_SESSION['admin'])) {

    $dashboard =
        "admin_dashboard.php";

} else {

    $dashboard =
        "pharmacist_dashboard.php";

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Invoice - Smart Pharmacy
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial, sans-serif;

            background-color:
                #f4f4f4;

        }


        .container {

            width: 850px;

            max-width: 95%;

            margin: 40px auto;

            background-color: white;

            padding: 40px;

            box-shadow:
                0 0 12px #bbb;

            border-radius: 10px;

        }


        .invoice-header {

            text-align: center;

            border-bottom:
                2px solid #333;

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

            justify-content:
                space-between;

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

            grid-template-columns:
                1fr 1fr;

            gap: 40px;

            margin-bottom: 30px;

        }


        .info-box {

            border:
                1px solid #ddd;

            padding: 15px;

            border-radius: 5px;

        }


        .info-box h3 {

            margin-top: 0;

            border-bottom:
                1px solid #ddd;

            padding-bottom: 8px;

        }


        .info-box p {

            margin: 8px 0;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            margin-top: 20px;

        }


        th {

            background-color: #333;

            color: white;

        }


        th,
        td {

            border:
                1px solid #ccc;

            padding: 12px;

            text-align: center;

        }


        .total-section {

            margin-top: 25px;

            text-align: right;

        }


        .total-section h2 {

            margin: 0;

        }


        .footer {

            text-align: center;

            margin-top: 40px;

            border-top:
                1px solid #ddd;

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
            Thank you for choosing us!
        </p>

    </div>


    <!-- INVOICE TITLE -->

    <div class="invoice-title">

        <h2>
            INVOICE
        </h2>

        <div class="invoice-number">

            Invoice No:

            <?php

            echo
                "INV-" .
                str_pad(
                    (int)$sale['SaleID'],
                    5,
                    "0",
                    STR_PAD_LEFT
                );

            ?>

        </div>

    </div>


    <!-- CUSTOMER AND SALE DETAILS -->

    <div class="info-section">


        <!-- CUSTOMER DETAILS -->

        <div class="info-box">

            <h3>
                Customer Details
            </h3>


            <p>

                <strong>
                    Customer ID:
                </strong>

                <?php

                echo e(
                    $sale['CustomerID']
                );

                ?>

            </p>


            <p>

                <strong>
                    Name:
                </strong>

                <?php

                echo e(
                    $sale['CustomerName']
                    ?? "Walk-in Customer"
                );

                ?>

            </p>


            <p>

                <strong>
                    Phone:
                </strong>

                <?php

                echo e(
                    $sale['CustomerPhone']
                    ?? "-"
                );

                ?>

            </p>

        </div>


        <!-- SALE DETAILS -->

        <div class="info-box">

            <h3>
                Sale Details
            </h3>


            <p>

                <strong>
                    Sale ID:
                </strong>

                <?php

                echo e(
                    $sale['SaleID']
                );

                ?>

            </p>


            <p>

                <strong>
                    Date:
                </strong>

                <?php

                $saleDate =
                    strtotime(
                        $sale['SaleDate']
                    );

                if ($saleDate !== false) {

                    echo date(
                        "d-m-Y",
                        $saleDate
                    );

                } else {

                    echo "-";

                }

                ?>

            </p>


            <p>

                <strong>
                    Pharmacist:
                </strong>

                <?php

                echo e(
                    $sale['PharmacistName']
                    ?? "-"
                );

                ?>

            </p>


            <p>

                <strong>
                    Payment:
                </strong>

                <?php

                echo e(
                    $sale['PaymentMethod']
                );

                ?>

            </p>

        </div>


    </div>


    <!-- MEDICINE TABLE -->

    <table>


        <tr>

            <th>#</th>

            <th>Medicine</th>

            <th>Quantity</th>

            <th>Price</th>

            <th>Subtotal</th>

        </tr>


        <?php

        $count = 1;

        $calculatedTotal = 0;


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


                $calculatedTotal +=
                    (float)$item['SubTotal'];

        ?>


        <tr>


            <td>

                <?php

                echo $count;

                ?>

            </td>


            <td>

                <?php

                echo e(
                    $item['MedicineName']
                );

                ?>

            </td>


            <td>

                <?php

                echo (int)
                    $item['Quantity'];

                ?>

            </td>


            <td>

                ₹

                <?php

                echo number_format(
                    (float)$item['SellingPrice'],
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

                No medicines found
                for this sale.

            </td>

        </tr>


        <?php

        }

        ?>


    </table>


    <!-- TOTAL -->

    <div class="total-section">

        <h2>

            Total Amount:

            ₹

            <?php

            echo number_format(
                (float)$sale['TotalAmount'],
                2
            );

            ?>

        </h2>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        <h3>
            Thank You!
        </h3>

        <p>
            Please visit again.
        </p>

        <p>
            This is a computer-generated invoice.
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