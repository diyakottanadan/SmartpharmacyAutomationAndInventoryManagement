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


/* -------------------------------------------------
   Variables
------------------------------------------------- */

$message = "";
$messageType = "";

$name = "";
$phone = "";
$email = "";
$username = "";
$password = "";


/* -------------------------------------------------
   Add Pharmacist
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

        $message = "Invalid form submission. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        /* -----------------------------------------
           Get Form Values
        ----------------------------------------- */

        $name = trim($_POST['Name'] ?? '');
        $phone = trim($_POST['Phone'] ?? '');
        $email = trim($_POST['Email'] ?? '');
        $username = trim($_POST['Username'] ?? '');
        $password = $_POST['Password'] ?? '';


        /* -----------------------------------------
           Required Validation
        ----------------------------------------- */

        if (
            $name === '' ||
            $phone === '' ||
            $email === '' ||
            $username === '' ||
            $password === ''
        ) {

            $message = "Please fill in all fields.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Name Validation
        ----------------------------------------- */

        elseif (strlen($name) < 2 || strlen($name) > 25) {

            $message = "Name must contain between 2 and 25 characters.";
            $messageType = "error";

        }

        elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $name)) {

            $message = "Name can contain only letters, spaces, apostrophes, periods and hyphens.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Phone Validation
        ----------------------------------------- */

        elseif (!preg_match("/^[0-9]{10}$/", $phone)) {

            $message = "Phone number must contain exactly 10 digits.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Email Validation
        ----------------------------------------- */

        elseif (strlen($email) > 25) {

            $message = "Email address must not exceed 25 characters.";
            $messageType = "error";

        }

        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $message = "Please enter a valid email address.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Username Validation
        ----------------------------------------- */

        elseif (strlen($username) < 3 || strlen($username) > 25) {

            $message = "Username must contain between 3 and 25 characters.";
            $messageType = "error";

        }

        elseif (!preg_match("/^[A-Za-z0-9_]+$/", $username)) {

            $message = "Username can contain only letters, numbers and underscore.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Password Validation
        ----------------------------------------- */

        elseif (strlen($password) < 6 || strlen($password) > 15) {

            $message = "Password must contain between 6 and 15 characters.";
            $messageType = "error";

        }

        elseif (preg_match('/\s/', $password)) {

            $message = "Password must not contain spaces.";
            $messageType = "error";

        }


        /* -----------------------------------------
           Database Operations
        ----------------------------------------- */

        else {

            /* -------------------------------------
               Check Username
            ------------------------------------- */

            $stmt = $conn->prepare(
                "SELECT PharmacistID
                 FROM pharmacist
                 WHERE Username = ?
                 LIMIT 1"
            );

            if (!$stmt) {

                $message = "Database Error: " . $conn->error;
                $messageType = "error";

            } else {

                $stmt->bind_param("s", $username);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows > 0) {

                    $message = "Username already exists.";
                    $messageType = "error";

                }

                $stmt->close();
            }


            /* -------------------------------------
               Check Email
            ------------------------------------- */

            if ($message === "") {

                $stmt = $conn->prepare(
                    "SELECT PharmacistID
                     FROM pharmacist
                     WHERE Email = ?
                     LIMIT 1"
                );

                if (!$stmt) {

                    $message = "Database Error: " . $conn->error;
                    $messageType = "error";

                } else {

                    $stmt->bind_param("s", $email);
                    $stmt->execute();

                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {

                        $message = "Email address already exists.";
                        $messageType = "error";

                    }

                    $stmt->close();
                }
            }


            /* -------------------------------------
               Insert Pharmacist
            ------------------------------------- */

            if ($message === "") {

                $stmt = $conn->prepare(
                    "INSERT INTO pharmacist
                    (
                        Name,
                        Phone,
                        Email,
                        Username,
                        Password
                    )
                    VALUES
                    (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {

                    $message =
                        "Database Error: " . $conn->error;

                    $messageType = "error";

                } else {

                    $stmt->bind_param(
                        "sssss",
                        $name,
                        $phone,
                        $email,
                        $username,
                        $password
                    );


                    if ($stmt->execute()) {

                        $message =
                            "Pharmacist added successfully.";

                        $messageType =
                            "success";

                        /* Clear fields after successful insert */

                        $name = "";
                        $phone = "";
                        $email = "";
                        $username = "";
                        $password = "";

                    } else {

                        $message =
                            "Database Error: " . $stmt->error;

                        $messageType = "error";
                    }

                    $stmt->close();
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Pharmacist</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }

        .container {
            width: 500px;
            max-width: 95%;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
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
            background-color: green;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.85;
        }

        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 12px;
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

        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Add Pharmacist</h2>


    <!-- Message -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo e($messageType); ?>">

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>


    <!-- Form -->

    <form method="POST">

        <!-- CSRF -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrf_token); ?>"
        >


        <!-- Name -->

        <label for="Name">
            Name
        </label>

        <input
            type="text"
            id="Name"
            name="Name"
            maxlength="25"
            value="<?php echo e($name); ?>"
            required
            autocomplete="name"
        >

        <div class="hint">
            2–25 characters. Letters, spaces,
            apostrophes, periods and hyphens only.
        </div>


        <!-- Phone -->

        <label for="Phone">
            Phone
        </label>

        <input
            type="text"
            id="Phone"
            name="Phone"
            maxlength="10"
            value="<?php echo e($phone); ?>"
            required
            autocomplete="tel"
        >

        <div class="hint">
            Enter exactly 10 digits.
        </div>


        <!-- Email -->

        <label for="Email">
            Email
        </label>

        <input
            type="email"
            id="Email"
            name="Email"
            maxlength="25"
            value="<?php echo e($email); ?>"
            required
            autocomplete="email"
        >


        <!-- Username -->

        <label for="Username">
            Username
        </label>

        <input
            type="text"
            id="Username"
            name="Username"
            maxlength="25"
            value="<?php echo e($username); ?>"
            required
            autocomplete="username"
        >

        <div class="hint">
            3–25 characters. Letters, numbers and underscore only.
        </div>


        <!-- Password -->

        <label for="Password">
            Password
        </label>

        <input
            type="password"
            id="Password"
            name="Password"
            maxlength="15"
            required
            autocomplete="new-password"
        >

        <div class="hint">
            6–15 characters. Spaces are not allowed.
        </div>


        <!-- Submit -->

        <button
            type="submit"
            name="add_pharmacist"
        >
            Add Pharmacist
        </button>

    </form>


    <a
        href="admin_dashboard.php"
        class="back"
    >
        Back to Dashboard
    </a>

</div>

</body>

</html>