<?php

session_start();
include("db_connect.php");


/* -------------------------------------------------
   Admin Login Check
------------------------------------------------- */

if (!isset($_SESSION['admin'])) {

    header("Location: login.php");
    exit();

}


/* -------------------------------------------------
   CSRF Token
------------------------------------------------- */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}

$csrf_token = $_SESSION['csrf_token'];


/* -------------------------------------------------
   Helper Function
------------------------------------------------- */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$errorMessage = "";


/* -------------------------------------------------
   Check Pharmacist ID
------------------------------------------------- */

if (
    !isset($_GET['id']) ||
    !ctype_digit($_GET['id']) ||
    (int)$_GET['id'] <= 0
) {

    die("Invalid Pharmacist ID.");

}

$pharmacistID = (int)$_GET['id'];


/* -------------------------------------------------
   Get Pharmacist Details
------------------------------------------------- */

$stmt = $conn->prepare(
    "SELECT
        PharmacistID,
        Name,
        Phone,
        Email,
        Username
     FROM pharmacist
     WHERE PharmacistID = ?
     LIMIT 1"
);

if (!$stmt) {

    die("Unable to process the request.");

}

$stmt->bind_param(
    "i",
    $pharmacistID
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    die("Pharmacist not found.");

}

$pharmacist = $result->fetch_assoc();

$stmt->close();


/* -------------------------------------------------
   Delete Pharmacist
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_pharmacist'])
) {


    /* ---------------------------------------------
       CSRF Validation
    --------------------------------------------- */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $errorMessage =
            "Invalid request. Please refresh the page and try again.";

    }


    /* ---------------------------------------------
       Verify Submitted Pharmacist ID
    --------------------------------------------- */

    elseif (
        !isset($_POST['PharmacistID']) ||
        !ctype_digit($_POST['PharmacistID']) ||
        (int)$_POST['PharmacistID'] !== $pharmacistID
    ) {

        $errorMessage =
            "Invalid Pharmacist ID.";

    }


    /* ---------------------------------------------
       Check Sales References
    --------------------------------------------- */

    else {

        $salesCheck = $conn->prepare(
            "SELECT SaleID
             FROM sale
             WHERE PharmacistID = ?
             LIMIT 1"
        );

        if (!$salesCheck) {

            $errorMessage =
                "Unable to verify pharmacist records.";

        } else {

            $salesCheck->bind_param(
                "i",
                $pharmacistID
            );

            $salesCheck->execute();

            $salesResult =
                $salesCheck->get_result();

            if ($salesResult->num_rows > 0) {

                $errorMessage =
                    "This pharmacist cannot be deleted because sales records are associated with this pharmacist.";

            }

            $salesCheck->close();
        }
    }


    /* ---------------------------------------------
       Check Purchase References
    --------------------------------------------- */

    if ($errorMessage === "") {

        $purchaseCheck = $conn->prepare(
            "SELECT Purchase_Id
             FROM purchase
             WHERE PharmacistID = ?
             LIMIT 1"
        );

        if (!$purchaseCheck) {

            $errorMessage =
                "Unable to verify purchase records.";

        } else {

            $purchaseCheck->bind_param(
                "i",
                $pharmacistID
            );

            $purchaseCheck->execute();

            $purchaseResult =
                $purchaseCheck->get_result();

            if ($purchaseResult->num_rows > 0) {

                $errorMessage =
                    "This pharmacist cannot be deleted because purchase records are associated with this pharmacist.";

            }

            $purchaseCheck->close();
        }
    }


    /* ---------------------------------------------
       Delete Pharmacist
    --------------------------------------------- */

    if ($errorMessage === "") {

        $delete = $conn->prepare(
            "DELETE FROM pharmacist
             WHERE PharmacistID = ?"
        );

        if (!$delete) {

            $errorMessage =
                "Unable to process the delete request.";

        } else {

            $delete->bind_param(
                "i",
                $pharmacistID
            );

            if ($delete->execute()) {

                if ($delete->affected_rows === 1) {

                    $delete->close();

                    header(
                        "Location: view_pharmacist.php?deleted=1"
                    );

                    exit();

                } else {

                    $errorMessage =
                        "Pharmacist could not be deleted.";

                }

            } else {

                $errorMessage =
                    "Unable to delete pharmacist. Please try again.";
            }

            $delete->close();
        }
    }
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

    <title>Delete Pharmacist</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 500px;
            max-width: 90%;
            margin: 80px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            box-sizing: border-box;
            text-align: center;
        }

        h2 {
            color: #c00;
            margin-bottom: 20px;
        }

        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .details {
            text-align: left;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .details p {
            margin: 8px 0;
        }

        .details strong {
            display: inline-block;
            width: 90px;
        }

        .delete-button {
            width: 100%;
            padding: 12px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .delete-button:hover {
            background-color: #c82333;
        }

        .delete-button:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #555;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back:hover {
            background-color: #333;
        }

        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Delete Pharmacist</h2>


    <?php if ($errorMessage !== ""): ?>

        <div class="error">

            <?php echo e($errorMessage); ?>

        </div>

    <?php endif; ?>


    <div class="warning">

        <strong>Warning:</strong>

        Are you sure you want to delete this pharmacist?

        This action cannot be undone.

    </div>


    <div class="details">

        <p>
            <strong>ID:</strong>
            <?php echo e($pharmacist['PharmacistID']); ?>
        </p>

        <p>
            <strong>Name:</strong>
            <?php echo e($pharmacist['Name']); ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?php echo e($pharmacist['Phone']); ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?php echo e($pharmacist['Email']); ?>
        </p>

        <p>
            <strong>Username:</strong>
            <?php echo e($pharmacist['Username']); ?>
        </p>

    </div>


    <form
        method="POST"
        onsubmit="return confirmDelete();"
    >

        <input
            type="hidden"
            name="PharmacistID"
            value="<?php echo e($pharmacistID); ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrf_token); ?>"
        >


        <button
            type="submit"
            name="delete_pharmacist"
            class="delete-button"
            id="deleteButton"
        >
            Yes, Delete Pharmacist
        </button>

    </form>


    <a
        href="view_pharmacist.php"
        class="back"
    >
        Cancel
    </a>

</div>


<script>

function confirmDelete() {

    const confirmed = confirm(
        "Are you sure you want to permanently delete this pharmacist?"
    );

    if (!confirmed) {
        return false;
    }

    const button =
        document.getElementById("deleteButton");

    button.disabled = true;

    button.innerText =
        "Deleting Pharmacist...";

    return true;
}

</script>

</body>

</html>