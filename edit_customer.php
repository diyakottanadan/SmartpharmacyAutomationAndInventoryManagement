<?php

include("db_connect.php");


/* =========================================
   INITIAL VARIABLES
   ========================================= */

$row = null;

$message = "";

$customerID = 0;


/* =========================================
   GET CUSTOMER ID FROM EDIT LINK
   ========================================= */

if (isset($_GET['id'])) {

    $customerID = (int)$_GET['id'];

}


/* =========================================
   UPDATE CUSTOMER
   ========================================= */

if (isset($_POST['update'])) {

    $customerID =
        (int)$_POST['customerID'];

    $customerName =
        mysqli_real_escape_string(
            $conn,
            trim($_POST['customerName'])
        );

    $phone =
        mysqli_real_escape_string(
            $conn,
            trim($_POST['phone'])
        );

    $email =
        mysqli_real_escape_string(
            $conn,
            trim($_POST['email'])
        );

    $address =
        mysqli_real_escape_string(
            $conn,
            trim($_POST['address'])
        );


    /* Validate Customer ID */

    if ($customerID <= 0) {

        $message =
            "Invalid Customer ID.";

    }


    /* Validate Customer Name */

    elseif ($customerName == "") {

        $message =
            "Customer name is required.";

    }


    /* Validate Phone */

    elseif ($phone == "") {

        $message =
            "Phone number is required.";

    }


    else {


        /* =========================================
           UPDATE DATABASE
           ========================================= */

        $updateSQL = "

            UPDATE customer

            SET

                CustomerName =
                    '$customerName',

                Phone =
                    '$phone',

                Email =
                    '$email',

                Address =
                    '$address'

            WHERE CustomerID =
                $customerID

        ";


        if (
            mysqli_query(
                $conn,
                $updateSQL
            )
        ) {

            header(
                "Location: view_customer.php"
            );

            exit();

        }

        else {

            $message =
                "Error: "
                .
                mysqli_error($conn);

        }

    }

}


/* =========================================
   GET CUSTOMER DETAILS
   ========================================= */

if ($customerID > 0) {

    $customerSQL = "

        SELECT *

        FROM customer

        WHERE CustomerID =
            $customerID

    ";


    $customerResult =
        mysqli_query(
            $conn,
            $customerSQL
        );


    if (

        $customerResult
        &&
        mysqli_num_rows(
            $customerResult
        ) > 0

    ) {

        $row =
            mysqli_fetch_assoc(
                $customerResult
            );

    }

    else {

        $message =
            "Customer not found.";

    }

}


?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Edit Customer
    </title>


    <style>

        body {

            font-family:
                Arial,
                sans-serif;

            background-color:
                #f4f4f4;

        }


        .container {

            width:
                450px;

            max-width:
                90%;

            margin:
                50px auto;

            background:
                white;

            padding:
                30px;

            border-radius:
                10px;

            box-shadow:
                0 0 10px #ccc;

        }


        h2 {

            text-align:
                center;

            margin-top:
                0;

        }


        label {

            display:
                block;

            margin-top:
                15px;

            font-weight:
                bold;

        }


        input,
        textarea {

            width:
                100%;

            padding:
                10px;

            margin-top:
                5px;

            box-sizing:
                border-box;

            font-size:
                15px;

        }


        textarea {

            min-height:
                80px;

            resize:
                vertical;

        }


        button {

            width:
                100%;

            padding:
                12px;

            margin-top:
                20px;

            border:
                none;

            cursor:
                pointer;

            font-size:
                16px;

            border-radius:
                5px;

        }


        .update-btn {

            background:
                green;

            color:
                white;

        }


        .message {

            text-align:
                center;

            color:
                #721c24;

            background:
                #f8d7da;

            padding:
                10px;

            border-radius:
                5px;

            margin-bottom:
                15px;

        }


        .back-btn {

            display:
                block;

            text-align:
                center;

            margin-top:
                15px;

            padding:
                12px;

            background:
                #555;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

        }


        .back-btn:hover {

            opacity:
                0.85;

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Edit Customer
    </h2>


    <?php

    if ($message != "") {

        echo "<div class='message'>";

        echo htmlspecialchars(
            $message
        );

        echo "</div>";

    }

    ?>


    <?php

    if ($row != null) {

    ?>


        <form method="POST">


            <!-- CUSTOMER ID -->

            <label>
                Customer ID
            </label>


            <input
                type="number"
                name="customerID"
                value="<?php
                    echo htmlspecialchars(
                        $row['CustomerID']
                    );
                ?>"
                readonly
            >



            <!-- CUSTOMER NAME -->

            <label>
                Customer Name
            </label>


            <input
                type="text"
                name="customerName"
                value="<?php
                    echo htmlspecialchars(
                        $row['CustomerName']
                    );
                ?>"
                required
            >



            <!-- PHONE -->

            <label>
                Phone
            </label>


            <input
                type="text"
                name="phone"
                value="<?php
                    echo htmlspecialchars(
                        $row['Phone']
                    );
                ?>"
                required
            >



            <!-- EMAIL -->

            <label>
                Email
            </label>


            <input
                type="email"
                name="email"
                value="<?php
                    echo htmlspecialchars(
                        $row['Email']
                    );
                ?>"
            >



            <!-- ADDRESS -->

            <label>
                Address
            </label>


            <textarea
                name="address"
            ><?php
                echo htmlspecialchars(
                    $row['Address']
                );
            ?></textarea>



            <!-- UPDATE -->

            <button
                type="submit"
                name="update"
                class="update-btn"
            >

                Update Customer

            </button>


        </form>


    <?php

    }

    ?>


    <a
        href="view_customer.php"
        class="back-btn"
    >

        Back to Customer List

    </a>


</div>


</body>

</html>