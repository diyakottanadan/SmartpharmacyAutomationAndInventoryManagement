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
   CHECK REQUEST METHOD
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        "Location: manage_medicine_requests.php"
    );

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

$requestID =
    $_POST['request_id'] ?? '';


if (
    !is_string($requestID) ||
    !ctype_digit($requestID) ||
    (int)$requestID <= 0
) {

    die("Invalid medicine request ID.");

}


$requestID =
    (int)$requestID;


/* =========================================
   UPDATE REQUEST STATUS
   ========================================= */

$sql = "

    UPDATE medicine_request

    SET Status = 'Rejected'

    WHERE RequestID = ?

    AND Status = 'Pending'

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    die(
        "Unable to reject medicine request."
    );

}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $requestID
);


if (
    mysqli_stmt_execute($stmt)
) {

    if (
        mysqli_stmt_affected_rows($stmt) === 1
    ) {

        mysqli_stmt_close($stmt);

        header(
            "Location: manage_medicine_requests.php?rejected=1"
        );

        exit();

    }

    else {

        mysqli_stmt_close($stmt);

        die(
            "This request does not exist or has already been processed."
        );

    }

}

else {

    mysqli_stmt_close($stmt);

    die(
        "Error rejecting medicine request."
    );

}

?>