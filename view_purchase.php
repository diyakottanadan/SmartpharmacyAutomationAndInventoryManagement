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
   Get Purchases
----------------------------------- */

$sql = "
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

    ORDER BY p.Purchase_Id DESC
";


$result = mysqli_query($conn, $sql);


if (!$result) {

    die(
        "Error loading purchases: "
        . e(mysqli_error($conn))
    );

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>View Purchases</title>

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
            width: 95%;
            max-width: 1400px;
            margin: 40px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        .top-buttons {
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }

        .back {
            background-color: #555;
        }

        .add {
            background-color: green;
        }

        .button:hover {
            opacity: 0.85;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            margin-top: 10px;
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
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #e9e9e9;
        }

        .details {
            display: inline-block;
            padding: 7px 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .details:hover {
            opacity: 0.85;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: bold;
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

        .no-data {
            text-align: center;
            padding: 25px;
            font-weight: bold;
            color: #666;
        }

        @media (max-width: 600px) {

            .container {
                margin: 20px auto;
                padding: 15px;
            }

            h2 {
                font-size: 22px;
            }

            .button {
                display: block;
                text-align: center;
                margin: 8px 0;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h2>Purchase Management</h2>


    <!-- Buttons -->

    <div class="top-buttons">

        <a
            href="admin_dashboard.php"
            class="button back"
        >
            Back to Dashboard
        </a>

        <a
            href="add_purchase.php"
            class="button add"
        >
            Add Purchase
        </a>

        <a
            href="add_purchase_item.php"
            class="button add"
        >
            Add Purchase Item
        </a>

    </div>


    <!-- Purchase Table -->

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>Purchase ID</th>

                    <th>Supplier</th>

                    <th>Pharmacist</th>

                    <th>Purchase Date</th>

                    <th>Total Amount</th>

                    <th>Status</th>

                    <th>Details</th>

                </tr>

            </thead>


            <tbody>

            <?php

            if (mysqli_num_rows($result) > 0) {

                while (
                    $row =
                    mysqli_fetch_assoc($result)
                ) {

                    /* Status class */

                    $statusClass = "pending";

                    if (
                        strtolower(
                            $row['Status']
                        ) === "completed"
                    ) {

                        $statusClass = "completed";

                    } elseif (
                        strtolower(
                            $row['Status']
                        ) === "cancelled"
                    ) {

                        $statusClass = "cancelled";

                    }

                    ?>

                    <tr>

                        <!-- Purchase ID -->

                        <td>

                            <?php
                            echo e(
                                $row['Purchase_Id']
                            );
                            ?>

                        </td>


                        <!-- Supplier -->

                        <td>

                            <?php

                            if (
                                !empty(
                                    $row['SupplierName']
                                )
                            ) {

                                echo e(
                                    $row['SupplierName']
                                );

                            } else {

                                echo "Supplier #"
                                    . e(
                                        $row['SupplierID']
                                    );

                            }

                            ?>

                        </td>


                        <!-- Pharmacist -->

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

                                echo "Pharmacist #"
                                    . e(
                                        $row['PharmacistID']
                                    );

                            }

                            ?>

                        </td>


                        <!-- Purchase Date -->

                        <td>

                            <?php

                            echo e(
                                date(
                                    "d-m-Y",
                                    strtotime(
                                        $row['PurchaseDate']
                                    )
                                )
                            );

                            ?>

                        </td>


                        <!-- Total Amount -->

                        <td class="amount">

                            ₹
                            <?php

                            echo number_format(
                                (float)$row['TotalAmount'],
                                2
                            );

                            ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <span
                                class="status
                                <?php
                                echo e(
                                    $statusClass
                                );
                                ?>"
                            >

                                <?php
                                echo e(
                                    $row['Status']
                                );
                                ?>

                            </span>

                        </td>


                        <!-- Details -->

                        <td>

                            <a
                                href="purchase_details.php?PurchaseID=<?php
                                    echo urlencode(
                                        $row['Purchase_Id']
                                    );
                                ?>"
                                class="details"
                            >

                                View Details

                            </a>

                        </td>

                    </tr>

                    <?php

                }

            } else {

                ?>

                <tr>

                    <td
                        colspan="7"
                        class="no-data"
                    >

                        No purchases found.

                    </td>

                </tr>

                <?php

            }

            ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>