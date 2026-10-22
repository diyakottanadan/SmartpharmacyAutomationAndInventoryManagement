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
   GET SALES
   =============================== */

$sql = "
    SELECT
        sale.SaleID,
        sale.CustomerID,
        customer.CustomerName AS CustomerName,
        sale.PharmacistID,
        pharmacist.Name AS PharmacistName,
        sale.SaleDate,
        sale.TotalAmount,
        sale.PaymentMethod

    FROM sale

    LEFT JOIN customer
        ON sale.CustomerID = customer.CustomerID

    LEFT JOIN pharmacist
        ON sale.PharmacistID = pharmacist.PharmacistID

    ORDER BY sale.SaleID DESC
";


$result = mysqli_query($conn, $sql);


if (!$result) {

    die("Error loading sales.");

}


/* ===============================
   DASHBOARD
   =============================== */

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

    <title>Sales Management</title>


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
            min-width: 850px;
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
            padding: 9px 14px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 3px;
        }

        .add {
            background-color: green;
        }

        .items {
            background-color: #007bff;
        }

        .back {
            background-color: #555;
        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Sales Management
    </h2>


    <!-- BACK TO DASHBOARD -->

    <a
        href="<?php echo e($dashboard); ?>"
        class="button back"
    >
        Back to Dashboard
    </a>


    <!-- ADD SALE -->

    <a
        href="add_sale.php"
        class="button add"
    >
        Add Sale
    </a>


    <table>

        <tr>

            <th>Sale ID</th>

            <th>Customer</th>

            <th>Pharmacist</th>

            <th>Sale Date</th>

            <th>Total Amount</th>

            <th>Payment Method</th>

            <th>Action</th>

        </tr>


        <?php

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_assoc($result)) {

        ?>


        <tr>


            <!-- SALE ID -->

            <td>

                <?php
                echo e($row['SaleID']);
                ?>

            </td>


            <!-- CUSTOMER -->

            <td>

                <?php

                if (
                    !empty(
                        $row['CustomerName']
                    )
                ) {

                    echo e(
                        $row['CustomerName']
                    );

                } else {

                    echo "Customer ID: ";

                    echo e(
                        $row['CustomerID']
                    );

                }

                ?>

            </td>


            <!-- PHARMACIST -->

            <td>

                <?php

                if (
                    !empty(
                        $row['PharmacistName']
                    )
                ) {

                    echo e(
                        $row['PharmacistName']
                    );

                } else {

                    echo "Pharmacist ID: ";

                    echo e(
                        $row['PharmacistID']
                    );

                }

                ?>

            </td>


            <!-- SALE DATE -->

            <td>

                <?php

                echo e(
                    $row['SaleDate']
                );

                ?>

            </td>


            <!-- TOTAL -->

            <td>

                ₹

                <?php

                echo number_format(
                    (float)$row['TotalAmount'],
                    2
                );

                ?>

            </td>


            <!-- PAYMENT METHOD -->

            <td>

                <?php

                echo e(
                    $row['PaymentMethod']
                );

                ?>

            </td>


            <!-- ACTION -->

            <td>

                <a
                    href="view_sale_items.php?id=<?php
                        echo urlencode(
                            $row['SaleID']
                        );
                    ?>"
                    class="button items"
                >
                    View Items
                </a>

            </td>


        </tr>


        <?php

            }

        } else {

        ?>


        <tr>

            <td colspan="7">

                No sales found.

            </td>

        </tr>


        <?php

        }

        ?>


    </table>


</div>


</body>

</html>