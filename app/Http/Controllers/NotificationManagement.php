<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\NotificationModel as Notification;
use App\Models\CustomerModel;
use Stichoza\GoogleTranslate\GoogleTranslate;

class NotificationManagement extends Controller
{
    /**
     * Add a new notification.
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNotification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                //'subject' => 'required|string|max:255',
                'message' => 'required',
                // 'notification_date' => 'required', // Corrected the field name
                'notification_type' => 'required|in:salon,customer,offer', // Corrected the validation rule
            ], [
                'required' => 'This :Attribute is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            $subject = $request->input('subject');
            $message = $request->input('message');

            $subject_es = GoogleTranslate::trans($subject, 'es');
            $message_es = GoogleTranslate::trans($message, 'es');
            $subject_pl = GoogleTranslate::trans($subject, 'pl');
            $message_pl = GoogleTranslate::trans($message, 'pl');

            $notification = new Notification();
            $notification->salon_id = $request->input('salon_id');
            $notification->customer_id = $request->input('customer_id');
            $notification->subject = $subject;
            $notification->message = $message;
            $notification->subject_es = $subject_es;
            $notification->message_es = $message_es;
            $notification->subject_pl = $subject_pl;
            $notification->message_pl = $message_pl;
            $notification->notification_type = $request->input('notification_type');
            $notification->status = 'Active'; // Set an initial status
            $notification->created_at = now(); // Use Carbon for date and time
            $notification->updated_at = now();
            $notification->save();

            $notification_id = $notification->notification_id;
            // $recipient_id = $request->post('recipient_id');

            // Corrected the condition to check if the notification was successfully saved
            if ($notification_id) {
                if ($notification->notification_type == 'salon') {
                    $salon_id = $notification->salon_id;
                    $msg = $notification->message;
                    $title = $notification->subject;

                    sendFirebaseNotification($salon_id, $msg, $title);
                }
                if ($notification->notification_type == 'customer') {

                    $customer_id = $notification->customer_id;
                    $msg = $notification->message;
                    $title = $notification->subject;
                    $userdata = select('customers', ['customer_name', 'email'], ['customer_id' => $customer_id])->first();
                    if (!empty($userdata->email)) {
                        $maildata['to'] = @$userdata->email;
                        $maildata['customer_name'] = @$userdata->customer_name;
                        $maildata['subject'] = $title;
                        $maildata['msg'] = $msg;
                        $maildata['view_name'] = 'notification';
                        sendMail($maildata);
                    }
                    sendCustomerFirebaseNotification($customer_id, $msg, $title);
                }
                return response()->json(['result' => 1, 'msg' => 'Notification Send successfully'], 201);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }


    public function updateNotification(Request $request, $notification_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|max:255',
                'notification_type' => 'required|in:salon,customer,offer',
            ], [
                'required' => 'This :Attribute is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            $notification = Notification::find($notification_id);

            if (!$notification) {
                return response()->json(['result' => 0, 'msg' => 'Notification not found'], 404);
            }

            $subject = $request->input('subject');
            $message = $request->input('message');

            $subject_es = GoogleTranslate::trans($subject, 'es');
            $message_es = GoogleTranslate::trans($message, 'es');
            $subject_pl = GoogleTranslate::trans($subject, 'pl');
            $message_pl = GoogleTranslate::trans($message, 'pl');

            $notification->salon_id = $request->input('salon_id');
            $notification->customer_id = $request->input('customer_id');
            $notification->subject = $subject;
            $notification->message = $message;
            $notification->subject_es = $subject_es;
            $notification->message_es = $message_es;
            $notification->subject_pl = $subject_pl;
            $notification->message_pl = $message_pl;
            $notification->notification_type = $request->input('notification_type');
            $notification->status = 'Active';
            // $notification->created_at = now(); 
            $notification->updated_at = now();
            $notification->save();
            // dd($notification); die;


            $notificationType = $notification->notification_type;

            if ($notificationType == 'salon') {
                $salon_id = $notification->salon_id;
                $msg = $notification->message;
                $title = $notification->subject;

                sendFirebaseNotification($salon_id, $msg, $title);
            } elseif ($notificationType == 'customer') {
                // dd($customer_id); die;
                $customer_id = $notification->customer_id;
                $msg = $notification->message;
                $title = $notification->subject;

                sendCustomerFirebaseNotification($customer_id, $msg, $title);
                $userdata = select('customers', ['customer_name', 'email'], ['customer_id' => $customer_id])->first();

                // dd($userdata);            
                $maildata['to'] = @$userdata->email;
                $maildata['customer_name'] = @$userdata->customer_name;
                $maildata['subject'] = $title;
                $maildata['msg'] = $msg;
                $maildata['view_name'] = 'notification';

                if ($maildata['to']) {
                    sendMail($maildata);
                }
            }

            return response()->json(['result' => 1, 'msg' => 'Notification updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function translateAndUpdateFromTable()
    {
        try {
            // Fetch all notifications from the database
            $notifications = select('notification', ['notification_id', 'subject', 'message'], [['message_es', '=', null]]);
            //dd($notifications); die;

            foreach ($notifications as $notification) {
                // Get the subject and message from the notification
                $subject = $notification->subject;
                $message = $notification->message;

                // Translate subject and message to Spanish and Polish
                $title_es = GoogleTranslate::trans($subject, 'es');
                $message_es = GoogleTranslate::trans($message, 'es');
                $title_pl = GoogleTranslate::trans($subject, 'pl');
                $message_pl = GoogleTranslate::trans($message, 'pl');

                // Update the notification with translated values
                $data = [
                    'subject_es' => $title_es,
                    'message_es' => $message_es,
                    'message_pl' => $message_pl,
                    'subject_pl' => $title_pl,
                ];
                update('notification', 'notification_id', $notification->notification_id, $data);
            }

            return response()->json(['result' => 1, 'msg' => 'Translation and update successful'], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Get all notifications.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllNotifications()
    {
        try {
            $notifications = Notification::where('status', '!=', 'deleted')->get();

            foreach ($notifications as $notification) {
                $notification->created_at = $notification->created_at ? formatDate($notification->created_at) : null;
                $notification->updated_at = $notification->updated_at ? formatDate($notification->updated_at) : null;
                // $notification->expiration_date = $notification->expiration_date ? formatDate($notification->expiration_date) : null;
                // $notification->recipient = selectWithJoin('notification','users',['users.email','users.user_id'],'user_id','user_id','inner',['notification_id'=>$notification->notification_id],true);
            }

            return response()->json(['result' => 1, "msg" => "Notifications data found", 'data' => ($notifications)]);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'No notifications were found'], 200);
        }
    }

    /**
     * Get a specific notification by ID.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotification(Request $request)
    {

        try {
            $notificationModel = new Notification();
            $notification_type = $request->input('notification_type');
            $salon_id = $request->input('salon_id');
            $customer_id = $request->input('customer_id');
            $lang = $request->input('lang');
            $notifications = $notificationModel->getNotification($lang, $salon_id, $customer_id, $notification_type);
            // dd($notifications);
            return response()->json(['result' => 1, 'msg' => 'Notifications found', 'data' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['result' => 0, 'msg' => 'Error retrieving notifications', 'error' => $e->getMessage()], 500);
        }
    }

    public function getNotificationForWeb(Request $request)
    {

        try {
            $notificationModel = new Notification();
            $notification_type = $request->input('notification_type');
            $salon_id = $request->input('salon_id');
            $customer_id = $request->input('customer_id');
            $lang = $request->input('lang');
            $pagination_limit = $request->query('pagination_limit') ?? 10;
            $notifications = $notificationModel->getNotificationForWeb($lang, $salon_id, $customer_id, $notification_type, $pagination_limit, 'false');
			
            if ($notifications) {
                $readCount = select('notification', 'notification_id', ['notification_status' => 'read'])->count();
				$unreadCount = select('notification', 'notification_id', ['notification_status' => 'unread'])->count();
            } else {
                $readCount = 0;
                $unreadCount = 0;
            }

            /* $filtered_notifications = [];
			if (!empty($notifications)) {
				foreach ($notifications as $value) {
					if (!empty($value->booking_id)) {
						$booking = select('booking', 'booking_id', ['booking_status' => 'pending', 'booking_id' => $value->booking_id]);
						if ($booking) {
							$filtered_notifications[] = $value;
						}
					}
				}
			}
			
			if ($filtered_notifications) {
				$readCount = $filtered_notifications->where('notification_status', 'read')->count();
				$unreadCount = $filtered_notifications->where('notification_status', 'unread')->count();
			} else {
				$readCount = 0;
				$unreadCount = 0;
			} */

