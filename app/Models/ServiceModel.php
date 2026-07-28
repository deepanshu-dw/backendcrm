<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class serviceModel extends Model
{
    use HasFactory;
    public static function addService($insert)
    {
        DB::table('services')
        ->insert($insert);
        return DB::getPdo()
        ->lastInsertId();
    }
    public static function getAllServices($lang, $is_admin = "true", $user = null, $user_id = null, $keyword = null)
    {
		if (($user == 'subadmin') && ($user_id)) {
			$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons);
				if (!empty($salon_ids)) {
					$services = DB::table('salon_services')->whereIn('salon_id', $salon_ids)->pluck('service_id');
					$data = DB::table('services')->whereIn('service_id', $services);
				} else {
					$data = DB::table('services');
				}
			} else {
				$data = DB::table('services');
			}
		} else {
			$data = DB::table('services');
		}

		if (!empty($lang)) {
			$data->select(
				'services.service_id', 
				'services.service_name_' . $lang . ' as service_name',
                'services.service_description_' . $lang . ' as service_description', //Added 
				'groups.group_name_' . $lang . ' as group_name'
			);
		} else {
			$data->select('services.*', 'groups.group_name_en as group_name');
		}

		$data->leftJoin('groups', 'groups.group_id', '=', 'services.group_id');

		if ($is_admin == "false") {
			$data->where('services.is_archived', 'No');
		}

		$data->where('services.status', 'Active');

		// Add search functionality
		if (!empty($keyword)) {
			$data->where(function ($query) use ($keyword) {
				$query->where('services.service_name_en', 'LIKE', '%' . $keyword . '%')
					  ->orWhere('groups.group_name_en', 'LIKE', '%' . $keyword . '%');
			});
		}

		return $data->get();
    }

    public static function getServiceDetail($lang,$service_id)
    {
      $data =DB::table('services');
        if (!empty($lang)) {
            $data->select('service_name_' . $lang . ' as service_name', 'service_id','groups.group_name_'. $lang);
        }
         $data->leftjoin('groups','groups.group_id','=','services.group_id');
     return $data= $data->where('services.service_id', $service_id)
      ->where('services.status', '!=', 'Deleted')
      ->get()
      ->first();
    }

    public static function updateService($update, $service_id)
    {
        return DB::table('services')
            ->where('service_id', $service_id)
            ->update($update);
    }

    public static function deleteService($service_id)
    {
        return DB::table('services')
            ->where('service_id', $service_id)
            ->update(['status' => 'Deleted']);
    }
     // ------------------------------Payment History---------------------------------------------------------------------

     public static function getAllPaymentHistory()
     {
         $data=DB::table('payment_history')->select('*')->get();
         return $data;
 
     }
 
     public static function addPayment($insert)
     {
         $data=DB::table('payment_history')->insert($insert);
         return $data;
     }
	 
	  public static function getPaymentHistoryBySalonId($salon_id)
    {
        $data = DB::table('payment_history')
        ->where('salon_id', $salon_id)
        ->get();
        return $data;
    } 

    public static function getPaymentHistoryByCustomerId($customer_id)
    {
        $data = DB::table('payment_history')
        ->where('customer_id', $customer_id)
        ->get();
        return $data;
    } 

    public static function getServices($service_id)
    {
        try {
              if(!is_array($service_id)){
                $service_id= [$service_id];
              }
            $result = DB::table('services')
                ->select('service_id','services.service_name_es',
                'services.service_name_en',
                'services.service_name_pl',
                'services.price_en','services.price_es','services.price_pl','status')
                ->whereIn('service_id',$service_id)
                ->get();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public static function getCollectionByPeriod($salonId, $period)
    {
        $query = DB::table('payment_history')->where('salon_id', $salonId);

        if ($period === 'today') {
            $query->whereDate('payment_date', now()->toDateString());
        }elseif ($period === 'week') {
        $query->whereYear('payment_date', now()->year)
            ->where('payment_date', '>=', now()->startOfWeek())
            ->where('payment_date', '<=', now()->endOfWeek());
        }
         elseif ($period === 'month') {
            $query->whereMonth('payment_date', now()->month);
        } elseif ($period === 'year') {
            $query->whereYear('payment_date', now()->year);
        }

        return $query->sum('amount');
    }
	public static function getCollectionByPeriodNew($salonIds, $period)
	{
		$query = DB::table('payment_history')
			->select('salon_id', DB::raw('SUM(amount) as total_amount'))
			->whereIn('salon_id', $salonIds);

		// Apply period filters
		if ($period === 'today') {
			$query->whereDate('payment_date', now()->toDateString());
		} elseif ($period === 'week') {
			$query->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()]);
		} elseif ($period === 'month') {
			$query->whereMonth('payment_date', now()->month)
				  ->whereYear('payment_date', now()->year);
		} elseif ($period === 'year') {
			$query->whereYear('payment_date', now()->year);
		}

		// Group by salon_id and return an associative array
		return $query->groupBy('salon_id')->pluck('total_amount', 'salon_id')->toArray();
	}

    public static function getTotalEmployeesWorking($salonId)
    {
        return DB::table('salon_worker')
            ->where('salon_id', $salonId)
            ->count();
    }
	public static function getTotalEmployeesWorkingNew($salonIds)
    {
        return DB::table('salon_worker')
            ->whereIn('salon_id', $salonIds)
            ->count();
    }

    public static function getTotalBookings($salonId)
    {
        return DB::table('booking')
            ->where('salon_id', $salonId)
            ->count();
    }

    public static function getTodayBooking($salonId)
    {
        return DB::table('booking')
           ->where('salon_id', $salonId)
           ->whereDate('booking_date', now()->toDateString())
           ->count();
    }

    public static function getTotalClients($salonId)
    {
        // dd($salonId);
        return DB::table('payment_history')
        ->where('salon_id',$salonId)
        ->distinct('customer_id')
        ->count();
    }
	
	//-------------------------------SUB-SERVICES----------------------------------------------------- 
    public static function addSubService($subServiceInsert)
    {
        return DB::table('sub_services')
        ->insert($subServiceInsert);
        return DB::getPdo()
        ->lastInsertId();
    }
    
    public static function deleteSubservicesByServiceId($service_id)
    {
        return DB::table('sub_services')
        ->where('service_id',$service_id)
        ->update(['status'=>'Deleted']);
        
    }

    public static function getSubserviceByServiceId($service_id)
    {
        return DB::table('sub_services')
            ->select('sub_services.*') 
            ->where('service_id', $service_id)
            ->where('status','!=','Deleted')
            ->get();
    }

    public static function updateSubServices($update,$id)
    {
        return DB::table('sub_services')
        ->where('id', $id)
        ->where('status','!=','Deleted')
        ->update($update);
        
    }

    public static function getSubServicesById($id=null)
    {
        return DB::table('sub_services')
        ->where('id',$id)
        ->where('status','!=','Deleted')
        ->get()
        ->first();
    }

    public static function getsubServices()
    {
        return DB::table('sub_services')
        ->select('*')
        ->where('status', '!=', 'Deleted')
        ->get();
    }
    
    public static function deleteSubService($id)
    {
         return DB::table('sub_services')
         ->where('id',$id)
         ->update(['status' =>'Deleted']);
    }
    

    public static function getAllSalonsCollection($period)
    {
        $query = DB::table('payment_history');
    
        if ($period === 'today') {
            $query->whereDate('payment_date', now()->toDateString());
        } elseif ($period === 'week') {
            $query->whereYear('payment_date', now()->year)
                ->where('payment_date', '>=', now()->startOfWeek())
                ->where('payment_date', '<=', now()->endOfWeek());
        } elseif ($period === 'month') {
            $query->whereMonth('payment_date', now()->month);
        } elseif ($period === 'year') {
            $query->whereYear('payment_date', now()->year);
        }
    
        return $query->sum('amount');
    }

    public static function getAllSalonsBooking($period)
    {
        $query = DB::table('booking');
    
        if ($period === 'today') {
            $query->whereDate('booking_date', now()->toDateString());
        } elseif ($period === 'week') {
            $query->whereYear('booking_date', now()->year)
                ->where('booking_date', '>=', now()->startOfWeek())
                ->where('booking_date', '<=', now()->endOfWeek());
        } elseif ($period === 'month') {
            $query->whereMonth('booking_date', now()->month);
        } elseif ($period === 'year') {
            $query->whereYear('booking_date', now()->year);
        }
    
        return $query->count();
    }

    public static function getClientDetails($salonId=null,$period)
    {
       $query=DB::table('payment_history')->where('salon_id', $salonId);  
       if($period==='today'){
        $query->whereDate('created_at',now()->toDateString());
       }elseif($period === 'week'){
        $query->whereYear('created_at', now()->year)
        ->where('created_at','>=',now()->startOfWeek())
        ->where('created_at','<=', now()->endOfWeek());
       }elseif($period==='month'){
         $query->whereMonth('created_at', now()->month);
       }elseif($period==='year'){
        $query->whereYear('created_at', now()->year);
       }
       
        return $query->count();
    }
	public static function getClientDetailsNew($salonIds = null, $period)
	{
		$query = DB::table('payment_history')
			->select('salon_id', DB::raw('COUNT(DISTINCT customer_id) as total_clients')) // Count unique clients
			->whereIn('salon_id', $salonIds);

		// Apply period filters
		if ($period === 'today') {
			$query->whereDate('created_at', now()->toDateString());
		} elseif ($period === 'week') {
			$query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
		} elseif ($period === 'month') {
			$query->whereMonth('created_at', now()->month)
				  ->whereYear('created_at', now()->year);
		} elseif ($period === 'year') {
			$query->whereYear('created_at', now()->year);
		}

		// Group by salon_id and return as an array
		return $query->groupBy('salon_id')->pluck('total_clients', 'salon_id')->toArray();
	}
    
    public static function getAllSalonsEmployeesWorking()
    {
        return DB::table('salon_worker')
            ->count();
    }

    public static function getAllSalonsClients()
    {
        return DB::table('payment_history')
            ->distinct('customer_id')
            ->count();
    }

    public static function getBookingDetails($salonId = null, $period)
    {
        $query = DB::table('booking')->where('salon_id', $salonId);
    
        if ($period === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($period === 'week') {
            $query->whereYear('created_at', now()->year)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'month') {
            $query->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month);
        } elseif ($period === 'year') {
            $query->whereYear('created_at', now()->year);
        }
		$query->where('status', 'Active');

        return $query->count();
    }
	public static function getBookingDetailsNew($salonIds = null, $period)
	{
		$query = DB::table('booking')
			->select('salon_id', DB::raw('COUNT(*) as total_bookings'))
			->whereIn('salon_id', $salonIds)
			->where('status', 'Active')
			->groupBy('salon_id');

		if ($period === 'today') {
			$query->whereDate('created_at', now()->toDateString());
		} elseif ($period === 'week') {
			$query->whereYear('created_at', now()->year)
				->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
		} elseif ($period === 'month') {
			$query->whereYear('created_at', now()->year)
				->whereMonth('created_at', now()->month);
		} elseif ($period === 'year') {
			$query->whereYear('created_at', now()->year);
		}

		return $query->pluck('total_bookings', 'salon_id')->toArray();
	}

    public function getAllSalonDetails($user = null, $user_id = null)
    {
		if (($user == 'subadmin') && ($user_id)) {
			$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons);
				return DB::table('salon')->select('salon_id', 'salon_name_en', 'salon_name_es', 'salon_name_pl')
				->where('status', '!=', 'Deleted')
				->whereIn('salon_id', $salon_ids)
				->get();
			} else {
				return DB::table('salon')->select('salon_id', 'salon_name_en', 'salon_name_es', 'salon_name_pl')
				->where('status', '!=', 'Deleted')
				->get();
			}
		} else {
			return DB::table('salon')->select('salon_id', 'salon_name_en', 'salon_name_es', 'salon_name_pl')
			->where('status', '!=', 'Deleted')
			->get();
		}
    }
	
	public static function getBookingRevenue($salonId = null, $period)
    {
        $query = DB::table('booking')->where('salon_id', $salonId);
    
        if ($period === 'today') {
            $query->whereDate('booking_date', now()->toDateString());
        } elseif ($period === 'week') {
            $query->whereYear('booking_date', now()->year)
                ->whereBetween('booking_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'month') {
            $query->whereYear('booking_date', now()->year)
                ->whereMonth('booking_date', now()->month);
        } elseif ($period === 'year') {
            $query->whereYear('booking_date', now()->year);
        }

        $bookingIds = $query->pluck('booking_id');
		$totalRevenue = DB::table('booking_pricing')->whereIn('booking_id', $bookingIds)->sum('total_amount');
		return $totalRevenue;
    }
	public static function getBookingRevenueNew($salonIds = null, $period)
	{
		// Start by querying the booking table to apply salon filters and the period
		$query = DB::table('booking')
			->whereIn('salon_id', $salonIds)
			->join('booking_pricing', 'booking.booking_id', '=', 'booking_pricing.booking_id') // Join with booking_pricing to sum the total amount
			->select('booking.salon_id', DB::raw('SUM(booking_pricing.total_amount) as total_revenue')); // Select the salon_id and the total revenue

		// Apply the period filters
		if ($period === 'today') {
			$query->whereDate('booking.booking_date', now()->toDateString());
		} elseif ($period === 'week') {
			$query->whereYear('booking.booking_date', now()->year)
				  ->whereBetween('booking.booking_date', [now()->startOfWeek(), now()->endOfWeek()]);
		} elseif ($period === 'month') {
			$query->whereYear('booking.booking_date', now()->year)
				  ->whereMonth('booking.booking_date', now()->month);
		} elseif ($period === 'year') {
			$query->whereYear('booking.booking_date', now()->year);
		}

		// Group by salon_id to get the revenue for each salon
		$revenues = $query->groupBy('booking.salon_id')->pluck('total_revenue', 'booking.salon_id')->toArray();

		return $revenues;
	}
	
	public static function getTopSalonsByBookings($salonIds = null)
	{
		$query = DB::table('booking')
			->select(
				'booking.salon_id',
				'salon.salon_name_en as salon_name',
				'salon.salon_thumbnail',
				DB::raw('COUNT(*) as total_completed_bookings')
			)
			->join('salon', 'salon.salon_id', '=', 'booking.salon_id')
			->whereIn('booking.salon_id', $salonIds)
			->where('booking.status', 'Active')
			->where('booking.booking_status', 'completed')
			->groupBy('booking.salon_id', 'salon.salon_name_en', 'salon.salon_thumbnail')
			->orderByDesc(DB::raw('COUNT(*)'))
			->limit(3);
		return $query->get();
	}
}
