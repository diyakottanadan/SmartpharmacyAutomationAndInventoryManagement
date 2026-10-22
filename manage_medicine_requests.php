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
   MESSAGE VARIABLES
   ========================================= */

$successMessage = "";
$errorMessage = "";


/* =========================================
   HANDLE APPROVE / REJECT
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =========================================
       CHECK CSRF TOKEN
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

    else {


        /* =========================================
           VALIDATE REQUEST ID
           ========================================= */

        $requestID =
            $_POST['request_id'] ?? '';

        if (
            !is_string($requestID) ||
            !ctype_digit($requestID) ||
            (int)$requestID <= 0
        ) {

            $errorMessage =
                "Invalid medicine request ID.";

        }

        else {

            $requestID =
                (int)$requestID;


            /* =========================================
               DETERMINE ACTION
               ========================================= */

            $action =
                $_POST['action'] ?? '';

            if (
                $action !== 'approve' &&
                $action !== 'reject'
            ) {

                $errorMessage =
                    "Invalid action.";

            }

            else {


                /* =========================================
                   GET PENDING REQUEST
                   ========================================= */

                $requestSQL = "

                    SELECT
                        RequestID,
                        PharmacistID,
                        MedicineName,
                        Category,
                        SupplierID,
                        BatchNo,
                        PurchasePrice,
                        SellingPrice,
                        ExpiryDate,
                        Description,
                        Status

                    FROM medicine_request

                    WHERE RequestID = ?

                    AND Status = 'Pending'

                    LIMIT 1

                ";


                $stmt =
                    mysqli_prepare(
                        $conn,
                        $requestSQL
                    );


                if (!$stmt) {

                    $errorMessage =
                        "Unable to process the request.";

                }

                else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "i",
                        $requestID
                    );


                    mysqli_stmt_execute($stmt);


                    $result =
                        mysqli_stmt_get_result($stmt);


                    if (
                        !$result ||
                        mysqli_num_rows($result) === 0
                    ) {

                        $errorMessage =
                            "This request does not exist or has already been processed.";

                    }

                    else {

                        $request =
                            mysqli_fetch_assoc($result);


                        /* =========================================
                           REJECT REQUEST
                           ========================================= */

                        if ($action === 'reject') {


                            $rejectSQL = "

                                UPDATE medicine_request

                                SET Status = 'Rejected'

                                WHERE RequestID = ?

                                AND Status = 'Pending'

                            ";


                            $rejectStmt =
                                mysqli_prepare(
                                    $conn,
                                    $rejectSQL
                                );


                            if (!$rejectStmt) {

                                $errorMessage =
                                    "Unable to reject the medicine request.";

                            }

                            else {

                                mysqli_stmt_bind_param(
                                    $rejectStmt,
                                    "i",
                                    $requestID
                                );


                                if (
                                    mysqli_stmt_execute(
                                        $rejectStmt
                                    )
                                ) {

                                    if (
                                        mysqli_stmt_affected_rows(
                                            $rejectStmt
                                        ) === 1
                                    ) {

                                        $successMessage =
                                            "Medicine request rejected successfully.";

                                    }

                                    else {

                                        $errorMessage =
                                            "The request could not be rejected. It may have already been processed.";

                                    }

                                }

                                else {

                                    $errorMessage =
                                        "Unable to reject the medicine request.";

                                }


                                mysqli_stmt_close(
                                    $rejectStmt
                                );

                            }

                        }


                        /* =========================================
                           APPROVE REQUEST
                           ========================================= */

                        elseif ($action === 'approve') {


                            /* =========================================
                               VALIDATE REQUEST DATA AGAIN
                               ========================================= */

                            $medicineName =
                                trim(
                                    $request['MedicineName']
                                );

                            $category =
                                trim(
                                    $request['Category']
                                );

                            $supplierID =
                                $request['SupplierID'];

                            $batchNo =
                                trim(
                                    $request['BatchNo']
                                );

                            $purchasePrice =
                                $request['PurchasePrice'];

                            $sellingPrice =
                                $request['SellingPrice'];

                            $expiryDate =
                                $request['ExpiryDate'];

                            $description =
                                trim(
                                    $request['Description']
                                );


                            $approvalErrors = [];


                            /* Medicine Name */

                            if (
                                $medicineName === '' ||
                                strlen($medicineName) < 2 ||
                                strlen($medicineName) > 25
                            ) {

                                $approvalErrors[] =
                                    "Invalid medicine name.";

                            }

                            elseif (
                                !preg_match(
                                    "/^[A-Za-z0-9][A-Za-z0-9 .()&+'\/-]*$/",
                                    $medicineName
                                )
                            ) {

                                $approvalErrors[] =
                                    "Invalid medicine name format.";

                            }


                            /* Category */

                            if (
                                $category === '' ||
                                strlen($category) < 2 ||
                                strlen($category) > 25
                            ) {

                                $approvalErrors[] =
                                    "Invalid medicine category.";

                            }

                            elseif (
                                !preg_match(
                                    "/^[A-Za-z][A-Za-z &\/-]*$/",
                                    $category
                                )
                            ) {

                                $approvalErrors[] =
                                    "Invalid medicine category format.";

                            }


                            /* Supplier ID */

                            if (
                                !ctype_digit(
                                    (string)$supplierID
                                ) ||
                                (int)$supplierID <= 0
                            ) {

                                $approvalErrors[] =
                                    "Invalid supplier.";

                            }


                            /* Batch Number */

                            if (
                                $batchNo === '' ||
                                strlen($batchNo) < 2 ||
                                strlen($batchNo) > 25
                            ) {

                                $approvalErrors[] =
                                    "Invalid batch number.";

                            }

                            elseif (
                                !preg_match(
                                    "/^[A-Za-z0-9][A-Za-z0-9-]*$/",
                                    $batchNo
                                )
                            ) {

                                $approvalErrors[] =
                                    "Invalid batch number format.";

                            }


                            /* Purchase Price */

                            if (
                                !is_numeric(
                                    $purchasePrice
                                ) ||
                                (float)$purchasePrice <= 0 ||
                                (float)$purchasePrice > 99999999.99
                            ) {

                                $approvalErrors[] =
                                    "Invalid purchase price.";

                            }


                            /* Selling Price */

                            if (
                                !is_numeric(
                                    $sellingPrice
                                ) ||
                                (float)$sellingPrice <= 0 ||
                                (float)$sellingPrice > 99999999.99
                            ) {

                                $approvalErrors[] =
                                    "Invalid selling price.";

                            }


                            /* Selling Price >= Purchase Price */

                            if (
                                is_numeric($purchasePrice) &&
                                is_numeric($sellingPrice) &&
                                (float)$sellingPrice <
                                (float)$purchasePrice
                            ) {

                                $approvalErrors[] =
                                    "Selling price cannot be less than purchase price.";

                            }


                            /* Expiry Date */

                            $expiryObject =
                                DateTime::createFromFormat(
                                    'Y-m-d',
                                    $expiryDate
                                );


                            $expiryValid =
                                $expiryObject &&
                                $expiryObject->format('Y-m-d') ===
                                $expiryDate;


                            if (!$expiryValid) {

                                $approvalErrors[] =
                                    "Invalid expiry date.";

                            }

                            elseif (
                                $expiryDate <=
                                date('Y-m-d')
                            ) {

                                $approvalErrors[] =
                                    "Medicine expiry date must be a future date.";

                            }


                            /* Description */

                            if (
                                $description === '' ||
                                strlen($description) < 5 ||
                                strlen($description) > 500
                            ) {

                                $approvalErrors[] =
                                    "Description must contain 5 to 500 characters.";

                            }


                            /* =========================================
                               CHECK SUPPLIER EXISTS
                               ========================================= */

                            if (
                                empty($approvalErrors)
                            ) {

                                $supplierCheckSQL = "

                                    SELECT SupplierID

                                    FROM supplier

                                    WHERE SupplierID = ?

                                    LIMIT 1

                                ";


                                $supplierStmt =
                                    mysqli_prepare(
                                        $conn,
                                        $supplierCheckSQL
                                    );


                                if (!$supplierStmt) {

                                    $approvalErrors[] =
                                        "Unable to verify supplier.";

                                }

                                else {

                                    $supplierIDInt =
                                        (int)$supplierID;


                                    mysqli_stmt_bind_param(
                                        $supplierStmt,
                                        "i",
                                        $supplierIDInt
                                    );


                                    mysqli_stmt_execute(
                                        $supplierStmt
                                    );


                                    $supplierResult =
                                        mysqli_stmt_get_result(
                                            $supplierStmt
                                        );


                                    if (
                                        !$supplierResult ||
                                        mysqli_num_rows(
                                            $supplierResult
                                        ) === 0
                                    ) {

                                        $approvalErrors[] =
                                            "Selected supplier does not exist.";

                                    }


                                    mysqli_stmt_close(
                                        $supplierStmt
                                    );

                                }

                            }


                            /* =========================================
                               CHECK DUPLICATE MEDICINE + BATCH
                               ========================================= */

                            if (
                                empty($approvalErrors)
                            ) {

                                $duplicateSQL = "

                                    SELECT MedicineID

                                    FROM medicine

                                    WHERE LOWER(MedicineName)
                                    =
                                    LOWER(?)

                                    AND BatchNo = ?

                                    LIMIT 1

                                ";


                                $duplicateStmt =
                                    mysqli_prepare(
                                        $conn,
                                        $duplicateSQL
                                    );


                                if (!$duplicateStmt) {

                                    $approvalErrors[] =
                                        "Unable to check duplicate medicine.";

                                }

                                else {

                                    mysqli_stmt_bind_param(
                                        $duplicateStmt,
                                        "ss",
                                        $medicineName,
                                        $batchNo
                                    );


                                    mysqli_stmt_execute(
                                        $duplicateStmt
                                    );


                                    $duplicateResult =
                                        mysqli_stmt_get_result(
                                            $duplicateStmt
                                        );


                                    if (
                                        $duplicateResult &&
                                        mysqli_num_rows(
                                            $duplicateResult
                                        ) > 0
                                    ) {

                                        $approvalErrors[] =
                                            "A medicine with the same name and batch number already exists.";

                                    }


                                    mysqli_stmt_close(
                                        $duplicateStmt
                                    );

                                }

                            }


                            /* =========================================
                               STOP IF VALIDATION FAILED
                               ========================================= */

                            if (
                                !empty($approvalErrors)
                            ) {

                                $errorMessage =
                                    implode(
                                        " ",
                                        $approvalErrors
                                    );

                            }

                            else {


                                /* =========================================
                                   START TRANSACTION
                                   ========================================= */

                                mysqli_begin_transaction(
                                    $conn
                                );


                                try {


                                    /* =========================================
                                       INSERT MEDICINE
                                       ========================================= */

                                    /*
                                       StockQty = 0 because approval
                                       only adds the medicine.
                                    */

                                    $stockQty = 0;

                                    /*
                                       Zero stock means unavailable.
                                    */

                                    $status =
                                        'Unavailable';


                                    $insertSQL = "

                                        INSERT INTO medicine

                                        (
                                            MedicineName,
                                            Category,
                                            SupplierID,
                                            BatchNo,
                                            PurchasePrice,
                                            SellingPrice,
                                            StockQty,
                                            ExpiryDate,
                                            Description,
                                            Status
                                        )

                                        VALUES

                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?
                                        )

                                    ";


                                    $insertStmt =
                                        mysqli_prepare(
                                            $conn,
                                            $insertSQL
                                        );


                                    if (!$insertStmt) {

                                        throw new Exception(
                                            "Unable to prepare medicine insertion."
                                        );

                                    }


                                    $supplierIDInt =
                                        (int)$supplierID;

                                    $purchasePriceFloat =
                                        (float)$purchasePrice;

                                    $sellingPriceFloat =
                                        (float)$sellingPrice;


                                    /*
                                       s = MedicineName
                                       s = Category
                                       i = SupplierID
                                       s = BatchNo
                                       d = PurchasePrice
                                       d = SellingPrice
                                       i = StockQty
                                       s = ExpiryDate
                                       s = Description
                                       s = Status
                                    */

                                    mysqli_stmt_bind_param(

                                        $insertStmt,

                                        "ssisddisss",

                                        $medicineName,
                                        $category,
                                        $supplierIDInt,
                                        $batchNo,
                                        $purchasePriceFloat,
                                        $sellingPriceFloat,
                                        $stockQty,
                                        $expiryDate,
                                        $description,
                                        $status

                                    );


                                    if (
                                        !mysqli_stmt_execute(
                                            $insertStmt
                                        )
                                    ) {

                                        throw new Exception(
                                            "Unable to add medicine."
                                        );

                                    }


                                    mysqli_stmt_close(
                                        $insertStmt
                                    );


                                    /* =========================================
                                       UPDATE REQUEST STATUS
                                       ========================================= */

                                    $updateSQL = "

                                        UPDATE medicine_request

                                        SET Status = 'Approved'

                                        WHERE RequestID = ?

                                        AND Status = 'Pending'

                                    ";


                                    $updateStmt =
                                        mysqli_prepare(
                                            $conn,
                                            $updateSQL
                                        );


                                    if (!$updateStmt) {

                                        throw new Exception(
                                            "Unable to update request status."
                                        );

                                    }


                                    mysqli_stmt_bind_param(
                                        $updateStmt,
                                        "i",
                                        $requestID
                                    );


                                    if (
                                        !mysqli_stmt_execute(
                                            $updateStmt
                                        )
                                    ) {

                                        throw new Exception(
                                            "Unable to approve medicine request."
                                        );

                                    }


                                    if (
                                        mysqli_stmt_affected_rows(
                                            $updateStmt
                                        ) !== 1
                                    ) {

                                        throw new Exception(
                                            "The request has already been processed."
                                        );

                                    }


                                    mysqli_stmt_close(
                                        $updateStmt
                                    );


                                    /* =========================================
                                       COMMIT
                                       ========================================= */

                                    mysqli_commit(
                                        $conn
                                    );


                                    $successMessage =
                                        "Medicine request approved successfully.";

                                }

                                catch (Exception $e) {

                                    mysqli_rollback(
                                        $conn
                                    );


                                    $errorMessage =
                                        "Unable to approve the medicine request. No changes were made.";

                                }

                            }

                        }

                    }


                    mysqli_stmt_close($stmt);

                }

            }

        }

    }

}


