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
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$message = "";
$messageType = "";


/* -------------------------------------------------
   Helper Function
------------------------------------------------- */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


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
   Get Current Pharmacist Details
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

$stmt->bind_param("i", $pharmacistID);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    die("Pharmacist not found.");
}

$pharmacist = $result->fetch_assoc();

$stmt->close();


/* -------------------------------------------------
   Default Form Values
------------------------------------------------- */

$name = $pharmacist['Name'];
$phone = $pharmacist['Phone'];
$email = $pharmacist['Email'];
$username = $pharmacist['Username'];


/* -------------------------------------------------
   Update Pharmacist
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_pharmacist'])
) {

    /* ---------------------------------------------
       CSRF Validation
    --------------------------------------------- */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        $message = "Invalid request. Please try again.";
        $messageType = "error";

    } else {

        /* -----------------------------------------
           Get Form Values
        ----------------------------------------- */

        $name = trim($_POST['Name'] ?? "");
        $phone = trim($_POST['Phone'] ?? "");
        $email = trim($_POST['Email'] ?? "");
        $username = trim($_POST['Username'] ?? "");
        $password = $_POST['Password'] ?? "";


        /* -----------------------------------------
           Empty Field Validation
           Password is optional during editing
        ----------------------------------------- */

        if (
            $name === "" ||
            $phone === "" ||
            $email === "" ||
            $username === ""
        ) {

            $message = "Please fill in all required fields.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Name Validation
        ----------------------------------------- */

        elseif (strlen($name) < 2 || strlen($name) > 25) {

            $message =
                "Name must contain between 2 and 25 characters.";
            $messageType = "error";

        }

        elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $name)) {

            $message =
                "Name can contain only letters, spaces, apostrophes, periods and hyphens.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Phone Validation
        ----------------------------------------- */

        elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

            $message =
                "Phone number must contain exactly 10 digits.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Email Validation
        ----------------------------------------- */

        elseif (strlen($email) > 25) {

            $message =
                "Email address must not exceed 25 characters.";
            $messageType = "error";

        }

        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $message =
                "Please enter a valid email address.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Username Validation
        ----------------------------------------- */

        elseif (strlen($username) < 3 || strlen($username) > 25) {

            $message =
                "Username must contain between 3 and 25 characters.";
            $messageType = "error";

        }

        elseif (!preg_match("/^[A-Za-z0-9_]+$/", $username)) {

            $message =
                "Username can contain only letters, numbers and underscore.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Password Validation
           Only validate if a new password is entered
        ----------------------------------------- */

        elseif ($password !== "" && strlen($password) < 6) {

            $message =
                "New password must contain at least 6 characters.";
            $messageType = "error";

        }

        elseif ($password !== "" && strlen($password) > 15) {

            $message =
                "New password must not exceed 15 characters.";
            $messageType = "error";

        }

        elseif ($password !== "" && preg_match('/\s/', $password)) {

            $message =
                "Password must not contain spaces.";
            $messageType = "error";
        }


        /* -----------------------------------------
           Database Validation
        ----------------------------------------- */

        else {

            /* -------------------------------------
               Check Duplicate Username
            ------------------------------------- */

            $checkUsername = $conn->prepare(
                "SELECT PharmacistID
                 FROM pharmacist
                 WHERE Username = ?
                 AND PharmacistID != ?
                 LIMIT 1"
            );

            $checkUsername->bind_param(
                "si",
                $username,
                $pharmacistID
            );

            $checkUsername->execute();

            $usernameResult = $checkUsername->get_result();

            if ($usernameResult->num_rows > 0) {

                $message = "Username already exists.";
                $messageType = "error";
            }

            $checkUsername->close();


            /* -------------------------------------
               Check Duplicate Email
            ------------------------------------- */

            if ($message === "") {

                $checkEmail = $conn->prepare(
                    "SELECT PharmacistID
                     FROM pharmacist
                     WHERE Email = ?
                     AND PharmacistID != ?
                     LIMIT 1"
                );

                $checkEmail->bind_param(
                    "si",
                    $email,
                    $pharmacistID
                );

                $checkEmail->execute();

                $emailResult = $checkEmail->get_result();

                if ($emailResult->num_rows > 0) {

                    $message = "Email address already exists.";
                    $messageType = "error";
                }

                $checkEmail->close();
            }


            /* -------------------------------------
               Update Pharmacist
            ------------------------------------- */

            if ($message === "") {

                if ($password !== "") {

                    /* Update including new password */

                    $update = $conn->prepare(
                        "UPDATE pharmacist
                         SET
                            Name = ?,
                            Phone = ?,
                            Email = ?,
                            Username = ?,
                            Password = ?
                         WHERE PharmacistID = ?"
                    );

                    if ($update) {

                        $update->bind_param(
                            "sssssi",
                            $name,
                            $phone,
                            $email,
                            $username,
                            $password,
                            $pharmacistID
                        );

                    }

                } else {

                    /* Keep existing password */

                    $update = $conn->prepare(
                        "UPDATE pharmacist
                         SET
                            Name = ?,
                            Phone = ?,
                            Email = ?,
                            Username = ?
                         WHERE PharmacistID = ?"
                    );

                    if ($update) {

                        $update->bind_param(
                            "ssssi",
                            $name,
                            $phone,
                            $email,
                            $username,
                            $pharmacistID
                        );
                    }
                }


                /* ---------------------------------
                   Execute Update
                --------------------------------- */

                if ($update) {

                    if ($update->execute()) {

                        $update->close();

                        header(
                            "Location: view_pharmacist.php?updated=1"
                        );

                        exit();

                    } else {

                        $message =
                            "Unable to update pharmacist. Please try again.";

                        $messageType = "error";

                        $update->close();
                    }

                } else {

                    $message =
                        "Unable to process the request.";

                    $messageType = "error";
                }
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Pharmacist</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 500px;
            max-width: 90%;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
            box-sizing: border-box;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            box-sizing: border-box;
            border: 1px solid #bbb;
            border-radius: 4px;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #007bff;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 25px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.85;
        }

        button:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
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

    </style>

</head>

<body>

<div class="container">

    <h2>Edit Pharmacist</h2>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo e($messageType); ?>">

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <form method="POST"
          id="pharmacistForm"
          onsubmit="return validateForm();">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrf_token); ?>"
        >


        <!-- Name -->

        <label for="Name">Name</label>

        <input
            type="text"
            id="Name"
            name="Name"
            maxlength="25"
            value="<?php echo e($name); ?>"
            pattern="[A-Za-z][A-Za-z .'-]*"
            autocomplete="name"
            required
        >

        <div class="hint">
            2–25 characters. Letters, spaces, apostrophes, periods and hyphens only.
        </div>


        <!-- Phone -->

        <label for="Phone">Phone</label>

        <input
            type="tel"
            id="Phone"
            name="Phone"
            maxlength="10"
            inputmode="numeric"
            value="<?php echo e($phone); ?>"
            pattern="[0-9]{10}"
            autocomplete="tel"
            required
        >

        <div class="hint">
            Enter exactly 10 digits.
        </div>


        <!-- Email -->

        <label for="Email">Email</label>

        <input
            type="email"
            id="Email"
            name="Email"
            maxlength="25"
            value="<?php echo e($email); ?>"
            autocomplete="email"
            required
        >

        <div class="hint">
            Enter a valid email address.
        </div>


        <!-- Username -->

        <label for="Username">Username</label>

        <input
            type="text"
            id="Username"
            name="Username"
            maxlength="25"
            value="<?php echo e($username); ?>"
            pattern="[A-Za-z0-9_]+"
            autocomplete="username"
            required
        >

        <div class="hint">
            3–25 characters. Letters, numbers and underscore only.
        </div>


        <!-- Password -->

        <label for="Password">
            New Password
        </label>

        <input
            type="password"
            id="Password"
            name="Password"
            maxlength="15"
            autocomplete="new-password"
        >

        <div class="hint">
            Leave blank to keep the current password.
            If changing it, use 6–15 characters without spaces.
        </div>


        <button
            type="submit"
            name="update_pharmacist"
            id="submitButton"
        >
            Update Pharmacist
        </button>

    </form>


    <a
        href="view_pharmacist.php"
        class="back"
    >
        Back to Pharmacists
    </a>

</div>


<script>

function validateForm() {

    const name =
        document.getElementById("Name").value.trim();

    const phone =
        document.getElementById("Phone").value.trim();

    const email =
        document.getElementById("Email").value.trim();

    const username =
        document.getElementById("Username").value.trim();

    const password =
        document.getElementById("Password").value;


    /* ---------------------------------------------
       Name Validation
    --------------------------------------------- */

    const namePattern =
        /^[A-Za-z][A-Za-z .'-]*$/;

    if (name.length < 2 || name.length > 25) {

        alert(
            "Name must contain between 2 and 25 characters."
        );

        return false;
    }

    if (!namePattern.test(name)) {

        alert(
            "Name can contain only letters, spaces, apostrophes, periods and hyphens."
        );

        return false;
    }


    /* ---------------------------------------------
       Phone Validation
    --------------------------------------------- */

    if (!/^[0-9]{10}$/.test(phone)) {

        alert(
            "Phone number must contain exactly 10 digits."
        );

        return false;
    }


    /* ---------------------------------------------
       Email Validation
    --------------------------------------------- */

    if (email.length > 25) {

        alert(
            "Email address must not exceed 25 characters."
        );

        return false;
    }

    const emailPattern =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(email)) {

        alert(
            "Please enter a valid email address."
        );

        return false;
    }


    /* ---------------------------------------------
       Username Validation
    --------------------------------------------- */

    if (username.length < 3 || username.length > 25) {

        alert(
            "Username must contain between 3 and 25 characters."
        );

        return false;
    }

    if (!/^[A-Za-z0-9_]+$/.test(username)) {

        alert(
            "Username can contain only letters, numbers and underscore."
        );

        return false;
    }


    /* ---------------------------------------------
       New Password Validation
       Only if user entered one
    --------------------------------------------- */

    if (password !== "") {

        if (password.length < 6 || password.length > 15) {

            alert(
                "New password must contain between 6 and 15 characters."
            );

            return false;
        }

        if (/\s/.test(password)) {

            alert(
                "Password must not contain spaces."
            );

            return false;
        }
    }


    /* ---------------------------------------------
       Prevent Double Submission
    --------------------------------------------- */

    document.getElementById("submitButton").disabled = true;

    document.getElementById("submitButton").innerText =
        "Updating Pharmacist...";

    return true;
}

</script>

</body>

</html>