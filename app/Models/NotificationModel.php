<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
class NotificationModel extends Model
{
    use HasFactory;
    protected $table = "notification";
    protected $primaryKey = 'notification_id';
  
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    public $timestamps = true;

    public function getNotification($lang, $salon_id = null, $customer_id = null, $notification_type = null)
    {
        $query = DB::table('notification');
		if ($lang == 'es') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject_es as subject', 'message_es as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} elseif ($lang == 'pl') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject_pl as subject', 'message_pl as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} else {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject', 'message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		}
        if ($salon_id !== null) {
            $query->where('salon_id', $salon_id);
        }
        if ($customer_id !== null) {
            $query->where('customer_id', $customer_id);
        }
        if ($notification_type !== null) {
            $query->where('notification_type', $notification_type);
        }
        $query->where('status', '=', 'Active');
		$query->orderByDesc('notification_id');

        return $query->get();
    }
	
	public function getNotificationForWeb($lang, $salon_id = null, $customer_id = null, $notification_type = null, $pagination_limit = 50,$all="true")
    {
        $query = DB::table('notification');
		if ($lang == 'es') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'booking_id', 'subject_es as subject', 'message_es as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} elseif ($lang == 'pl') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'booking_id', 'subject_pl as subject', 'message_pl as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} else {
			$query->select('notification_id', 'salon_id', 'customer_id', 'booking_id', 'subject', 'message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		}
        if ($salon_id !== null) {
            $query->where('salon_id', $salon_id);
        }
        if ($customer_id !== null) {
            $query->where('customer_id', $customer_id);
        }
        if ($notification_type !== null) {
            $query->where('notification_type', $notification_type);
        }
        $query->where('status', '=', 'Active');
		$query->orderByDesc('notification_id');
        if($all == 'true'){
            return $query->get();
        }else{
            return $query->paginate($pagination_limit);
        }
    }

    public function deleteNotification($notification_id)
    {
        $notification = $this->find($notification_id);
    
        if ($notification) {
            $notification->status = 'Deleted'; 
            return $notification->save();
        }
    
        return false;
    }
	
	public function getCustomerNotifications($customer_id, $lang)
    {
		$query = DB::table('notification');
		if ($lang == 'es') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject as subject_en', 'subject_es as subject', 'message_es as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} elseif ($lang == 'pl') {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject as subject_en', 'subject_pl as subject', 'message_pl as message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		} else {
			$query->select('notification_id', 'salon_id', 'customer_id', 'subject as subject_en', 'subject', 'message', 'notification_type', 'notification_status', 'status', 'created_at', 'updated_at');
		}
        $query->where('status', '=', 'Active');
		$query->where('notification_type', '=', 'customer');
		$query->where('customer_id', '=', $customer_id);
		$query->orderByDesc('notification_id');
        return $query->get();
    }
	
	public function getCustomerOfferNotifications($customer_id, $lang)
    {
		$query = DB::table('offer_notifications');
		if ($lang == 'es') {
			$query->select('id', 'customer_id', 'offer_id', 'title_es as title', 'message_es as message', 'notification_status', 'status', 'created_at', 'updated_at');
		} elseif ($lang == 'pl') {
			$query->select('id', 'customer_id', 'offer_id', 'title_pl as title', 'message_pl as message', 'notification_status', 'status', 'created_at', 'updated_at');
		} else {
			$query->select('id', 'customer_id', 'offer_id', 'title', 'message', 'notification_status', 'status', 'created_at', 'updated_at');
		}
        $query->where('status', '=', 'Active');
		$query->where('customer_id', '=', $customer_id);
		$query->orderByDesc('id');
        return $query->get();
    }
	
    public static function getCustomerHistoryBySalon($salon_id, $lang, $customer_id=null)
    {
        $data = DB::table('booking')
            ->where('salon_id', $salon_id);
        if (!empty($customer_id)) {
            $data->where('customer_id', $customer_id);
        }
        return $data = $data->get();
    }
    public static function getFirebaseTokens($customer_ids)
    {
        return DB::table('customers_authentication')
            ->whereIn('customer_id', $customer_ids)
            ->get()
            ->map(function ($item) {
                return $item->firebase_token;
            })
            ->toArray();
    }
}