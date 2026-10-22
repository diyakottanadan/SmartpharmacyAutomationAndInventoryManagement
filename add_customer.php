
<?php
session_start();

include("db_connect.php");

/* CHECK LOGIN */
if (!isset($_SESSION['admin']) && !isset($_SESSION['pharmacist'])) {
    header("Location: login.php");
    exit();
}

/* IDENTIFY LOGGED-IN USER */
$isAdmin = isset($_SESSION['admin']);
$isPharmacist = isset($_SESSION['pharmacist']);

/* CSRF TOKEN */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/* HELPER FUNCTION */
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* FORM VARIABLES */
$customerName = "";
$phone = "";
$email = "";
$address = "";

$errors = [];

/* SUCCESS MESSAGE AFTER REDIRECT */
$successMessage = "";

if (isset($_GET['success']) && $_GET['success'] === 'added') {
    $successMessage = "Customer added successfully!";
}

/* FORM SUBMISSION */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* CSRF VALIDATION */
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        $errors[] = "Invalid request. Please try again.";
    }

    /* GET FORM VALUES */
    $customerName = trim($_POST['customerName'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    /* CUSTOMER NAME VALIDATION */
    if ($customerName === '') {
        $errors[] = "Customer name is required.";
    } elseif (strlen($customerName) < 2 || strlen($customerName) > 15) {
        $errors[] = "Customer name must contain 2 to 15 characters.";
    } elseif (!preg_match("/^[A-Za-z][A-Za-z .'-]*$/", $customerName)) {
        $errors[] = "Customer name can contain only letters, spaces, dots, apostrophes and hyphens.";
    }

    /* PHONE VALIDATION */
    if ($phone === '') {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
        $errors[] = "Enter a valid 10-digit Indian mobile number.";
    }

    /* EMAIL VALIDATION */
    if ($email === '') {
        $errors[] = "Email address is required.";
    } elseif (strlen($email) > 15) {
        $errors[] = "Email address cannot exceed 15 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid email address.";
    }

    /* ADDRESS VALIDATION */
    if ($address === '') {
        $errors[] = "Address is required.";
    } elseif (strlen($address) < 3 || strlen($address) > 15) {
        $errors[] = "Address must contain 3 to 15 characters.";
    } elseif (!preg_match("/^[A-Za-z0-9][A-Za-z0-9 .,#\/'-]*$/", $address)) {
        $errors[] = "Address contains invalid characters.";
    }

    /* INSERT CUSTOMER */
    if (empty($errors)) {

        $sql = "INSERT INTO customer
                (CustomerName, Phone, Email, Address)
                VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $errors[] = "Unable to prepare customer information.";
        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $customerName,
                $phone,
                $email,
                $address
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                /* REDIRECT AFTER SUCCESSFUL INSERT */
                header("Location: add_customer.php?success=added");
                exit();

            } else {
                $errors[] = "Unable to add customer. Please try again.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .container {
            width: 400px;
            max-width: 95%;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            text-align: center;
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #555;
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        .required {
            color: red;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
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

        .error ul {
            margin: 5px 0 0;
            padding-left: 20px;
            text-align: left;
        }

        button {
            width: 100%;
            padding: 11px;
            margin-top: 20px;
            border: none;
            background: green;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            font-size: 15px;
        }

        button:hover {
            background: #006400;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #333;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
<div class="container">

    <h2>Add Customer</h2>

    <?php if ($successMessage !== "") { ?>
        <div class="message success">
            <?php echo e($successMessage); ?>
        </div>
    <?php } ?>

    <?php if (!empty($errors)) { ?>
        <div class="message error">
            <strong>Please correct the following:</strong>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo e($error); ?></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <form method="POST" action="add_customer.php" id="customerForm">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($csrfToken); ?>"
        >

        <label for="customerName">
            Customer Name <span class="required">*</span>
        </label>
        <input
            type="text"
            id="customerName"
            name="customerName"
            maxlength="15"
            minlength="2"
            value="<?php echo e($customerName); ?>"
            pattern="[A-Za-z][A-Za-z .'-]*"
            title="Enter a valid customer name."
            required
        >

        <label for="phone">
            Phone <span class="required">*</span>
        </label>
        <input
            type="tel"
            id="phone"
            name="phone"
            maxlength="10"
            minlength="10"
            value="<?php echo e($phone); ?>"
            pattern="[6-9][0-9]{9}"
            title="Enter a valid 10-digit Indian mobile number."
            inputmode="numeric"
            required
        >

        <label for="email">
            Email <span class="required">*</span>
        </label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="15"
            value="<?php echo e($email); ?>"
            required
        >

        <label for="address">
            Address <span class="required">*</span>
        </label>
        <textarea
            id="address"
            name="address"
            maxlength="15"
            minlength="3"
            required
        ><?php echo e($address); ?></textarea>

        <button type="submit" name="submit">Add Customer</button>
    </form>

    <!-- BACK TO THE CORRECT DASHBOARD -->
    <?php if ($isAdmin) { ?>
        <a href="admin_dashboard.php" class="back">
            &larr; Back to Admin Dashboard
        </a>
    <?php } elseif ($isPharmacist) { ?>
        <a href="pharmacist_dashboard.php" class="back">
            &larr; Back to Pharmacist Dashboard
        </a>
    <?php } ?>

</div>

<script>
document.getElementById("customerForm").addEventListener("submit", function(event) {

    const name = document.getElementById("customerName").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const email = document.getElementById("email").value.trim();
    const address = document.getElementById("address").value.trim();

    const namePattern = /^[A-Za-z][A-Za-z .'-]*$/;
    const phonePattern = /^[6-9][0-9]{9}$/;
    const addressPattern = /^[A-Za-z0-9][A-Za-z0-9 .,#\/'-]*$/;

    if (name.length < 2 || name.length > 15 || !namePattern.test(name)) {
        alert("Please enter a valid customer name.");
        event.preventDefault();
        return;
    }

    if (!phonePattern.test(phone)) {
        alert("Please enter a valid 10-digit Indian mobile number.");
        event.preventDefault();
        return;
    }

    if (email.length > 15 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert("Please enter a valid email address.");
        event.preventDefault();
        return;
    }

    if (address.length < 3 || address.length > 15 || !addressPattern.test(address)) {
        alert("Please enter a valid address.");
        event.preventDefault();
        return;
    }
});
</script>

</body>
</html>