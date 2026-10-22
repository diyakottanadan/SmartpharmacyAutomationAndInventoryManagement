<?php
session_start();

if(!isset($_SESSION['username']))
{
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>

<h1>Smart Pharmacy Automation System</h1>

<h3>Welcome <?php echo $_SESSION['username']; ?></h3>

<h3>Role : <?php echo $_SESSION['role']; ?></h3>

<a href="logout.php">Logout</a>

</body>
</html>