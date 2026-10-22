<?php

session_start();

include("db_connect.php");


/* ===============================
   LOGIN CHECK
   =============================== */

if (
    !isset($_SESSION['admin']) &&
    !isset($_SESSION['pharmacist'])
) {

    header("Location: login.php");
    exit();

}


/* ===============================
   SAFE OUTPUT
   =============================== */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ===============================
   VALIDATE SALE ID
   =============================== */

$saleID = filter_input(
    INPUT_GET,
    'id',
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


/* ===============================
   GET SALE DETAILS
   =============================== */

$saleSQL = "

    SELECT
        sale.SaleID,
        sale.CustomerID,
        customer.CustomerName,
        sale.PharmacistID,
        pharmacist.Name AS PharmacistName,
        sale.SaleDate,
        sale.TotalAmount,
        sale.PaymentMethod

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

    die("Unable to load sale details.");

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

    die("Unable to load sale details.");

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


/* ===============================
   GET SALE ITEMS
   =============================== */

$itemSQL = "

    SELECT
        sale_items.MedicineID,
        sale_items.Quantity,
        sale_items.SellingPrice,
        sale_items.SubTotal,
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

    die("Unable to load sale items.");

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

    die("Unable to load sale items.");

}


mysqli_stmt_close(
    $itemStmt
);

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sale Details</title>


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
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            overflow-x: auto;
        }

        h2 {
            text-align: center;
        }

        .details {
            line-height: 2;
            margin: 25px 0;
            background-color: #f8f8f8;
            padding: 20px;
            border-radius: 8px;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #333;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Sale Details
    </h2>


    <!-- SALE DETAILS -->

    <div class="details">


        <strong>
            Sale ID:
        </strong>

        <?php
        echo e($sale['SaleID']);
        ?>

        <br>


        <strong>
            Customer:
        </strong>

        <?php

        if (!empty($sale['CustomerName'])) {

            echo e(
                $sale['CustomerName']
            );

        } else {

            echo "Customer ID: ";

            echo e(
                $sale['CustomerID']
            );

        }

        ?>

        <br>


        <strong>
            Pharmacist:
        </strong>

        <?php

        if (!empty($sale['PharmacistName'])) {

            echo e(
                $sale['PharmacistName']
            );

        } else {

            echo "Pharmacist ID: ";

            echo e(
                $sale['PharmacistID']
            );

        }

        ?>

        <br>


        <strong>
            Sale Date:
        </strong>

        <?php
        echo e($sale['SaleDate']);
        ?>

        <br>


        <strong>
            Payment Method:
        </strong>

        <?php
        echo e($sale['PaymentMethod']);
        ?>

        <br>


        <strong>
            Total Amount:
        </strong>

        ₹<?php

        echo number_format(
            (float)$sale['TotalAmount'],
            2
        );

        ?>


    </div>


    <!-- MEDICINES -->

    <h3>
        Medicines Sold
    </h3>


    <table>


        <tr>

            <th>Medicine</th>

            <th>Quantity</th>

            <th>Selling Price</th>

            <th>Subtotal</th>

        </tr>


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

                echo (int)
                    $item['Quantity'];

                ?>

            </td>


            <td>

                ₹<?php

                echo number_format(
                    (float)$item['SellingPrice'],
                    2
                );

                ?>

            </td>


            <td>

                ₹<?php

                echo number_format(
                    (float)$item['SubTotal'],
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

            <td colspan="4">

                No sale items found.

            </td>

        </tr>


        <?php

        }

        ?>


    </table>


    <a
        href="view_sale.php"
        class="back"
    >
        Back to Sales
    </a>


</div>


</body>

</html>