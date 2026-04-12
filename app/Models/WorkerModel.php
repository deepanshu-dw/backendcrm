<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WorkerModel extends Model
{
    use HasFactory;
    public static function addSalonWorker($insert)
    {
        DB::table('salon_worker')
            ->insert($insert);
        return DB::getPdo()
            ->lastInsertId();
    }

    public static function getAllSalonWorkers($user = null, $user_id = null, $keyword = null)
    {
		$query = DB::table('salon_worker')
			->select('salon_worker.*', 'salon.salon_name_en')
			->leftJoin('salon', 'salon.salon_id', '=', 'salon_worker.salon_id')
			->where('salon_worker.status', '!=', 'Deleted');

		if (!empty($keyword)) {
			$query->where(function ($q) use ($keyword) {
				$q->orWhere('salon.salon_name_en', 'LIKE', '%' . $keyword . '%')
				  ->orWhere('salon_worker.phone', 'LIKE', '%' . $keyword . '%')
				  ->orWhere('salon_worker.worker_name', 'LIKE', '%' . $keyword . '%');
			});
		}

		if (($user == 'subadmin') && ($user_id)) {
			$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons);
				$query->whereIn('salon_worker.salon_id', $salon_ids);
			}
		}

		return $query->get();
    }

    public static function getSalonWorker($worker_id)
    {
        return DB::table('salon_worker')
             ->select('salon_worker.*','salon.salon_id','salon.salon_name_en','salon.salon_name_es',
             'salon_name_pl','salon.salon_address_es','salon.salon_address_en','salon.salon_address_pl',
             'salon.user_name','salon.phone_no','salon.opening_time','salon.closing_time')
            ->leftJoin('salon', 'salon.salon_id', '=', 'salon_worker.salon_id')
            ->where('salon_worker.worker_id', $worker_id)
            ->where('salon_worker.status', '!=', 'Deleted')
            ->get()
            ->first();
    }

    public static function updateSalonWorker($update, $worker_id)
    {
        return DB::table('salon_worker')
            ->where('worker_id', $worker_id)
            ->update($update);
    }

    public static function deleteSalonWorker($worker_id)
    {
        return DB::table('salon_worker')
            ->where('worker_id', $worker_id)
            ->update(['status' => 'Deleted']);
    }

    public static function getWorkerBySalon($salon_id)
    {
        return DB::table('salon_worker')
            ->leftJoin('salon','salon.salon_id','=','salon_worker.salon_id')
            ->where('salon_worker.salon_id', $salon_id)
            ->where('salon_worker.status', '!=', 'Deleted')
            ->get();
        
    }
    
    public static function searchWorker($keyword)
    {
        return DB::table('salon_worker')
            ->select('worker_name', 'phone', 'email', 'worker_id')
            ->where(function ($query) use ($keyword) {
                $query->where('worker_name',$keyword)
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhere('email', $keyword);
            })
            ->get();
    }
    
    public static function getServices($service_id)
    {
        try {
              if(!is_array($service_id)){
                $service_id= [$service_id];
              }
            $result = DB::table('services')
                ->select('service_id','service_name_en as service_name')
                ->whereIn('service_id',$service_id)
                ->get();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
}
