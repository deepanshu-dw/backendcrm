<?php

namespace App\Models;

use AWS\CRT\HTTP\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalonModel extends Model
{
    use HasFactory;
    /**
     * 
     * Defaults Model Settings
     * 
     */
    protected $table = 'salon';

    protected $primaryKey = 'salon_id';

    const CREATED_AT = 'creates_at';
    const UPDATED_AT = 'updated_at';

    public $timestamps = false;

    public static function salonEmailExists($email, $excludeSalonId = null): bool
    {
        // logger()->info('Checking if salon email exists: ' . $email . ', excluding salon ID: ' . $excludeSalonId);
        $query = DB::table('salon')
            ->where('status', 'Active')
            ->whereRaw(
                'LOWER(TRIM(email)) = LOWER(?)',
                [trim($email)]
            );

        if (!empty($excludeSalonId)) {
            $query->where(
                'salon_id',
                '!=',
                $excludeSalonId
            );
        }

        return $query->exists();
    }

    public static function addSalon($insert, $device_type = null)
    {
        $id = DB::table('salon')->insertGetId($insert);

        if (!empty($id)) {
            DB::table('salon_authentication')->insert([
                'salon_token' => generateToken(),
                'salon_id' => $id,
                'device_type' => $device_type,
            ]);
        }

        return $id;
    }

    public static function addSalonCategory(array $insert)
    {
        return DB::table('salon_categories')
            ->insertGetId($insert);
    }

    public static function addSalonServices(array $services)
    {
        if (empty($services)) {
            return true;
        }

        return DB::table('salon_services')
            ->insert($services);
    }

    public static function updateSalon(array $update,$salonId): int {
        return DB::table('salon')
            ->where('salon_id', $salonId)
            ->update($update);
    }

    public static function getSalonDetails($salonId,$lang = null) 
    {
        return DB::table('salon')
            ->where('salon_id', $salonId)
            ->first();
    }

    public static function getAllSalons($paginate = false, $user = null, $user_id = null)
    {
        try {
			if (($user == 'subadmin') && ($user_id)) {
				$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
				if ($manager && !empty($manager->salons)) {
					$salon_ids = json_decode($manager->salons);
					$result = DB::table('salon')->whereIn('salon_id', $salon_ids)->where('status', 'Active')->orderBy("created_at", "desc");
				} else {
					$result = DB::table('salon')->where('status', 'Active')->orderBy("created_at", "desc");
				}
			} else {
				$result = DB::table('salon')->where('status', 'Active')->orderBy("created_at", "desc");
			}

            if ($paginate == 'true') {
                $result = $result->paginate(10);
            } else {
                $result = $result->get();
            }

            return $result;
        } catch (\Exception $e) {
            // An error occurred, rollback the transaction
            DB::rollback();
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public static function deleteSalonCategories(
    $salonId
    ): int {
        return DB::table('salon_categories')
            ->where('salon_id', $salonId)
            ->delete();
    }

    public static function deleteSalonServices(
    $salonId
    ): int {
        return DB::table('salon_services')
            ->where('salon_id', $salonId)
            ->delete();
    }

    public static function getSalonCategories($salonId) {
        return DB::table('salon_categories')
            ->leftJoin(
                'categories',
                'categories.id',
                '=',
                'salon_categories.category_id'
            )
            ->select(
                'salon_categories.salon_category_id',
                'salon_categories.category_id',
                'salon_categories.booking_slots',
                'salon_categories.status',
                'categories.name as category_name',
                'categories.booking_type'
            )
            ->where(
                'salon_categories.salon_id',
                $salonId
            )
            ->where(
                'salon_categories.status',
                'Active'
            )
            ->get();
    }

    public static function getSalonServiceIdsByCategory($salonId,$categoryId) {
        return DB::table('salon_services')
            ->join(
                'services',
                'services.service_id',
                '=',
                'salon_services.service_id'
            )
            ->where(
                'salon_services.salon_id',
                $salonId
            )
            ->where(
                'services.category_id',
                $categoryId
            )
            ->pluck(
                'salon_services.service_id'
            );
    }

    public static function getSalonServiceIds($salonId) {
        return DB::table('salon_services')
            ->where('salon_id', $salonId)
            ->pluck('service_id');
    }


    // firebasetoken releted queries
    public static function checkfirebaseToken($token, $salon_id)
    {
        return DB::table("salon_authentication")
            ->where("firebase_token", $token)
            ->where("salon_id", $salon_id)
            ->get()
            ->first();
    }
    public static function updatefirebaseToken($token, $salon_id, $device_type)
    {
        DB::table("salon_authentication")
            ->where("salon_id", $salon_id)
            ->update([
                "firebase_token" => $token,
                "device_type" => $device_type,
            ]);
        return true;
    }

    public static function deleteFirebaseToken($salon_id)
    {
        DB::table("salon_authentication")
            ->where("salon_id", $salon_id)
            ->update(["firebase_token" => null]);
        return true;
    }

    public static function salonLogin($email, $password)
    {
        try {
            $result = DB::table('salon')
                ->where(function ($query) use ($email) {
                    $query->where('email', $email)->orWhere('user_name', $email);
                })
                ->where('password', $password)
                ->where('status', 'Active')
                ->first();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public static function getSalonByEmailOrUsername($email)
    {
        try {
            $result = DB::table('salon')
                ->where(function ($query) use ($email) {
                    $query->where('email', $email)->orWhere('user_name', $email);
                })
                ->where('status', 'Active')
                ->first();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }



    public static function deleteSalon($salon_id)
    {
        try {
            DB::beginTransaction();
            DB::table('salon')->where('salon_id', $salon_id)->update(['status' => 'Deleted']);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage();
            DB::rollback();
            return false;
        }
    }

    public static function getServices($service_id, $lang = 'en', $is_admin = null)
    {
        try {
            if (!is_array($service_id)) {
                $service_id = [$service_id];
            }

            $query = DB::table('services')
                // ->select('*')
                ->select('service_id', "service_name_$lang as service_name", "service_description_$lang as description", "price_$lang as price", 'service_time_taken', 'is_hair_extension', 'hair_gm', 'hair_per_unit', 'hair_gm_pln', 'hair_gm_dl', 'hair_gm_eu', 'is_archived','service_thumbnail')
                ->whereIn('service_id', $service_id);
            // ->where('status', '!=', 'deleted');
            if ($is_admin == "false") {
				$query->where('is_archived', '=', 'No');
            }
            $result = $query->get();

            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public static function getSubserviceByServiceId($service_id, $lang = null)
    {
        $query = DB::table('sub_services');

        if ($lang) {
            $query->select('name_' . $lang . ' as name', 'service_id', 'price');
        } else {
            $query->select('*');
        }

        $result = $query->where('service_id', $service_id)->get();

        return $result;
    }


    public static function getSlots($slot_id)
    {
        try {
            if (!is_array($slot_id)) {
                $slot_id = [$slot_id];
            }
            $result = DB::table('slots')
                ->select('slot_id', 'slot_name_en as slot_name', 'slot_time')
                ->whereIn('slot_id', $slot_id)
                ->where('status', 'Active')
                ->get();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public static function getTokenBySalonId($salon_id)
    {
        return DB::table('salon_authentication')
            ->select('*')
            ->where('salon_id', $salon_id)
            ->get()->first();
    }

    public static function checkTokenid($salon_id)
    {
        return DB::table('salon_authentication')
            ->where('salon_id', $salon_id)
            ->get()->first();
    }

    public static function insertToken($salon_id, $firebaseToken, $deviceType)
    {
        return DB::table('salon_authentication')->insert([
            'salon_id' => $salon_id,
            'firebase_token' => $firebaseToken,
            'device_type' => $deviceType,
        ]);
    }

    public static function updatefToken($salon_id, $firebaseToken, $device_type)
    {
        return DB::table('salon_authentication')
            ->where('salon_id', $salon_id)
            ->update([
                'firebase_token' => $firebaseToken,
                'device_type' => $device_type,
                'updated_at' => now()
            ]);
    }

    public static function updateData($update, $booking_id)
    {
        DB::table('booking')
            ->select('*')
            ->where('booking_id', $booking_id)
            ->update($update);
        return true;
    }

    public static function getAllBookings($status = null, $salon_id = null, $visit_type = null, $booking_date = null, $keyword = null, $salons_ids = null, $worker_ids = null, $customer_ids = null, $type = null, $month = null, $year = null, $is_paginate = null, $user = null, $user_id = null)
    {
		if (($user == 'subadmin') && ($user_id)) {
			$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons);
				$query = DB::table('booking')
				->select('booking.*', 'agreement.pathToSignature', 'agreement.pathToPDF')
				->leftJoin('agreement', 'booking.booking_id', '=', 'agreement.booking_id')
				->whereIn('salon_id', $salon_ids);
			} else {
				$query = DB::table('booking')
				->select('booking.*', 'agreement.pathToSignature', 'agreement.pathToPDF')
				->leftJoin('agreement', 'booking.booking_id', '=', 'agreement.booking_id');
			}
		} else {		
			$query = DB::table('booking')
			->select('booking.*', 'agreement.pathToSignature', 'agreement.pathToPDF')
			->leftJoin('agreement', 'booking.booking_id', '=', 'agreement.booking_id');
		}

        if (!empty($keyword)) {
            $query->where(function ($query) use ($keyword, $salon_id, $worker_ids, $salons_ids, $customer_ids) {


                if (!empty($customer_ids)) {
                    $query->orWhereIn('booking.customer_id', $customer_ids);
                }

                // Filter by salon_id and worker_id only if keyword is present
                if (!empty($salons_ids)) {
                    $query->orWhereIn('booking.salon_id', $salons_ids);
                }

                if (!empty($worker_ids)) {
                    $query->orWhereIn('worker_id', $worker_ids);
                }
                $query->orWhere('booking_for', 'LIKE',  $keyword . '%');
            });
        }

        if (!empty($status)) {
            $query->where('booking_status', '=', $status);
        }

        if (!empty($visit_type)) {
			$query->where('visit_type', '=', $visit_type);
			/* if ($visit_type == 'website') {
				$query->where('visit_type', '=', $visit_type);
			} else {
				$query->where('visit_type', '!=', 'website');
			} */
        }

        if (!empty($booking_date)) {
            $query->where('booking_date', 'LIKE', $booking_date . '%');
        }

        if (!empty($salon_id)) {
            $query->where('salon_id', '=', $salon_id);
        }

        if (!empty($type)) {
            // $lastMonthStartDate = date('Y-m-d', strtotime('-1 month'));
            $currentDate = date('Y-m-d');
            // $query->whereBetween('booking_date', [$lastMonthStartDate, $currentDate]);
            $query->whereDate('created_at', $currentDate);
        }

        if (!empty($year)) {
            $query->whereYear('booking_date', $year);
        }
        if (!empty($month)) {
            $query->whereMonth('booking_date', $month);
        }

        $query->where('booking.status', '=', 'Active');
        $query->orderBy('booking_date', 'desc');

        if ($is_paginate != 'nopaginate') {
            $data = $query->paginate(10);
        } else {
            $data = $query->get();
        }

        return $data;
    }
	
	public static function getBookingsV2($status = null, $salon_id = null, $visit_type = null, $booking_date = null, $keyword = null, $salons_ids = null, $worker_ids = null, $customer_ids = null, $type = null, $month = null, $year = null, $is_paginate = null, $user = null, $user_id = null)
	{
		$query = DB::table('booking')
			->select(
				'booking.booking_id',
				'booking.booking_status',
                'booking.contact_no',
				'booking.booking_date',
                'booking.booking_time',
				'booking.booking_for',
				'booking.created_at',
				'booking.visit_type',
				'agreement.pathToSignature',
				'agreement.pathToPDF',
				'customers.customer_name',
				'customers.email',
				'customers.phone',
				'salon.salon_name_en',
				'salon.salon_id',
				'salon.salon_thumbnail',
				'salon_worker.worker_name',
				'slots.slot_time'
			)
			->leftJoin('agreement', 'booking.booking_id', '=', 'agreement.booking_id')
			->leftJoin('customers', 'booking.customer_id', '=', 'customers.customer_id')
			->leftJoin('salon', 'booking.salon_id', '=', 'salon.salon_id')
			->leftJoin('salon_worker', 'booking.worker_id', '=', 'salon_worker.worker_id')
			->leftJoinSub(
				DB::table('slots')->select('slot_id', 'slot_time'),
				'slots',
				function ($join) {
					$join->on(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(booking.slots, '$[0]'))"), '=', 'slots.slot_id');
				}
			)
			->where('booking.status', 'Active');

            if (($user == 'subadmin') && ($user_id)) {
			$manager = DB::table('admins')
				->where('status', 'Active')
				->where('admin_id', $user_id)
				->first();

			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons, true);
				if (!empty($salon_ids)) {
					$query->whereIn('booking.salon_id', $salon_ids);
				}
			}
		}

        if (!empty($keyword)) {
			$query->where(function ($query) use ($keyword, $salons_ids, $worker_ids, $customer_ids) {
				if (!empty($customer_ids)) $query->orWhereIn('booking.customer_id', $customer_ids);
				if (!empty($salons_ids)) $query->orWhereIn('booking.salon_id', $salons_ids);
				if (!empty($worker_ids)) $query->orWhereIn('booking.worker_id', $worker_ids);
				$query->orWhere('booking.booking_for', 'LIKE', $keyword . '%');
                $query->orWhere('booking.contact_no', 'LIKE', '%' . $keyword . '%');
			});
		}

        if (!empty($status)) $query->where('booking.booking_status', $status);
		if (!empty($visit_type)) $query->where('booking.visit_type', $visit_type);
		if (!empty($booking_date)) $query->whereDate('booking.booking_date', $booking_date);
		if (!empty($salon_id)) $query->where('booking.salon_id', $salon_id);
		if (!empty($year)) $query->whereYear('booking.booking_date', $year);
		if (!empty($month)) $query->whereMonth('booking.booking_date', $month);

        $query->orderBy('booking.booking_date', 'desc');

        return ($is_paginate != 'nopaginate') ? $query->paginate(10) : $query->get();
	}
	
	public static function getCalenderBookings($salon_id = null, $month = null, $year = null, $is_paginate = null, $booking_date = null)
    {
		$query = DB::table('booking')
			->select(
				'booking.booking_id', 
				'booking.booking_for', 
				'booking.booking_date', 
				'booking.booking_time',
				'booking.booking_status',
				'booking.services',
				'booking.note',
				'booking.salon_id',
				'booking.contact_no',
				'booking.is_double_confirmed',
				'customers.customer_name',
				'customers.email',
				/* 'customers.phone as contact_no', */
				'salon_worker.worker_name',
				'slots.slot_id',
				'slots.slot_time'
			)
		->leftJoin('customers', 'booking.customer_id', '=', 'customers.customer_id')
		->leftJoin('salon_worker', 'booking.worker_id', '=', 'salon_worker.worker_id')
		->leftJoinSub(
			DB::table('slots')->select('slot_id', 'slot_time'),
			'slots',
			function ($join) {
				$join->on(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(booking.slots, '$[0]'))"), '=', 'slots.slot_id');
			}
		);
        if (!empty($salon_id)) {
            $query->where('booking.salon_id', '=', $salon_id);
        }
        if (!empty($year)) {
            $query->whereYear('booking.booking_date', $year);
        }
        if (!empty($month)) {
            $query->whereMonth('booking.booking_date', $month);
        }
        $query->where('booking.status', '=', 'Active');
		if (!empty($booking_date)) {
            $query->where('booking.booking_date', 'LIKE', $booking_date . '%');
        }
        $query->orderBy('booking.booking_date', 'desc');
		$query->orderBy('slots.slot_time', 'asc');
        if ($is_paginate != 'nopaginate') {
            $data = $query->paginate(10);
        } else {
            $data = $query->get();
        }
        return $data;
    }
	
	public static function getCalenderBookingDetails($booking_id = null)
    {
		$query = DB::table('booking')
			->select('*', 'salon_worker.worker_name', 'slots.slot_time')
			->leftJoin('salon_worker', 'booking.worker_id', '=', 'salon_worker.worker_id')
			->leftJoinSub(
				DB::table('slots')->select('slot_id', 'slot_time'),
				'slots',
				function ($join) {
					$join->on(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(booking.slots, '$[0]'))"), '=', 'slots.slot_id');
				}
			);
		$services_taken = [];
		$data = $query->where('booking.booking_id', '=', $booking_id)->where('booking.status', '=', 'Active')->get();
		if ($data && isset($data->services) && !empty($data->services)) {
			$service_ids = json_decode($data->services, true);
			if (!empty($service_ids) && is_array($service_ids)) {
				$valid_service_ids = array_filter($service_ids, function ($id) {
					return is_numeric($id) && $id > 0;
				});
				if (!empty($valid_service_ids)) {
					$services_taken = DB::table('services')
						->whereIn('service_id', $valid_service_ids)
						->pluck('service_name_en')
						->toArray();
				}
			}
		}
		$data->services_taken = $services_taken;
		return $data;
    }

	public static function getUnAssignedBookings($keyword = null)
    {
        return DB::table('booking')
			->select('booking.*', 'salon_worker.status as worker_status')
			->leftJoin('salon_worker', 'booking.worker_id', '=', 'salon_worker.worker_id')
			->when(!empty($keyword), function ($query) use ($keyword) {
				$query->where(function ($q) use ($keyword) {
					$q->orWhere('booking.booking_for', 'LIKE', $keyword . '%');
				});
			})
			->where('salon_worker.status', '!=', 'Active')
			->where('booking.status', '=', 'Active')
			->orderBy('booking.booking_date', 'desc')
			->paginate(10);
    }

    public static function addNote($booking_id, $note)
    {
        return DB::table('booking')->where('booking_id', $booking_id)->update(['note' => $note]);
    }

    public static function getCustomerBookingNotes($customer_id)
    {
        return DB::table('booking')->where('customer_id', $customer_id)->where('status', '!=', 'Deleted')
            ->select(
                'booking_id as bookingId',
                'note',
                'created_at as createdAt'
            )
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function confirm($booking_id, $is_double_confirmed)
    {
        $updateResult = DB::table('booking')
            ->where('booking_id', $booking_id)
            ->update(['is_double_confirmed' => $is_double_confirmed, 'booking_status' => 'accepted']);

        return true;
    }
    public static function getWorkerByName($keyword)
    {
        return DB::table('salon_worker')->select('worker_id')->where('worker_name', 'like', '%' . $keyword . '%')->get();
    }

    public static function getSalonByName($keyword)
    {
        return DB::table('salon')
            ->select('salon_id')
            ->where(function ($query) use ($keyword) {
                $query->where('salon_name_en', 'like', '%' . $keyword . '%')
                    ->orWhere('salon_name_es', 'like', '%' . $keyword . '%')
                    ->orWhere('salon_name_pl', 'like', '%' . $keyword . '%');
            })
            ->get();
    }
    public static function getPhoneByName($keyword)
    {
        return DB::table('customers')
            ->select('customer_id')
            ->where('phone', 'like', '%' . $keyword . '%')
            ->get();
    }

    public static function deleteBooking($booking_id)
    {
        return DB::table('booking')
            ->where('booking_id', $booking_id)
            ->update(['status' => 'Deleted']);
    }
	
	public static function getTotalBookings($customer_id)
	{
		try {
		$bookings = DB::table('booking')->select('booking_id')->where('status', 'Active')->where('customer_id', $customer_id)->get();
		$total_bookings = !is_int($bookings) ? count($bookings) : 0;
		$last_booking = DB::table('booking')->select('services')->where('status', 'Active')->where('customer_id', $customer_id)->orderBy('booking_id', 'desc')->first();
		$services_taken = [];
		if ($last_booking && !empty($last_booking->services)) {
			$service_ids = json_decode($last_booking->services, true);
			if (!empty($service_ids) && is_array($service_ids)) {
				// Filter out invalid IDs from the service_ids array
				$valid_service_ids = array_filter($service_ids, function ($id) {
					return is_numeric($id) && $id > 0; // Check if the ID is a positive numeric value
				});

				// If there are valid IDs, query the database
				if (!empty($valid_service_ids)) {
					$services_taken = DB::table('services')
						->whereIn('service_id', $valid_service_ids)
						->pluck('service_name_en')
						->toArray();
				} else {
					$services_taken = []; // Default to an empty array if no valid IDs
				}
			} else {
				$services_taken = []; // Default to an empty array if service_ids is invalid
			}
		}
		return [
			'total_bookings' => $total_bookings,
			'services_taken' => $services_taken,
		];
		} catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Error in getTotalBookings: ' . $e->getMessage());

        // Return default values in case of error
        return [
            'total_bookings' => 0,
            'services_taken' => [],
        ];
    }
	}
}
