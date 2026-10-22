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
   CHECK REQUEST METHOD
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: manage_medicine_requests.php");
    exit();

}


/* =========================================
   CHECK CSRF TOKEN
   ========================================= */

$submittedToken =
    $_POST['csrf_token'] ?? '';

if (
    empty($submittedToken) ||
    !hash_equals(
        $csrfToken,
        $submittedToken
    )
) {

    die("Invalid request. Please try again.");

}


/* =========================================
   VALIDATE REQUEST ID
   ========================================= */

$requestID = $_POST['request_id'] ?? '';

if (
    !is_string($requestID) ||
    !ctype_digit($requestID) ||
    (int)$requestID <= 0
) {

    die("Invalid medicine request ID.");

}

$requestID = (int)$requestID;


/* =========================================
   GET PENDING REQUEST
   ========================================= */

$requestSQL = "

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
        Status

    FROM medicine_request

    WHERE RequestID = ?

    AND Status = 'Pending'

    LIMIT 1

";


$requestStmt =
    mysqli_prepare(
        $conn,
        $requestSQL
    );


if (!$requestStmt) {

    die("Unable to process the request.");

}


mysqli_stmt_bind_param(
    $requestStmt,
    "i",
    $requestID
);


if (
    !mysqli_stmt_execute(
        $requestStmt
    )
) {

    mysqli_stmt_close($requestStmt);

    die("Unable to process the request.");

}


$requestResult =
    mysqli_stmt_get_result(
        $requestStmt
    );


if (
    !$requestResult ||
    mysqli_num_rows($requestResult) === 0
) {

    mysqli_stmt_close($requestStmt);

    die(
        "This request does not exist or has already been processed."
    );

}


$request =
    mysqli_fetch_assoc(
        $requestResult
    );


mysqli_stmt_close($requestStmt);


/* =========================================
   GET REQUEST DETAILS
   ========================================= */

$medicineName =
    trim($request['MedicineName']);

$category =
    trim($request['Category']);

$supplierID =
    (int)$request['SupplierID'];

$batchNo =
    trim($request['BatchNo']);

$purchasePrice =
    (float)$request['PurchasePrice'];

$sellingPrice =
    (float)$request['SellingPrice'];

$expiryDate =
    $request['ExpiryDate'];

$description =
    trim($request['Description']);


/* =========================================
   BASIC VALIDATION
   ========================================= */

if (
    $medicineName === '' ||
    strlen($medicineName) < 2 ||
    strlen($medicineName) > 25 ||
    !preg_match(
        "/^[A-Za-z0-9][A-Za-z0-9 .()&+'\/-]*$/",
        $medicineName
    )
) {

    die("Invalid medicine name.");

}


if (
    $category === '' ||
    strlen($category) < 2 ||
    strlen($category) > 25 ||
    !preg_match(
        "/^[A-Za-z][A-Za-z &\/-]*$/",
        $category
    )
) {

    die("Invalid medicine category.");

}


if ($supplierID <= 0) {

    die("Invalid supplier.");

}


if (
    $batchNo === '' ||
    strlen($batchNo) < 2 ||
    strlen($batchNo) > 25 ||
    !preg_match(
        "/^[A-Za-z0-9][A-Za-z0-9-]*$/",
        $batchNo
    )
) {

    die("Invalid batch number.");

}


if (
    $purchasePrice <= 0 ||
    $purchasePrice > 99999999.99
) {

    die("Invalid purchase price.");

}


if (
    $sellingPrice <= 0 ||
    $sellingPrice > 99999999.99
) {

    die("Invalid selling price.");

}


if ($sellingPrice < $purchasePrice) {

    die(
        "Selling price cannot be less than purchase price."
    );

}


/* =========================================
   VALIDATE EXPIRY DATE
   ========================================= */

$expiryObject =
    DateTime::createFromFormat(
        'Y-m-d',
        $expiryDate
    );


if (
    !$expiryObject ||
    $expiryObject->format('Y-m-d') !== $expiryDate
) {

    die("Invalid expiry date.");

}


if ($expiryDate <= date('Y-m-d')) {

    die(
        "Medicine expiry date must be a future date."
    );

}


/* =========================================
   VALIDATE DESCRIPTION
   ========================================= */

if (
    $description === '' ||
    strlen($description) < 5 ||
    strlen($description) > 500
) {

    die(
        "Description must contain 5 to 500 characters."
    );

}


/* =========================================
   START TRANSACTION
   ========================================= */

mysqli_begin_transaction($conn);


try {


    /* =========================================
       CHECK SUPPLIER EXISTS
       ========================================= */

    $supplierSQL = "

        SELECT SupplierID

        FROM supplier

        WHERE SupplierID = ?

        LIMIT 1

    ";


    $supplierStmt =
        mysqli_prepare(
            $conn,
            $supplierSQL
        );


    if (!$supplierStmt) {

        throw new Exception(
            "Unable to verify supplier."
        );

    }


    mysqli_stmt_bind_param(
        $supplierStmt,
        "i",
        $supplierID
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
        mysqli_num_rows($supplierResult) === 0
    ) {

        mysqli_stmt_close($supplierStmt);

        throw new Exception(
            "Selected supplier does not exist."
        );

    }


    mysqli_stmt_close($supplierStmt);


    /* =========================================
       CHECK DUPLICATE MEDICINE + BATCH
       ========================================= */

    $duplicateSQL = "

        SELECT MedicineID

        FROM medicine

        WHERE LOWER(MedicineName) = LOWER(?)

        AND BatchNo = ?

        LIMIT 1

    ";


    $duplicateStmt =
        mysqli_prepare(
            $conn,
            $duplicateSQL
        );


    if (!$duplicateStmt) {

        throw new Exception(
            "Unable to check duplicate medicine."
        );

    }


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
        mysqli_num_rows($duplicateResult) > 0
    ) {

        mysqli_stmt_close($duplicateStmt);

        throw new Exception(
            "A medicine with the same name and batch number already exists."
        );

    }


    mysqli_stmt_close($duplicateStmt);


    /* =========================================
       INSERT MEDICINE
       ========================================= */

    $stockQty = 0;

    $status = 'Unavailable';


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
            "Unable to add medicine."
        );

    }


    mysqli_stmt_bind_param(
        $insertStmt,
        "ssisddisss",
        $medicineName,
        $category,
        $supplierID,
        $batchNo,
        $purchasePrice,
        $sellingPrice,
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

        mysqli_stmt_close($insertStmt);

        throw new Exception(
            "Unable to add medicine."
        );

    }


    mysqli_stmt_close($insertStmt);


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

        mysqli_stmt_close($updateStmt);

        throw new Exception(
            "Unable to approve medicine request."
        );

    }


    if (
        mysqli_stmt_affected_rows(
            $updateStmt
        ) !== 1
    ) {

        mysqli_stmt_close($updateStmt);

        throw new Exception(
            "The request has already been processed."
        );

    }


    mysqli_stmt_close($updateStmt);


    /* =========================================
       COMMIT
       ========================================= */

    mysqli_commit($conn);


    header(
        "Location: manage_medicine_requests.php?approved=1"
    );

    exit();


}


/* =========================================
   ROLLBACK IF ERROR
   ========================================= */

catch (Exception $e) {

    mysqli_rollback($conn);

    die(
        e($e->getMessage())
    );

}

?>