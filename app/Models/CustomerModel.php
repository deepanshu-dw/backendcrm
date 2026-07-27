<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\SalonModel;

class CustomerModel extends Model
{
    use HasFactory;

    public static function getAllCustomer($keyword = null, $customer_id = null)
    {
        $query = DB::table('customers');
        $result = $query
            ->select('customers.*');
        // ->leftJoin('agreement', 'customers.mongo_id', '=', 'agreement.mongo_customer_id');

        if (!empty($keyword)) {
            $result->where(function ($query) use ($keyword) {
                $query->orWhere('email', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('phone', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('surname', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('customer_name', 'LIKE', '%' . $keyword . '%')
					->orWhere(DB::raw("CONCAT(customer_name, ' ', surname)"), 'LIKE', '%' . $keyword . '%');
            });
        }

        if (!empty($customer_id)) {
            $result->where('customer_id', $customer_id);
        }
        $result = $result->where('customers.status', '!=', 'Deleted')
            ->paginate(100);

        return $result;
    }
	
	public static function getAllCustomerDocuments($keyword = null)
	{
		$query = DB::table('customers')
			->join('booking', 'customers.customer_id', '=', 'booking.customer_id')
			->join('agreement_documents', 'booking.booking_id', '=', 'agreement_documents.booking_id')
			->whereNotNull('agreement_documents.contract_file')
			->where('agreement_documents.contract_file', '!=', '')
			->where('customers.status', '!=', 'Deleted')
			->select('customers.*','booking.contact_no');
			// ->distinct(); // Ensures unique customers are selected

		if (!empty($keyword)) {
			$query->where(function ($q) use ($keyword) {
				$q->orWhere('customers.email', 'LIKE', "%{$keyword}%")
				  ->orWhere('customers.phone', 'LIKE', "%{$keyword}%")
				  ->orWhere('customers.surname', 'LIKE', "%{$keyword}%")
				  ->orWhere('customers.customer_name', 'LIKE', "%{$keyword}%")
                  ->orWhere('booking.contact_no', 'LIKE', "%{$keyword}%")
				  ->orWhere(DB::raw("CONCAT(customers.customer_name, ' ', customers.surname)"), 'LIKE', "%{$keyword}%");
			});
		}

		return $query->paginate(10);
	}
	
	/* public static function getAllCustomer($keyword = null, $customer_id = null)
    {
        $query = DB::table('customers')
			->select(
				'customers.*', 
				'booking.booking_id',  // Only selecting booking_id from booking table
				'agreement_documents.document_file', 
				'agreement_documents.contract_file', 
				'agreement.pathToPDF', 
				'agreement.pathToSignature'
			)
			->leftJoin('booking', 'customers.customer_id', '=', 'booking.customer_id')  // Join booking table
			->leftJoin('agreement_documents', 'booking.booking_id', '=', 'agreement_documents.booking_id')  // Join agreement_documents table
			->leftJoin('agreement', 'customers.mongo_id', '=', 'agreement.mongo_customer_id');  // Join agreement table

		if (!empty($keyword)) {
			$query->where(function ($query) use ($keyword) {
				$query->orWhere('customers.email', 'LIKE', '%' . $keyword . '%')
					->orWhere('customers.phone', 'LIKE', '%' . $keyword . '%')
					->orWhere('customers.surname', 'LIKE', '%' . $keyword . '%')
					->orWhere('customers.customer_name', 'LIKE', '%' . $keyword . '%')
					->orWhere(DB::raw("CONCAT(customers.customer_name, ' ', customers.surname)"), 'LIKE', '%' . $keyword . '%');
			});
		}

		if (!empty($customer_id)) {
			$query->where('customers.customer_id', $customer_id);
		}

		$result = $query->where('customers.status', '!=', 'Deleted')
			->paginate(100);

		return $result;
    } */

    public static function addCustomer($insert)
    {
        DB::table('customers')->insert($insert);
        return DB::getPdo()->lastInsertId();
    }
    public static function getCustomerDetail($customer_id)
    {
        return DB::table('customers')
            ->where('customer_id', $customer_id)
            ->where('status', '!=', 'Deleted')
            ->get()->first();
    }

    public static function updateCustomer($update, $customer_id)
    {
        return DB::table('customers')
            ->where('customer_id', $customer_id)
            ->update($update);
    }

    public static function deleteCustomer($customer_id)
    {
        return DB::table('customers')
            ->where('customer_id', $customer_id)
            ->update(['status' => 'Deleted']);
    }

    public static function searchCustomer($keyword)
    {
        return  DB::table('customers')
            ->select('customer_name', 'phone', 'email','surname', 'customer_id')
            ->where(function ($query) use ($keyword) {
                $query->where('customer_name', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhere('surname', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
                // Add more conditions if needed
            })->get();
    }
    // public static function searchCustomerByEmail($email){
    //     return DB::table('customers')
    //         ->select('*')
    //         ->where('email',$email)->get()->first();        
    // }

    public static function searchCustomerByEmail($email)
    {
        return DB::table('customers')
            ->select('customers.*', 'booking.*')
            ->leftJoin('booking', 'customers.customer_id', '=', 'booking.customer_id')
            ->where('customers.email', $email)
            ->where('booking.booking_status', '=', 'completed')
            ->where('customers.status', '!=', 'Deleted')
            ->get()->first();
    }
    public static function searchCustomerByEmailV2($email)
    {
        return DB::table('customers')
            ->select('customers.*', 'booking.*')
            ->leftJoin('booking', 'customers.customer_id', '=', 'booking.customer_id')
            ->where('customers.email', $email)
            ->where('booking.booking_status', '=', 'completed')
            ->where('customers.status', '!=', 'Deleted')
            ->get();
    }
    public static function searchCustomerByEmailName($email)
    {
        return DB::table('customers')
            ->select('*')
            ->where('email', 'LIKE', '%' . $email . '%')
            ->where('status', '!=', 'Deleted')
            ->get();
    }


    public static function searchCustomerByPhone($phone)
    {
        return DB::table('customers')
			->select('*')
			->where('phone', 'LIKE', '%' . $phone . '%')
            ->where('status', '!=', 'Deleted')
            ->get();
    }

    public static function searchCustomerBySurname($surname)
    {
        return DB::table('booking')
            //     ->select('*')
            //     ->where('surname', 'LIKE', '%' . $surname . '%')
            //     ->where('status', '!=', 'Deleted')
            //     ->get();
            ->select('booking.*', 'customers.*')
            ->join('customers', 'booking.customer_id', '=', 'customers.customer_id')
            ->where('customers.surname', 'LIKE', '%' . $surname . '%')
            ->where('booking.status', '!=', 'Deleted')
            ->get();
    }

    public static function searchCustomerByName($searchTerm)
    {
        $result = DB::table('booking')
            ->select('booking.*', 'customers.*')
            ->join('customers', 'booking.customer_id', '=', 'customers.customer_id')
            ->where('booking.booking_for', 'LIKE', "%$searchTerm%")
            ->where('booking.status', '!=', 'Deleted')
            ->get();

        return $result;
    }



    // public static function searchCustomerByName($name)
    // {
    //     return DB::table('customers')->select('*')->where('customer_name', $name)
    //     ->where('status','!=','Deleted')
    //     ->get()
    //     ->first();
    // }       

    public static function getCustomerHistoryBySalon($salon_id, $lang, $customer_id = null)
    {
        $data = DB::table('booking')
            ->where('salon_id', $salon_id);
        if (!empty($customer_id)) {
            $data->where('customer_id', $customer_id);
        }
        $data->where('booking_status', '=', 'completed');
        $data->where('status', '=', 'Active');
        return $data = $data->get();
    }

    public static function getBookings($customer_id)
    {
        $data = DB::table('booking')
            ->select('*')
            ->where('customer_id', $customer_id)
            ->where('status', '=', 'Active')
            ->where('booking_status', '=', 'completed')
            ->get();

        return $data;
    }
    public static function getBookingsCustomerID($customer_id)
    {

        $data = DB::table('booking')
            ->select('booking.*', 'customers.*')
            ->join('customers', 'booking.customer_id', '=', 'customers.customer_id')
            ->whereIn('booking.customer_id', $customer_id)
           // ->where('booking.status', '=', 'Active')
            /* ->where('booking.booking_status', '=', 'completed') */
            ->get();

        return $data;
    }


    public static function getCustomerHistory($customer_id, $salon_id = null)
    {
        $query = DB::table('booking')
            ->select('booking.*', 'salon.salon_name_en', 'salon.salon_name_pl', 'salon.salon_name_es')
            ->leftJoin('salon', 'salon.salon_id', '=', 'booking.salon_id')
            ->leftJoin('customers', 'customers.customer_id', '=', 'booking.customer_id')
            ->where('booking.customer_id', $customer_id)
			->where('booking.booking_status', 'completed')
			->orderBy('booking.booking_date', 'desc');

        if ($salon_id !== null) {
            $query->where('booking.salon_id', $salon_id);
        }
        $records = $query->get();
        if (($records->isNotEmpty())) {
            foreach ($records as $bookings) {
                if (!empty($bookings)) {
                    if (empty($bookings->services)) {
                        $bookings->servicedetails  = [];
                    } else {
                        $bookings->servicedetails = SalonModel::getServices(json_decode($bookings->services), 'en', 'false');
						if (!empty($bookings->servicedetails)) {
							foreach ($bookings->servicedetails as $value) {
								$booking_pricing = select('booking_pricing', 'hair_gm', ['service_id' => $value->service_id])->first();
								$value->hair_gm = !empty($booking_pricing->hair_gm) ? $booking_pricing->hair_gm : null; 
							}
						}
                    }
                    if (empty($bookings->slots)) {
                        $bookings->slotsdetails = [];
                    } else {
                        $bookings->slotsdetails = SalonModel::getSlots(json_decode($bookings->slots));
                    }
					if (empty($bookings->worker_id)) {
                        $bookings->worker_name  = null;
                    } else {
                        $bookings->worker_name = select('salon_worker', '*', [['status', '!=', 'Deleted'], ['worker_id', '=', $bookings->worker_id]])->first();
                    }
					$total_amount = select('booking_pricing', '*', [['status', '!=', 'Deleted'], ['booking_id', '=', $bookings->booking_id]])->first();
					if (!empty($total_amount->total_amount)) {
						$bookings->total_amount  = $total_amount->total_amount;
					} else {
						$bookings->total_amount  = null;
					}
					if (!empty($total_amount->hair_gm)) {
						$bookings->hair_gm  = $total_amount->hair_gm;
					} else {
						$bookings->hair_gm  = null;
					}
                }
            }
        }
		
        $count = $query->count();
		
		$latestVisit = $query->latest('booking_date')->first();

        return ['Last_Visit' => @$latestVisit->booking_date, 'Visit' => $count, 'records' => $records];
    }


    public static function getAllServices($lang, $service_ids)
    {
        $data = DB::table('services');
        if (!empty($lang)) {
            $data->select('service_id', 'service_name_' . $lang . ' as service_name', "price_$lang as price", 'is_hair_extension', 'hair_gm', 'hair_per_unit');
        }
        $data->whereIn('service_id', $service_ids);
        return $data->get();
    }

    public static function getAllSlotDetails($lang, $slot_id)
    {
        $data = DB::table('slots');
        if (!empty($lang)) {
            $data->select('slot_name_' . $lang . ' as slot_name', 'slot_time', 'slot_id');
        }
        $data->whereIn('slot_id', $slot_id);
        return $data = $data->get();
    }

    public static function getTokenByCustomerId($customer_id)
    {
        return DB::table('customers_authentication')
            ->select('*')
            ->where('customer_id', $customer_id)
            ->get()->first();
    }


    public static function checkFireToken($customer_id)
    {
        return DB::table('customers_authentication')
            ->where('customer_id', $customer_id)
            ->get()->first();
    }

    public static function insertFireToken($customer_id, $firebaseToken, $deviceType)
    {
        return DB::table('customers_authentication')->insert([
            'customer_id' => $customer_id,
            'firebase_token' => $firebaseToken,
            'device_type' => $deviceType,
        ]);
    }

    public static function updateFireToken($customer_id, $firebaseToken, $device_type)
    {
        return DB::table('customers_authentication')
            ->where('customer_id', $customer_id)
            ->update([
                'firebase_token' => $firebaseToken,
                'device_type' => $device_type,
                'updated_at' => now()
            ]);
    }
}
