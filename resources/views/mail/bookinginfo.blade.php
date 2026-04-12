<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Website</title>
</head>

<body style="font-family: 'Arial', sans-serif;">

    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">

        <!-- Handling the subject text -->
        @if(isset($subjecttext) && !empty($subjecttext))
            <h3>{{ $subjecttext }}</h3>
        @else
            <h3>Welcome!</h3> <!-- Fallback if $subjecttext is missing -->
        @endif

        <!-- Handling the dear text with the name -->
        @if(isset($deartext))
            <p>{{ $deartext }} {{ $name ?? 'Valued Customer' }},</p>
        @else
            <p>Dear {{ $name ?? 'Valued Customer' }},</p> <!-- Fallback if $deartext is missing -->
        @endif

        <!-- @isset($para1text)<p>{{ @$para1text }}</p>@endisset -->

        <!-- Handling para2 text -->
        <p>{{ $para2text ?? '' }}</p> <!-- This will not display anything if $para2text is missing -->

        <!-- Handling the booking date text -->
        @if(isset($bookingdatetext) && !empty($booking_details->booking_date))
            <p><strong>{{ $bookingdatetext }}: </strong>{{ date('d F, Y', strtotime($booking_details->booking_date)) }}</p>
        @else
            <p><strong>Booking Date: </strong>Not Available</p> <!-- Fallback for missing booking details -->
        @endif

        <!-- Commented code for visit type and pre-payment amount -->
        <!-- <p><strong>{{ @$visittypetext }}: </strong><?php //echo !empty($booking_details->visit_type) ? ucwords($booking_details->visit_type) : null; ?></p> -->
        <?php //if (!empty($booking_details) && ($booking_details->pre_payment_made == 'yes')) { ?>
        <!-- <p><strong>{{ @$prepaymentamttext }}: </strong><?php //echo !empty($booking_details->pre_payment_amt) ? $booking_details->currency_code . ' ' . $booking_details->pre_payment_amt : null; ?></p> -->
        <?php //} ?>

        <!-- Handling the slot time -->
        @if(isset($slottext) && !empty($booking_details->slotsdetails[0]->slot_time))
            <p><strong>{{ $slottext }}: </strong>{{ date('h:i', strtotime($booking_details->slotsdetails[0]->slot_time)) }}</p>
        @else
            <p><strong>Slot Time: </strong>Not Available</p> <!-- Fallback for missing slot details -->
        @endif

        <!-- Handling the service details -->
        @if(isset($servicetext) && !empty($booking_details->servicedetails))
            <?php $i = 1; ?>
            @foreach($booking_details->servicedetails as $service)
                <p><strong>{{ $servicetext }} {{ $i }}: </strong>{{ $service->service_name ?? 'Service Not Available' }}</p>
                <?php $i++; ?>
            @endforeach
        @else
            <p><strong>Services: </strong>No services available</p> <!-- Fallback for missing service details -->
        @endif

        <!-- Handling the deposit info -->
        <p><strong>{{ $deposittext ?? 'Deposit' }}: </strong>{{ $depositinfo ?? 'No Deposit Information' }}</p>

        <!-- Handling footer paragraph 1 -->
        <p>{{ $footerpara1text ?? '' }}</p> <!-- Will not display anything if $footerpara1text is missing -->

        <!-- Handling the contact numbers -->
        <p>{{ $contactno1 ?? 'No contact available' }}</p>
        <p>{{ $contactno2 ?? '' }}</p> <!-- Will not display anything if $contactno2 is missing -->

        <!-- Handling footer paragraph 2 -->
        <p>{{ $footerpara2text ?? '' }}</p> <!-- Will not display anything if $footerpara2text is missing -->

        <!-- Handling the final footer text -->
        <p>{{ $footerpara3text ?? 'Best regards' }},<br />Hollywood Hair</p>

    </div>

</body>

</html>
