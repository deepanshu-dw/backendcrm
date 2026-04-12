<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Welcome extends Controller
{
    public function home()
    {
        return "Welcome to Hollywood Hair";
    }

    public function sendMail(Request $request)
    {
        try {
            $maildata['deartext'] = 'Dear';
            $maildata['subjecttext'] = 'We are happy to inform you that your appointment has been successfully booked.';
            /* $maildata['para1text'] = 'We are excited to inform you that your appointment has been successfully booked with Hollywood Hair.'; */
            $maildata['para2text'] = 'Your booking details:';
            $maildata['bookingdatetext'] = 'Booking Date';
            /* $maildata['visittypetext'] = 'Visit Type'; */
            /* $maildata['prepaymentamttext'] = 'Pre Payment Amount'; */
            $maildata['slottext'] = 'Slot';
            $maildata['servicetext'] = 'Service';
            $maildata['footerpara1text'] = 'If you have additional questions or need help, please contact us at:';
            $maildata['contactno1'] = 'SHOWROOMS POLAND : +48578903292';
            $maildata['contactno2'] = 'SHOWROOMS SPAIN : +34651439815';
            $maildata['footerpara2text'] = 'Thank you for choosing Hollywood Hair, we hope it will be a unique experience for you.';
            $maildata['footerpara3text'] = 'Best regards';

            $maildata['to'] = "shekhar.designoweb@gmail.com";
            $maildata['subject'] = 'Booking Accepted';
            $maildata['view_name'] = 'bookinginfo';
            $maildata['name'] = "Shekhar";
            $maildata['email'] = "shekhar.designoweb@gmail.com";
            $maildata['booking_details'] = null;

            if (!empty($maildata['email'])) {
                $result = sendMail1($maildata);
				dd($result); die;
            }

            if ($result) {
                return response()->json(['result' => 1, 'msg' => 'E-Mail sent successfully.']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Failed to send email.']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'Exception: ' . $e->getMessage()]);
        }
    }


    public function submitCareerForm(Request $request)
    {
        try {
            $request->validate([
                // Your other validation rules
                'upload_resume' => 'required|mimes:pdf|max:2048',
            ]);
			
			if ($request->has('g-recaptcha-response')) {
				$recaptcha_response = $request->input('g-recaptcha-response');
				if (empty($recaptcha_response)) {
					return response()->json(['result' => -5, 'msg' => 'Please fill the reCAPTCHA']);
				}
				$recaptcha_secret = "6LceZAYqAAAAAHqfRTPx6Olfda8r0Ulh1JyB3L3D";
				if (isset($recaptcha_response) && !empty($recaptcha_response)) {
					// Verify the reCAPTCHA response
					$response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
					$responseKeys = json_decode($response, true);
					if (intval($responseKeys["success"]) !== 1) {
						return response()->json(['result' => -5, 'msg' => "Failed to verify reCAPTCHA"]);
					}
				}
			}

            $uploadFile = $request->file('upload_resume');

            // Create 'uploads' directory if it doesn't exist
            if (!Storage::disk('public')->exists('uploads')) {
                Storage::disk('public')->makeDirectory('uploads');
            }

            // Store the attachment
            $attachmentPath = $uploadFile->store('uploads', 'public');

            // Get the public URL for the attachment
            $attachmentPublicPath = asset('public/storage/' . $attachmentPath);

            $to = 'order@hollywoodhair.pl';
            $subject = 'Career Form Details';

            $emailData = [
                'to' => $to,
                'subject' => $subject,
                'view_name' => 'career', // Adjust if necessary
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'contact_number' => $request->input('contact_number'),
                'email_id' => $request->input('email_id'),
                'address' => $request->input('address'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'select_service' => $request->input('select_service'),
                'upload_resume' => $attachmentPublicPath, // Use the public URL
            ];

            $emailResult = sendMailWithAttachment($emailData);

            if ($emailResult) {
                return response()->json(['result' => 1, 'msg' => 'Form has been submitted successfully. We will contact you soon.']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Failed to send email.']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'Exception: ' . $e->getMessage()]);
        }
    }


    public function contactForm(Request $request)
    {
        try {
			if ($request->has('g-recaptcha-response')) {
				$recaptcha_response = $request->input('g-recaptcha-response');
				if (empty($recaptcha_response)) {
					return response()->json(['result' => -5, 'msg' => 'Please fill the reCAPTCHA']);
				}
				$recaptcha_secret = "6LceZAYqAAAAAHqfRTPx6Olfda8r0Ulh1JyB3L3D";
				if (isset($recaptcha_response) && !empty($recaptcha_response)) {
					// Verify the reCAPTCHA response
					$response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
					$responseKeys = json_decode($response, true);
					if (intval($responseKeys["success"]) !== 1) {
						return response()->json(['result' => -5, 'msg' => "Failed to verify reCAPTCHA"]);
					}
				}
			}
			
            $to = 'order@hollywoodhair.pl';
            $subject = 'Contact Form Details';

            $emailData = [
                'to' => $to,
                'subject' => $subject,
                'view_name' => 'contact', // Adjust if necessary
                'full_name' => $request->input('full_name'),
                'user_email' => $request->input('user_email'),
                'contact_number' => $request->input('contact_number'),
                'contact_description' => $request->input('contact_description')
            ];

            $emailResult = sendMailWithAttachment($emailData);

            if ($emailResult) {
                return response()->json(['result' => 1, 'msg' => 'Form has been submitted successfully. We will contact you soon.']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Failed to send email.']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'Exception: ' . $e->getMessage()]);
        }
    }

    public function submitComplaintForm(Request $request)
    {
        try {
            $request->validate([
                'full_name' => 'required',
                'contact_number' => 'required',
                'email_id' => 'required|email',
                'appointment_date' => 'required|date',
                'appointment_time' => 'required', // Update this line based on your expected time format
                'salon_location' => 'required',
                'select_service' => 'required',
                'complaint_description' => 'required',
                //'upload_file' => 'required|mimes:pdf|max:2048', // Adjust file size and allowed types as needed
            ]);
			
			if ($request->has('g-recaptcha-response')) {
				$recaptcha_response = $request->input('g-recaptcha-response');
				if (empty($recaptcha_response)) {
					return response()->json(['result' => -5, 'msg' => 'Please fill the reCAPTCHA']);
				}
				$recaptcha_secret = "6LceZAYqAAAAAHqfRTPx6Olfda8r0Ulh1JyB3L3D";
				if (isset($recaptcha_response) && !empty($recaptcha_response)) {
					// Verify the reCAPTCHA response
					$response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
					$responseKeys = json_decode($response, true);
					if (intval($responseKeys["success"]) !== 1) {
						return response()->json(['result' => -5, 'msg' => "Failed to verify reCAPTCHA"]);
					}
				}
			}

            // Get form data
            $full_name = $request->input('full_name');
            $contact_number = $request->input('contact_number');
            $email_id = $request->input('email_id');
            $appointment_date = $request->input('appointment_date');

            // Validate and format appointment time
            $appointment_time = $request->input('appointment_time');
            $parsed_time = strtotime($appointment_time);
            if ($parsed_time === false) {
                throw new \Exception('Invalid appointment time format');
            }
            $formatted_time = date('h:i A', $parsed_time);

            $salon_location = $request->input('salon_location');
            $select_service = $request->input('select_service');
            $complaint_description = $request->input('complaint_description');


            $uploadFile = $request->file('upload_file');
            if (!empty($uploadFile)) {
                // Handle file upload
                // Create 'uploads' directory if it doesn't exist
                if (!Storage::disk('public')->exists('uploads')) {
                    Storage::disk('public')->makeDirectory('uploads');
                }

                // Store the attachment
                $attachmentPath = $uploadFile->store('uploads', 'public');

                // Get the public URL for the attachment
                $attachmentPublicPath = asset('public/storage/' . $attachmentPath);
            }

            // Send email with attachment
            $emailData = [
                'to' => 'order@hollywoodhair.pl',
                //'to' => 'ambuj.designoweb@gmail.com',
                'subject' => 'Complaint Form Details',
                'view_name' => 'complaint',
                'full_name' => $full_name,
                'contact_number' => $contact_number,
                'email_id' => $email_id,
                'appointment_date' => $appointment_date,
                'appointment_time' => $formatted_time,
                'salon_location' => $salon_location,
                'select_service' => $select_service,
                'complaint_description' => $complaint_description,
                'upload_file' => @$attachmentPublicPath, // Change this line
            ];

            $emailResult = sendMailWithAttachmentV2($emailData);

            if ($emailResult) {
                return response()->json(['result' => 1, 'msg' => 'Form has been submitted successfully. We will contact you soon.']);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong. Please try again later.']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
}
