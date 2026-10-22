<?php

session_start();

include("db_connect.php");


/* ==========================================
   SAFE OUTPUT FUNCTION
   ========================================== */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ==========================================
   CSRF TOKEN
   ========================================== */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

}


$error = "";


/* ==========================================
   LOGIN PROCESS
   ========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* ======================================
       CHECK CSRF TOKEN
       ====================================== */

    $csrfToken =
        $_POST['csrf_token'] ?? '';

    if (
        empty($csrfToken) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrfToken
        )
    ) {

        $error =
            "Invalid request. Please try again.";

    } else {


        /* ==================================
           GET FORM VALUES
           ================================== */

        $username =
            trim($_POST['username'] ?? '');

        $password =
            $_POST['password'] ?? '';


        /* ==================================
           BASIC VALIDATION
           ================================== */

        if ($username === '') {

            $error =
                "Please enter your username.";

        } elseif ($password === '') {

            $error =
                "Please enter your password.";

        } elseif (strlen($username) > 25) {

            $error =
                "Invalid username or password.";

        } else {


            /* ==================================
               CHECK ADMIN LOGIN
               ================================== */

            $adminSQL = "
                SELECT Username
                FROM admin
                WHERE Username = ?
                AND Password = ?
                LIMIT 1
            ";


            $adminStmt = mysqli_prepare(
                $conn,
                $adminSQL
            );


            if ($adminStmt) {

                mysqli_stmt_bind_param(
                    $adminStmt,
                    "ss",
                    $username,
                    $password
                );


                mysqli_stmt_execute(
                    $adminStmt
                );


                $adminResult =
                    mysqli_stmt_get_result(
                        $adminStmt
                    );


                if (
                    $adminResult &&
                    mysqli_num_rows($adminResult) > 0
                ) {


                    /* ==========================
                       NEW SESSION ID
                       ========================== */

                    session_regenerate_id(true);


                    /* ==========================
                       CLEAR OLD LOGIN DATA
                       ========================== */

                    unset(
                        $_SESSION['pharmacist'],
                        $_SESSION['PharmacistID'],
                        $_SESSION['PharmacistName']
                    );


                    /* ==========================
                       STORE ADMIN SESSION
                       ========================== */

                    $_SESSION['admin'] =
                        $username;


                    /* ==========================
                       NEW CSRF TOKEN
                       ========================== */

                    $_SESSION['csrf_token'] =
                        bin2hex(
                            random_bytes(32)
                        );


                    mysqli_stmt_close(
                        $adminStmt
                    );


                    header(
                        "Location: admin_dashboard.php"
                    );

                    exit();

                }


                mysqli_stmt_close(
                    $adminStmt
                );

            }


            /* ==================================
               CHECK PHARMACIST LOGIN
               ================================== */

            $pharmacistSQL = "
                SELECT
                    PharmacistID,
                    Name,
                    Username
                FROM pharmacist
                WHERE Username = ?
                AND Password = ?
                LIMIT 1
            ";


            $pharmacistStmt =
                mysqli_prepare(
                    $conn,
                    $pharmacistSQL
                );


            if ($pharmacistStmt) {

                mysqli_stmt_bind_param(
                    $pharmacistStmt,
                    "ss",
                    $username,
                    $password
                );


                mysqli_stmt_execute(
                    $pharmacistStmt
                );


                $pharmacistResult =
                    mysqli_stmt_get_result(
                        $pharmacistStmt
                    );


                if (
                    $pharmacistResult &&
                    mysqli_num_rows(
                        $pharmacistResult
                    ) > 0
                ) {


                    $pharmacist =
                        mysqli_fetch_assoc(
                            $pharmacistResult
                        );


                    /* ==========================
                       NEW SESSION ID
                       ========================== */

                    session_regenerate_id(true);


                    /* ==========================
                       CLEAR OLD LOGIN DATA
                       ========================== */

                    unset(
                        $_SESSION['admin']
                    );


                    /* ==========================
                       STORE PHARMACIST SESSION
                       ========================== */

                    $_SESSION['pharmacist'] =
                        $pharmacist['Username'];


                    $_SESSION['PharmacistID'] =
                        (int)$pharmacist['PharmacistID'];


                    $_SESSION['PharmacistName'] =
                        $pharmacist['Name'];


                    /* ==========================
                       NEW CSRF TOKEN
                       ========================== */

                    $_SESSION['csrf_token'] =
                        bin2hex(
                            random_bytes(32)
                        );


                    mysqli_stmt_close(
                        $pharmacistStmt
                    );


                    header(
                        "Location: pharmacist_dashboard.php"
                    );

                    exit();

                }


                mysqli_stmt_close(
                    $pharmacistStmt
                );

            }


            /* ==================================
               INVALID LOGIN
               ================================== */

            $error =
                "Invalid Username or Password.";

        }

    }

}

?>


<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Smart Pharmacy Login
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #f2f2f2;

        }


        .login-box {

            width: 350px;

            max-width: 90%;

            margin: 100px auto;

            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow: 0 0 10px gray;

        }


        h2 {

            text-align: center;

            margin-top: 0;

        }


        input {

            width: 100%;

            padding: 10px;

            margin: 10px 0;

            box-sizing: border-box;

            border: 1px solid #ccc;

            border-radius: 5px;

        }


        input:focus {

            outline: none;

            border-color: #3498db;

        }


        button {

            width: 100%;

            padding: 10px;

            margin-top: 10px;

            background: green;

            color: white;

            border: none;

            cursor: pointer;

            border-radius: 5px;

            font-size: 15px;

        }


        button:hover {

            opacity: 0.85;

        }


        .info {

            text-align: center;

            color: #555;

            margin-bottom: 15px;

        }


        .error {

            background-color: #f8d7da;

            color: #842029;

            padding: 10px;

            border-radius: 5px;

            margin-bottom: 15px;

            text-align: center;

        }

    </style>

</head>


<body>


<div class="login-box">


    <h2>
        Smart Pharmacy Login
    </h2>


    <p class="info">

        Login as Admin or Pharmacist

    </p>


    <?php if ($error !== ''): ?>

        <div class="error">

            <?php echo e($error); ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        onsubmit="disableLoginButton()"
    >


        <!-- CSRF TOKEN -->

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e(
                $_SESSION['csrf_token']
            ); ?>"
        >


        <input
            type="text"
            name="username"
            placeholder="Enter Username"
            maxlength="25"
            autocomplete="username"
            value="<?php echo e(
                $_POST['username'] ?? ''
            ); ?>"
            required
        >


        <input
            type="password"
            name="password"
            placeholder="Enter Password"
            maxlength="15"
            autocomplete="current-password"
            required
        >


        <button
            type="submit"
            name="login"
            id="loginButton"
        >

            Login

        </button>


    </form>


</div>


<script>

function disableLoginButton()
{
    const button =
        document.getElementById("loginButton");

    button.disabled = true;

    button.innerText = "Logging in...";
}

</script>


</body>

</html>