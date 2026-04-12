<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Website</title>
</head>
<body style="font-family: 'Arial', sans-serif;">

    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">

        <h2>Welcome to Hollywood Hair Salon!</h2>

        <p>Dear <?php echo @$name; ?>,</p>

        <p>Thank you for joining our community! We're delighted to have you on board.</p>

        <p>Your account has been created with the following details:</p>

        <p>
            <strong>Username:</strong> [<?php echo  @$user_name;?>]
        </p>
        <p>
            <strong>Email:</strong> [<?php echo  @$email; ?>]
        </p>
        <p>
            <strong>Password:</strong> <?php echo @$password; ?>
        </p>

        <p>Please keep your login credentials secure. You can now log in to our website using the provided username and password.</p>

        <p>If you have any questions or need assistance, feel free to contact our support team.</p>

        <p>Best regards,<br>
        [Hollywood Hair]</p>

    </div>

</body>
</html>