/* =========================================
   GET ALL MEDICINE REQUESTS
   ========================================= */

$requestsSQL = "

    SELECT

        medicine_request.*,

        pharmacist.Name

    FROM medicine_request

    INNER JOIN pharmacist

        ON medicine_request.PharmacistID
        =
        pharmacist.PharmacistID

    ORDER BY

        CASE

            WHEN medicine_request.Status = 'Pending'
            THEN 1

            WHEN medicine_request.Status = 'Approved'
            THEN 2

            ELSE 3

        END,

        medicine_request.RequestDate DESC

";


$requestsResult =
    mysqli_query(
        $conn,
        $requestsSQL
    );


?>


<!DOCTYPE html>

<html>

<head>

    <title>
        Manage Medicine Requests
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


        /* HEADER */

        .header {

            background-color:
                #333;

            color:
                white;

            padding:
                20px;

            text-align:
                center;

        }


        /* CONTAINER */

        .container {

            width:
                95%;

            margin:
                30px auto;

        }


        /* BACK BUTTON */

        .back-button {

            display:
                inline-block;

            margin-bottom:
                20px;

            padding:
                10px 18px;

            background-color:
                #555;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

        }


        .back-button:hover {

            background-color:
                #444;

        }


        /* MESSAGE */

        .message {

            padding:
                14px;

            margin-bottom:
                20px;

            border-radius:
                5px;

            font-weight:
                bold;

        }


        .success {

            background-color:
                #d4edda;

            color:
                #155724;

            border:
                1px solid #c3e6cb;

        }


        .error {

            background-color:
                #f8d7da;

            color:
                #721c24;

            border:
                1px solid #f5c6cb;

        }


        /* TABLE */

        .table-container {

            overflow-x:
                auto;

            background-color:
                white;

        }


        table {

            width:
                100%;

            min-width:
                1400px;

            border-collapse:
                collapse;

            background-color:
                white;

            box-shadow:
                0 0 8px #ccc;

        }


        th {

            background-color:
                #333;

            color:
                white;

        }


        th,
        td {

            padding:
                12px;

            text-align:
                center;

            border:
                1px solid #ddd;

            vertical-align:
                middle;

        }


        td {

            color:
                #333;

        }


        /* BUTTONS */

        .action-form {

            display:
                inline-block;

            margin:
                2px;

        }


        .approve,
        .reject {

            border:
                none;

            padding:
                8px 12px;

            color:
                white;

            border-radius:
                5px;

            cursor:
                pointer;

            font-size:
                14px;

        }


        .approve {

            background-color:
                green;

        }


        .approve:hover {

            background-color:
                #006400;

        }


        .reject {

            background-color:
                #c0392b;

        }


        .reject:hover {

            background-color:
                #962d22;

        }


        /* STATUS */

        .pending {

            color:
                orange;

            font-weight:
                bold;

        }


        .approved {

            color:
                green;

            font-weight:
                bold;

        }


        .rejected {

            color:
                red;

            font-weight:
                bold;

        }


        /* DESCRIPTION */

        .description {

            max-width:
                250px;

            word-wrap:
                break-word;

        }


        /* NO DATA */

        .no-data {

            text-align:
                center;

            background-color:
                white;

            padding:
                20px;

            font-weight:
                bold;

        }


        /* MOBILE */

        @media (max-width: 768px) {

            .container {

                width:
                    98%;

                margin:
                    15px auto;

            }

            .header h1 {

                font-size:
                    24px;

            }

        }

    </style>


