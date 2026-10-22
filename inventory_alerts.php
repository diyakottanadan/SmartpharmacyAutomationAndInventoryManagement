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
   1. LOW STOCK MEDICINES
   ========================================= */

$lowStockSQL = "

    SELECT
        MedicineID,
        MedicineName,
        BatchNo,
        StockQty,
        ExpiryDate

    FROM medicine

    WHERE StockQty <= 10

    ORDER BY StockQty ASC

";


$lowStockResult = mysqli_query(
    $conn,
    $lowStockSQL
);


if (!$lowStockResult) {

    die("Unable to load low stock alerts.");

}


$lowStockCount =
    mysqli_num_rows(
        $lowStockResult
    );


/* =========================================
   2. EXPIRED MEDICINES
   ========================================= */

$expiredSQL = "

    SELECT
        MedicineID,
        MedicineName,
        BatchNo,
        StockQty,
        ExpiryDate

    FROM medicine

    WHERE ExpiryDate < CURDATE()

    ORDER BY ExpiryDate ASC

";


$expiredResult = mysqli_query(
    $conn,
    $expiredSQL
);


if (!$expiredResult) {

    die("Unable to load expired medicine alerts.");

}


$expiredCount =
    mysqli_num_rows(
        $expiredResult
    );


/* =========================================
   3. EXPIRING SOON
   Within next 30 days
   ========================================= */

$expiringSoonSQL = "

    SELECT
        MedicineID,
        MedicineName,
        BatchNo,
        StockQty,
        ExpiryDate

    FROM medicine

    WHERE ExpiryDate >= CURDATE()

    AND ExpiryDate <= DATE_ADD(
        CURDATE(),
        INTERVAL 30 DAY
    )

    ORDER BY ExpiryDate ASC

";


$expiringSoonResult = mysqli_query(
    $conn,
    $expiringSoonSQL
);


if (!$expiringSoonResult) {

    die("Unable to load expiry alerts.");

}


