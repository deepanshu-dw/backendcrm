<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Hollywood Hair Appointment</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background-color: #f2f2f2;
    }
    .container {
      max-width: 700px;
      margin: 50px auto;
      padding: 30px;
      border-radius: 10px;
      background-color: #ffffff;
      box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
    }
    .logo {
      text-align: center;
    }
    .logo img {
      max-width: 200px;
      height: auto;
    }
    h4 {
      text-align: center;
    }
    p {
      margin-top: 20px;
      font-size: 16px;
      line-height: 1.5;
    }
    .activate-btn {
      display: inline-block;
      padding: 8px 16px;
      background-color: #E91E63;
      border-radius: 6px;
      text-decoration: none;
      color: #ffffff;
    }
    .footer {
      margin-top: 30px;
      text-align: center;
      font-size: 12px;
    }
    .footer p {
      margin: 5px 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="https://app.hollywoodhair.pl/crm/assets/Logo-26b219b5.png" alt="Hollywood Hair Logo">
    </div>
    <p>Dear <?php echo @$customer_name ;?></p>
    <p>We're excited to remind you of your upcoming appointment at Hollywood Hair:</p>
    <p>Subject: <?php echo @$subject ;?></p>
    <p>Message: <?php  echo @$msg; ?></p>
    <p>If you have any questions or need to reschedule, please contact us at the provided email address or give us a call.</p>
    <p><a href="mailto:kontakt@hollywoodhair.pl" class="activate-btn" style="color:#fff">Contact Us</a></p>
    <p>Best Regards, The Hollywood Hair Support Team <br/>
        Your Insights and Leads Delivered</p>
    <div class="footer">
    <p>© <?php echo date('Y'); ?> Hollywood Support Team . All Rights Reserved.</p>
    </div>
  </div>
</body>
</html>
