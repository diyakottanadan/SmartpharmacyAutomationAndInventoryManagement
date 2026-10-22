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
   VARIABLES
   ========================================= */

$errors = [];

$successMessage = "";

$supplierName = "";
$contactPerson = "";
$contactNo = "";
$email = "";
$address = "";


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
   FORM SUBMISSION
   ========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* =====================================
       CSRF VALIDATION
       ===================================== */

    $submittedToken =
        $_POST['csrf_token'] ?? "";

    if (
        empty($submittedToken) ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $errors[] =
            "Invalid form submission. Please try again.";

    }


    /* =====================================
       GET FORM VALUES
       ===================================== */

    $supplierName =
        trim($_POST['SupplierName'] ?? "");

    $contactPerson =
        trim($_POST['ContactPerson'] ?? "");

    $contactNo =
        trim($_POST['ContactNo'] ?? "");

    $email =
        trim($_POST['Email'] ?? "");

    $address =
        trim($_POST['Address'] ?? "");


    /* =====================================
       SUPPLIER NAME VALIDATION
       ===================================== */

    if ($supplierName === "") {

        $errors[] =
            "Supplier name is required.";

    }
    elseif (
        strlen($supplierName) < 2 ||
        strlen($supplierName) > 25
    ) {

        $errors[] =
            "Supplier name must be between 2 and 25 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z][A-Za-z .&'-]*$/",
            $supplierName
        )
    ) {

        $errors[] =
            "Supplier name can contain only letters, spaces, dots, ampersands, apostrophes and hyphens.";

    }


    /* =====================================
       CONTACT PERSON VALIDATION
       ===================================== */

    if ($contactPerson === "") {

        $errors[] =
            "Contact person is required.";

    }
    elseif (
        strlen($contactPerson) < 2 ||
        strlen($contactPerson) > 25
    ) {

        $errors[] =
            "Contact person must be between 2 and 25 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z][A-Za-z .'-]*$/",
            $contactPerson
        )
    ) {

        $errors[] =
            "Contact person can contain only letters, spaces, dots, apostrophes and hyphens.";

    }


    /* =====================================
       CONTACT NUMBER VALIDATION
       ===================================== */

    if ($contactNo === "") {

        $errors[] =
            "Contact number is required.";

    }
    elseif (
        !preg_match(
            "/^[6-9][0-9]{9}$/",
            $contactNo
        )
    ) {

        $errors[] =
            "Contact number must be a valid 10-digit Indian mobile number.";

    }


    /* =====================================
       EMAIL VALIDATION
       ===================================== */

    if ($email === "") {

        $errors[] =
            "Email address is required.";

    }
    elseif (
        strlen($email) > 25
    ) {

        $errors[] =
            "Email address must not exceed 25 characters.";

    }
    elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            "Please enter a valid email address.";

    }


    /* =====================================
       ADDRESS VALIDATION
       ===================================== */

    if ($address === "") {

        $errors[] =
            "Address is required.";

    }
    elseif (
        strlen($address) < 3 ||
        strlen($address) > 255
    ) {

        $errors[] =
            "Address must be between 3 and 255 characters.";

    }
    elseif (
        !preg_match(
            "/^[A-Za-z0-9][A-Za-z0-9 .,#\/'()&-]*$/",
            $address
        )
    ) {

        $errors[] =
            "Address contains invalid characters.";

    }


    /* =====================================
       DATABASE VALIDATION
       ===================================== */

    if (empty($errors)) {


        /* =================================
           CHECK DUPLICATE SUPPLIER
           ================================= */

        $checkSql = "
            SELECT SupplierID
            FROM supplier
            WHERE LOWER(SupplierName) = LOWER(?)
            LIMIT 1
        ";


        $checkStmt =
            mysqli_prepare(
                $conn,
                $checkSql
            );


        if ($checkStmt) {

            mysqli_stmt_bind_param(
                $checkStmt,
                "s",
                $supplierName
            );

            mysqli_stmt_execute(
                $checkStmt
            );

            mysqli_stmt_store_result(
                $checkStmt
            );


            if (
                mysqli_stmt_num_rows(
                    $checkStmt
                ) > 0
            ) {

                $errors[] =
                    "A supplier with this name already exists.";

            }


            mysqli_stmt_close(
                $checkStmt
            );

        }
        else {

            $errors[] =
                "Unable to validate supplier information.";

        }

    }


    /* =====================================
       INSERT SUPPLIER
       ===================================== */

    if (empty($errors)) {


        $sql = "
            INSERT INTO supplier
            (
                SupplierName,
                ContactPerson,
                ContactNo,
                Email,
                Address
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";


        $stmt =
            mysqli_prepare(
                $conn,
                $sql
            );


        if ($stmt) {


            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $supplierName,
                $contactPerson,
                $contactNo,
                $email,
                $address
            );


            if (
                mysqli_stmt_execute(
                    $stmt
                )
            ) {

                $successMessage =
                    "Supplier added successfully.";

                /* Clear form after successful insertion */

                $supplierName = "";
                $contactPerson = "";
                $contactNo = "";
                $email = "";
                $address = "";

            }
            else {

                $errors[] =
                    "Unable to add supplier. Please try again.";

            }


            mysqli_stmt_close(
                $stmt
            );

        }
        else {

            $errors[] =
                "Unable to process the request. Please try again.";

        }

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <title>Add Supplier</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                sans-serif;

            background-color:
                #f4f4f4;

            margin:
                0;

        }


        .container {

            width:
                500px;

            max-width:
                95%;

            margin:
                40px auto;

            background-color:
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

            border:
                1px solid #bbb;

            border-radius:
                4px;

            font-size:
                14px;

        }


        input:focus,
        textarea:focus {

            outline:
                none;

            border-color:
                #2980b9;

        }


        textarea {

            height:
                90px;

            resize:
                vertical;

        }


        button {

            width:
                100%;

            padding:
                12px;

            margin-top:
                25px;

            background-color:
                green;

            color:
                white;

            border:
                none;

            border-radius:
                5px;

            font-size:
                16px;

            cursor:
                pointer;

        }


        button:hover {

            background-color:
                #006400;

        }


        .error-box {

            background-color:
                #f8d7da;

            color:
                #721c24;

            border:
                1px solid #f5c6cb;

            padding:
                12px;

            border-radius:
                5px;

            margin-bottom:
                15px;

        }


        .error-box ul {

            margin:
                0;

            padding-left:
                20px;

        }


        .success-box {

            background-color:
                #d4edda;

            color:
                #155724;

            border:
                1px solid #c3e6cb;

            padding:
                12px;

            border-radius:
                5px;

            margin-bottom:
                15px;

            text-align:
                center;

            font-weight:
                bold;

        }


        .back {

            display:
                inline-block;

            margin-top:
                15px;

            padding:
                10px 15px;

            background-color:
                #555;

            color:
                white;

            text-decoration:
                none;

            border-radius:
                5px;

        }


        .back:hover {

            background-color:
                #333;

        }


        .required {

            color:
                red;

        }

    </style>

