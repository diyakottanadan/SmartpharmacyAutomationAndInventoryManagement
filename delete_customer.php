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
   CSRF TOKEN
   ========================================= */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrfToken =
    $_SESSION['csrf_token'];


/* =========================================
   HELPER FUNCTION
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
   VARIABLES
   ========================================= */

$customerID = 0;

$customer = null;

$errorMessage = "";


/* =========================================
   GET CUSTOMER ID
   ========================================= */

if (isset($_GET['id'])) {

    $getID = $_GET['id'];

    if (
        is_string($getID) &&
        ctype_digit($getID) &&
        (int)$getID > 0
    ) {

        $customerID = (int)$getID;

    }
    else {

        $errorMessage =
            "Invalid Customer ID.";

    }

}


/* =========================================
   DELETE CUSTOMER
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =========================================
       CSRF VALIDATION
       ========================================= */

    $submittedToken =
        $_POST['csrf_token'] ?? '';

    if (
        empty($submittedToken) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        )
    ) {

        $errorMessage =
            "Invalid request. Please try again.";

    }


    /* =========================================
       VALIDATE CUSTOMER ID
       ========================================= */

    $postedID =
        $_POST['customerID'] ?? '';

    if (
        !is_string($postedID) ||
        !ctype_digit($postedID) ||
        (int)$postedID <= 0
    ) {

        $errorMessage =
            "Invalid Customer ID.";

    }
    else {

        $customerID =
            (int)$postedID;

    }


    /* =========================================
       CHECK CUSTOMER EXISTS
       ========================================= */

    if (
        $errorMessage === "" &&
        $customerID > 0
    ) {

        $checkSQL = "

            SELECT
                CustomerID,
                CustomerName

            FROM customer

            WHERE CustomerID = ?

            LIMIT 1

        ";


        $checkStmt =
            mysqli_prepare(
                $conn,
                $checkSQL
            );


        if (!$checkStmt) {

            $errorMessage =
                "Unable to verify customer.";

        }
        else {

            mysqli_stmt_bind_param(
                $checkStmt,
                "i",
                $customerID
            );


            mysqli_stmt_execute(
                $checkStmt
            );


            $checkResult =
                mysqli_stmt_get_result(
                    $checkStmt
                );


            if (
                !$checkResult ||
                mysqli_num_rows(
                    $checkResult
                ) === 0
            ) {

                $errorMessage =
                    "Customer not found.";

            }
            else {

                $customer =
                    mysqli_fetch_assoc(
                        $checkResult
                    );

            }


            mysqli_stmt_close(
                $checkStmt
            );

        }

    }


    /* =========================================
       CHECK WHETHER CUSTOMER HAS SALES
       ========================================= */

    if (
        $errorMessage === "" &&
        $customerID > 0
    ) {


        $saleCheckSQL = "

            SELECT SaleID

            FROM sale

            WHERE CustomerID = ?

            LIMIT 1

        ";


        $saleCheckStmt =
            mysqli_prepare(
                $conn,
                $saleCheckSQL
            );


        if (!$saleCheckStmt) {

            $errorMessage =
                "Unable to check customer sales.";

        }
        else {

            mysqli_stmt_bind_param(
                $saleCheckStmt,
                "i",
                $customerID
            );


            mysqli_stmt_execute(
                $saleCheckStmt
            );


            $saleResult =
                mysqli_stmt_get_result(
                    $saleCheckStmt
                );


            if (
                $saleResult &&
                mysqli_num_rows(
                    $saleResult
                ) > 0
            ) {

                $errorMessage =
                    "This customer cannot be deleted because sales records already exist for this customer.";

            }


            mysqli_stmt_close(
                $saleCheckStmt
            );

        }

    }


    /* =========================================
       DELETE CUSTOMER
       ========================================= */

    if (
        $errorMessage === "" &&
        $customerID > 0
    ) {


        $deleteSQL = "

            DELETE FROM customer

            WHERE CustomerID = ?

        ";


        $deleteStmt =
            mysqli_prepare(
                $conn,
                $deleteSQL
            );


        if (!$deleteStmt) {

            $errorMessage =
                "Unable to delete customer.";

        }
        else {

            mysqli_stmt_bind_param(
                $deleteStmt,
                "i",
                $customerID
            );


            if (
                mysqli_stmt_execute(
                    $deleteStmt
                )
            ) {

                if (
                    mysqli_stmt_affected_rows(
                        $deleteStmt
                    ) === 1
                ) {

                    header(
                        "Location: view_customer.php?deleted=1"
                    );

                    exit();

                }
                else {

                    $errorMessage =
                        "Customer could not be deleted.";

                }

            }
            else {

                $errorMessage =
                    "Unable to delete customer. Please try again.";

            }


            mysqli_stmt_close(
                $deleteStmt
            );

        }

    }

}


