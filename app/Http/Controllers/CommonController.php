<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\CommonModel;
use Illuminate\Support\Facades\DB;

class CommonController extends Controller
{
    public function dashbord(Request $request)
    {
		$today = date('Y-m-d 00:00:00');
		$user = !empty($request->query('u')) ? $request->query('u') : null;
		$user_id = !empty($request->query('uid')) ? $request->query('uid') : null;
		if (($user == 'subadmin') && ($user_id)) {
			$data['customers'] = select('customers', ['customer_id'], [['status', '=', 'Active']])->count();
			$data['todays_customers'] = DB::table('customers')->where('created_at', 'LIKE', date('Y-m-d') . '%')->where('status', 'Active')->count();
			$manager = DB::table('admins')->where('status', 'Active')->where('admin_id', $user_id)->first();
			if ($manager && !empty($manager->salons)) {
				$salon_ids = json_decode($manager->salons);
				$data['booking'] = DB::table('booking')->select('booking_id')->where('status', 'Active')->whereIn('salon_id', $salon_ids)->count();
				$data['completed_booking'] = DB::table('booking')->select('booking_id')->where('booking_status', 'completed')->where('status', 'Active')->whereIn('salon_id', $salon_ids)->count();
				$data['todays_booking'] = DB::table('booking')->select('booking_id')->where('created_at', 'LIKE', date('Y-m-d') . '%')->where('status', 'Active')->whereIn('salon_id', $salon_ids)->count();
				$data['salon'] = DB::table('salon')->select('salon_id')->where('status', 'Active')->whereIn('salon_id', $salon_ids)->count();
				$data['salon_worker'] = DB::table('salon_worker')->leftJoin('salon','salon.salon_id','=','salon_worker.salon_id')->where('salon_worker.status', '!=', 'Deleted')->whereIn('salon_worker.salon_id', $salon_ids)->count();
			} else {
				$data['booking'] = select('booking', ['booking_id'], [['status', '=', 'Active']])->count();
				$data['completed_booking'] = select('booking', ['booking_id'], [['booking_status', '=', 'completed'], ['status', '=', 'Active']])->count();
				$data['todays_booking'] = DB::table('booking')->select('booking_id')->where('created_at', 'LIKE', date('Y-m-d') . '%')->where('status', 'Active')->count();
				$data['salon'] = select('salon', ['salon_id'], [['status', '=', 'Active']])->count();
				$data['salon_worker'] = select('salon_worker', ['worker_id'], [['status', '=', 'Active']])->count();
			}
			$pricings = select('booking_pricing', ['total_amount'], [['status', '=', 'Active'],['created_at', '>=', '2024-05-01']]);
			$data['total_revenue'] = $pricings->isNotEmpty() ? $pricings->sum(fn ($pricing) => (float) $pricing->total_amount) : 0;
		} else {
			$data['customers'] = select('customers', ['customer_id'], [['status', '=', 'Active']])->count();
			$data['todays_customers'] = DB::table('customers')->where('created_at', 'LIKE', date('Y-m-d') . '%')->where('status', 'Active')->count();
			$data['booking'] = select('booking', ['booking_id'], [['status', '=', 'Active']])->count();
			$data['completed_booking'] = select('booking', ['booking_id'], [['booking_status', '=', 'completed'], ['status', '=', 'Active']])->count();
			$data['todays_booking'] = DB::table('booking')->select('booking_id')->where('created_at', 'LIKE', date('Y-m-d') . '%')->where('status', 'Active')->count();
			$data['salon'] = select('salon', ['salon_id'], [['status', '=', 'Active']])->count();
			$data['salon_worker'] = select('salon_worker', ['worker_id'], [['status', '=', 'Active']])->count();
			$pricings = select('booking_pricing', ['total_amount'], [['status', '=', 'Active'],['created_at', '>=', '2024-05-01']]);
			$data['total_revenue'] = $pricings->isNotEmpty() ? $pricings->sum(fn ($pricing) => (float) $pricing->total_amount) : 0;
		}
        return response()->json(['result' => 1, 'msg' => "data found", 'data' => $data]);
    }