$expiringSoonCount =
    mysqli_num_rows(
        $expiringSoonResult
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
        Inventory Alerts
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial, sans-serif;

            background-color:
                #f4f4f4;

            margin: 0;

        }


        .container {

            width: 95%;

            margin: 40px auto;

        }


        h1 {

            text-align: center;

        }


        .summary {

            display: flex;

            justify-content: center;

            gap: 20px;

            margin-bottom: 30px;

            flex-wrap: wrap;

        }


        .alert-card {

            width: 220px;

            padding: 20px;

            background: white;

            border-radius: 10px;

            text-align: center;

            box-shadow:
                0 0 8px #ccc;

        }


        .number {

            font-size: 32px;

            font-weight: bold;

            margin-top: 10px;

        }


        .low {

            border-top:
                6px solid orange;

        }


        .expired {

            border-top:
                6px solid red;

        }


        .soon {

            border-top:
                6px solid #ff9800;

        }


        .section {

            background: white;

            padding: 25px;

            border-radius: 10px;

            margin-bottom: 30px;

            box-shadow:
                0 0 8px #ccc;

            overflow-x: auto;

        }


        table {

            width: 100%;

            min-width: 700px;

            border-collapse:
                collapse;

            margin-top: 15px;

        }


        th,
        td {

            padding: 12px;

            border:
                1px solid #ddd;

            text-align: center;

        }


        th {

            background-color:
                #333;

            color: white;

        }


        .status-low {

            color: orange;

            font-weight: bold;

        }


        .status-expired {

            color: red;

            font-weight: bold;

        }


        .status-soon {

            color: #e67e22;

            font-weight: bold;

        }


        .back-button {

            display: inline-block;

            padding: 10px 18px;

            background-color:
                #555;

            color: white;

            text-decoration: none;

            border-radius: 5px;

            margin-bottom: 20px;

        }


        .back-button:hover {

            background-color:
                #333;

        }


        @media screen
        and
        (max-width: 600px) {

            .container {

                width: 95%;

            }


            .section {

                padding: 15px;

            }


            table {

                font-size: 13px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <h1>
        Inventory Alerts
    </h1>


    <!-- BACK BUTTON -->

    <a
        href="<?php echo e($dashboard); ?>"
        class="back-button"
    >
        ← Back to Dashboard
    </a>


    <!-- ALERT SUMMARY -->

    <div class="summary">


        <div class="alert-card low">

            <strong>
                Low Stock
            </strong>

            <div class="number">

                <?php

                echo $lowStockCount;

                ?>

            </div>

        </div>


        <div class="alert-card expired">

            <strong>
                Expired Medicines
            </strong>

            <div class="number">

                <?php

                echo $expiredCount;

                ?>

            </div>

        </div>


        <div class="alert-card soon">

            <strong>
                Expiring Soon
            </strong>

            <div class="number">

                <?php

                echo $expiringSoonCount;

                ?>

            </div>

        </div>


    </div>


    <!-- =========================================
         LOW STOCK
         ========================================= -->

    <div class="section">


        <h2>
            ⚠ Low Stock Medicines
        </h2>


        <table>


            <tr>

                <th>Medicine ID</th>

                <th>Medicine Name</th>

                <th>Batch No</th>

                <th>Current Stock</th>

                <th>Expiry Date</th>

                <th>Status</th>

            </tr>


            <?php

            if ($lowStockCount > 0) {

                while (
                    $row =
                    mysqli_fetch_assoc(
                        $lowStockResult
                    )
                ) {

            ?>


            <tr>


                <td>

                    <?php

                    echo e(
                        $row['MedicineID']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['MedicineName']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['BatchNo']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo (int)
                        $row['StockQty'];

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['ExpiryDate']
                    );

                    ?>

                </td>


                <td class="status-low">

                    LOW STOCK

                </td>


            </tr>


            <?php

                }

            } else {

            ?>


            <tr>

                <td colspan="6">

                    No low stock medicines.

                </td>

            </tr>


            <?php

            }

            ?>


        </table>


    </div>


    <!-- =========================================
         EXPIRED MEDICINES
         ========================================= -->

    <div class="section">


        <h2>
            🔴 Expired Medicines
        </h2>


        <table>


            <tr>

                <th>Medicine ID</th>

                <th>Medicine Name</th>

                <th>Batch No</th>

                <th>Current Stock</th>

                <th>Expiry Date</th>

                <th>Status</th>

            </tr>


            <?php

            if ($expiredCount > 0) {

                while (
                    $row =
                    mysqli_fetch_assoc(
                        $expiredResult
                    )
                ) {

            ?>


            <tr>


                <td>

                    <?php

                    echo e(
                        $row['MedicineID']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['MedicineName']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['BatchNo']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo (int)
                        $row['StockQty'];

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['ExpiryDate']
                    );

                    ?>

                </td>


                <td class="status-expired">

                    EXPIRED

                </td>


            </tr>


            <?php

                }

            } else {

            ?>


            <tr>

                <td colspan="6">

                    No expired medicines.

                </td>

            </tr>


            <?php

            }

            ?>


        </table>


    </div>


    <!-- =========================================
         EXPIRING SOON
         ========================================= -->

    <div class="section">


        <h2>

            🟠 Medicines Expiring Soon
            (Within 30 Days)

        </h2>


        <table>


            <tr>

                <th>Medicine ID</th>

                <th>Medicine Name</th>

                <th>Batch No</th>

                <th>Current Stock</th>

                <th>Expiry Date</th>

                <th>Status</th>

            </tr>


            <?php

            if ($expiringSoonCount > 0) {

                while (
                    $row =
                    mysqli_fetch_assoc(
                        $expiringSoonResult
                    )
                ) {

            ?>


            <tr>


                <td>

                    <?php

                    echo e(
                        $row['MedicineID']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['MedicineName']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['BatchNo']
                    );

                    ?>

                </td>


                <td>

                    <?php

                    echo (int)
                        $row['StockQty'];

                    ?>

                </td>


                <td>

                    <?php

                    echo e(
                        $row['ExpiryDate']
                    );

                    ?>

                </td>


                <td class="status-soon">

                    EXPIRING SOON

                </td>


            </tr>


            <?php

                }

            } else {

            ?>


            <tr>

                <td colspan="6">

                    No medicines expiring
                    within 30 days.

                </td>

            </tr>


            <?php

            }

            ?>


        </table>


    </div>


</div>


</body>

</html>