/* =========================================
   LOAD CUSTOMER FOR CONFIRMATION
   ========================================= */

if (
    $customerID > 0 &&
    $customer === null &&
    $errorMessage === ""
) {


    $customerSQL = "

        SELECT
            CustomerID,
            CustomerName,
            Phone,
            Email

        FROM customer

        WHERE CustomerID = ?

        LIMIT 1

    ";


    $customerStmt =
        mysqli_prepare(
            $conn,
            $customerSQL
        );


    if (!$customerStmt) {

        $errorMessage =
            "Unable to load customer information.";

    }
    else {

        mysqli_stmt_bind_param(
            $customerStmt,
            "i",
            $customerID
        );


        mysqli_stmt_execute(
            $customerStmt
        );


        $customerResult =
            mysqli_stmt_get_result(
                $customerStmt
            );


        if (
            !$customerResult ||
            mysqli_num_rows(
                $customerResult
            ) === 0
        ) {

            $errorMessage =
                "Customer not found.";

        }
        else {

            $customer =
                mysqli_fetch_assoc(
                    $customerResult
                );

        }


        mysqli_stmt_close(
            $customerStmt
        );

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Delete Customer
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

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
                95%;

            margin:
                70px auto;

            background:
                white;

            padding:
                30px;

            border-radius:
                10px;

            box-shadow:
                0 0 10px #ccc;

            text-align:
                center;

        }


        h2 {

            margin-top:
                0;

            color:
                #c0392b;

        }


        .warning {

            background:
                #fff3cd;

            color:
                #856404;

            padding:
                15px;

            border-radius:
                5px;

            margin-bottom:
                20px;

            border:
                1px solid #ffeeba;

        }


        .customer-details {

            text-align:
                left;

            background:
                #f8f9fa;

            padding:
                15px;

            border-radius:
                5px;

            margin-bottom:
                20px;

        }


        .customer-details p {

            margin:
                8px 0;

        }


        .error {

            background:
                #f8d7da;

            color:
                #721c24;

            padding:
                15px;

            border-radius:
                5px;

            margin-bottom:
                20px;

            border:
                1px solid #f5c6cb;

        }


        .delete-btn {

            width:
                100%;

            padding:
                12px;

            border:
                none;

            background:
                #c0392b;

            color:
                white;

            border-radius:
                5px;

            cursor:
                pointer;

            font-size:
                16px;

        }


        .delete-btn:hover {

            background:
                #962d22;

        }


        .back-btn {

            display:
                block;

            width:
                100%;

            padding:
                12px;

            margin-top:
                10px;

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

            background:
                #444;

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Delete Customer
    </h2>


    <?php if ($errorMessage !== "") { ?>

        <div class="error">

            <?php
            echo e($errorMessage);
            ?>

        </div>


        <a
            href="view_customer.php"
            class="back-btn"
        >

            Back to Customer List

        </a>

    <?php } ?>


    <?php if ($customer !== null && $errorMessage === "") { ?>


        <div class="warning">

            <strong>
                Warning
            </strong>

            <br><br>

            Are you sure you want to delete this customer?

        </div>


        <div class="customer-details">


            <p>

                <strong>
                    Customer ID:
                </strong>

                <?php
                echo e(
                    $customer['CustomerID']
                );
                ?>

            </p>


            <p>

                <strong>
                    Name:
                </strong>

                <?php
                echo e(
                    $customer['CustomerName']
                );
                ?>

            </p>


            <p>

                <strong>
                    Phone:
                </strong>

                <?php
                echo e(
                    $customer['Phone']
                );
                ?>

            </p>


            <p>

                <strong>
                    Email:
                </strong>

                <?php
                echo e(
                    $customer['Email']
                );
                ?>

            </p>


        </div>


        <form
            method="POST"
            onsubmit="
                return confirm(
                    'Are you sure you want to permanently delete this customer?'
                );
            "
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo e($csrfToken);
                ?>"
            >


            <input
                type="hidden"
                name="customerID"
                value="<?php
                    echo e(
                        $customer['CustomerID']
                    );
                ?>"
            >


            <button
                type="submit"
                class="delete-btn"
            >

                Yes, Delete Customer

            </button>


        </form>


        <a
            href="view_customer.php"
            class="back-btn"
        >

            Cancel

        </a>


    <?php } ?>


</div>


</body>

</html>