</head>


<body>


<div class="container">


    <h2>
        Add Supplier
    </h2>


    <!-- =====================================
         ERROR MESSAGES
         ===================================== -->

    <?php if (!empty($errors)) { ?>

        <div class="error-box">

            <ul>

                <?php foreach ($errors as $error) { ?>

                    <li>
                        <?php echo e($error); ?>
                    </li>

                <?php } ?>

            </ul>

        </div>

    <?php } ?>


    <!-- =====================================
         SUCCESS MESSAGE
         ===================================== -->

    <?php if ($successMessage !== "") { ?>

        <div class="success-box">

            <?php
            echo e($successMessage);
            ?>

        </div>

    <?php } ?>


    <form
        method="POST"
        id="supplierForm"
        novalidate
    >


        <!-- CSRF -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrfToken); ?>"
        >


        <!-- SUPPLIER NAME -->

        <label for="SupplierName">

            Supplier Name
            <span class="required">*</span>

        </label>


        <input
            type="text"
            id="SupplierName"
            name="SupplierName"
            maxlength="25"
            minlength="2"
            value="<?php echo e($supplierName); ?>"
            pattern="[A-Za-z][A-Za-z .&'-]*"
            required
        >



        <!-- CONTACT PERSON -->

        <label for="ContactPerson">

            Contact Person
            <span class="required">*</span>

        </label>


        <input
            type="text"
            id="ContactPerson"
            name="ContactPerson"
            maxlength="25"
            minlength="2"
            value="<?php echo e($contactPerson); ?>"
            pattern="[A-Za-z][A-Za-z .'-]*"
            required
        >



        <!-- CONTACT NUMBER -->

        <label for="ContactNo">

            Contact Number
            <span class="required">*</span>

        </label>


        <input
            type="tel"
            id="ContactNo"
            name="ContactNo"
            maxlength="10"
            minlength="10"
            value="<?php echo e($contactNo); ?>"
            pattern="[6-9][0-9]{9}"
            inputmode="numeric"
            required
        >



        <!-- EMAIL -->

        <label for="Email">

            Email
            <span class="required">*</span>

        </label>


        <input
            type="email"
            id="Email"
            name="Email"
            maxlength="25"
            value="<?php echo e($email); ?>"
            required
        >



        <!-- ADDRESS -->

        <label for="Address">

            Address
            <span class="required">*</span>

        </label>


        <textarea
            id="Address"
            name="Address"
            maxlength="255"
            minlength="3"
            required
        ><?php echo e($address); ?></textarea>



        <!-- SUBMIT -->

        <button
            type="submit"
            name="add_supplier"
            id="submitButton"
        >

            Add Supplier

        </button>


    </form>



    <a
        href="admin_dashboard.php"
        class="back"
    >

        ← Back to Dashboard

    </a>


