<?php

session_start();

include("db_connect.php");


/* =========================================
   CHECK WHETHER PHARMACIST IS LOGGED IN
   ========================================= */

if (!isset($_SESSION['pharmacist'])) {

    header("Location: login.php");
    exit();

}


/* =========================================
   SAFE OUTPUT FUNCTION
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
   GET LOGGED-IN PHARMACIST ID
   ========================================= */

$pharmacistID = filter_var(
    $_SESSION['PharmacistID'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);


if ($pharmacistID === false) {

    session_destroy();

    header("Location: login.php");
    exit();

}


/* =========================================
   GET MEDICINE REQUESTS
   ========================================= */

$sql = "

    SELECT
        RequestID,
        MedicineName,
        Category,
        SupplierID,
        BatchNo,
        PurchasePrice,
        SellingPrice,
        ExpiryDate,
        Description,
        RequestDate,
        Status

    FROM medicine_request

    WHERE PharmacistID = ?

    ORDER BY RequestDate DESC

";


$stmt = mysqli_prepare($conn, $sql);


if (!$stmt) {

    die("Unable to load medicine requests.");

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $pharmacistID
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    die("Unable to load medicine requests.");

}


$result = mysqli_stmt_get_result($stmt);

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
        My Medicine Requests
    </title>


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

            width: 95%;
            margin: 30px auto;

        }


        h2 {

            text-align: center;

        }


        .table-container {

            width: 100%;
            overflow-x: auto;

        }


        table {

            width: 100%;
            min-width: 1100px;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 0 8px #ccc;

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

            background-color: #f9f9f9;

        }


        .button {

            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            color: white;
            background-color: #555;
            border-radius: 5px;
            margin-bottom: 20px;

        }


        .button:hover {

            opacity: 0.85;

        }


        .pending {

            color: orange;
            font-weight: bold;

        }


        .approved {

            color: green;
            font-weight: bold;

        }


        .rejected {

            color: red;
            font-weight: bold;

        }


        .no-data {

            background-color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 0 8px #ccc;

        }

    </style>

</head>


<body>


<div class="container">


    <a
        href="pharmacist_dashboard.php"
        class="button"
    >
        ← Back to Dashboard
    </a>


    <h2>
        My Medicine Requests
    </h2>


    <?php

    if (mysqli_num_rows($result) > 0) {

    ?>


        <div class="table-container">


            <table>


                <tr>

                    <th>
                        Request ID
                    </th>

                    <th>
                        Medicine Name
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Supplier ID
                    </th>

                    <th>
                        Batch No
                    </th>

                    <th>
                        Purchase Price
                    </th>

                    <th>
                        Selling Price
                    </th>

                    <th>
                        Expiry Date
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Request Date
                    </th>

                    <th>
                        Status
                    </th>

                </tr>


                <?php

                while (
                    $row = mysqli_fetch_assoc($result)
                ) {

                ?>


                    <tr>


                        <td>

                            <?php
                            echo e($row['RequestID']);
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
                            echo e($row['SupplierID']);
                            ?>

                        </td>


                        <td>

                            <?php
                            echo e($row['BatchNo']);
                            ?>

                        </td>


                        <td>

                            ₹<?php
                            echo number_format(
                                (float)$row['PurchasePrice'],
                                2
                            );
                            ?>

                        </td>


                        <td>

                            ₹<?php
                            echo number_format(
                                (float)$row['SellingPrice'],
                                2
                            );
                            ?>

                        </td>


                        <td>

                            <?php
                            echo e($row['ExpiryDate']);
                            ?>

                        </td>


                        <td>

                            <?php
                            echo e($row['Description']);
                            ?>

                        </td>


                        <td>

                            <?php
                            echo e($row['RequestDate']);
                            ?>

                        </td>


                        <td>


                            <?php

                            $status = $row['Status'];


                            if ($status === 'Pending') {

                            ?>

                                <span class="pending">
                                    Pending
                                </span>

                            <?php

                            } elseif ($status === 'Approved') {

                            ?>

                                <span class="approved">
                                    Approved
                                </span>

                            <?php

                            } elseif ($status === 'Rejected') {

                            ?>

                                <span class="rejected">
                                    Rejected
                                </span>

                            <?php

                            } else {

                            ?>

                                <?php
                                echo e($status);
                                ?>

                            <?php

                            }

                            ?>


                        </td>


                    </tr>


                <?php

                }

                ?>


            </table>


        </div>


    <?php

    } else {

    ?>


        <div class="no-data">

            You have not submitted any medicine
            requests yet.

        </div>


    <?php

    }


    mysqli_stmt_close($stmt);

    ?>


</div>


</body>

</html>