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
        SaleID,
        SaleDate,
        TotalAmount,
        PaymentMethod

    FROM sale

    WHERE SaleID = ?

";


$saleStmt = mysqli_prepare(
    $conn,
    $saleSQL
);


if (!$saleStmt) {

    die("Unable to load sale.");

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

    die("Unable to load sale.");

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

$sql = "

    SELECT
        sale_items.SaleItemID,
        sale_items.SaleID,
        sale_items.MedicineID,
        medicine.MedicineName,
        sale_items.Quantity,
        sale_items.SellingPrice,
        sale_items.SubTotal

    FROM sale_items

    LEFT JOIN medicine
        ON sale_items.MedicineID =
           medicine.MedicineID

    WHERE sale_items.SaleID = ?

    ORDER BY sale_items.SaleItemID ASC

";


$itemStmt = mysqli_prepare(
    $conn,
    $sql
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


$result =
    mysqli_stmt_get_result(
        $itemStmt
    );


if (!$result) {

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

    <title>Sale Items</title>


    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 90%;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            overflow-x: auto;
        }

        h2 {
            text-align: center;
        }

        .sale-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f2f2f2;
            border-radius: 5px;
        }

        .sale-info p {
            margin: 8px;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
            margin-top: 25px;
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

        .button {
            display: inline-block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }

        .back {
            background-color: #555;
        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Sale Items
    </h2>


    <!-- SALE INFORMATION -->

    <div class="sale-info">


        <p>

            <strong>
                Sale ID:
            </strong>

            <?php
            echo e($sale['SaleID']);
            ?>

        </p>


        <p>

            <strong>
                Sale Date:
            </strong>

            <?php
            echo e($sale['SaleDate']);
            ?>

        </p>


        <p>

            <strong>
                Payment Method:
            </strong>

            <?php
            echo e($sale['PaymentMethod']);
            ?>

        </p>


        <p>

            <strong>
                Total Amount:
            </strong>

            ₹

            <?php

            echo number_format(
                (float)$sale['TotalAmount'],
                2
            );

            ?>

        </p>


    </div>


    <!-- SALE ITEMS -->

    <table>


        <tr>

            <th>Sale Item ID</th>

            <th>Medicine ID</th>

            <th>Medicine Name</th>

            <th>Quantity</th>

            <th>Selling Price</th>

            <th>Sub Total</th>

        </tr>


        <?php

        if (
            mysqli_num_rows($result) > 0
        ) {

            while (
                $row =
                mysqli_fetch_assoc($result)
            ) {

        ?>


        <tr>


            <td>

                <?php

                echo e(
                    $row['SaleItemID']
                );

                ?>

            </td>


            <td>

                <?php

                echo e(
                    $row['MedicineID']
                );

                ?>

            </td>


            <td>

                <?php

                if (
                    !empty(
                        $row['MedicineName']
                    )
                ) {

                    echo e(
                        $row['MedicineName']
                    );

                } else {

                    echo "Medicine not found";

                }

                ?>

            </td>


            <td>

                <?php

                echo (int)
                    $row['Quantity'];

                ?>

            </td>


            <td>

                ₹

                <?php

                echo number_format(
                    (float)$row['SellingPrice'],
                    2
                );

                ?>

            </td>


            <td>

                ₹

                <?php

                echo number_format(
                    (float)$row['SubTotal'],
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

            <td colspan="6">

                No items found for this sale.

            </td>

        </tr>


        <?php

        }

        ?>


    </table>


    <a
        href="view_sale.php"
        class="button back"
    >

        Back to Sales

    </a>


</div>


</body>

</html>