</div>



<script>

document
    .getElementById("supplierForm")
    .addEventListener(
        "submit",
        function(event) {


            let supplierName =
                document
                .getElementById("SupplierName")
                .value
                .trim();


            let contactPerson =
                document
                .getElementById("ContactPerson")
                .value
                .trim();


            let contactNo =
                document
                .getElementById("ContactNo")
                .value
                .trim();


            let email =
                document
                .getElementById("Email")
                .value
                .trim();


            let address =
                document
                .getElementById("Address")
                .value
                .trim();


            let errors = [];


            /* Supplier Name */

            if (
                supplierName.length < 2 ||
                supplierName.length > 25
            ) {

                errors.push(
                    "Supplier name must be between 2 and 25 characters."
                );

            }
            else if (
                !/^[A-Za-z][A-Za-z .&'-]*$/
                    .test(supplierName)
            ) {

                errors.push(
                    "Supplier name contains invalid characters."
                );

            }


            /* Contact Person */

            if (
                contactPerson.length < 2 ||
                contactPerson.length > 25
            ) {

                errors.push(
                    "Contact person must be between 2 and 25 characters."
                );

            }
            else if (
                !/^[A-Za-z][A-Za-z .'-]*$/
                    .test(contactPerson)
            ) {

                errors.push(
                    "Contact person contains invalid characters."
                );

            }


            /* Contact Number */

            if (
                !/^[6-9][0-9]{9}$/
                    .test(contactNo)
            ) {

                errors.push(
                    "Contact number must be a valid 10-digit Indian mobile number."
                );

            }


            /* Email */

            if (
                email.length > 25 ||
                !/^[^\s@]+@[^\s@]+\.[^\s@]+$/
                    .test(email)
            ) {

                errors.push(
                    "Please enter a valid email address."
                );

            }


            /* Address */

            if (
                address.length < 3 ||
                address.length > 255
            ) {

                errors.push(
                    "Address must be between 3 and 255 characters."
                );

            }
            else if (
                !/^[A-Za-z0-9][A-Za-z0-9 .,#\/'()&-]*$/
                    .test(address)
            ) {

                errors.push(
                    "Address contains invalid characters."
                );

            }


            /* Stop submission if errors exist */

            if (errors.length > 0) {

                event.preventDefault();

                alert(
                    errors.join("\n")
                );

            }

        }
    );

</script>


</body>

</html>