            return response()->json(['result' => 1, 'msg' => 'Notifications found', 'data' => $notifications, "readCount" => $readCount, "unreadCount" => $unreadCount]);
        } catch (\Exception $e) {
            return response()->json(['result' => 0, 'msg' => 'Error retrieving notifications', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCustomerNotifications(Request $request)
    {
        try {
            $customer_id = $request->post('customer_id');
            $lang = $request->post('lang');
            $customer = @select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $customer_id]])->first();
            $notificationModel = new Notification();
            $result = $notificationModel->getCustomerNotifications(@$customer->customer_id, $lang);

            if (!empty($result)) {
                return response()->json(['result' => 1, "msg" => "Notifications fetched successfully.", 'data' => $result]);
            } else {
                return response()->json(['result' => -1, "msg" => "Something went Wrong!", 'data' => null]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Delete a specific notification by ID.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteNotification($notification_id)
    {
        try {
            $notificationModel = new Notification();

            $result = $notificationModel->deleteNotification($notification_id);

            if ($result) {
                return response()->json(['result' => 1, 'msg' => 'Notification deleted successfully']);
            } else {
                return response()->json(['result' => 0, 'msg' => 'Notification not found or not deleted']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'error' => 'Error deleting notification', 'message' => $e->getMessage()], 500);
        }
    }

    public function sendFirebaseNotification($customer_id, $message, $title)
    {
        $token = CustomerModel::getTokenByCustomerId($customer_id);
        $url = 'https://fcm.googleapis.com/fcm/send';
        $data = [
            "message" => $message,
            "title" => $title,
        ];
        $fields = [
            'registration_ids' => [
                $token['firebase_token'],
            ],
            'data' => $data,
        ];

        $fields = json_encode($fields);
        //$this->Driver_model->addNotificationMessage($title, $message, $driver_id);
        $headers = array(
            'Authorization: key=' . env('API_ACCESS_KEY'),
            'Content-Type: application/json',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $result = curl_exec($ch);
        curl_close($ch);
        return response()->json(['result' => 1, 'msg' => 'Notification Sent Successfully']);
    }

    public function sendNotification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id' => 'required',
                'offer_id' => 'required'
            ], [
                'required' => 'This :Attribute is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            $salon_id = $request->input('salon_id');
            $offer_id = $request->input('offer_id');

            $customers = Notification::getCustomerHistoryBySalon($salon_id, $lang = null)->map(function ($item) {
                return $item->customer_id;
            })->toArray();
            $uniqueCustomers = array_unique($customers);

            $title = "New Offer";
            $message = "You have a new offer";
            $title_es = GoogleTranslate::trans($title, 'es');
            $message_es = GoogleTranslate::trans($message, 'es');
            $title_pl = GoogleTranslate::trans($title, 'pl');
            $message_pl = GoogleTranslate::trans($message, 'pl');

            if (!empty($uniqueCustomers)) {
                foreach ($uniqueCustomers as $customer_id) {
                    if (!empty($customer_id)) {
                        $customer = @select('customers', '*', [['status', '=', 'Active'], ['customer_id', '=', $customer_id]])->first();
                        if (!empty($customer->shopify_user_id)) {
                            sendCustomerFirebaseNotification(@$customer->shopify_user_id, $message, $title, 'Offer');

                            $notification = [
                                'customer_id' => @$customer->shopify_user_id,
                                'offer_id' => !empty($offer_id) ? $offer_id : null,
                                'title'       => $title,
                                'message'     => $message,
                                'title_es'       => $title_es,
                                'message_es'     => $message_es,
                                'title_pl'       => $title_pl,
                                'message_pl'     => $message_pl,
                                'created_at'    => now(),
                                'updated_at'    =>  now()
                            ];
                            $customer_id  = insert('offer_notifications', $notification);
                        }
                    }
                }
            }

            return response()->json(['result' => 1, 'msg' => 'Notification added and sent successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function getCustomerOfferNotifications(Request $request)
    {
        try {
            $customer_id = $request->post('customer_id');
            $lang = $request->post('lang');
            $notificationModel = new Notification();
            $result = $notificationModel->getCustomerOfferNotifications($customer_id, $lang);
            if (!empty($result)) {
                foreach ($result as $value) {
                    if (!empty($value->offer_id)) {
                        $offer = select('offer_management', '*', ['offer_id' => $value->offer_id])->first();
                        $value->offer_name = !empty($offer->offer_name) ? $offer->offer_name : null;
                        $value->percentage = !empty($offer->percentage) ? $offer->percentage : null;
                        $value->upto = !empty($offer->upto) ? $offer->upto : null;
                        $value->coupon_code = !empty($offer->coupon_code) ? $offer->coupon_code : null;
                        $value->offer_type = !empty($offer->offer_type) ? $offer->offer_type : null;
                        $value->offer_start_date = !empty($offer->offer_start_date) ? $offer->offer_start_date : null;
                        $value->offer_end_date = !empty($offer->offer_end_date) ? $offer->offer_end_date : null;
                    }
                }

                return response()->json(['result' => 1, "msg" => "Notifications fetched successfully.", 'data' => $result]);
            } else {
                return response()->json(['result' => -1, "msg" => "Something went Wrong!", 'data' => null]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function updateOfferNotificationStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required',
                'customer_id' => 'required',
                'salon_id' => [
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('type') == 'salon' && $request->input('notification_type') == 'salon') {
                            $fail("The $attribute field is required.");
                        }
                    },
                ],
                'notification_type' => [
                    'required_if:type,salon',
                ]
            ], [
                'required' => 'The :attribute field is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $type = $request->post('type');
            $customer_id = $request->post('customer_id');
            $salon_id = $request->post('salon_id');
            $notification_type = $request->post('notification_type');

            if ($type == 'customer') {
                $notification_ids = select('offer_notifications', 'id', ['status' => 'Active', 'customer_id' => $customer_id]);
                if (!empty($notification_ids)) {
                    foreach ($notification_ids as $notification_id) {
                        update('offer_notifications', 'id', $notification_id->id, ['notification_status' => 'read']);
                    }
                }
            } elseif ($type == 'salon') {
                if ($notification_type == 'customer') {
                    $customer = select('customers', 'customer_id', ['status' => 'Active', 'shopify_user_id' => $customer_id])->first();
                    if (!empty($customer)) {
                        $notification_ids = select('notification', 'notification_id', ['status' => 'Active', 'customer_id' => $customer->customer_id, 'notification_type' => $notification_type]);
                    }
                }
                if ($notification_type == 'salon') {
                    $notification_ids = select('notification', 'notification_id', ['status' => 'Active', 'salon_id' => $salon_id, 'notification_type' => $notification_type]);
                }
                if (!empty($notification_ids)) {
                    foreach ($notification_ids as $notification_id) {
                        update('notification', 'notification_id', $notification_id->notification_id, ['notification_status' => 'read']);
                    }
                }
            }

            return response()->json(['result' => 1, "msg" => "Notifications status updated successfully."]);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function updateNotificationStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'notification_id' => 'required'
            ], [
                'required' => 'The :attribute field is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $notification_id = $request->post('notification_id');

            if (!empty($notification_id)) {
                update('notification', 'notification_id', $notification_id, ['notification_status' => 'read']);
                return response()->json(['result' => 1, "msg" => "Notifications status updated successfully."]);
            } else {
                return response()->json(['result' => -1, "msg" => "Notifications status updated failed!"]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
}
