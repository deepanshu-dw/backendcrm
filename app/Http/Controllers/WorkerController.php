<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use App\Models\WorkerModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    public function addSalonWorker(Request $request)
    {
        $validator=validator::make($request->all(),[
            'worker_name' => 'required',
            // 'phone'  =>  'required',
            'salon_id' => 'required',
            // 'email' => 'required|email|unique:salon_worker,email'
        ]);
            
        if ($validator->fails()) {
            /* $errors = $validator->errors();
            if ($errors->has('email')) {
                return response()->json(['result' => 0, 'errors' => 'This email is already in use.'], 422);
            } */
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()], 422);
        }

        $admin_id= $request->input('admin_id');

        $profile_image = $request->hasfile('profile_image');

        // if (empty($profile_image)) {
        //     return response()->json(['result' => -1, 'msg' => 'Please add Profile Image']);
        //     return false;
        // } else {
            if (!empty($profile_image)) {
                $profile_image =singleAwsUpload($request, 'profile_image');
            }
        //}
        
        $insert = [
            'worker_name' => $request->post('worker_name'),
            'profile_image' => $profile_image,
            'phone' => $request->post('phone') ?? null,
            'salon_id' =>$request->post('salon_id'),
            'email'=>$request->post('email') ?? null,
			'dob' => $request->post('dob'),
            'worker_address' => $request->post('worker_address'),
            'job_title' => $request->post('job_title'),
            'service_id' => json_encode($request->post('service_id')),
            'employment_status' => $request->post('employment_status'),
            'joining_date'=>$request->post('joining_date')
        ];
        
        $result=WorkerModel::addSalonWorker($insert);
    
        if($result){

            DB::table('activity_logs')->insert([
                'admin_id'   => $admin_id,
                'action'     => 'Salon Worker Created',
                'description'=> "New salon worker created: {$insert['worker_name']}",
                'created_at' => now(),
            ]);
            return response()->json(['result'=>1, 'msg'=> 'Salon Worker Added Successfully', 'data'=>$result]);
        }else{
            return response()->json(['result'=>-1, 'msg'=> 'Salon Worker Not Added Successfully']);
        }
    } 
    public function getAllSalonWorkers(Request $request)
    {
		$user = !empty($request->query('u')) ? $request->query('u') : null;
		$user_id = !empty($request->query('uid')) ? $request->query('uid') : null;
		$keyword = $request->query('keyword');
        $result = WorkerModel::getAllSalonWorkers($user, $user_id, $keyword);

        if($result){
			foreach ($result as $worker) {
              $service_ids = explode(',',str_replace(['[', ']',"\/",'"'," "], '',$worker->service_id));
    
                $service_details = WorkerModel::getServices($service_ids);
                //echo ($service_ids);
                $worker->services = $service_details;
				if (isset($worker->profile_image) && !str_contains($worker->profile_image, 'amazonaws.com')) {
					$worker->profile_image = baseURL($worker->profile_image);
				}
				if (isset($worker->salon_thumbnail) && !str_contains($worker->salon_thumbnail, 'amazonaws.com')) {
					$worker->salon_thumbnail = baseURL($worker->salon_thumbnail);
				}
            }
			/* addBasePath($result,'profile_image');
			addBasePath($result,'salon_thumbnail'); */
          
            return response()->json(['result'=> 1, 'msg' =>'Workers Found', 'data'=>$result]);
        }else{
            return response()->json(['result'=> -1, 'msg' =>'No Worker']);
        }
    }

    public function getSalonWorker($worker_id)
    {
        $result=WorkerModel::getSalonWorker($worker_id);
        if($result){
			$service_ids = !empty($result->service_id)?explode(',',str_replace(['[', ']',"\/",'"'," "], '',@$result->service_id)):"";
			$service_details = WorkerModel::getServices($service_ids);
			$result->services = @$service_details;

			if (isset($result->profile_image) && !str_contains($result->profile_image, 'amazonaws.com')) {
				$result->profile_image = baseURL($result->profile_image);
			}
			if (isset($result->salon_thumbnail) && !str_contains($result->salon_thumbnail, 'amazonaws.com')) {
				$result->salon_thumbnail = baseURL($result->salon_thumbnail);
			}
            /* addBasePath($result,'profile_image',true);
            addBasePath($result,'salon_thumbnail',true); */
            return response()->json(['result' => 1, 'msg' =>'Data found', 'data' =>$result]);
        }else{
            return response()->json(['result' => -1, 'msg' => 'No data found']);
        }
    }

    public function updateSalonWorker(Request $request)
    {
        $Validator=validator::make($request->all(),[
            'worker_name' => 'required',
            // 'phone'  =>  'required',
            'salon_id' => 'required',
            // 'email'=>'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $worker_id = $request->post('worker_id');
        $admin_id = $request->post('admin_id');

        $old = WorkerModel::getSalonWorker($worker_id);
        if ($request->hasfile('profile_image')) {
            $profile_image = singleAwsUpload($request, 'profile_image');
        }else{
            $profile_image = $old->profile_image;
        }  
        $update = [
            'worker_name' => $request->post('worker_name'),
            'profile_image' => $profile_image,
            'phone' => $request->post('phone') ?? null,
            'salon_id' => $request->post('salon_id'),
            'email' => $request->post('email') ?? null,
            'dob' => $request->post('dob'),
            'worker_address' => $request->post('worker_address'),
            'job_title' => $request->post('job_title'),
            'service_id' => json_encode($request->post('service_id')),
            'employment_status' => $request->post('employment_status'),
        ];
        
        $result=WorkerModel::updateSalonWorker($update,$worker_id);
    
        if($result){
            DB::table('activity_logs')->insert([
                'admin_id'   => $admin_id,
                'action'     => 'Salon Worker Created',
                'description'=> "New salon worker created: {$update['worker_name']}",
                'created_at' => now(),
            ]);
            return response()->json(['result'=>1, 'msg'=> 'Salon Worker Updated Successfully', 'data'=>$result]);
        }else{
            return response()->json(['result'=>-1, 'msg'=> 'No Changes Updated']);
        }
    }


    public function deleteSalonWorker(Request $request,$worker_id)
    {
        $admin_id = $request->input('admin_id');
        $worker = DB::table('salon_worker')->where('worker_id', $worker_id)->first();

        if (!$worker) {
            return response()->json(['result' => -1, 'msg' => 'Worker not found']);
        }
        $result=WorkerModel::deleteSalonWorker($worker_id);
        if($result){
            //  WorkerModel::InsertActivityLogs($worker_id);
            DB::table('activity_logs')->insert([
                'admin_id'   => $admin_id,
                'action'     => 'Deleted Salon Worker',
                'description'=> "Deleted salon worker: {$worker->worker_name} (ID: {$worker->worker_id})",
                'created_at' => now(),
            ]);
            return response()->json(['result' =>1, 'msg' => 'Salon Worker Deleted Successfully' ,'data'=>$result]);
        }else{
            return response()->json(['result' =>-1, 'msg' => 'Try Again later']);
        }
    }
    
    
    public function getWorkerBySalon($salon_id)
    {
        $result = WorkerModel::getWorkerBySalon($salon_id);

        if ($result) {
			foreach ($result as $worker) {
              $service_ids = explode(',',str_replace(['[', ']',"\/",'"'," "], '',$worker->service_id));
    
                $service_details = WorkerModel::getServices($service_ids);
                //echo ($service_ids);
                $worker->services = $service_details;
				if (isset($worker->profile_image) && !str_contains($worker->profile_image, 'amazonaws.com')) {
					$worker->profile_image = baseURL($worker->profile_image);
				}
				if (isset($worker->salon_thumbnail) && !str_contains($worker->salon_thumbnail, 'amazonaws.com')) {
					$worker->salon_thumbnail = baseURL($worker->salon_thumbnail);
				}
            }
			/* addBasePath($result,'profile_image');
			addBasePath($result,'salon_thumbnail'); */
            return response()->json(['result' => 1, 'msg' => 'Data found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No data found']);
        }
    }
	
	public function searchWorker(Request $request)
    {
        $keyword = $request->post('keywords');
        $salon_id = $request->post('salon_id');
        // $lang = !empty($request->post('lang')) ? $request->post('lang') : "en";
        $workers = WorkerModel::getWorkerBySalon($salon_id);
        // dd($workers);
        $worker_ids = $workers->map(function ($item) {
            return $item->worker_id;
        });

        $result = WorkerModel::searchWorker($keyword)->whereIn('worker_id', $worker_ids)->reindex();
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Record Found']);
        }
    }
}