</head>


<body>


<!-- =========================================
     HEADER
     ========================================= -->

<div class="header">

    <h1>
        Medicine Requests
    </h1>


    <p>
        Approve or Reject Pharmacist Requests
    </p>

</div>



<div class="container">


    <!-- =========================================
         BACK BUTTON
         ========================================= -->

    <a
        href="admin_dashboard.php"
        class="back-button"
    >

        ← Back to Dashboard

    </a>



    <!-- =========================================
         SUCCESS MESSAGE
         ========================================= -->

    <?php if ($successMessage !== "") { ?>

        <div class="message success">

            <?php
            echo e($successMessage);
            ?>

        </div>

    <?php } ?>



    <!-- =========================================
         ERROR MESSAGE
         ========================================= -->

    <?php if ($errorMessage !== "") { ?>

        <div class="message error">

            <?php
            echo e($errorMessage);
            ?>

        </div>

    <?php } ?>



    <!-- =========================================
         REQUEST TABLE
         ========================================= -->

    <div class="table-container">

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


            if (
                $requestsResult &&
                mysqli_num_rows(
                    $requestsResult
                ) > 0
            ) {


                while (
                    $row =
                        mysqli_fetch_assoc(
                            $requestsResult
                        )
                ) {

            ?>


            <tr>


                <!-- REQUEST ID -->

                <td>

                    <?php
                    echo e(
                        $row['RequestID']
                    );
                    ?>

                </td>



                <!-- PHARMACIST -->

                <td>

                    <?php
                    echo e(
                        $row['Name']
                    );
                    ?>

                </td>



                <!-- MEDICINE NAME -->

                <td>

                    <?php
                    echo e(
                        $row['MedicineName']
                    );
                    ?>

                </td>



                <!-- CATEGORY -->

                <td>

                    <?php
                    echo e(
                        $row['Category']
                    );
                    ?>

                </td>



                <!-- SUPPLIER -->

                <td>

                    <?php
                    echo e(
                        $row['SupplierID']
                    );
                    ?>

                </td>



                <!-- BATCH -->

                <td>

                    <?php
                    echo e(
                        $row['BatchNo']
                    );
                    ?>

                </td>



                <!-- PURCHASE PRICE -->

                <td>

                    ₹<?php
                    echo number_format(
                        (float)$row['PurchasePrice'],
                        2
                    );
                    ?>

                </td>



                <!-- SELLING PRICE -->

                <td>

                    ₹<?php
                    echo number_format(
                        (float)$row['SellingPrice'],
                        2
                    );
                    ?>

                </td>



                <!-- EXPIRY DATE -->

                <td>

                    <?php
                    echo e(
                        $row['ExpiryDate']
                    );
                    ?>

                </td>



                <!-- DESCRIPTION -->

                <td class="description">

                    <?php
                    echo e(
                        $row['Description']
                    );
                    ?>

                </td>



                <!-- REQUEST DATE -->

                <td>

                    <?php
                    echo e(
                        $row['RequestDate']
                    );
                    ?>

                </td>



                <!-- STATUS -->

                <td>


                    <?php

                    if (
                        $row['Status'] ===
                        'Pending'
                    ) {

                    ?>

                        <span class="pending">
                            Pending
                        </span>

                    <?php

                    }

                    elseif (
                        $row['Status'] ===
                        'Approved'
                    ) {

                    ?>

                        <span class="approved">
                            Approved
                        </span>

                    <?php

                    }

                    else {

                    ?>

                        <span class="rejected">
                            Rejected
                        </span>

                    <?php

                    }

                    ?>


                </td>



                <!-- ACTION -->

                <td>


                    <?php

                    if (
                        $row['Status'] ===
                        'Pending'
                    ) {

                    ?>


                        <!-- APPROVE -->

                        <form
                            method="POST"
                            action="manage_medicine_requests.php"
                            class="action-form"
                            onsubmit="
                                return confirm(
                                    'Are you sure you want to approve this medicine request?'
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
                                name="request_id"
                                value="<?php
                                    echo e(
                                        $row['RequestID']
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >


                            <button
                                type="submit"
                                class="approve"
                            >

                                Approve

                            </button>

                        </form>



                        <!-- REJECT -->

                        <form
                            method="POST"
                            action="manage_medicine_requests.php"
                            class="action-form"
                            onsubmit="
                                return confirm(
                                    'Are you sure you want to reject this medicine request?'
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
                                name="request_id"
                                value="<?php
                                    echo e(
                                        $row['RequestID']
                                    );
                                ?>"
                            >


                            <input
                                type="hidden"
                                name="action"
                                value="reject"
                            >


                            <button
                                type="submit"
                                class="reject"
                            >

                                Reject

                            </button>

                        </form>


                    <?php

                    }

                    else {

                        echo "-";

                    }

                    ?>


                </td>


            </tr>


            <?php

                }

            }

            else {

            ?>


                <tr>

                    <td
                        colspan="13"
                        class="no-data"
                    >

                        No Medicine Requests Found

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