    public function getSettings($type = null)
    {
        if (empty($type)) {
            $data = DB::table('settings')->get();
        } else {
            $data = DB::table('settings')->where('type', $type)->first();
        }
        return response()->json(['result' => 1, 'msg' => "data found", 'data' => $data]);
    }

    public function updateSiteSetting(Request $request)
    {
        $update_data = [
            'description' =>  $request->post('description'),
        ];
        $key = $request->post('type');
        $title = $key;
        $result = DB::table('settings')->where('type', $key)->update($update_data);
        if ($result) {
            return response()->json(['result' => 1,  'msg' => $title . " updated successfully"]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No changes were found!']);
        }
    }

    public function getAllCurrencies()
    {
        $result = CommonModel::getAllCurrencies();

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    }

    //----------------------------------------------Banner Apis----------------------------------------------------------
    public function addBanner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banner_image' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
        }

        if (!$request->hasFile('banner_image')) {
            return response()->json(['result' => -1, 'msg' => 'Please add Banner Image']);
        } else {
            $bannerImage = singleAwsUpload($request, 'banner_image');

            if (!$bannerImage) {
                return response()->json(['result' => -1, 'msg' => 'Failed to upload image']);
            }
        }

        $result = CommonModel::addBanner(['banner_image' => $bannerImage]);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Banner logo created successfully', 'data' => $result], 201);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Banner Not Added. Try Again Later']);
        }
    }

    public function updateBanner(Request $request, $banner_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'banner_image' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            if (!$request->hasFile('banner_image')) {
                return response()->json(['result' => -1, 'msg' => 'Please add Banner Image']);
            } else {
                $updateBanner = singleAwsUpload($request, 'banner_image');

                if (!$updateBanner) {
                    return response()->json(['result' => -1, 'msg' => 'Failed to upload image']);
                }
            }

            $result = CommonModel::updateBanner(['banner_image' => $updateBanner], $banner_id);

            if ($result) {
                return response()->json(['result' => 1, 'msg' => 'Banner logo Updated successfully', 'data' => $result], 201);
            } else {
                return response()->json(['result' => -1, 'msg' => 'No Changes Updated , Try Again Later']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public function getAllBanner()
    {
        $result = CommonModel::getAllBanner();

        if ($result->isNotEmpty()) {
            foreach ($result as $row) {
				if (isset($row->banner_image) && !str_contains($row->banner_image, 'amazonaws.com')) {
					$row->banner_image = baseURL($row->banner_image);
				}
            }
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    }

    public function getBannerById($banner_id)
    {
        $result = CommonModel::getBannerById($banner_id);

        if ($result) {
			if (isset($result->banner_image) && !str_contains($result->banner_image, 'amazonaws.com')) {
				$result->banner_image = baseURL($result->banner_image);
			}

            return response()->json(['result' => 1, 'msg' => 'Banner Found', 'data' => $result]);
        } else {
            return response()->json(['result' => 1, 'msg' => 'No Banner Found']);
        }
    }

    public function deleteBanner($banner_id)
    {
        $result = CommonModel::deleteBanner($banner_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Banner Deleted Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Banner Not Deleted, Try After Sometime']);
        }
    }


  public function uploadFile(Request $request)
{
    if (!$request->hasFile('file')) {
        return response()->json([
            'result' => -1,
            'msg' => 'file key is missing'
        ]);
    }

    try {
        $upload = AwsSingleUpload(
            $request->file('file'),
            'images/uploads'
        );

        return response()->json([
            'result' => 1,
            'msg' => 'Upload successful',
            'data' => $upload
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'result' => -1,
            'msg' => $e->getMessage()
        ]);
    }
}



    public function getActivityLogs()
    {
        $result = CommonModel::getActivityLogs();
        if($result){
            return response()->json(['result'=>1, 'msg' => 'Activity Logs feteched Successfully', 'data' =>$result]);
        }else{
            return response()->json(['result' =>-1, 'msg'=> 'No Data found']);
        }
    }
}
