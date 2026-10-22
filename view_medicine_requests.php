<?php

session_start();

include("db_connect.php");


/* =========================================
   CHECK WHETHER ADMIN IS LOGGED IN
   ========================================= */

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}


/* =========================================
   GET ALL MEDICINE REQUESTS
   ========================================= */

$sql = "

    SELECT
        medicine_request.*,
        pharmacist.Name AS PharmacistName

    FROM medicine_request

    INNER JOIN pharmacist
        ON medicine_request.PharmacistID =
           pharmacist.PharmacistID

    ORDER BY
        medicine_request.RequestDate DESC

";

$result = mysqli_query(
    $conn,
    $sql
);

?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Medicine Requests
    </title>


    <style>

        body {

            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;

        }


        .container {

            width: 95%;
            margin: 30px auto;

        }


        h2 {

            text-align: center;

        }


        table {

            width: 100%;
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
            padding: 8px 12px;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            margin: 2px;

        }


        .approve {

            background-color: green;

        }


        .reject {

            background-color: #c0392b;

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


        .back-button {

            background-color: #555;
            margin-bottom: 20px;

        }


        .no-data {

            text-align: center;
            background-color: white;
            padding: 30px;

        }

    </style>

</head>


<body>


<div class="container">


    <a
        href="admin_dashboard.php"
        class="button back-button"
    >
        ← Back to Dashboard
    </a>


    <h2>
        Medicine Requests
    </h2>


    <?php

    if (
        mysqli_num_rows($result) > 0
    ) {

    ?>


    <table>


        <tr>

            <th>
                Request ID
            </th>

            <th>
                Pharmacist
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

            <th>
                Action
            </th>

        </tr>


        <?php

        while (
            $row =
            mysqli_fetch_assoc(
                $result
            )
        ) {

        ?>


        <tr>


            <td>

                <?php
                echo $row['RequestID'];
                ?>

            </td>


            <td>

                <?php

                echo htmlspecialchars(
                    $row['PharmacistName']
                );

                ?>

            </td>


            <td>

                <?php

                echo htmlspecialchars(
                    $row['MedicineName']
                );

                ?>

            </td>


            <td>

                <?php

                echo htmlspecialchars(
                    $row['Category']
                );

                ?>

            </td>


            <td>

                <?php

                echo $row['SupplierID'];

                ?>

            </td>


            <td>

                <?php

                echo htmlspecialchars(
                    $row['BatchNo']
                );

                ?>

            </td>


            <td>

                ₹

                <?php

                echo $row['PurchasePrice'];

                ?>

            </td>


            <td>

                ₹

                <?php

                echo $row['SellingPrice'];

                ?>

            </td>


            <td>

                <?php

                echo $row['ExpiryDate'];

                ?>

            </td>


            <td>

                <?php

                echo htmlspecialchars(
                    $row['Description']
                );

                ?>

            </td>


            <td>

                <?php

                echo $row['RequestDate'];

                ?>

            </td>


            <td>


                <?php

                if (
                    $row['Status'] ==
                    "Pending"
                ) {

                ?>


                    <span class="pending">

                        Pending

                    </span>


                <?php

                } elseif (
                    $row['Status'] ==
                    "Approved"
                ) {

                ?>


                    <span class="approved">

                        Approved

                    </span>


                <?php

                } else {

                ?>


                    <span class="rejected">

                        Rejected

                    </span>


                <?php

                }

                ?>


            </td>


            <td>


                <?php

                if (
                    $row['Status'] ==
                    "Pending"
                ) {

                ?>


                    <a

                        href="approve_medicine_request.php?id=<?php echo $row['RequestID']; ?>"

                        class="button approve"

                    >

                        Approve

                    </a>


                    <a

                        href="reject_medicine_request.php?id=<?php echo $row['RequestID']; ?>"

                        class="button reject"

                        onclick="return confirm('Are you sure you want to reject this medicine request?');"

                    >

                        Reject

                    </a>


                <?php

                } else {

                    echo "-";

                }

                ?>


            </td>


        </tr>


        <?php

        }

        ?>


    </table>


    <?php

    } else {

    ?>


        <div class="no-data">

            No medicine requests found.

        </div>


    <?php

    }

    ?>


</div>


</body>

</html>