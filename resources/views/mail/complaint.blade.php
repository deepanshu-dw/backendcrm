<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Form Submission</title>
</head>
<body>

    <h2>Complaint Form Submission</h2>

    <p><strong>Full Name:</strong> {{ $full_name }}</p>
    <p><strong>Contact Number:</strong> {{ $contact_number }}</p>
    <p><strong>Email:</strong> {{ $email_id }}</p>
    <p><strong>Appointment Date:</strong> {{ $appointment_date }}</p>
    <p><strong>Appointment Time:</strong> {{ $appointment_time }}</p>
    <p><strong>Salon Location:</strong> {{ $salon_location }}</p>
    <p><strong>Selected Service:</strong> {{ $select_service }}</p>
    <p><strong>Complaint Description:</strong> {{ $complaint_description }}</p>

    <p>Thank you for submitting your complaint. We will get in touch with you soon.</p>

</body>
</html>
