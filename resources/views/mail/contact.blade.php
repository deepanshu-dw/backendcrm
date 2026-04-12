<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
</head>
<body>

    <h2>Contact Form Submission</h2>

    <p><strong>Full Name:</strong> {{ $full_name }}</p>
    <p><strong>Email:</strong> {{ $user_email }}</p>
    <p><strong>Contact Number:</strong> {{ $contact_number }}</p>
    <p><strong>Complaint Description:</strong> {{ $contact_description }}</p>

    <p>Thank you for submitting your complaint. We will get in touch with you soon.</p>

</body>
</html>
