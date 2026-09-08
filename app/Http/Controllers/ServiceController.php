<?php

namespace App\Http\Controllers;

use App\Models\ServiceModel;
use App\Models\CategoryModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ServiceController extends Controller
{

  	public function addService(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'category_id' => 'required|integer|min:1',

			'service_name_en' => 'required|string|max:100',
			'service_description_en' => 'required|string|max:500',

			'customer_name_en' => 'required|string|max:150',
			'customer_name_es' => 'required|string|max:150',
			'customer_name_pl' => 'required|string|max:150',

			'price_type' => 'required|in:fixed,from,hair_weight',
			'price_note' => 'nullable|string|max:150',

			'price_en' => 'required|numeric|min:0',
			'price_es' => 'required|numeric|min:0',
			'price_pl' => 'required|numeric|min:0',

			'service_time_taken' => 'required|integer|min:1',
			'is_archived' => 'sometimes|string|in:Yes,No'
		], [
			'category_id.required' => 'Atleast one Category must be selected.',
			'category_id.integer' => 'Category must be a valid number.',
			'category_id.min' => 'Category must be greater than 0.',

			// Service
			'service_name_en.required' => 'Service Name is required.',
			'service_name_en.string' => 'Service Name must be a string.',
			'service_name_en.max' => 'Service Name is too long',

			'service_description_en.required' => 'Service Description is required.',
			'service_description_en.string' => 'Service Description must be a string.',
			'service_description_en.max' => 'Service Description is too long.',

			// Customer Name - English
			'customer_name_en.required' => 'Customer Name (English) is required.',
			'customer_name_en.string' => 'Customer Name (English) must be a string.',
			'customer_name_en.max' => 'Customer Name (English) is too long.',

			// Customer Name - Spanish
			'customer_name_es.required' => 'Customer Name (Spanish) is required.',
			'customer_name_es.string' => 'Customer Name (Spanish) must be a string.',
			'customer_name_es.max' => 'Customer Name (Spanish) is too long.',

			// Customer Name - Polish
			'customer_name_pl.required' => 'Customer Name (Polish) is required.',
			'customer_name_pl.string' => 'Customer Name (Polish) must be a string.',
			'customer_name_pl.max' => 'Customer Name (Polish) is too long',

			// Price Type
			'price_type.required' => 'Price Type is required.',
			'price_type.in' => 'Invalid Price Type. Allowed values are fixed, from, or hair_weight.',

			// Price Note
			'price_note.string' => 'Price Note must be a string.',
			'price_note.max' => 'Price Note is too long',

			// Prices
			'price_en.required' => 'Price (English) is required.',
			'price_en.numeric' => 'Price (English) must be a valid number.',
			'price_en.min' => 'Price (English) cannot be less than 0.',

			'price_es.required' => 'Price (Spanish) is required.',
			'price_es.numeric' => 'Price (Spanish) must be a valid number.',
			'price_es.min' => 'Price (Spanish) cannot be less than 0.',

			'price_pl.required' => 'Price (Polish) is required.',
			'price_pl.numeric' => 'Price (Polish) must be a valid number.',
			'price_pl.min' => 'Price (Polish) cannot be less than 0.',

			// Service Time
			'service_time_taken.required' => 'Service Time Taken is required.',
			'service_time_taken.integer' => 'Service Time Taken must be a valid integer.',
			'service_time_taken.min' => 'Service Time Taken must be at least 1 minute.',

			// Archived
			'is_archived.string' => 'Archive status must be a string.',
			'is_archived.in' => 'Invalid archive status. Allowed values are Yes or No.',
		]);

		if ($validator->fails()) {
            return response()->json([
                "result" => 0,
                "errors" => $validator->errors()->first(),
            ]);
        }

		$serviceName = trim($request->input('service_name_en'));

		if (ServiceModel::serviceNameExists($serviceName)) {
			return response()->json([
				'result' => 0,
				'msg' => "This Service already exists. Please enter a unique service name.",
			]);
		}

		$categoryId = (int) $request->input('category_id');

		/*
		* Validate that the category exists.
		*/
		$category = CategoryModel::getCategoryById($categoryId);

		if (!$category) {
			return response()->json([
				'result' => 0,
				'msg' => 'Please provide a valid category.',
			]);
		}

		if ($category->status !== 'Active') {
			return response()->json([
				'result' => 0,
				'msg' => 'Please provide an active category.',
			]);
		}

		$service_thumbnail = null;

		if (!empty($request->hasfile('service_thumbnail'))) {
			$service_thumbnail = singleAwsUpload($request,'service_thumbnail');
		}

		$insert = [
			'category_id' => $categoryId,
			'service_name_en' => $serviceName,
			'service_description_en' => $request->input('service_description_en'),

			'customer_name_en' => trim($request->input('customer_name_en')),
			'customer_name_es' => trim($request->input('customer_name_es')),
			'customer_name_pl' => trim($request->input('customer_name_pl')),

			'price_type' => $request->input('price_type'),
			'price_note' => $request->input('price_note'),

			'price_en' => (float) $request->input('price_en'),
			'price_es' => (float) $request->input('price_es'),
			'price_pl' => (float) $request->input('price_pl'),

			'service_time_taken' => (int) $request->input('service_time_taken'),

			'is_archived' => $request->input('is_archived', 'No'),
			'service_thumbnail' => $service_thumbnail,
			'status' => 'Active',
			'created_at' => now(),
			'updated_at' => now(),
		];

		$serviceId = ServiceModel::addService($insert);

		if (!$serviceId) {
			return response()->json([
				'result' => -1,
				'msg' => 'Service not added.',
			]);
		}

		$adminId = $request->input('admin_id');

		if ($adminId) {
			DB::table('activity_logs')->insert([
				'admin_id' => $adminId,
				'action' => 'Service Added',
				'description' =>
					"New service created: {$insert['service_name_en']}",
				'created_at' => now(),
			]);
		}

		return response()->json([
			'result' => 1,
			'msg' => 'Service added successfully.',
			'data' => [
				'service_id' => (int) $serviceId,
				'category_id' => $categoryId,
			],
		]);
	}

	public function getAllServices(Request $request, $lang)
	{
		$user = !empty($request->query('u')) ? $request->query('u') : null;
		$user_id = !empty($request->query('uid')) ? $request->query('uid') : null;
		$is_admin = $request->input('is_admin', "true");
		$keyword = $request->query('keyword');
		$result=ServiceModel::getAllServices($lang, $is_admin, $user, $user_id, $keyword);
		if ($result) {
			foreach ($result as $row) {
				$row->subservices = ServiceModel::getSubserviceByServiceId($row->service_id);
			}
			return response()->json(['result'=> 1,'msg' => 'Data Found','data'=>$result]);
		}else{
			return response()->json(['result'=> -1,'msg' => ' No data Found']);
		}
	}

	public function getServiceDetail($lang=null, $service_id)
	{
		$result = ServiceModel::getServiceDetail($lang, $service_id);

		if ($result) {
			$result->subservices = ServiceModel::getSubserviceByServiceId($result->service_id);

			return response()->json(['result' => 1, 'msg' => 'Data found', 'data' => $result]);
		} else {
			return response()->json(['result' => -1, 'msg' => 'No data Found']);
		}
	}

	public function updateService(Request $request, $serviceId)
	{
		if (!is_numeric($serviceId) || (int) $serviceId <= 0) {
			return response()->json([
				'result' => 0,
				'msg' => 'Please provide a valid service ID.',
			]);
		}

		$serviceId = (int) $serviceId;

		$service = ServiceModel::getServiceById($serviceId);

		if (!$service) {
			return response()->json([
				'result' => -1,
				'msg' => 'Service not found.',
			]);
		}

		/*
		* Only active services can be updated.
		*/
		if ($service->status !== 'Active') {
			return response()->json([
				'result' => -1,
				'msg' => 'Only active services can be updated.',
			]);
		}

		$validator = Validator::make($request->all(), [
			'category_id' => 'sometimes|required|integer|min:1',
			'service_name_en' => 'sometimes|required|string|max:150',
			'service_description_en' => 'sometimes|required|string',

			'customer_name_en' => 'sometimes|required|string|max:150',
			'customer_name_es' => 'sometimes|required|string|max:150',
			'customer_name_pl' => 'sometimes|required|string|max:150',

			'price_type' => 'sometimes|required|in:fixed,from,hair_weight',
			'price_note' => 'sometimes|nullable|string|max:500',

			'price_en' => 'sometimes|required|numeric|min:0',
			'price_es' => 'sometimes|required|numeric|min:0',
			'price_pl' => 'sometimes|required|numeric|min:0',

			'service_time_taken' => 'sometimes|required|integer|min:1',
			'is_archived' => 'sometimes|string|in:Yes,No',
		], [
			'category_id.required' => 'Atleast one Category must be selected.',
			'category_id.integer' => 'Category must be a valid number.',
			'category_id.min' => 'Category must be greater than 0.',

			// Service
			'service_name_en.required' => 'Service Name is required.',
			'service_name_en.string' => 'Service Name must be a string.',
			'service_name_en.max' => 'Service Name is too long',

			'service_description_en.required' => 'Service Description is required.',
			'service_description_en.string' => 'Service Description must be a string.',
			'service_description_en.max' => 'Service Description is too long.',

			// Customer Name - English
			'customer_name_en.required' => 'Customer Name (English) is required.',
			'customer_name_en.string' => 'Customer Name (English) must be a string.',
			'customer_name_en.max' => 'Customer Name (English) is too long.',

			// Customer Name - Spanish
			'customer_name_es.required' => 'Customer Name (Spanish) is required.',
			'customer_name_es.string' => 'Customer Name (Spanish) must be a string.',
			'customer_name_es.max' => 'Customer Name (Spanish) is too long.',

			// Customer Name - Polish
			'customer_name_pl.required' => 'Customer Name (Polish) is required.',
			'customer_name_pl.string' => 'Customer Name (Polish) must be a string.',
			'customer_name_pl.max' => 'Customer Name (Polish) is too long',

			// Price Type
			'price_type.required' => 'Price Type is required.',
			'price_type.in' => 'Invalid Price Type. Allowed values are fixed, from, or hair_weight.',

			// Price Note
			'price_note.string' => 'Price Note must be a string.',
			'price_note.max' => 'Price Note is too long',

			// Prices
			'price_en.required' => 'Price (English) is required.',
			'price_en.numeric' => 'Price (English) must be a valid number.',
			'price_en.min' => 'Price (English) cannot be less than 0.',

			'price_es.required' => 'Price (Spanish) is required.',
			'price_es.numeric' => 'Price (Spanish) must be a valid number.',
			'price_es.min' => 'Price (Spanish) cannot be less than 0.',

			'price_pl.required' => 'Price (Polish) is required.',
			'price_pl.numeric' => 'Price (Polish) must be a valid number.',
			'price_pl.min' => 'Price (Polish) cannot be less than 0.',

			// Service Time
			'service_time_taken.required' => 'Service Time Taken is required.',
			'service_time_taken.integer' => 'Service Time Taken must be a valid integer.',
			'service_time_taken.min' => 'Service Time Taken must be at least 1 minute.',

			// Archived
			'is_archived.string' => 'Archive status must be a string.',
			'is_archived.in' => 'Invalid archive status. Allowed values are Yes or No.',
		]);

		if ($validator->fails()) {
            return response()->json([
                "result" => 0,
                "errors" => $validator->errors()->first(),
            ]);
        }

		$allowedFields = [
			'category_id',
			'service_name_en',
			'service_description_en',
			'customer_name_en',
			'customer_name_es',
			'customer_name_pl',
			'price_type',
			'price_note',
			'price_en',
			'price_es',
			'price_pl',
			'service_time_taken',
			'is_archived',
		];

		if (!$request->hasAny($allowedFields)) {
			return response()->json([
				'result' => 0,
				'msg' => 'Please provide at least one field to update.',
			]);
		}

		$serviceName = null;

		if ($request->has('service_name_en')) {
			$serviceName = trim($request->input('service_name_en'));

			if (ServiceModel::serviceNameExists($serviceName,$serviceId)) {
				return response()->json([
					'result' => 0,
					'msg' => "This Service already exists. Please enter a unique service name.",
				]);
			}
		}


		if ($request->has('category_id')) {
			$categoryId = (int) $request->input('category_id');

			$category = CategoryModel::getCategoryById($categoryId);

			if (!$category) {
				return response()->json(['result' => 0,'msg' => 'Please provide a valid category.']);
			}

			if ($category->status !== 'Active') {
				return response()->json(['result' => 0,'msg' => 'Please provide an active category.']);
			}
		}

		$update = [];

		if ($request->has('category_id')) {
			$update['category_id'] = (int) $request->input('category_id');
		}

		if ($request->has('service_name_en')) {
			$update['service_name_en'] = $serviceName;
		}

		if ($request->has('service_description_en')) {
			$update['service_description_en'] = $request->input('service_description_en');
		}

		if ($request->has('customer_name_en')) {
			$update['customer_name_en'] = trim($request->input('customer_name_en'));
		}

		if ($request->has('customer_name_es')) {
			$update['customer_name_es'] = trim($request->input('customer_name_es'));
		}

		if ($request->has('customer_name_pl')) {
			$update['customer_name_pl'] = trim($request->input('customer_name_pl'));
		}

		if ($request->has('price_type')) {
			$update['price_type'] = $request->input('price_type');
		}

		if ($request->exists('price_note')) {
			$update['price_note'] = $request->input('price_note');
		}

		if ($request->has('price_en')) {
			$update['price_en'] = (float) $request->input('price_en');
		}

		if ($request->has('price_es')) {
			$update['price_es'] = (float) $request->input('price_es');
		}

		if ($request->has('price_pl')) {
			$update['price_pl'] = (float) $request->input('price_pl');
		}

		if ($request->has('service_time_taken')) {
			$update['service_time_taken'] = (int) $request->input('service_time_taken');
		}

		if ($request->has('is_archived')) {
			$update['is_archived'] = $request->input('is_archived');
		}

		$service_thumbnail = null;

		if (!empty($request->hasfile('service_thumbnail'))) {
			$update['service_thumbnail'] = singleAwsUpload($request,'service_thumbnail');
		}

		$update['updated_at'] = now();

		$result = ServiceModel::updateService($update,$serviceId);

		if ($result === 0) {
			return response()->json([
				'result' => -1,
				'msg' => 'No changes were detected.',
			]);
		}

		$adminId = $request->input('admin_id');

		if ($adminId) {
			$updatedServiceName =
				$update['service_name_en']
				?? $service->service_name_en;

			DB::table('activity_logs')->insert([
				'admin_id' => $adminId,
				'action' => 'Service Updated',
				'description' =>
					"Service updated: {$updatedServiceName} (ID: {$serviceId})",
				'created_at' => now(),
			]);
		}

		return response()->json([
			'result' => 1,
			'msg' => 'Service updated successfully.',
			'data' => [
				'service_id' => $serviceId,
				'updated_fields' => array_values(
					array_diff(
						array_keys($update),
						['updated_at']
					)
				),
			],
		]);
	}
	
	public function deleteService(Request $request,$serviceId) 
	{
		if (!is_numeric($serviceId) || (int) $serviceId <= 0) {
			return response()->json([
				'result' => 0,
				'msg' => 'Please provide a valid service ID.',
			]);
		}

		$serviceId = (int) $serviceId;

		$service = ServiceModel::getServiceById($serviceId);

		if (!$service) {
			return response()->json([
				'result' => -1,
				'msg' => 'Service not found.',
			]);
		}

		if ($service->status === 'Inactive') {
			return response()->json([
				'result' => -1,
				'msg' => 'Service is already inactive.',
			]);
		}

		$result = ServiceModel::deleteService($serviceId);

		if (!$result) {
			return response()->json([
				'result' => -1,
				'msg' => 'Service could not be deleted.',
			]);
		}

		$adminId = $request->input('admin_id');

		if ($adminId) {
			DB::table('activity_logs')->insert([
				'admin_id' => $adminId,
				'action' => 'Service Deleted',
				'description' =>
					"Service marked inactive: {$service->service_name_en} (ID: {$serviceId})",
				'created_at' => now(),
			]);
		}

		/*
		* No sub-service concept is currently being used.
		*
		* Keep this only if sub-services may return later:
		*
		* $subservices =
		*     ServiceModel::getSubserviceByServiceId($serviceId);
		*
		* if ($subservices && !$subservices->isEmpty()) {
		*     ServiceModel::deleteSubservicesByServiceId(
		*         $serviceId
		*     );
		* }
		*/

		return response()->json([
			'result' => 1,
			'msg' => 'Service deleted successfully.',
		]);
	}
   // ---------------------------Payment History Apis ---------------------------------------------------------------
   public function getAllPaymentHistory()
   {
      $result=ServiceModel::getAllPaymentHistory();
      // dd($result);
      if($result){
        return response()->json(['result' => 1, 'msg' => 'Payment History', 'data'=>$result]);
      }else{
        return response()->json(['result' => -1, 'msg' => 'No Payment History']);
      }
         
   }  
   
   public function addPayment(Request $request)
   {
       try {
			$validator = Validator::make($request->all(), [
				'customer_id' => 'required',
				'salon_id' => 'required',
				'service_id' => 'required',
				'payment_method' => 'required|in:cash,credit,debit,wallet',
			]);

			if ($validator->fails()) {
				return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
			}
			
			$booking_id = $request->input('booking_id');

			$insert = [
				'booking_id' => !empty($booking_id) ? $booking_id : null,
				'customer_id' => $request->input('customer_id'),
				'salon_id' => $request->input('salon_id'),
				'service_id' => json_encode($request->input('service_id')),
				'amount' => $request->input('total_amount'),
				'payment_date' => $request->input('payment_date'),
				'payment_method' => $request->input('payment_method')
			];

			$result = ServiceModel::addPayment($insert);

			if ($result) {
				$pre_payment_amt = $request->input('prepaid_amount');
				if (!empty($pre_payment_amt)) {
					update('booking', 'booking_id', $booking_id, ['pre_payment_amt' => $pre_payment_amt, 'pre_payment_made' => 'yes']);
				}
				
				$services = $request->input('services');
				$serviceIds = [];
				if (!empty($services)) {
					foreach ($services as $service) {
						$serviceIds[] = $service['service_id'];
						$pricingData = [
							'booking_id' => !empty($booking_id) ? $booking_id : null,
							'service_id' => !empty($service['service_id']) ? $service['service_id'] : null,
							'total_amount' => !empty($service['total_amount']) ? $service['total_amount'] : null,
							'hair_gm' => !empty($service['hair_gm']) ? $service['hair_gm'] : null,
							'price_per_unit' => !empty($service['price_per_unit']) ? $service['price_per_unit'] : null
						];
						insert('booking_pricing', $pricingData);
					}
					
					if (!empty($serviceIds)) {
						/* return response()->json(['result' => 1, 'serviceIds' => $serviceIds]); */
						$booking_details = select('booking', 'services', [['status', '=', 'Active'], ['booking_id', '=', $booking_id]])->first();
						if (!empty($booking_details)) {
							update('booking', 'booking_id', $booking_id, ['old_services' => $booking_details->services]);
						}
						$serviceIdsString = json_encode($serviceIds);
						update('booking', 'booking_id', $booking_id, ['services' => $serviceIdsString]);
					}
				}
				
				return response()->json(['result' => 1, 'msg' => 'Payment Added Successfully', 'data' => $result, 'services' => !empty($services) ? $services : null]);
			} else {
				return response()->json(['result' => -1, 'msg' => 'Try Again Later']);
			}
		} catch (\Exception $e) {
			return response()->json(['result' => -1, 'msg' => 'An error occurred: ' . $e->getMessage()]);
		}      
   }
   
    public function getPaymentHistoryBySalonId($salon_id)
    {
       $result=ServiceModel::getPaymentHistoryBySalonId($salon_id);
       if($result){
        foreach($result as $row){
            
          $row->salons = SalonModel::getSalonDetails($row->salon_id);

          $services = str_replace(['\\', '/'], '',$row->service_id);
       
          $row->services =ServiceModel::getServices(json_decode($services));
        }
        return response()->json(['result' => 1, 'msg' => 'Payment History', 'data' => $result]);
       }else{
        return response()->json(['result' => -1, 'msg' => 'No Payment History']);
       }
    }

    public function getPaymentHistoryByCustomerId($customer_id)
    {
       $result=ServiceModel::getPaymentHistoryByCustomerId($customer_id);
       if($result){
        foreach($result as $row){
            
          $row->Customer = CustomerModel::getCustomerById($row->customer_id);

          $services = str_replace(['\\', '/'], '',$row->service_id);
       
          $row->services =ServiceModel::getServices(json_decode($services));
        }
        return response()->json(['result' => 1, 'msg' => 'Payment History', 'data' => $result]);
       }else{
        return response()->json(['result' => -1, 'msg' => 'No Payment History']);
       }
    }
	
	public function getSalonStats(Request $request, $salon_id, ServiceModel $serviceModel)
    {
        try {
			$salon = DB::table('salon')->where('salon_id', $salon_id)->select('salon_name_en', 'salon_address_en','salon_thumbnail')->first();

			$result = [
				'salon_name_en' => $salon->salon_name_en ?? '',
            	'salon_address_en' => $salon->salon_address_en ?? '',
				'salon_thumbnail' => $salon->salon_thumbnail ?? '',

				'todayCollection' => $serviceModel->getCollectionByPeriod($salon_id, 'today'),
				'weeklyCollection' => $serviceModel->getCollectionByPeriod($salon_id, 'week'),
				'monthlyCollection' => $serviceModel->getCollectionByPeriod($salon_id, 'month'),
				'yearlyCollection' => $serviceModel->getCollectionByPeriod($salon_id, 'year'),
				'totalClients' => $serviceModel->getTotalClients($salon_id),
				'totalEmployeesWorking' => $serviceModel->getTotalEmployeesWorking($salon_id),
				'todaysBooking' => $serviceModel->getTodayBooking($salon_id, 'today'),
			    'totalBookings' => $serviceModel->getTotalBookings($salon_id)
			];
    
            if ($result) {
				return response()->json(['result' => 1, 'msg' => 'Records found', 'data' => $result]);
			} else {
				return response()->json(['result' => -1, 'msg' => 'No Records found']);
			}
        } catch (\Exception $e) {
            Log::error('Error in getSalonStats: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Internal Server Error', 'details' => $e->getMessage()], 500);
        }
    }
	
	// --------------------------------------SUB-SERVICES----------------------------------------------------------
    public function addSubServices(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'service_id' => 'required',
            'name_en' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }

        $subServiceInsert = [
            'service_id' => $request->post('service_id'),
            'name_en' => $request->post('name_en'),
            'name_es' => $request->post('name_es'),
            'name_pl' => $request->post('name_pl'),
            'hair_per_unit' => $request->post('hair_per_unit'),
            'hair_gm' => $request->post('hair_gm'),
            'price'=>$request->post('price')
        ];
        // dd($subServiceInsert);
        $result = ServiceModel::addSubService($subServiceInsert);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Sub-service Added Succesfully']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Sub-Services Not Added']);
        }
    }

    public function updateSubServices(Request $request, $id)
    {
        $Validator = Validator::make($request->all(), [
            'service_id' => 'required',
            'name_en' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $update = [
            'service_id' => $request->post('service_id'),
            'name_en' => $request->post('name_en'),
            'name_es' => $request->post('name_es'),
            'name_pl' => $request->post('name_pl'),
            'hair_per_unit' => $request->post('hair_per_unit'),
            'hair_gm' => $request->post('hair_gm'),
            'price' =>$request->post('price')
        ];

        $result = ServiceModel::updateSubServices($update, $id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Sub-service Updated Succesfully']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }

    public function getSubServicesById($id = null)
    {
        $result = ServiceModel::getSubServicesById($id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Data Not Found']);
        }
    }

    public function subServices()
    {
        $result = ServiceModel::getsubServices();
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data']);
        }
    }
    
    public function deleteSubService($id)
    {
        $result=ServiceModel::deleteSubService($id);
        if($result){
            return response()->json(['result'=>1,'msg'=>'Sub-Services Deleted Successfully']);
        }else{
            return response()->json(['result'=>-1,'msg'=>'Sub-services Not Deleted']);
        }
    }

    public function getAllSalonStats(Request $request, ServiceModel $serviceModel)
    {
        try {
            $todayCollection = $serviceModel->getAllSalonsCollection('today');
            $weeklyCollection = $serviceModel->getAllSalonsCollection('week');
            $monthlyCollection = $serviceModel->getAllSalonsCollection('month');
            $yearlyCollection = $serviceModel->getAllSalonsCollection('year');
            $totalClients = $serviceModel->getAllSalonsClients();
            $totalEmployeesWorking = $serviceModel->getAllSalonsEmployeesWorking();
            $todaysBooking = $serviceModel->getAllSalonsBooking('today');
            $monthlyBookings = $serviceModel->getAllSalonsBooking('month');
            $yearlyBookings = $serviceModel->getAllSalonsBooking('year');
            $weeklyBookings = $serviceModel->getAllSalonsBooking('week');

            $result = [
                'todaysCollection' => $todayCollection,
                'weeklyCollection' => $weeklyCollection,
                'monthlyCollection' => $monthlyCollection,
                'yearlyCollection' => $yearlyCollection,
                'totalClients' => $totalClients,
                'totalEmployeesWorking' => $totalEmployeesWorking,
                'weeklyBookings' => $weeklyBookings,
                'monthlyBooking'=> $monthlyBookings,
                'yearlyBooking'=> $yearlyBookings,
                'todaysBooking' => $todaysBooking,
            ];
            // dd($result);
            if ($result) {
                return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'No Data Found']);
            }
        } catch (\Exception $e) {
            Log::error('Error in getSalonStats: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Internal Server Error', 'details' => $e->getMessage()], 500);
        }
    }

    public function getSalonStatisticsOld(Request $request, ServiceModel $serviceModel)
    {
		try {
			$user = !empty($request->query('u')) ? $request->query('u') : null;
			$user_id = !empty($request->query('uid')) ? $request->query('uid') : null;
			$allSalons = $serviceModel->getAllSalonDetails($user, $user_id);
			$salonStats = [];

			foreach ($allSalons as $salon) {
				$salonName = $salon->salon_name_en ?? $salon->salon_name_es ?? $salon->salon_name_pl ?? null;

				$todaysCollection = $serviceModel->getCollectionByPeriod($salon->salon_id, 'today');
				$monthlyCollection = $serviceModel->getCollectionByPeriod($salon->salon_id, 'month');
				$weeklyCollection = $serviceModel->getCollectionByPeriod($salon->salon_id, 'week');
				$yearlyCollection = $serviceModel->getCollectionByPeriod($salon->salon_id, 'year');
				
				$todaysBooking = $serviceModel->getBookingDetails($salon->salon_id, 'today');
				$weeklyBooking = $serviceModel->getBookingDetails($salon->salon_id, 'week');
				$monthlyBooking = $serviceModel->getBookingDetails($salon->salon_id, 'month');
				$yearlyBooking = $serviceModel->getBookingDetails($salon->salon_id, 'year');

				$todayClients = $serviceModel->getClientDetails($salon->salon_id, 'today');
				$weeklyClients = $serviceModel->getClientDetails($salon->salon_id, 'week');
				$monthlyClients = $serviceModel->getClientDetails($salon->salon_id, 'month');
				$yearlyClients = $serviceModel->getClientDetails($salon->salon_id, 'year');
				
				$todayRevenue = $serviceModel->getBookingRevenue($salon->salon_id, 'today');
				$weeklyRevenue = $serviceModel->getBookingRevenue($salon->salon_id, 'week');
				$monthlyRevenue = $serviceModel->getBookingRevenue($salon->salon_id, 'month');
				$yearlyRevenue = $serviceModel->getBookingRevenue($salon->salon_id, 'year');

				$totalEmployeesWorking=$serviceModel->getTotalEmployeesWorking($salon->salon_id);
				
				$salonStats[] = [
					'salon_id' => $salon->salon_id,
					'salon_name' => $salonName,
					'statistics' => [
						'todaysCollection' => $todaysCollection,
						'monthlyCollection' => $monthlyCollection,
						'weeklyCollection' => $weeklyCollection,
						'yearlyCollection' => $yearlyCollection,
						'todaysBooking' => $todaysBooking,
						'weeklyBooking' => $weeklyBooking,
						'monthlyBooking' => $monthlyBooking,
						'yearlyBooking' => $yearlyBooking,
						'todayClients' => $todayClients,
						'weeklyClients'=> $weeklyClients,
						'monthlyClients'=> $monthlyClients,
						'yearlyClients' => $yearlyClients,
						'todayRevenue' => $todayRevenue,
						'weeklyRevenue'=> $weeklyRevenue,
						'monthlyRevenue'=> $monthlyRevenue,
						'yearlyRevenue' => $yearlyRevenue,
						'totalEmployeesWorking'=>$totalEmployeesWorking
					],
				];
			}

			if ($salonStats) {
				return response()->json(['result' => 1, 'msg' => 'Salon statistics retrieved successfully', 'data' => $salonStats]);
			} else {
				return response()->json(['result' => -1, 'msg' => 'No salon statistics found']);
			}
		} catch (\Exception $e) {
			// Handle exceptions, log or return an error response
			return response()->json(['result' => -1, 'msg' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function getSalonStatistics(Request $request, ServiceModel $serviceModel)
	{
		try {
			$user = $request->query('u') ?? null;
			$user_id = $request->query('uid') ?? null;
			$allSalons = $serviceModel->getAllSalonDetails($user, $user_id);
			$salonIds = $allSalons->pluck('salon_id')->toArray();
			if (empty($salonIds)) {
				return response()->json(['result' => -1, 'msg' => 'No salons found']);
			}

			$todaysCollection = $serviceModel->getCollectionByPeriodNew($salonIds, 'today');
			$monthlyCollection = $serviceModel->getCollectionByPeriodNew($salonIds, 'month');
			$weeklyCollection = $serviceModel->getCollectionByPeriodNew($salonIds, 'week');
			$yearlyCollection = $serviceModel->getCollectionByPeriodNew($salonIds, 'year');
			
			$todaysBooking = $serviceModel->getBookingDetailsNew($salonIds, 'today');
			$weeklyBooking = $serviceModel->getBookingDetailsNew($salonIds, 'week');
			$monthlyBooking = $serviceModel->getBookingDetailsNew($salonIds, 'month');
			$yearlyBooking = $serviceModel->getBookingDetailsNew($salonIds, 'year');

			$todayClients = $serviceModel->getClientDetailsNew($salonIds, 'today');
			$weeklyClients = $serviceModel->getClientDetailsNew($salonIds, 'week');
			$monthlyClients = $serviceModel->getClientDetailsNew($salonIds, 'month');
			$yearlyClients = $serviceModel->getClientDetailsNew($salonIds, 'year');
			
			$todayRevenue = $serviceModel->getBookingRevenueNew($salonIds, 'today');
			$weeklyRevenue = $serviceModel->getBookingRevenueNew($salonIds, 'week');
			$monthlyRevenue = $serviceModel->getBookingRevenueNew($salonIds, 'month');
			$yearlyRevenue = $serviceModel->getBookingRevenueNew($salonIds, 'year');
			
			$totalEmployeesWorking=$serviceModel->getTotalEmployeesWorkingNew($salonIds);

			$salonStats = [];
			foreach ($allSalons as $salon) {
				$salonStats[] = [
					'salon_id' => $salon->salon_id,
					'salon_name' => $salon->salon_name_en ?? $salon->salon_name_es ?? $salon->salon_name_pl ?? null,
					'statistics' => [
						'todaysCollection' => $todaysCollection[$salon->salon_id] ?? 0,
						'monthlyCollection' => $monthlyCollection[$salon->salon_id] ?? 0,
						'weeklyCollection' => $weeklyCollection[$salon->salon_id] ?? 0,
						'yearlyCollection' => $yearlyCollection[$salon->salon_id] ?? 0,
						'weeklyBooking' => $weeklyBooking[$salon->salon_id] ?? 0,
						'todaysBooking' => $todaysBooking[$salon->salon_id] ?? 0,
						'monthlyBooking' => $monthlyBooking[$salon->salon_id] ?? 0,
						'yearlyBooking' => $yearlyBooking[$salon->salon_id] ?? 0,
						'todayClients' => $todayClients[$salon->salon_id] ?? 0,
						'weeklyClients'=> $weeklyClients[$salon->salon_id] ?? 0,
						'monthlyClients'=> $monthlyClients[$salon->salon_id] ?? 0,
						'yearlyClients' => $yearlyClients[$salon->salon_id] ?? 0,
						'todayRevenue' => $todayRevenue[$salon->salon_id] ?? 0,
						'weeklyRevenue'=> $weeklyRevenue[$salon->salon_id] ?? 0,
						'monthlyRevenue'=> $monthlyRevenue[$salon->salon_id] ?? 0,
						'yearlyRevenue' => $yearlyRevenue[$salon->salon_id] ?? 0,
						'totalEmployeesWorking'=>$totalEmployeesWorking
					],
				];
			}

			return response()->json(['result' => 1, 'msg' => 'Salon statistics retrieved successfully', 'data' => $salonStats]);
		} catch (\Exception $e) {
			return response()->json(['result' => -1, 'msg' => 'Error occurred: ' . $e->getMessage()]);
		}
	}
	
	public function getSalonStatisticsV2(Request $request, ServiceModel $serviceModel)
	{
		try {
			$user = $request->query('u') ?? null;
			$user_id = $request->query('uid') ?? null;
			$allSalons = $serviceModel->getAllSalonDetails($user, $user_id);
			$salonIds = $allSalons->pluck('salon_id')->toArray();
			if (empty($salonIds)) {
				return response()->json(['result' => -1, 'msg' => 'No salons found']);
			}
			$salonStats = $serviceModel->getTopSalonsByBookings($salonIds);
			if (!empty($salonStats)) {
				foreach ($salonStats as $val) {
					$val->salon_thumbnail = !empty($val->salon_thumbnail) ? baseURL($val->salon_thumbnail) : null;
				}
			}
			return response()->json(['result' => 1, 'msg' => 'Salon statistics retrieved successfully', 'data' => $salonStats]);
		} catch (\Exception $e) {
			return response()->json(['result' => -1, 'msg' => 'Error occurred: ' . $e->getMessage()]);
		}
	}
}
