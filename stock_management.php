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
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   GET MEDICINES
   ========================================= */

$sql = "

    SELECT
        MedicineID,
        MedicineName,
        Category,
        BatchNo,
        PurchasePrice,
        SellingPrice,
        StockQty,
        ExpiryDate,
        Status

    FROM medicine

    ORDER BY MedicineID

";


$result = mysqli_query($conn, $sql);


if (!$result) {

    die("Error loading stock.");

}


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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Stock Management</title>


    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 95%;
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

        table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            margin-top: 25px;
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

        .available {
            font-weight: bold;
        }

        .low-stock {
            font-weight: bold;
            background-color: #fff3cd;
        }

        .out-stock {
            font-weight: bold;
            background-color: #f8d7da;
        }

        .expired {
            font-weight: bold;
            background-color: #f8d7da;
        }

        .expiring {
            font-weight: bold;
            background-color: #fff3cd;
        }

        .valid {
            font-weight: bold;
        }

        .button {
            display: inline-block;
            padding: 10px 15px;
            background-color: green;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .back {
            background-color: #555;
        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Stock Management
    </h2>


    <a
        href="<?php echo e($dashboard); ?>"
        class="button back"
    >
        Back to Dashboard
    </a>


    <table>

        <tr>

            <th>Medicine ID</th>

            <th>Medicine Name</th>

            <th>Category</th>

            <th>Batch No</th>

            <th>Purchase Price</th>

            <th>Selling Price</th>

            <th>Stock Quantity</th>

            <th>Expiry Date</th>

            <th>Stock Status</th>

            <th>Expiry Status</th>

        </tr>


        <?php

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_assoc($result)) {

                $stock = (int)$row['StockQty'];


                /* =========================================
                   STOCK STATUS
                   ========================================= */

                if ($stock <= 0) {

                    $stockClass = "out-stock";
                    $stockStatus = "Out of Stock";

                } elseif ($stock <= 10) {

                    $stockClass = "low-stock";
                    $stockStatus = "Low Stock";

                } else {

                    $stockClass = "available";
                    $stockStatus = "Available";

                }


                /* =========================================
                   EXPIRY STATUS
                   ========================================= */

                $expiryClass = "valid";
                $expiryStatus = "Valid";


                try {

                    $today = new DateTime();

                    $expiryDate = new DateTime(
                        $row['ExpiryDate']
                    );

                    $difference = $today->diff(
                        $expiryDate
                    );

                    $days = (int)$difference->format('%r%a');


                    if ($days < 0) {

                        $expiryClass = "expired";
                        $expiryStatus = "Expired";

                    } elseif ($days <= 30) {

                        $expiryClass = "expiring";
                        $expiryStatus = "Expiring Soon";

                    }

                } catch (Exception $e) {

                    $expiryClass = "expired";
                    $expiryStatus = "Invalid Date";

                }


        ?>


        <tr>


            <td>
                <?php
                echo e($row['MedicineID']);
                ?>
            </td>


            <td>
                <?php
                echo e($row['MedicineName']);
                ?>
            </td>


            <td>
                <?php
                echo e($row['Category']);
                ?>
            </td>


            <td>
                <?php
                echo e($row['BatchNo']);
                ?>
            </td>


            <td>
                ₹
                <?php
                echo number_format(
                    (float)$row['PurchasePrice'],
                    2
                );
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


            <td class="<?php echo e($stockClass); ?>">

                <?php
                echo $stock;
                ?>

            </td>


            <td>
                <?php
                echo e($row['ExpiryDate']);
                ?>
            </td>


            <td class="<?php echo e($stockClass); ?>">

                <?php
                echo e($stockStatus);
                ?>

            </td>


            <td class="<?php echo e($expiryClass); ?>">

                <?php
                echo e($expiryStatus);
                ?>

            </td>


        </tr>


        <?php

            }

        } else {

        ?>


        <tr>

            <td colspan="10">

                No medicines found.

            </td>

        </tr>


        <?php

        }

        ?>


    </table>


</div>


</body>

</html>