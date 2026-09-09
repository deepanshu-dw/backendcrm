<?php

namespace App\Http\Controllers;

use App\Models\SalonModel;
use App\Models\CategoryModel;
use App\Models\ServiceModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Carbon\Carbon;

class SalonController extends Controller
{
    public function addSalon(Request $request)
    {
        // Validate the request data using Laravel's Validator
        $validator = Validator::make($request->all(), [
            'salon_name_en' => 'required|string',
            'salon_name_es' => 'required|string',
            'salon_name_pl' => 'required|string',
            'salon_address_en' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ], [
            'required' => 'This :attribute is required',
            'unique' => 'The :attribute has already been taken',
			'min' => 'The :attribute must be at least :min characters long'
        ]);

       $admin_id = $request->input('admin_id');

        // If validation fails, return an error response
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()], 400);
        }
        try {

            $thumbnail_image = null;
            if (!empty($request->hasfile('salon_thumbnail'))) {
                $thumbnail_image = singleAwsUpload($request, 'salon_thumbnail');
            }
            $rand = rand(1111, 9999);
            $password = $request->post('password');
            $user_name = 'hollywood' . $rand;
            $insert = [
                'salon_name_en' => $request->post('salon_name_en'),
                'salon_name_es' => $request->post('salon_name_es'),
                'salon_name_pl' => $request->post('salon_name_pl'),
                'salon_address_en' => $request->post('salon_address_en'),
                'salon_address_es' => $request->post('salon_address_es'),
                'salon_address_pl' => $request->post('salon_address_pl'),
                'city_es' => $request->post('city_es'),
                'city_en' => $request->post('city_en'),
                'city_pl' => $request->post('city_pl'),
                'phone_no' => $request->post('phone_no'),
                'user_name' => $request->post('user_name'),
                'email' => $request->post('email'),
                'password' => hash('sha256', $request->post('password')),
                'opening_time' => $request->post('opening_time'),
                'closing_time' => $request->post('closing_time'),
                'salon_thumbnail' => $thumbnail_image,
                'latitude' => $request->post('latitude'),
                'longitude' => $request->post('longitude'),
				'location' => $request->post('location')
            ];
            $device_type = $request->post('device_type');
            $result = SalonModel::addSalon($insert, $device_type);

            if ($result) {
                DB::table('activity_logs')->insert([
                'admin_id'   => $admin_id,
                'action'     => 'Salon Added',
                'description'=> "New Salon created: {$insert['salon_name_en']}",
                'created_at' => now(),
		      ]);
                $services = $request->post('services_ids');
                if (!empty($services)) {
                    foreach ($services as $val) {
                        $temp['salon_id'] = $result;
                        $temp['service_id'] = $val;
                        insert('salon_services', $temp);
                    }
                }

                $maildata['to'] = $request->post('email');
                $maildata['subject'] = 'Activation Salon and Credentials';
                $maildata['view_name'] = 'welcome';
                $maildata['name'] = $request->post('salon_name_en');
                $maildata['email'] = $request->post('email');
                $maildata['password'] = $password;
                $maildata['user_name'] = $user_name;
				if (!empty($maildata['email'])) {
					sendMail($maildata);
				}
                return response()->json(['result' => 1, 'msg' => 'Salon Added successfull', 'data' => $result]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Salon not added ']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public function addSalonV2(Request $request)
    {
        try {
            $categories = $request->input('categories');
            if (is_string($categories)) {
                $categories = json_decode($categories,true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return response()->json(['result' => 0,'msg' => 'Invalid categories format.'], 400);
                }

                $request->merge([
                    'categories' => $categories
                ]);
            }
            $validator = Validator::make(
                $request->all(),
                [
                    'salon_name_en' => 'required',
                    'salon_address_en' => 'required',
                    'email' => 'required|email',
                    'password' => 'required|min:6',
                    'opening_time' => 'required|date_format:h:i A',
                    'closing_time' => 'required|date_format:h:i A',
                    'categories' => 'required|array|min:1',
                    'categories.*.category_id' => 'required|integer',
                    'categories.*.service_ids' => 'required|array|min:1',
                    'categories.*.service_ids.*' => 'required|integer',
                    'categories.*.booking_slots' => 'required|array|min:1',
                    'categories.*.booking_slots.*.start_time' => 'required|date_format:H:i',
                    'categories.*.booking_slots.*.end_time' => 'nullable|date_format:H:i',
                ], [
                    'required' => 'This :attribute is required',
                    'unique' => 'The :attribute has already been taken',
                    'min' => 'The :attribute must be at least :min characters long',
                    'date_format' => 'The :attribute must be in HH:MM AM/PM format'
                ]
            );


            if ($validator->fails()) {
                return response()->json([
                    'result' => 0,
                    'errors' =>
                        $validator->errors()->first()
                ], 400);
            }


            $email = trim($request->input('email'));


            if (SalonModel::salonEmailExists($email)) {
                return response()->json([
                    'result' => 0,
                    'msg' =>
                        'Salon for this email already exists.'
                ], 400);
            }
            
            $openingTime =
                strtoupper(
                    trim(
                        $request->input(
                            'opening_time'
                        )
                    )
                );

            $closingTime =
                strtoupper(
                    trim(
                        $request->input(
                            'closing_time'
                        )
                    )
                );


            $openingTime24 =
                \Carbon\Carbon::createFromFormat(
                    'h:i A',
                    $openingTime
                )->format('H:i');


            $closingTime24 =
                \Carbon\Carbon::createFromFormat(
                    'h:i A',
                    $closingTime
                )->format('H:i');


            /*
            * Salon closing time validation.
            */
            if (
                $closingTime24 <=
                $openingTime24
            ) {
                return response()->json([
                    'result' => 0,
                    'msg' =>
                        'Closing time must be greater than opening time.'
                ], 400);
            }


            /*
            * Normalize AM/PM format before storing.
            *
            * Example:
            * 09:00 AM -> 09:00 AM
            * 06:00 PM -> 06:00 PM
            */
            $openingTime =
                \Carbon\Carbon::createFromFormat(
                    'H:i',
                    $openingTime24
                )->format('h:i A');


            $closingTime =
                \Carbon\Carbon::createFromFormat(
                    'H:i',
                    $closingTime24
                )->format('h:i A');


            /*
            * This array will hold already validated
            * category configurations.
            */
            $categoryConfigurations = [];

            $usedCategoryIds = [];


            /*
            * Validate complete category/service/slot
            * configuration before inserting anything.
            */
            foreach ($categories as $categoryData) {

                $categoryId =
                    (int) $categoryData['category_id'];


                if (
                    in_array(
                        $categoryId,
                        $usedCategoryIds,
                        true
                    )
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Category {$categoryId} has been selected more than once."
                    ], 400);
                }


                $usedCategoryIds[] =
                    $categoryId;


                /*
                * getCategoryById() already returns
                * only Active category.
                */
                $category =
                    CategoryModel::getCategoryById(
                        $categoryId
                    );


                if (!$category) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Category {$categoryId} not found or inactive."
                    ], 400);
                }


                /*
                * Normalize service IDs.
                */
                $serviceIds = array_values(
                    array_unique(
                        array_map(
                            'intval',
                            $categoryData['service_ids']
                        )
                    )
                );


                if (empty($serviceIds)) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Please select at least one service for {$category->name}."
                    ], 400);
                }


                /*
                * Validate services using ServiceModel.
                */
                $validServices =
                    ServiceModel::getActiveServicesByCategoryAndIds(
                        $categoryId,
                        $serviceIds
                    );


                if (
                    $validServices->count() !==
                    count($serviceIds)
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "One or more selected services are invalid for {$category->name}."
                    ], 400);
                }


                /*
                * Validate category slots.
                *
                * Existing booking slots remain H:i.
                *
                * Salon opening/closing times are passed
                * as H:i only for validation.
                */
                $slotValidation =
                    $this->validateSalonCategorySlots(
                        $category->booking_type,
                        $categoryData['booking_slots'],
                        $openingTime24,
                        $closingTime24
                    );


                if ($slotValidation !== null) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "{$category->name}: {$slotValidation}"
                    ], 400);
                }


                /*
                * Store normalized configuration.
                *
                * Booking slots are NOT changed.
                */
                $categoryConfigurations[] = [

                    'category_id' =>
                        $categoryId,

                    'service_ids' =>
                        $serviceIds,

                    'booking_slots' =>
                        array_values(
                            $categoryData['booking_slots']
                        )
                ];
            }


            /*
            * Upload salon thumbnail only after
            * all request validations pass.
            */
            $thumbnailImage = null;

            if ($request->hasFile('salon_thumbnail')) {
                $thumbnailImage =singleAwsUpload($request,'salon_thumbnail');
            }

            $rand = rand(1111, 9999);
            $password = $request->post('password');
            $userName = 'hollywood' . $rand;
            $insert = [
                'salon_name_en' => $request->post('salon_name_en'),
                'salon_name_es' => $request->post('salon_name_es'),
                'salon_name_pl' => $request->post('salon_name_pl'),
                'salon_address_en' => $request->post('salon_address_en'),
                'salon_address_es' => $request->post('salon_address_es'),
                'salon_address_pl' => $request->post('salon_address_pl'),
                'phone_no' =>
                    $request->post('phone_no'),

                'user_name' =>
                    $userName,

                'email' =>
                    $email,

                'city_es' =>
                    $request->post('city_es'),

                'city_en' =>
                    $request->post('city_en'),

                'city_pl' =>
                    $request->post('city_pl'),

                'country' => $request->post('country'),

                'password' => hash('sha256',$request->post('password')),
                'opening_time' => $openingTime,
                'closing_time' =>
                    $closingTime,

                'salon_thumbnail' =>
                    $thumbnailImage,

                'latitude' =>
                    $request->post('latitude'),

                'longitude' =>
                    $request->post('longitude'),

                'location' => $request->post('location')
            ];
            $deviceType = $request->post('device_type');
            $adminId = $request->input('admin_id');

            DB::beginTransaction();


            try {
                $salonId = SalonModel::addSalon($insert, $deviceType);
                if (empty($salonId)) {
                    throw new \Exception('Salon not added.');
                }

                $salonServices = [];
                foreach ($categoryConfigurations as $categoryConfiguration) {
                    $salonCategoryInsert = [
                        'salon_id' =>
                            $salonId,

                        'category_id' =>
                            $categoryConfiguration[
                                'category_id'
                            ],

                        'booking_slots' =>
                            json_encode(
                                $categoryConfiguration[
                                    'booking_slots'
                                ]
                            ),

                        'status' =>
                            'Active',

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now()
                    ];


                    SalonModel::addSalonCategory(
                        $salonCategoryInsert
                    );


                    /*
                    * Prepare service mappings.
                    */
                    foreach (
                        $categoryConfiguration['service_ids']
                        as $serviceId
                    ) {

                        $salonServices[] = [
                            'salon_id' =>
                                $salonId,

                            'service_id' =>
                                $serviceId
                        ];
                    }
                }


                /*
                * Normalize service duplicates.
                */
                $uniqueSalonServices = [];

                $usedServiceIds = [];


                foreach (
                    $salonServices
                    as $salonService
                ) {

                    $serviceId =
                        (int) $salonService['service_id'];


                    if (
                        !in_array(
                            $serviceId,
                            $usedServiceIds,
                            true
                        )
                    ) {

                        $usedServiceIds[] =
                            $serviceId;

                        $uniqueSalonServices[] =
                            $salonService;
                    }
                }


                /*
                * Bulk insert salon services.
                */
                if (!empty($uniqueSalonServices)) {

                    SalonModel::addSalonServices(
                        $uniqueSalonServices
                    );
                }


                /*
                * Existing activity log.
                */
                DB::table(
                    'activity_logs'
                )->insert([

                    'admin_id' =>
                        $adminId,

                    'action' =>
                        'Salon Added',

                    'description' =>
                        "New Salon created: {$insert['salon_name_en']}",

                    'created_at' =>
                        now()
                ]);


                DB::commit();


            } catch (\Throwable $e) {

                DB::rollBack();

                throw $e;
            }


            /*
            * Email happens after successful DB commit.
            */
            try {

                $maildata = [

                    'to' =>
                        $request->post('email'),

                    'subject' =>
                        'Activation Salon and Credentials',

                    'view_name' =>
                        'welcome',

                    'name' =>
                        $request->post(
                            'salon_name_en'
                        ),

                    'email' =>
                        $request->post('email'),

                    'password' =>
                        $password,

                    'user_name' =>
                        $userName
                ];


                if (!empty($maildata['email'])) {
                    sendMail($maildata);
                }


            } catch (\Throwable $mailException) {

                \Log::error(
                    'Salon activation email failed: ' .
                    $mailException->getMessage()
                );
            }


            return response()->json([
                'result' => 1,
                'msg' =>
                    'Salon Added successfully',
                'data' =>
                    $salonId
            ]);


        } catch (\Throwable $e) {

            return response()->json([
                'result' => -1,
                'msg' =>
                    $e->getMessage()
            ], 500);
        }
    }


    public function updateSalon(Request $request,$salon_id ) 
    {
        try {

            /*
            * Decode categories because request can be
            * multipart/form-data.
            */
            $categories =
                $request->input('categories');


            if (is_string($categories)) {

                $categories =
                    json_decode(
                        $categories,
                        true
                    );


                if (
                    json_last_error() !==
                    JSON_ERROR_NONE
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            'Invalid categories format.'
                    ], 400);
                }


                $request->merge([
                    'categories' =>
                        $categories
                ]);
            }


            /*
            * Request validation.
            *
            * Salon opening/closing time MUST be:
            * h:i A
            *
            * Example:
            * 09:00 AM
            * 06:00 PM
            */
            $validator = Validator::make(
                $request->all(),
                [
                    'salon_name_en' =>
                        'required|string',

                    // 'salon_name_es' =>
                    //     'required|string',

                    // 'salon_name_pl' =>
                    //     'required|string',

                    'salon_address_en' =>
                        'required|string',

                    'email' =>
                        'required|email',

                    'password' =>
                        'nullable|min:6',

                    'opening_time' =>
                        'required|date_format:h:i A',

                    'closing_time' =>
                        'required|date_format:h:i A',

                    'categories' =>
                        'required|array|min:1',

                    'categories.*.category_id' =>
                        'required|integer',

                    'categories.*.service_ids' =>
                        'required|array|min:1',

                    'categories.*.service_ids.*' =>
                        'required|integer',

                    'categories.*.booking_slots' =>
                        'required|array|min:1',

                    /*
                    * Booking slots remain H:i.
                    */
                    'categories.*.booking_slots.*.start_time' =>
                        'required|date_format:H:i',

                    'categories.*.booking_slots.*.end_time' =>
                        'nullable|date_format:H:i',
                ],
                [
                    'required' =>
                        'This :attribute is required',

                    'min' =>
                        'The :attribute must be at least :min characters long',

                    'date_format' =>
                        'The :attribute must be in HH:MM format'
                ]
            );


            if ($validator->fails()) {
                return response()->json([
                    'result' => 0,
                    'errors' =>
                        $validator->errors()->first()
                ], 400);
            }


            /*
            * Make sure salon exists.
            */
            $oldData =
                SalonModel::getSalonDetails(
                    $salon_id
                );


            if (!$oldData) {
                return response()->json([
                    'result' => 0,
                    'msg' =>
                        'Salon not found.'
                ], 404);
            }


            $email =
                trim(
                    $request->input('email')
                );


            if (
                SalonModel::salonEmailExists(
                    $email,
                    $salon_id
                )
            ) {
                return response()->json([
                    'result' => 0,
                    'msg' =>
                        'Salon for this email already exists.'
                ], 400);
            }


            /*
            * Salon opening/closing time.
            *
            * AM/PM is mandatory because validation
            * requires h:i A.
            */
            $openingTime =
                strtoupper(
                    trim(
                        $request->input(
                            'opening_time'
                        )
                    )
                );


            $closingTime =
                strtoupper(
                    trim(
                        $request->input(
                            'closing_time'
                        )
                    )
                );


            /*
            * Convert to 24-hour format only for
            * comparison and existing slot validation.
            */
            $openingTime24 =
                \Carbon\Carbon::createFromFormat(
                    'h:i A',
                    $openingTime
                )->format('H:i');


            $closingTime24 =
                \Carbon\Carbon::createFromFormat(
                    'h:i A',
                    $closingTime
                )->format('H:i');


            if (
                $closingTime24 <=
                $openingTime24
            ) {
                return response()->json([
                    'result' => 0,
                    'msg' =>
                        'Closing time must be greater than opening time.'
                ], 400);
            }


            /*
            * Normalize salon times to AM/PM
            * before storing.
            */
            $openingTime =
                \Carbon\Carbon::createFromFormat(
                    'H:i',
                    $openingTime24
                )->format('h:i A');


            $closingTime =
                \Carbon\Carbon::createFromFormat(
                    'H:i',
                    $closingTime24
                )->format('h:i A');


            /*
            * Validate complete category configuration
            * before updating DB.
            */
            $categoryConfigurations = [];

            $usedCategoryIds = [];


            foreach (
                $categories
                as $categoryData
            ) {

                $categoryId =
                    (int) $categoryData[
                        'category_id'
                    ];


                /*
                * Duplicate category.
                */
                if (
                    in_array(
                        $categoryId,
                        $usedCategoryIds,
                        true
                    )
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Category {$categoryId} has been selected more than once."
                    ], 400);
                }


                $usedCategoryIds[] =
                    $categoryId;


                /*
                * Active category validation.
                */
                $category =
                    CategoryModel::getCategoryById(
                        $categoryId
                    );


                if (!$category) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Category {$categoryId} not found or inactive."
                    ], 400);
                }


                /*
                * Normalize service IDs.
                */
                $serviceIds =
                    array_values(
                        array_unique(
                            array_map(
                                'intval',
                                $categoryData[
                                    'service_ids'
                                ]
                            )
                        )
                    );


                if (empty($serviceIds)) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "Please select at least one service for {$category->name}."
                    ], 400);
                }


                /*
                * Validate service/category relation.
                */
                $validServices =
                    ServiceModel::getActiveServicesByCategoryAndIds(
                        $categoryId,
                        $serviceIds
                    );


                if (
                    $validServices->count() !==
                    count($serviceIds)
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "One or more selected services are invalid for {$category->name}."
                    ], 400);
                }


                /*
                * Validate fixed/window slots.
                *
                * Existing booking slots remain H:i.
                *
                * Salon opening/closing times are passed
                * as H:i for the existing validator.
                */
                $slotValidation =
                    $this->validateSalonCategorySlots(
                        $category->booking_type,
                        $categoryData[
                            'booking_slots'
                        ],
                        $openingTime24,
                        $closingTime24
                    );


                if (
                    $slotValidation !== null
                ) {
                    return response()->json([
                        'result' => 0,
                        'msg' =>
                            "{$category->name}: {$slotValidation}"
                    ], 400);
                }


                $categoryConfigurations[] = [

                    'category_id' =>
                        $categoryId,

                    'service_ids' =>
                        $serviceIds,

                    /*
                    * Booking slots are unchanged.
                    */
                    'booking_slots' =>
                        array_values(
                            $categoryData[
                                'booking_slots'
                            ]
                        )
                ];
            }


            /*
            * Thumbnail.
            */
            if (
                $request->hasFile(
                    'salon_thumbnail'
                )
            ) {

                $thumbnailImage =
                    singleAwsUpload(
                        $request,
                        'salon_thumbnail'
                    );

            } else {

                $thumbnailImage =
                    $oldData->salon_thumbnail;
            }


            /*
            * Main salon update.
            */
            $update = [

                'salon_name_en' =>
                    $request->post(
                        'salon_name_en'
                    ),

                'salon_name_es' =>
                    $request->post(
                        'salon_name_es'
                    ),

                'salon_name_pl' =>
                    $request->post(
                        'salon_name_pl'
                    ),

                'salon_address_en' =>
                    $request->post(
                        'salon_address_en'
                    ),

                'salon_address_es' =>
                    $request->post(
                        'salon_address_es'
                    ),

                'salon_address_pl' =>
                    $request->post(
                        'salon_address_pl'
                    ),

                'city_es' =>
                    $request->post('city_es'),

                'city_en' =>
                    $request->post('city_en'),

                'city_pl' =>
                    $request->post('city_pl'),

                'phone_no' =>
                    $request->post('phone_no'),

                'email' =>
                    $email,

                /*
                * Store salon times in AM/PM format.
                */
                'opening_time' =>
                    $openingTime,

                'closing_time' =>
                    $closingTime,

                'salon_thumbnail' =>
                    $thumbnailImage,

                'latitude' =>
                    $request->post('latitude'),

                'longitude' =>
                    $request->post('longitude'),

                'location' =>
                    $request->post('location'),

                'country' =>
                    $request->post('country')
            ];


            /*
            * Password is optional during update.
            */
            $password =
                $request->post('password');


            if (!empty($password)) {

                $update['password'] =
                    hash(
                        'sha256',
                        $password
                    );
            }


            $adminId =
                $request->post('admin_id');


            /*
            * Complete update transaction.
            */
            DB::beginTransaction();


            try {

                /*
                * Update salon master information.
                */
                SalonModel::updateSalon(
                    $update,
                    $salon_id
                );


                /*
                * Replace old mappings.
                */
                SalonModel::deleteSalonServices(
                    $salon_id
                );


                SalonModel::deleteSalonCategories(
                    $salon_id
                );


                $salonServices = [];


                foreach (
                    $categoryConfigurations
                    as $categoryConfiguration
                ) {

                    /*
                    * Recreate salon category.
                    */
                    SalonModel::addSalonCategory([
                        'salon_id' =>
                            $salon_id,

                        'category_id' =>
                            $categoryConfiguration[
                                'category_id'
                            ],

                        'booking_slots' =>
                            json_encode(
                                $categoryConfiguration[
                                    'booking_slots'
                                ]
                            ),

                        'status' =>
                            'Active',

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now()
                    ]);


                    /*
                    * Prepare salon services.
                    */
                    foreach (
                        $categoryConfiguration[
                            'service_ids'
                        ]
                        as $serviceId
                    ) {

                        $salonServices[] = [
                            'salon_id' =>
                                $salon_id,

                            'service_id' =>
                                $serviceId
                        ];
                    }
                }


                /*
                * Remove duplicate service IDs
                * before inserting.
                */
                $uniqueSalonServices = [];

                $usedServiceIds = [];


                foreach (
                    $salonServices
                    as $salonService
                ) {

                    $serviceId =
                        (int) $salonService[
                            'service_id'
                        ];


                    if (
                        !in_array(
                            $serviceId,
                            $usedServiceIds,
                            true
                        )
                    ) {

                        $usedServiceIds[] =
                            $serviceId;

                        $uniqueSalonServices[] =
                            $salonService;
                    }
                }


                if (
                    !empty(
                        $uniqueSalonServices
                    )
                ) {

                    SalonModel::addSalonServices(
                        $uniqueSalonServices
                    );
                }


                /*
                * Activity log.
                */
                DB::table(
                    'activity_logs'
                )->insert([

                    'admin_id' =>
                        $adminId,

                    'action' =>
                        'Salon Updated',

                    'description' =>
                        "Salon Updated: {$update['salon_name_en']}",

                    'created_at' =>
                        now()
                ]);


                DB::commit();


            } catch (\Throwable $e) {

                DB::rollBack();

                throw $e;
            }


            return response()->json([
                'result' => 1,
                'msg' =>
                    'Salon updated successfully.',
                'data' =>
                    $salon_id
            ]);


        } catch (\Throwable $e) {

            return response()->json([
                'result' => -1,
                'msg' =>
                    $e->getMessage()
            ], 500);
        }
    }

    public function getSalonDetails(Request $request,$salon_id,$lang = 'en') {
        try {

            $isAdmin =
                $request->input('is_admin');


            if ($lang == 0) {
                $lang = 'en';
            }


            $result =
                SalonModel::getSalonDetails(
                    $salon_id,
                    $lang
                );


            if (!$result) {
                return response()->json([
                    'result' => -1,
                    'msg' => 'No data found'
                ]);
            }


            /*
            * Keep old services response.
            */
            $serviceIds =
                SalonModel::getSalonServiceIds(
                    $salon_id
                )
                ->map(function ($serviceId) {
                    return (int) $serviceId;
                })
                ->toArray();


            $result->services =
                SalonModel::getServices(
                    $serviceIds,
                    $lang,
                    $isAdmin
                );


            /*
            * New category configuration.
            */
            $salonCategories =
                SalonModel::getSalonCategories(
                    $salon_id
                );


            foreach (
                $salonCategories
                as $category
            ) {

                /*
                * Convert DB JSON string back
                * into proper response array.
                */
                $category->booking_slots =
                    !empty(
                        $category->booking_slots
                    )
                    ? json_decode(
                        $category->booking_slots,
                        true
                    )
                    : [];


                /*
                * Get services selected under
                * this category.
                */
                $categoryServiceIds =
                    SalonModel::getSalonServiceIdsByCategory(
                        $salon_id,
                        $category->category_id
                    )
                    ->map(function ($serviceId) {
                        return (int) $serviceId;
                    })
                    ->toArray();


                $category->service_ids =
                    $categoryServiceIds;


                /*
                * Full services are useful on the
                * detail/edit screen.
                */
                $category->services =
                    SalonModel::getServices(
                        $categoryServiceIds,
                        $lang,
                        $isAdmin
                    );
            }


            $result->categories =
                $salonCategories;


            /*
            * Existing currency logic.
            */
            $currency =
                select(
                    'currency',
                    '*',
                    [
                        'country' =>
                            $lang
                    ]
                )->first();


            if (
                !empty(
                    $currency->currency_icon
                )
            ) {
                $result->currency_icon =
                    $currency->currency_icon;
            }


            /*
            * Existing thumbnail logic.
            */
            if (
                isset(
                    $result->salon_thumbnail
                ) &&
                !str_contains(
                    $result->salon_thumbnail,
                    'amazonaws.com'
                )
            ) {
                $result->salon_thumbnail =
                    baseURL(
                        $result->salon_thumbnail
                    );
            }


            return response()->json([
                'result' => 1,
                'msg' =>
                    'Salon data found',
                'data' =>
                    $result
            ]);


        } catch (\Throwable $e) {

            return response()->json([
                'result' => -1,
                'msg' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllSalons(Request $request) {
        try {

            $user =
                !empty(
                    $request->query('u')
                )
                ? $request->query('u')
                : null;


            $userId =
                !empty(
                    $request->query('uid')
                )
                ? $request->query('uid')
                : null;


            $paginate =
                $request->query('paginate');


            $result =
                SalonModel::getAllSalons(
                    $paginate,
                    $user,
                    $userId
                );


            if (empty($result)) {
                return response()->json([
                    'result' => -1,
                    'msg' => 'No data found'
                ]);
            }


            /*
            * Works with both Collection and
            * Laravel paginator.
            */
            foreach ($result as $row) {

                /*
                * Existing thumbnail behavior.
                */
                if (
                    isset(
                        $row->salon_thumbnail
                    ) &&
                    !str_contains(
                        $row->salon_thumbnail,
                        'amazonaws.com'
                    )
                ) {

                    $row->salon_thumbnail =
                        baseURL(
                            $row->salon_thumbnail
                        );
                }


                /*
                * New category configurations.
                */
                $categories =
                    SalonModel::getSalonCategories(
                        $row->salon_id
                    );


                foreach (
                    $categories
                    as $category
                ) {

                    $category->booking_slots =
                        !empty(
                            $category->booking_slots
                        )
                        ? json_decode(
                            $category->booking_slots,
                            true
                        )
                        : [];


                    $category->service_ids =
                        SalonModel::getSalonServiceIdsByCategory(
                            $row->salon_id,
                            $category->category_id
                        )
                        ->map(function (
                            $serviceId
                        ) {
                            return (int) $serviceId;
                        })
                        ->toArray();
                }


                $row->categories =
                    $categories;
            }


            return response()->json([
                'result' => 1,
                'msg' =>
                    'Salon data found',
                'data' =>
                    $result
            ]);


        } catch (\Throwable $e) {

            return response()->json([
                'result' => -1,
                'msg' =>
                    $e->getMessage()
            ], 500);
        }
    }

    public function setFirebaseToken(Request $request)
    {
        $salon_id = $request->post('salon_id');
        // dd($customer_id);
        $firebase_token = $request->post('firebase_token');
        $device_type = $request->post('device_type');

        $check = SalonModel::checkTokenid($salon_id);
        // dd($check);

        if (!empty($check)) {
            SalonModel::updatefToken($check->salon_id, $firebase_token, $device_type);
        } else {
            $result = SalonModel::insertToken($salon_id, $firebase_token, $device_type);
        }
        return response()->json(['result' => 1, 'msg' => 'Token Id Updated']);
    }


    public function salonLogin(Request $request)
    {
        $requestdata = $request->all();
        $validator = Validator::make($requestdata, [
            'email_or_username'    => 'required',
            'password' => 'required'
        ], [
            'required' => 'This :Attribute is Required',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            return false;
        }
        try {
            $email = $request->post('email_or_username');
            $password = hash('sha256', $request->post('password'));
            $result = SalonModel::salonLogin($email, $password);
            if (!empty($result)) {
                if ($result->status == 'Deleted') {
                    header('HTTP/1.1 402 User Account has been deleted.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been deleted.'], 401);
                }

                if ($result->status == 'Blocked') {
                    header('HTTP/1.1 402 User Account Is Blocked.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account is blocked.'], 401);
                }

                if ($result->status == 'Inactive') {
                    header('HTTP/1.1 402 User Account Is Inactive.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been inactive by admin.'], 401);
                }
				if (isset($result->salon_thumbnail) && !str_contains($result->salon_thumbnail, 'amazonaws.com')) {
					$result->salon_thumbnail = baseURL($result->salon_thumbnail);
				}
                update('salon', 'salon_id', $result->salon_id, ['updated_at' => now()]);
                return response()->json(['result' => 1, 'msg' => 'Solon Login Successfully', 'data' => ($result)]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email or Password is wrong', 'data' => NUll]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage(), 'data' => null]);
        }
    }

    public function changePassword(Request $request)
    {
        $requestdata = $request->all();
        $validator = Validator::make($requestdata, [
            'salon_id'       => 'required',
            'current_password' => 'required',
            'new_password' => 'required|min:6|same:confirm_password',
            'confirm_password' => 'required_with:new_password',
        ], [
            'required' => 'This :attribute is required',
            'min' => 'The :attribute must be at least :min characters.',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
        }

        try {
            $salon_id = $request->post('salon_id');
            $salon = SalonModel::where('salon_id', $salon_id)->first();
            if ($salon) {
                if (hash('sha256', $request->post('current_password')) !== $salon->password) {
                    return response()->json(['result' => -1, 'msg' => 'Incorrect current password']);
                }
                $password = hash('sha256', $request->post('new_password'));
                $updatepasword = update('salon', 'salon_id', $salon_id, ['password' => $password]);
                if ($updatepasword) {
                    return response()->json(['result' => 1, 'msg' => 'Password changed successfully']);
                }
                return response()->json(['result' => 1, 'msg' => 'Password Already Updated']);
            } else {
                return response()->json(['result' => 1, 'msg' => 'Invalid Solon id']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_or_username' => 'required',
            ], [
                'required' => 'This :Attribute is Required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $email = $request->post('email_or_username');

            $checkemail = SalonModel::getSalonByEmailOrUsername($email);

            if (!empty($checkemail)) {
                if ($checkemail->status == 'Deleted') {
                    header('HTTP/1.1 402 User Account has been deleted.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been deleted.'], 401);
                }

                if ($checkemail->status == 'Blocked') {
                    header('HTTP/1.1 402 User Account Is Blocked.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account is blocked.'], 401);
                }

                if ($checkemail->status == 'Inactive') {
                    header('HTTP/1.1 402 User Account Is Inactive.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been inactive by admin.'], 401);
                }

                $otp = generateOtp();
                $maildata['name'] = $checkemail->salon_name_en;
                $maildata['to'] = $checkemail->email;
                $maildata['message'] = 'Your verifiation OTP is ' . $otp;
                $maildata['subject'] = 'OTP Verifiation Email For Forgot Password';
                $maildata['view_name'] = 'mail.otpmail';
                update('salon', 'salon_id', $checkemail->salon_id, ['otp' => $otp]);
				if (!empty($maildata['to'])) {
					// sendMail($maildata);
				}
                return response()->json(['result' => 1, 'msg' => 'Otp Sent on your mail.', 'data' => (['salon_id' => $checkemail->salon_id, 'email' => $checkemail->email, 'otp' => $otp])], 200);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email does not exist'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id' => 'required',
                'password' => 'required',
                'confirm_password' => 'required|same:password',
            ], [
                'required' => 'The :attribute field is Required',
                'same' => 'The :attribute field must match the password field',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $salon_id = $request->post('salon_id');
            $password = $request->post('password');

            $result = update('salon', 'salon_id', $salon_id, ['password' => hash('sha256', $password)]);

            if ($result > 0) {
                return response()->json(['result' => 1, 'msg' => 'Password reset successfully.'], 200);
            } else {
                return response()->json(['result' => 0, 'msg' => 'Already updated!']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
    public function resendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id' => 'required',
            ], [
                'required' => 'This :Attribute is Required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $salon_id = $request->post('salon_id');

            $checkemail = SalonModel::getSalonDetails($salon_id);

            if (!empty($checkemail)) {
                $status = $checkemail->status;

                if (in_array($status, ['Deleted', 'Blocked', 'Inactive'])) {
                    return response()->json(['result' => -2, 'msg' => "User Account Is $status."], 402);
                }

                $result = SalonModel::getSalonDetails($checkemail->salon_id);
                $otp = generateOtp();
                $maildata = [
                    'name' => $result->salon_name_en,
                    'to' => $result->email,
                    'message' => 'Your verification OTP is ' . $otp,
                    'subject' => 'OTP Verification Email For Forgot Password',
                    'view_name' => 'mail.otpmail',
                ];
                update('salon', 'salon_id', $checkemail->salon_id, ['otp' => $otp]);
                if (!empty($maildata['to'])) {
					// sendMail($maildata);
				}
                return response()->json(['result' => 1, 'msg' => 'Otp sent successfully.', 'data' => (['salon_id' => $result->salon_id, 'email' => $checkemail->email, 'otp' => $otp])], 200);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email does not exist'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id' => 'required',
                'otp' => 'required',
            ], [
                'required' => 'The :attribute field is required.',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $salon_id = $request->post('salon_id');
            $otp = $request->post('otp');

            $salonDetails = SalonModel::getSalonDetails($salon_id);

            if (empty($salonDetails)) {
                return response()->json(['result' => -1, 'msg' => 'Salon does not exist'], 401);
            }

            if (@$salonDetails->otp == $otp) {
                return response()->json(['result' => 1, 'msg' => 'OTP verified successfully.', 'data' => (['salon_id' => $salonDetails->salon_id])], 200);
            }

            return response()->json(['result' => -1, 'msg' => 'The OTP entered is incorrect!'], 401);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function booking(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id'  => 'required',
                'customer_name'      => 'required',
            ], [
                'required' => 'This :Attribute is Required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'result' => 0,
                    'msg' => $validator->errors()->first()
                ]);
            }

            if ($request->has('g-recaptcha-response')) {
                $recaptcha_response = $request->input('g-recaptcha-response');

                if (empty($recaptcha_response)) {
                    return response()->json([
                        'result' => -5,
                        'msg' => 'Please fill the reCAPTCHA'
                    ]);
                }

                $recaptcha_secret = "6LceZAYqAAAAAHqfRTPx6Olfda8r0Ulh1JyB3L3D";

                if (!empty($recaptcha_response)) {
                    $response = file_get_contents(
                        "https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response"
                    );

                    $responseKeys = json_decode($response, true);

                    if (intval($responseKeys["success"]) !== 1) {
                        return response()->json([
                            'result' => -5,
                            'msg' => "Failed to verify reCAPTCHA"
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Predefined Notification Translations
            |--------------------------------------------------------------------------
            | No Google Translate API is used here.
            | This prevents 429 Too Many Requests errors from translation calls.
            */
            $notificationTranslations = [
                'appointment_booked' => [
                    'en' => [
                        'subject' => 'Appointment Booked',
                        'message' => 'New booking has been successful',
                    ],
                    'es' => [
                        'subject' => 'Cita reservada',
                        'message' => 'La nueva reserva se ha realizado correctamente',
                    ],
                    'pl' => [
                        'subject' => 'Wizyta zarezerwowana',
                        'message' => 'Nowa rezerwacja została pomyślnie dokonana',
                    ],
                ],

                'appointment_updated' => [
                    'en' => [
                        'subject' => 'Appointment Updated',
                        'message' => 'Booking has been updated.',
                    ],
                    'es' => [
                        'subject' => 'Cita actualizada',
                        'message' => 'La reserva ha sido actualizada.',
                    ],
                    'pl' => [
                        'subject' => 'Wizyta zaktualizowana',
                        'message' => 'Rezerwacja została zaktualizowana.',
                    ],
                ],
            ];

            $booking_id = $request->post('booking_id');
            $visit_type = $request->post('visit_type');
            $slots = @json_encode($request->post('slots'));
            $services = @json_encode($request->post('services'));
            $services_array = $request->post('services');

            /*
            |--------------------------------------------------------------------------
            | Check Worker Availability
            |--------------------------------------------------------------------------
            */
            if (empty($booking_id)) {
                $worker_id = $request->post('worker_id');
                $booking_date = $request->post('booking_date');

                if (!empty($worker_id)) {
                    $existedBooking = select(
                        'booking',
                        '*',
                        [
                            ['worker_id', '=', $worker_id],
                            ['slots', '=', @json_encode($request->post('slots'))],
                            ['booking_date', '=', date('Y-m-d', strtotime($booking_date))]
                        ]
                    )->first();

                    if (!empty($existedBooking)) {
                        return response()->json([
                            'result' => -2,
                            'msg' => "This worker is not available on this slot for this booking date! Choose another one."
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Calculate Total Service Time
            |--------------------------------------------------------------------------
            */
            $servicetime = array_map(function ($item) {
                return select(
                    'services',
                    ['service_time_taken', 'service_id'],
                    [['service_id', '=', $item]]
                )->first();
            }, $services_array ?? []);

            $salon_closing_time = @select(
                'salon',
                ['salon_id', 'closing_time'],
                [['salon_id', '=', $request->post('salon_id')]]
            )->first()->closing_time;

            $slot_array = @$request->post('slots')[0];

            $slot_time_data = select(
                'slots',
                ['slot_time'],
                [['slot_id', '=', $slot_array]]
            )->first();

            $total_time = 0;

            if (!empty($servicetime)) {
                foreach ($servicetime as $service) {
                    $time_taken = !empty($service->service_time_taken)
                        ? $service->service_time_taken
                        : 0;

                    $total_time += $time_taken;
                }
            }

            $start_time = @Carbon::createFromFormat(
                'H:i:s',
                $slot_time_data->slot_time
            );

            $end_time = @Carbon::createFromFormat(
                'H:i:s',
                $salon_closing_time
            );

            $time_diff_in_minutes = $end_time->diffInMinutes($start_time);

            /*
            |--------------------------------------------------------------------------
            | Check Salon Closing Time
            |--------------------------------------------------------------------------
            */
            if ($time_diff_in_minutes < $total_time &&(date('Y-m-d') == date('Y-m-d', strtotime($booking_date)) ||strtotime($booking_date) > strtotime(date('Y-m-d')))
            ) {
                return response()->json([
                    'result' => -2,
                    'msg' => "We're delighted to have you here, but please be mindful of the time. Closing time is near, and we'll be closing soon"
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Slot
            |--------------------------------------------------------------------------
            */
            if (empty(array_filter($request->post('slots')))) {
                return response()->json([
                    'result' => -2,
                    'msg' => "Please select at least one slot"
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Create / Update Customer
            |--------------------------------------------------------------------------
            */
            $customerexist = select(
                'customers',
                '*',
                [
                    ['status', '!=', 'Deleted'],
                    ['email', '=', $request->post('email')]
                ]
            )->first();

            $email = $request->post('email');
            $shopify_user_id = $request->post('shopify_user_id');

            if (!empty($customerexist) && $email) {

                $customer = [
                    'gender' => !empty($request->post('gender'))
                        ? $request->post('gender')
                        : null,

                    'age' => !empty($request->post('age'))
                        ? $request->post('age')
                        : null,

                    'dob' => !empty($request->post('dob'))
                        ? $request->post('dob')
                        : null,

                    'pesel' => !empty($request->post('pesel_no'))
                        ? $request->post('pesel_no')
                        : null,

                    'updated_at' => now()
                ];

                if (!empty($shopify_user_id)) {
                    $customer['shopify_user_id'] = $shopify_user_id;
                }

                update(
                    'customers',
                    'customer_id',
                    $customerexist->customer_id,
                    $customer
                );

                $customer_id = $customerexist->customer_id;

            } else {

                $customer = [
                    'customer_name' => !empty($request->post('customer_name'))
                        ? $request->post('customer_name')
                        : null,

                    'gender' => !empty($request->post('gender'))
                        ? $request->post('gender')
                        : null,

                    'email' => !empty($request->post('email'))
                        ? $request->post('email')
                        : null,

                    'phone' => !empty($request->post('phone'))
                        ? $request->post('phone')
                        : null,

                    'age' => !empty($request->post('age'))
                        ? $request->post('age')
                        : null,

                    'dob' => !empty($request->post('dob'))
                        ? $request->post('dob')
                        : null,

                    'pesel' => !empty($request->post('pesel_no'))
                        ? $request->post('pesel_no')
                        : null,

                    'created_at' => now(),
                    'updated_at' => now()
                ];

                if (!empty($shopify_user_id)) {
                    $customer['shopify_user_id'] = $shopify_user_id;
                }

                $customer_id = insert('customers', $customer);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE EXISTING BOOKING
            |--------------------------------------------------------------------------
            */
            if (!empty($booking_id)) {

                $oldbookingdata = select(
                    'booking',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['status', '=', 'Active']
                    ]
                )->first();

                $oldagreement = select(
                    'agreement',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['agreement_id', '=', @$oldbookingdata->agreement_id],
                        ['status', '=', 'Active']
                    ]
                )->first();

                $oldagreementdocument = select(
                    'agreement_documents',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['document_type', '=', 'agreement'],
                        ['status', '=', 'Active']
                    ]
                )->first();

                $oldagreementsignature = select(
                    'agreement_documents',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['document_type', '=', 'signature'],
                        ['status', '=', 'Active']
                    ]
                )->first();

                if (!empty($oldbookingdata)) {

                    $signature_image = null;
                    $contract_file = null;

                    if ($request->hasFile('signature')) {
                        $signature_image = singleAwsUpload($request, 'signature');
                    }

                    if ($request->hasFile('contract_file')) {
                        $contract_file = singleAwsUpload($request, 'contract_file');
                    }

                    if (!$request->hasFile('signature')) {
                        $bookingstatus = 'inprogress';
                    } else {
                        $bookingstatus = 'pending';
                    }

                    $updatedata = [
                        'booking_for' => $request->post('customer_name'),
                        'contact_no' => !empty($request->post('phone'))
                            ? $request->post('phone')
                            : null,

                        'worker_id' => $request->post('worker_id'),
                        'salon_id' => $request->post('salon_id'),
                        'currency_id' => $request->post('currency_id'),
                        'currency_code' => $request->post('currency_code'),
                        'is_confirmed' => 'no',
                        'isArchived' => 'no',
                        'agreement_id' => @$oldagreement->agreement_id,
                        'slots' => $slots,
                        'services' => $services,

                        'booking_date' => date(
                            'Y-m-d',
                            strtotime($request->post('booking_date'))
                        ),

                        'booking_status' => 'inprogress',
                        'total_pay_amt' => $request->post('total_pay_amt'),
                        'payment_type' => $request->post('payment_type'),
                        'pre_payment_made' => $request->post('pre_payment_made'),
                        'pre_payment_amt' => $request->post('pre_payment_amt') ?? null,
                        'visit_type' => $visit_type,
                        'preferred_lang' => $request->post('preferred_lang') ?? null,
                        'secondary_lang' => $request->post('secondary_lang') ?? null,
                        'status' => 'Active',
                    ];

                    update(
                        'booking',
                        'booking_id',
                        $booking_id,
                        $updatedata
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Booking Availability
                    |--------------------------------------------------------------------------
                    */
                    $slotdetails = $request->post('slots');

                    if (!empty($slotdetails)) {

                        // Uncomment if old availability records should be removed
                        // before inserting the updated slots.
                        // delete('booking_availability', 'booking_id', $booking_id);

                        foreach ($slotdetails as $val) {

                            $temp = [
                                'booking_id' => $booking_id,
                                'salon_id' => $request->post('salon_id'),
                                'booking_date' => date(
                                    'Y-m-d',
                                    strtotime($request->post('booking_date'))
                                ),
                                'slot_id' => $val
                            ];

                            insert('booking_availability', $temp);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Agreement Document
                    |--------------------------------------------------------------------------
                    */
                    if (!empty($oldagreementdocument)) {
                        update(
                            'agreement_documents',
                            'document_id',
                            $oldagreementdocument->document_id,
                            [
                                'document_file' => null
                            ]
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Signature
                    |--------------------------------------------------------------------------
                    */
                    if (!empty($signature_image)) {

                        deleteMultiple(
                            'agreement_documents',
                            [
                                'booking_id' => $booking_id,
                                'document_type' => 'signature'
                            ]
                        );

                        insert('agreement_documents', [
                            'booking_id' => $booking_id,
                            'document_file' => $signature_image,
                            'document_type' => 'signature',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Contract
                    |--------------------------------------------------------------------------
                    */
                    if (!empty($contract_file)) {

                        deleteMultiple(
                            'agreement_documents',
                            [
                                'booking_id' => $booking_id,
                                'document_type' => 'contract'
                            ]
                        );

                        insert('agreement_documents', [
                            'booking_id' => $booking_id,
                            'contract_file' => $contract_file,
                            'document_type' => 'contract',
                        ]);
                    }
                }

                $result = $booking_id;

                $salontitle = "Appointment Updated";
                $salonmsg = "Booking has been updated.";

                $notificationType = 'appointment_updated';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CREATE NEW BOOKING
                |--------------------------------------------------------------------------
                */
                $signature_image = null;
                $contract_file = null;

                if ($request->hasFile('signature')) {
                    $signature_image = singleAwsUpload($request, 'signature');
                }

                if ($request->hasFile('contract_file')) {
                    $contract_file = singleAwsUpload($request, 'contract_file');
                }

                $payment = !empty($request->post('payment_type'))
                    ? $request->post('payment_type')
                    : "cash";

                $worker_id = $request->post('worker_id');

                $insertdata = [
                    'customer_id' => $customer_id,
                    'booking_for' => $request->post('customer_name'),
                    'contact_no' => !empty($request->post('phone'))
                        ? $request->post('phone')
                        : null,

                    'worker_id' => (
                        !empty($worker_id) &&
                        ($worker_id !== 'null')
                    )
                        ? $worker_id
                        : null,

                    'salon_id' => $request->post('salon_id'),
                    'currency_id' => $request->post('currency_id'),
                    'currency_code' => $request->post('currency_code'),
                    'is_confirmed' => 'no',
                    'isArchived' => 'no',
                    'agreement_id' => null,
                    'booking_status' => 'Pending',
                    'total_pay_amt' => $request->post('total_pay_amt'),
                    'payment_type' => $payment,
                    'contract_signed' => $request->post('contract_signed'),
                    'pre_payment_made' => $request->post('pre_payment_made'),
                    'pre_payment_amt' => $request->post('pre_payment_amt') ?? null,

                    'booking_date' => date(
                        'Y-m-d',
                        strtotime($request->post('booking_date'))
                    ),

                    'slots' => $slots,
                    'services' => $services,
                    'visit_type' => $visit_type,
                    'preferred_lang' => $request->post('preferred_lang'),
                    'secondary_lang' => $request->post('secondary_lang'),
                    'status' => 'Active',
                ];

                $result = insert('booking', $insertdata);

                if ($result) {

                    $slotsdetails = $request->post('slots');

                    if (!empty($slotsdetails)) {

                        foreach ($slotsdetails as $val) {

                            $temp = [
                                'booking_id' => $result,
                                'salon_id' => $request->post('salon_id'),
                                'booking_date' => date(
                                    'Y-m-d',
                                    strtotime($request->post('booking_date'))
                                ),
                                'slot_id' => $val
                            ];

                            insert('booking_availability', $temp);
                        }
                    }
                }

                $salontitle = "New Appointment";
                $salonmsg = "New booking has been made at your salon.";

                $notificationType = 'appointment_booked';
            }

            /*
            |--------------------------------------------------------------------------
            | Agreement / Signature
            |--------------------------------------------------------------------------
            */
            if ($result) {

                $agreement = [
                    'customer_id' => $customer_id,
                    'booking_id' => $result,
                    'is_signed' => 'yes',
                    'date_of_sign' => now(),
                    'status' => 'Active',
                ];

                if (empty($booking_id)) {

                    $agreementresult = insert('agreement', $agreement);

                    if ($agreementresult) {

                        insert('agreement_documents', [
                            'agreement_id' => $agreementresult,
                            'document_file' => null,
                            'document_type' => 'agreement'
                        ]);

                        update(
                            'booking',
                            'booking_id',
                            $result,
                            [
                                'agreement_id' => $agreementresult
                            ]
                        );
                    }

                    if ($signature_image) {

                        insert('agreement_documents', [
                            'booking_id' => $result,
                            'document_file' => $signature_image,
                            'document_type' => 'signature',
                        ]);
                    }

                    if (!empty($contract_file)) {

                        insert('agreement_documents', [
                            'booking_id' => $result,
                            'contract_file' => $contract_file,
                            'document_type' => 'contract',
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Get Complete Booking Details
                |--------------------------------------------------------------------------
                */
                $booking_id = $result;

                $bookings = select(
                    'booking',
                    '*',
                    [['booking_id', '=', $booking_id]]
                )->first();

                if (!empty($bookings)) {

                    $services = json_decode($bookings->services);
                    $slots = json_decode($bookings->slots);

                    if (empty($services[0])) {
                        $bookings->servicedetails = [];
                    } else {
                        $bookings->servicedetails =
                            SalonModel::getServices($services);
                    }

                    if (empty($slots)) {
                        $bookings->slotsdetails = [];
                    } else {
                        $bookings->slotsdetails =
                            SalonModel::getSlots($slots);
                    }

                    $bookings->signature = select(
                        'agreement_documents',
                        '*',
                        [
                            ['booking_id', '=', $booking_id],
                            ['document_type', '=', 'signature']
                        ]
                    )->first();

                    if (
                        isset($bookings->signature->document_file) &&
                        !str_contains(
                            $bookings->signature->document_file,
                            'amazonaws.com'
                        )
                    ) {
                        $bookings->signature->document_file =
                            baseURL($bookings->signature->document_file);
                    }

                    $bookings->agreementdocument = select(
                        'agreement_documents',
                        '*',
                        [
                            ['booking_id', '=', $booking_id],
                            ['document_type', '=', 'agreement']
                        ]
                    )->first();

                    $bookings->customer = @select(
                        'customers',
                        '*',
                        [
                            ['status', '!=', 'Deleted'],
                            ['customer_id', '=', $bookings->customer_id]
                        ]
                    )->first();

                    $bookings->salon_details = @select(
                        'salon',
                        '*',
                        [
                            ['status', '!=', 'Deleted'],
                            ['salon_id', '=', $bookings->salon_id]
                        ]
                    )->first();

                    if (
                        isset($bookings->salon_details->thumbnail) &&
                        !str_contains(
                            $bookings->salon_details->thumbnail,
                            'amazonaws.com'
                        )
                    ) {
                        $bookings->salon_details->thumbnail =
                            baseURL(@$bookings->salon_details->salon_thumbnail);
                    }

                    $bookings->worker_name = @select(
                        'salon_worker',
                        '*',
                        [
                            ['status', '!=', 'Deleted'],
                            ['worker_id', '=', $bookings->worker_id]
                        ]
                    )->first();
                }

                /*
                |--------------------------------------------------------------------------
                | Customer Email + Notification
                |--------------------------------------------------------------------------
                */
                if (empty($signature_image)) {

                    $lang = $request->post('preferred_lang')
                        ?? $request->post('lang');

                    if ($lang == 'en') {

                        $maildata['subjecttext'] =
                            'Your visit is waiting for confirmation. You will receive a message from us within 24 hours.';

                        $maildata['deartext'] = 'Dear';
                        $maildata['para2text'] = 'Your booking details:';
                        $maildata['bookingdatetext'] = 'Booking Date';
                        $maildata['slottext'] = 'Slot';
                        $maildata['servicetext'] = 'Service';

                        $maildata['footerpara1text'] =
                            'If you have additional questions or need help, please contact us at:';

                        $maildata['contactno1'] =
                            'SHOWROOMS POLAND : +48578903292';

                        $maildata['contactno2'] =
                            'SHOWROOMS SPAIN : +34651439815';

                        $maildata['footerpara2text'] =
                            'Thank you for choosing Hollywood Hair, we hope it will be a unique experience for you.';

                        $maildata['footerpara3text'] =
                            'Best regards';
                    }

                    if ($lang == 'es') {

                        $maildata['subjecttext'] =
                            'Su visita está pendiente de confirmación. Recibirá un mensaje nuestro dentro de las 24 horas.';

                        $maildata['deartext'] = 'Estimado';
                        $maildata['para2text'] = 'Los datos de tu reserva:';
                        $maildata['bookingdatetext'] = 'Fecha de reserva';
                        $maildata['slottext'] = 'Ranura';
                        $maildata['servicetext'] = 'Servicio';

                        $maildata['footerpara1text'] =
                            'Si tiene preguntas adicionales o necesita ayuda, contáctenos en:';

                        $maildata['contactno1'] =
                            'SALA DE EXPOSICIONES POLONIA : +48578903292';

                        $maildata['contactno2'] =
                            'SALAS DE EXPOSICIÓN ESPAÑA : +34651439815';

                        $maildata['footerpara2text'] =
                            'Gracias por elegir Hollywood Hair, esperamos que sea una experiencia única para ti.';

                        $maildata['footerpara3text'] =
                            'Atentamente';
                    }

                    if ($lang == 'pl') {

                        $maildata['subjecttext'] =
                            'Twoja wizyta czeka na potwierdzenie. W ciągu 24h otrzymasz od nas wiadomość.';

                        $maildata['deartext'] = 'Hej';
                        $maildata['para2text'] = 'Szczegóły Twojej rezerwacji:';
                        $maildata['bookingdatetext'] = 'Data wizyty';
                        $maildata['slottext'] = 'Godziny zabiegu';
                        $maildata['servicetext'] = 'Usługa';

                        $maildata['footerpara1text'] =
                            'Jeśli masz dodatkowe pytania lub potrzebujesz pomocy, skontaktuj się z nami pod numerem:';

                        $maildata['contactno1'] =
                            'SALONY POLSKA : +48578903292';

                        $maildata['contactno2'] =
                            'SALONY HISZPANIA : +34651439815';

                        $maildata['footerpara2text'] =
                            'Dziękujemy za wybór Hollywood Hair, mamy nadzieję, że będzie to dla Ciebie wyjątkowe doświadczenie.';

                        $maildata['footerpara3text'] =
                            'Pozdrawiamy';
                    }

                    $maildata['to'] = $request->post('email');
                    $maildata['subject'] = 'Appointment Booked';
                    $maildata['view_name'] = 'bookinginfo';
                    $maildata['name'] = $request->post('customer_name');
                    $maildata['email'] = $request->post('email');
                    $maildata['booking_details'] = $bookings;

                    if (!empty($maildata['email'])) {
                        sendMail($maildata);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Customer Notification
                    |--------------------------------------------------------------------------
                    */
                    $salon = select(
                        'salon',
                        '*',
                        [
                            ['status', '!=', 'Deleted'],
                            ['salon_id', '=', $request->post('salon_id')]
                        ]
                    )->first();

                    $title = $notificationTranslations[$notificationType]['en']['subject'];

                    $salonName = !empty($salon->salon_name_en)
                        ? $salon->salon_name_en
                        : '';

                    /*
                    |--------------------------------------------------------------------------
                    | English
                    |--------------------------------------------------------------------------
                    */
                    $msg = !empty($salonName)
                        ? $notificationTranslations[$notificationType]['en']['message']
                            . " at " . $salonName
                        : $notificationTranslations[$notificationType]['en']['message'];

                    /*
                    |--------------------------------------------------------------------------
                    | Spanish
                    |--------------------------------------------------------------------------
                    */
                    $subject_es =
                        $notificationTranslations[$notificationType]['es']['subject'];

                    $message_es =
                        $notificationTranslations[$notificationType]['es']['message'];

                    if (!empty($salonName)) {

                        $message_es .=
                            " en " . $salonName;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Polish
                    |--------------------------------------------------------------------------
                    */
                    $subject_pl =
                        $notificationTranslations[$notificationType]['pl']['subject'];

                    $message_pl =
                        $notificationTranslations[$notificationType]['pl']['message'];

                    if (!empty($salonName)) {

                        $message_pl .=
                            " w " . $salonName;
                    }

                    $notificationData = [
                        'customer_id' => @$bookings->customer_id,
                        'subject' => $title,
                        'message' => $msg,
                        'subject_es' => $subject_es,
                        'message_es' => $message_es,
                        'subject_pl' => $subject_pl,
                        'message_pl' => $message_pl,
                        'notification_type' => 'customer',
                        'booking_id' => $bookings->booking_id
                    ];

                    $customer = @select(
                        'customers',
                        '*',
                        [
                            ['status', '=', 'Active'],
                            ['customer_id', '=', @$bookings->customer_id]
                        ]
                    )->first();

                    insert('notification', $notificationData);

                    @sendCustomerFirebaseNotification(
                        @$customer->shopify_user_id,
                        $msg,
                        $title
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Salon Notification
                |--------------------------------------------------------------------------
                */
                $subject_es =
                    $notificationTranslations[$notificationType]['es']['subject'];

                $message_es =
                    $notificationTranslations[$notificationType]['es']['message'];

                $subject_pl =
                    $notificationTranslations[$notificationType]['pl']['subject'];

                $message_pl =
                    $notificationTranslations[$notificationType]['pl']['message'];

                $notificationData = [
                    'salon_id' => @$bookings->salon_id,
                    'subject' => $salontitle,
                    'message' => $salonmsg,
                    'subject_es' => $subject_es,
                    'message_es' => $message_es,
                    'subject_pl' => $subject_pl,
                    'message_pl' => $message_pl,
                    'notification_type' => 'salon',
                    'booking_id' => $bookings->booking_id
                ];

                insert('notification', $notificationData);

                sendFirebaseNotification(
                    @$bookings->salon_id,
                    $salonmsg,
                    $salontitle
                );

                return response()->json([
                    'result' => 1,
                    'msg' => 'Booked successfully.',
                    'data' => $bookings
                ], 200);

            } else {

                return response()->json([
                    'result' => -1,
                    'msg' => 'Something Went Wrong'
                ]);
            }

        } catch (\Exception $e) {

            return response()->json([
                'result' => -1,
                'msg' => 'An error occurred while processing your request: ' . $e->getMessage()
            ], 500);
        }
    }


    public function bookingV2(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'salon_id' => 'required',
                'customer_name' => 'required|string|max:150'
            ], [
                'required' => 'This :attribute is Required',
                'string' => 'The :attribute must be a string',
                'max' => 'The :attribute may not be greater than :max characters'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'result' => 0,
                    'msg' => $validator->errors()->first()
                ]);
            }

            if ($request->has('g-recaptcha-response')) {
                $recaptcha_response = $request->input('g-recaptcha-response');

                if (empty($recaptcha_response)) {
                    return response()->json([
                        'result' => -5,
                        'msg' => 'Please fill the reCAPTCHA'
                    ]);
                }

                $recaptcha_secret = "6LceZAYqAAAAAHqfRTPx6Olfda8r0Ulh1JyB3L3D";

                if (!empty($recaptcha_response)) {

                    $response = file_get_contents(
                        "https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response"
                    );

                    $responseKeys = json_decode($response, true);

                    if (intval($responseKeys["success"]) !== 1) {
                        return response()->json([
                            'result' => -5,
                            'msg' => "Failed to verify reCAPTCHA"
                        ]);
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | NOTIFICATION TRANSLATIONS
            |--------------------------------------------------------------------------
            */

            $notificationTranslations = [

                'appointment_booked' => [
                    'en' => [
                        'subject' => 'Appointment Booked',
                        'message' => 'New booking has been successful',
                    ],
                    'es' => [
                        'subject' => 'Cita reservada',
                        'message' => 'La nueva reserva se ha realizado correctamente',
                    ],
                    'pl' => [
                        'subject' => 'Wizyta zarezerwowana',
                        'message' => 'Nowa rezerwacja została pomyślnie dokonana',
                    ],
                ],

                'appointment_updated' => [
                    'en' => [
                        'subject' => 'Appointment Updated',
                        'message' => 'Booking has been updated.',
                    ],
                    'es' => [
                        'subject' => 'Cita actualizada',
                        'message' => 'La reserva ha sido actualizada.',
                    ],
                    'pl' => [
                        'subject' => 'Wizyta zaktualizowana',
                        'message' => 'Rezerwacja została zaktualizowana.',
                    ],
                ],
            ];


            /*
            |--------------------------------------------------------------------------
            | REQUEST DATA
            |--------------------------------------------------------------------------
            */

            $salon_id = $request->post('salon_id');

            /*
            * IMPORTANT:
            * This is the same create/update mechanism as old booking API.
            */
            $booking_id = $request->post('booking_id');

            $category_id = $request->post('category_id');
            $visit_type = $request->post('visit_type');
            $worker_id = $request->post('worker_id');


            /*
            |--------------------------------------------------------------------------
            | SERVICES
            |--------------------------------------------------------------------------
            */

            $services_array = null;

            if ($request->has('services')) {

                $services_input = $request->post('services');

                if (is_array($services_input)) {

                    $services_array = $services_input;

                } else {

                    $services_array = json_decode(
                        $services_input,
                        true
                    );
                }

                if (!is_array($services_array)) {
                    $services_array = [];
                }
            }


            /*
            |--------------------------------------------------------------------------
            | BOOKING DATE
            |--------------------------------------------------------------------------
            */

            $booking_date = null;

            if (
                $request->has('booking_date') &&
                !empty($request->post('booking_date'))
            ) {

                $booking_date = date(
                    'Y-m-d',
                    strtotime($request->post('booking_date'))
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BOOKING TIME
            |--------------------------------------------------------------------------
            */

            $booking_time = null;

            if (
                $request->has('booking_time') &&
                !empty($request->post('booking_time'))
            ) {

                $booking_time = $request->post('booking_time');

                if (!preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $booking_time
                )) {

                    return response()->json([
                        'result' => 0,
                        'msg' => 'The booking time must be in 24-hour format (HH:mm).'
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE SALON
            |--------------------------------------------------------------------------
            */

            $salon = select(
                'salon',
                '*',
                [
                    ['status', '!=', 'Deleted'],
                    ['salon_id', '=', $salon_id]
                ]
            )->first();

            if (empty($salon)) {

                return response()->json([
                    'result' => -2,
                    'msg' => 'Salon not found.'
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE CATEGORY
            |--------------------------------------------------------------------------
            */

            $category = null;

            if (!empty($category_id)) {

                $category = select(
                    'categories',
                    '*',
                    [
                        ['status', '=', 'Active'],
                        ['id', '=', $category_id]
                    ]
                )->first();

                if (empty($category)) {

                    return response()->json([
                        'result' => -2,
                        'msg' => 'Category not found or inactive.'
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE SERVICES
            |--------------------------------------------------------------------------
            */

            $services = null;

            if ($request->has('services')) {

                $services_array = array_values(
                    array_filter(
                        $services_array ?? [],
                        function ($value) {
                            return !empty($value);
                        }
                    )
                );

                if (empty($services_array)) {

                    return response()->json([
                        'result' => -2,
                        'msg' => 'Please select at least one service.'
                    ]);
                }


                /*
                * Get service records.
                */

                $serviceRecords = DB::table('services')
                    ->select(
                        'service_id',
                        'category_id',
                        'service_time_taken',
                        'is_archived'
                    )
                    ->whereIn('service_id', $services_array)
                    ->get();


                /*
                |--------------------------------------------------------------------------
                | CHECK ALL SERVICES EXIST
                |--------------------------------------------------------------------------
                */

                if (
                    $serviceRecords->count() !==
                    count($services_array)
                ) {

                    return response()->json([
                        'result' => -2,
                        'msg' => 'One or more selected services were not found.'
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK SERVICES CATEGORY
                |--------------------------------------------------------------------------
                */

                if (!empty($category_id)) {

                    foreach ($serviceRecords as $service) {

                        if (
                            (string) $service->category_id !==
                            (string) $category_id
                        ) {

                            return response()->json([
                                'result' => -2,
                                'msg' => 'All selected services must belong to the selected category.'
                            ]);
                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CHECK SERVICES ACTIVE
                |--------------------------------------------------------------------------
                */

                foreach ($serviceRecords as $service) {

                    if (
                        isset($service->is_archived) &&
                        strtolower($service->is_archived) !== 'no'
                    ) {

                        return response()->json([
                            'result' => -2,
                            'msg' => 'One or more selected services are no longer available.'
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | STORE SERVICES
                |--------------------------------------------------------------------------
                */

                $services = json_encode($services_array);
            }


            /*
            |--------------------------------------------------------------------------
            | CUSTOMER
            |--------------------------------------------------------------------------
            */

            $email = $request->post('email');
            $shopify_user_id = $request->post('shopify_user_id');

            $customer_id = null;
            $customerexist = null;


            if (!empty($email)) {

                $customerexist = select(
                    'customers',
                    '*',
                    [
                        ['status', '!=', 'Deleted'],
                        ['email', '=', $email]
                    ]
                )->first();
            }


            if (!empty($customerexist)) {

                $customer = [

                    'customer_name' => $request->has('customer_name')
                        ? $request->post('customer_name')
                        : $customerexist->customer_name,

                    'gender' => $request->has('gender')
                        ? $request->post('gender')
                        : $customerexist->gender,

                    'age' => $request->has('age')
                        ? $request->post('age')
                        : $customerexist->age,

                    'dob' => $request->has('dob')
                        ? $request->post('dob')
                        : $customerexist->dob,

                    'pesel' => $request->has('pesel_no')
                        ? $request->post('pesel_no')
                        : $customerexist->pesel,

                    'phone' => $request->has('phone')
                        ? $request->post('phone')
                        : $customerexist->phone,

                    'updated_at' => now()
                ];


                if (!empty($shopify_user_id)) {
                    $customer['shopify_user_id'] = $shopify_user_id;
                }


                update(
                    'customers',
                    'customer_id',
                    $customerexist->customer_id,
                    $customer
                );

                $customer_id = $customerexist->customer_id;

            } else {

                if (
                    !empty($email) ||
                    $request->has('customer_name') ||
                    $request->has('phone') ||
                    $request->has('gender') ||
                    $request->has('age') ||
                    $request->has('dob') ||
                    $request->has('pesel_no') ||
                    !empty($shopify_user_id)
                ) {

                    $customer = [

                        'customer_name' => $request->post('customer_name'),

                        'gender' => $request->post('gender'),

                        'email' => $email,

                        'phone' => $request->post('phone'),

                        'age' => $request->post('age'),

                        'dob' => $request->post('dob'),

                        'pesel' => $request->post('pesel_no'),

                        'created_at' => now(),

                        'updated_at' => now()
                    ];


                    if (!empty($shopify_user_id)) {
                        $customer['shopify_user_id'] = $shopify_user_id;
                    }


                    $customer_id = insert(
                        'customers',
                        $customer
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | FILES
            |--------------------------------------------------------------------------
            */

            $signature_image = null;
            $contract_file = null;


            if ($request->hasFile('signature')) {

                $signature_image = singleAwsUpload(
                    $request,
                    'signature'
                );
            }


            if ($request->hasFile('contract_file')) {

                $contract_file = singleAwsUpload(
                    $request,
                    'contract_file'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PAYMENT
            |--------------------------------------------------------------------------
            */

            $payment = !empty($request->post('payment_type'))
                ? $request->post('payment_type')
                : 'cash';


            /*
            |--------------------------------------------------------------------------
            | CREATE / UPDATE BOOKING
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Same behavior as OLD booking() API:
            |
            | booking_id exists -> UPDATE
            |
            | booking_id empty -> CREATE
            |
            |--------------------------------------------------------------------------
            */

            if (!empty($booking_id)) {


                /*
                |--------------------------------------------------------------------------
                | UPDATE EXISTING BOOKING
                |--------------------------------------------------------------------------
                */

                $oldbookingdata = select(
                    'booking',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['status', '=', 'Active']
                    ]
                )->first();


                if (empty($oldbookingdata)) {

                    return response()->json([
                        'result' => -2,
                        'msg' => 'Booking not found or inactive.'
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | EXISTING AGREEMENT
                |--------------------------------------------------------------------------
                */

                $oldagreement = select(
                    'agreement',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['agreement_id', '=', @$oldbookingdata->agreement_id],
                        ['status', '=', 'Active']
                    ]
                )->first();


                /*
                |--------------------------------------------------------------------------
                | EXISTING AGREEMENT DOCUMENT
                |--------------------------------------------------------------------------
                */

                $oldagreementdocument = select(
                    'agreement_documents',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['document_type', '=', 'agreement'],
                        ['status', '=', 'Active']
                    ]
                )->first();


                /*
                |--------------------------------------------------------------------------
                | EXISTING SIGNATURE
                |--------------------------------------------------------------------------
                */

                $oldagreementsignature = select(
                    'agreement_documents',
                    '*',
                    [
                        ['booking_id', '=', $booking_id],
                        ['document_type', '=', 'signature'],
                        ['status', '=', 'Active']
                    ]
                )->first();


                /*
                |--------------------------------------------------------------------------
                | UPDATE DATA
                |--------------------------------------------------------------------------
                */

                $updatedata = [

                    'customer_id' => !empty($customer_id)
                        ? $customer_id
                        : $oldbookingdata->customer_id,

                    'booking_for' => $request->post('customer_name'),

                    'contact_no' => $request->has('phone')
                        ? $request->post('phone')
                        : $oldbookingdata->contact_no,

                    'worker_id' => $request->has('worker_id')
                        ? (
                            !empty($worker_id)
                                ? $worker_id
                                : null
                        )
                        : $oldbookingdata->worker_id,

                    'salon_id' => $salon_id,

                    'category_id' => $request->has('category_id')
                        ? (
                            !empty($category_id)
                                ? $category_id
                                : null
                        )
                        : $oldbookingdata->category_id,

                    'currency_id' => $request->has('currency_id')
                        ? $request->post('currency_id')
                        : $oldbookingdata->currency_id,

                    'currency_code' => $request->has('currency_code')
                        ? $request->post('currency_code')
                        : $oldbookingdata->currency_code,

                    'is_confirmed' => 'no',

                    'isArchived' => 'no',

                    'agreement_id' => @$oldagreement->agreement_id,

                    'booking_status' => 'inprogress',

                    'contract_signed' => $request->has('contract_signed')
                        ? $request->post('contract_signed')
                        : $oldbookingdata->contract_signed,

                    'total_pay_amt' => $request->has('total_pay_amt')
                        ? $request->post('total_pay_amt')
                        : $oldbookingdata->total_pay_amt,

                    'payment_type' => $request->has('payment_type')
                        ? $payment
                        : $oldbookingdata->payment_type,

                    'pre_payment_made' => $request->has('pre_payment_made')
                        ? $request->post('pre_payment_made')
                        : $oldbookingdata->pre_payment_made,

                    'pre_payment_amt' => $request->has('pre_payment_amt')
                        ? $request->post('pre_payment_amt')
                        : $oldbookingdata->pre_payment_amt,

                    'booking_date' => $request->has('booking_date')
                        ? $booking_date
                        : $oldbookingdata->booking_date,

                    'booking_time' => $request->has('booking_time')
                        ? $booking_time
                        : $oldbookingdata->booking_time,

                    /*
                    * V2 does not use legacy slots.
                    */
                    'slots' => null,

                    'services' => $request->has('services')
                        ? $services
                        : $oldbookingdata->services,

                    'visit_type' => $request->has('visit_type')
                        ? $visit_type
                        : $oldbookingdata->visit_type,

                    'preferred_lang' => $request->has('preferred_lang')
                        ? $request->post('preferred_lang')
                        : $oldbookingdata->preferred_lang,

                    'secondary_lang' => $request->has('secondary_lang')
                        ? $request->post('secondary_lang')
                        : $oldbookingdata->secondary_lang,

                    'status' => 'Active',
                ];


                /*
                |--------------------------------------------------------------------------
                | UPDATE BOOKING
                |--------------------------------------------------------------------------
                */

                update(
                    'booking',
                    'booking_id',
                    $booking_id,
                    $updatedata
                );


                $result = $booking_id;


                /*
                |--------------------------------------------------------------------------
                | UPDATE AGREEMENT DOCUMENT
                |--------------------------------------------------------------------------
                */

                if (!empty($oldagreementdocument)) {

                    update(
                        'agreement_documents',
                        'document_id',
                        $oldagreementdocument->document_id,
                        [
                            'document_file' => null
                        ]
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE SIGNATURE
                |--------------------------------------------------------------------------
                */

                if (!empty($signature_image)) {

                    deleteMultiple(
                        'agreement_documents',
                        [
                            'booking_id' => $booking_id,
                            'document_type' => 'signature'
                        ]
                    );


                    insert(
                        'agreement_documents',
                        [
                            'booking_id' => $booking_id,
                            'document_file' => $signature_image,
                            'document_type' => 'signature',
                        ]
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE CONTRACT
                |--------------------------------------------------------------------------
                */

                if (!empty($contract_file)) {

                    deleteMultiple(
                        'agreement_documents',
                        [
                            'booking_id' => $booking_id,
                            'document_type' => 'contract'
                        ]
                    );


                    insert(
                        'agreement_documents',
                        [
                            'booking_id' => $booking_id,
                            'contract_file' => $contract_file,
                            'document_type' => 'contract',
                        ]
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE NOTIFICATION
                |--------------------------------------------------------------------------
                */

                $salontitle = "Appointment Updated";
                $salonmsg = "Booking has been updated.";

                $notificationType = 'appointment_updated';


            } else {


                /*
                |--------------------------------------------------------------------------
                | CREATE NEW BOOKING
                |--------------------------------------------------------------------------
                */

                $insertdata = [

                    'customer_id' => $customer_id,

                    'booking_for' => $request->post('customer_name'),

                    'contact_no' => $request->post('phone'),

                    'worker_id' => !empty($worker_id)
                        ? $worker_id
                        : null,

                    'salon_id' => $salon_id,

                    'category_id' => !empty($category_id)
                        ? $category_id
                        : null,

                    'currency_id' => $request->post('currency_id'),

                    'currency_code' => $request->post('currency_code'),

                    'is_confirmed' => 'no',

                    'isArchived' => 'no',

                    'agreement_id' => null,

                    'booking_status' => 'Pending',

                    'contract_signed' => $request->post('contract_signed'),

                    'total_pay_amt' => $request->post('total_pay_amt'),

                    'payment_type' => $payment,

                    'pre_payment_made' => $request->post('pre_payment_made'),

                    'pre_payment_amt' => $request->post('pre_payment_amt'),

                    'booking_date' => $booking_date,

                    'booking_time' => $booking_time,

                    /*
                    * V2 does not use legacy slots.
                    */
                    'slots' => null,

                    'services' => $services,

                    'visit_type' => $visit_type,

                    'preferred_lang' => $request->post('preferred_lang'),

                    'secondary_lang' => $request->post('secondary_lang'),

                    'status' => 'Active',
                ];


                /*
                |--------------------------------------------------------------------------
                | INSERT BOOKING
                |--------------------------------------------------------------------------
                */

                $result = insert(
                    'booking',
                    $insertdata
                );


                if (!$result) {

                    return response()->json([
                        'result' => -1,
                        'msg' => 'Something Went Wrong'
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | NEW BOOKING NOTIFICATION
                |--------------------------------------------------------------------------
                */

                $salontitle = "New Appointment";
                $salonmsg = "New booking has been made at your salon.";

                $notificationType = 'appointment_booked';
            }


            /*
            |--------------------------------------------------------------------------
            | AGREEMENT
            |--------------------------------------------------------------------------
            |
            | Same important behavior as OLD booking API:
            |
            | Agreement is created ONLY for a NEW booking.
            |
            | It is NOT created again when booking_id exists.
            |--------------------------------------------------------------------------
            */

            if ($result) {


                if (empty($booking_id)) {

                    $agreement = [

                        'customer_id' => $customer_id,

                        'booking_id' => $result,

                        'is_signed' => 'yes',

                        'date_of_sign' => now(),

                        'status' => 'Active',
                    ];


                    $agreementresult = insert(
                        'agreement',
                        $agreement
                    );


                    if ($agreementresult) {

                        insert(
                            'agreement_documents',
                            [
                                'agreement_id' => $agreementresult,
                                'document_file' => null,
                                'document_type' => 'agreement'
                            ]
                        );


                        update(
                            'booking',
                            'booking_id',
                            $result,
                            [
                                'agreement_id' => $agreementresult
                            ]
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | NEW SIGNATURE
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($signature_image)) {

                        insert(
                            'agreement_documents',
                            [
                                'booking_id' => $result,
                                'document_file' => $signature_image,
                                'document_type' => 'signature',
                            ]
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | NEW CONTRACT
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($contract_file)) {

                        insert(
                            'agreement_documents',
                            [
                                'booking_id' => $result,
                                'contract_file' => $contract_file,
                                'document_type' => 'contract',
                            ]
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | GET COMPLETE BOOKING
                |--------------------------------------------------------------------------
                */

                $booking_id = $result;


                $bookings = select(
                    'booking',
                    '*',
                    [
                        ['booking_id', '=', $booking_id]
                    ]
                )->first();


                if (!empty($bookings)) {


                    /*
                    |--------------------------------------------------------------------------
                    | SERVICES
                    |--------------------------------------------------------------------------
                    */

                    $booking_services = !empty($bookings->services)
                        ? json_decode(
                            $bookings->services,
                            true
                        )
                        : [];


                    if (!empty($booking_services)) {

                        $bookings->servicedetails =
                            SalonModel::getServices(
                                $booking_services
                            );

                    } else {

                        $bookings->servicedetails = [];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SLOTS
                    |--------------------------------------------------------------------------
                    */

                    $bookings->slotsdetails = [];


                    /*
                    |--------------------------------------------------------------------------
                    | CATEGORY
                    |--------------------------------------------------------------------------
                    */

                    $bookings->category_details = !empty($category)
                        ? $category
                        : null;


                    /*
                    |--------------------------------------------------------------------------
                    | BOOKING TIME
                    |--------------------------------------------------------------------------
                    */

                    $bookings->booking_time =
                        !empty($bookings->booking_time)
                            ? $bookings->booking_time
                            : null;


                    /*
                    |--------------------------------------------------------------------------
                    | SIGNATURE
                    |--------------------------------------------------------------------------
                    */

                    $bookings->signature = select(
                        'agreement_documents',
                        '*',
                        [
                            ['booking_id', '=', $booking_id],
                            ['document_type', '=', 'signature']
                        ]
                    )->first();


                    if (
                        isset($bookings->signature->document_file) &&
                        !str_contains(
                            $bookings->signature->document_file,
                            'amazonaws.com'
                        )
                    ) {

                        $bookings->signature->document_file =
                            baseURL(
                                $bookings->signature->document_file
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | AGREEMENT
                    |--------------------------------------------------------------------------
                    */

                    $bookings->agreementdocument = select(
                        'agreement_documents',
                        '*',
                        [
                            ['booking_id', '=', $booking_id],
                            ['document_type', '=', 'agreement']
                        ]
                    )->first();


                    /*
                    |--------------------------------------------------------------------------
                    | CUSTOMER
                    |--------------------------------------------------------------------------
                    */

                    $bookings->customer =
                        !empty($bookings->customer_id)
                            ? @select(
                                'customers',
                                '*',
                                [
                                    ['status', '!=', 'Deleted'],
                                    [
                                        'customer_id',
                                        '=',
                                        $bookings->customer_id
                                    ]
                                ]
                            )->first()
                            : null;


                    /*
                    |--------------------------------------------------------------------------
                    | SALON
                    |--------------------------------------------------------------------------
                    */

                    $bookings->salon_details = @select(
                        'salon',
                        '*',
                        [
                            ['status', '!=', 'Deleted'],
                            [
                                'salon_id',
                                '=',
                                $bookings->salon_id
                            ]
                        ]
                    )->first();


                    if (
                        isset($bookings->salon_details->thumbnail) &&
                        !str_contains(
                            $bookings->salon_details->thumbnail,
                            'amazonaws.com'
                        )
                    ) {

                        $bookings->salon_details->thumbnail =
                            baseURL(
                                @$bookings->salon_details->salon_thumbnail
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | WORKER
                    |--------------------------------------------------------------------------
                    */

                    $bookings->worker_name = null;

                    if (!empty($bookings->worker_id)) {

                        $bookings->worker_name = @select(
                            'salon_worker',
                            '*',
                            [
                                ['status', '!=', 'Deleted'],
                                [
                                    'worker_id',
                                    '=',
                                    $bookings->worker_id
                                ]
                            ]
                        )->first();
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER EMAIL
                |--------------------------------------------------------------------------
                */

                $lang = $request->post('preferred_lang')
                    ?? $request->post('lang')
                    ?? 'en';


                $maildata = [];


                if ($lang == 'en') {

                    $maildata['subjecttext'] =
                        'Your visit is waiting for confirmation. You will receive a message from us within 24 hours.';

                    $maildata['deartext'] = 'Dear';

                    $maildata['para2text'] =
                        'Your booking details:';

                    $maildata['bookingdatetext'] =
                        'Booking Date';

                    $maildata['slottext'] =
                        'Appointment Time';

                    $maildata['servicetext'] =
                        'Service';

                    $maildata['footerpara1text'] =
                        'If you have additional questions or need help, please contact us at:';

                    $maildata['contactno1'] =
                        'SHOWROOMS POLAND : +48578903292';

                    $maildata['contactno2'] =
                        'SHOWROOMS SPAIN : +34651439815';

                    $maildata['footerpara2text'] =
                        'Thank you for choosing Hollywood Hair, we hope it will be a unique experience for you.';

                    $maildata['footerpara3text'] =
                        'Best regards';
                }


                if ($lang == 'es') {

                    $maildata['subjecttext'] =
                        'Su visita está pendiente de confirmación. Recibirá un mensaje nuestro dentro de las 24 horas.';

                    $maildata['deartext'] = 'Estimado';

                    $maildata['para2text'] =
                        'Los datos de tu reserva:';

                    $maildata['bookingdatetext'] =
                        'Fecha de reserva';

                    $maildata['slottext'] =
                        'Hora de la cita';

                    $maildata['servicetext'] =
                        'Servicio';

                    $maildata['footerpara1text'] =
                        'Si tiene preguntas adicionales o necesita ayuda, contáctenos en:';

                    $maildata['contactno1'] =
                        'SALA DE EXPOSICIONES POLONIA : +48578903292';

                    $maildata['contactno2'] =
                        'SALAS DE EXPOSICIÓN ESPAÑA : +34651439815';

                    $maildata['footerpara2text'] =
                        'Gracias por elegir Hollywood Hair, esperamos que sea una experiencia única para ti.';

                    $maildata['footerpara3text'] =
                        'Atentamente';
                }


                if ($lang == 'pl') {

                    $maildata['subjecttext'] =
                        'Twoja wizyta czeka na potwierdzenie. W ciągu 24h otrzymasz od nas wiadomość.';

                    $maildata['deartext'] = 'Hej';

                    $maildata['para2text'] =
                        'Szczegóły Twojej rezerwacji:';

                    $maildata['bookingdatetext'] =
                        'Data wizyty';

                    $maildata['slottext'] =
                        'Godziny zabiegu';

                    $maildata['servicetext'] =
                        'Usługa';

                    $maildata['footerpara1text'] =
                        'Jeśli masz dodatkowe pytania lub potrzebujesz pomocy, skontaktuj się z nami pod numerem:';

                    $maildata['contactno1'] =
                        'SALONY POLSKA : +48578903292';

                    $maildata['contactno2'] =
                        'SALONY HISZPANIA : +34651439815';

                    $maildata['footerpara2text'] =
                        'Dziękujemy za wybór Hollywood Hair, mamy nadzieję, że będzie to dla Ciebie wyjątkowe doświadczenie.';

                    $maildata['footerpara3text'] =
                        'Pozdrawiamy';
                }


                /*
                |--------------------------------------------------------------------------
                | SEND EMAIL
                |--------------------------------------------------------------------------
                */

                $maildata['to'] = $request->post('email');

                $maildata['subject'] =
                    empty($booking_id)
                        ? 'Appointment Booked'
                        : (
                            $notificationType === 'appointment_updated'
                                ? 'Appointment Updated'
                                : 'Appointment Booked'
                        );

                $maildata['view_name'] = 'bookinginfo';

                $maildata['name'] =
                    $request->post('customer_name');

                $maildata['email'] =
                    $request->post('email');

                $maildata['booking_details'] =
                    $bookings;


                if (!empty($maildata['email'])) {

                    sendMail($maildata);
                }


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER NOTIFICATION
                |--------------------------------------------------------------------------
                */

                $title =
                    $notificationTranslations[$notificationType]['en']['subject'];


                $msg =
                    $notificationTranslations[$notificationType]['en']['message'];


                $salonName = !empty($salon->salon_name_en)
                    ? $salon->salon_name_en
                    : '';


                if (!empty($salonName)) {

                    $msg .= ' at ' . $salonName;
                }


                /*
                |--------------------------------------------------------------------------
                | SPANISH
                |--------------------------------------------------------------------------
                */

                $subject_es =
                    $notificationTranslations[$notificationType]['es']['subject'];


                $message_es =
                    $notificationTranslations[$notificationType]['es']['message'];


                if (!empty($salonName)) {

                    $message_es .= ' en ' . $salonName;
                }


                /*
                |--------------------------------------------------------------------------
                | POLISH
                |--------------------------------------------------------------------------
                */

                $subject_pl =
                    $notificationTranslations[$notificationType]['pl']['subject'];


                $message_pl =
                    $notificationTranslations[$notificationType]['pl']['message'];


                if (!empty($salonName)) {

                    $message_pl .= ' w ' . $salonName;
                }


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER NOTIFICATION RECORD
                |--------------------------------------------------------------------------
                */

                $notificationData = [

                    'customer_id' =>
                        @$bookings->customer_id,

                    'subject' =>
                        $title,

                    'message' =>
                        $msg,

                    'subject_es' =>
                        $subject_es,

                    'message_es' =>
                        $message_es,

                    'subject_pl' =>
                        $subject_pl,

                    'message_pl' =>
                        $message_pl,

                    'notification_type' =>
                        'customer',

                    'booking_id' =>
                        $bookings->booking_id
                ];


                insert(
                    'notification',
                    $notificationData
                );


                $customer = @select(
                    'customers',
                    '*',
                    [
                        ['status', '=', 'Active'],
                        [
                            'customer_id',
                            '=',
                            @$bookings->customer_id
                        ]
                    ]
                )->first();


                @sendCustomerFirebaseNotification(
                    @$customer->shopify_user_id,
                    $msg,
                    $title
                );


                /*
                |--------------------------------------------------------------------------
                | SALON NOTIFICATION
                |--------------------------------------------------------------------------
                */

                $salonNotificationData = [

                    'salon_id' =>
                        @$bookings->salon_id,

                    'subject' =>
                        $salontitle,

                    'message' =>
                        $salonmsg,

                    'subject_es' =>
                        $subject_es,

                    'message_es' =>
                        $message_es,

                    'subject_pl' =>
                        $subject_pl,

                    'message_pl' =>
                        $message_pl,

                    'notification_type' =>
                        'salon',

                    'booking_id' =>
                        $bookings->booking_id
                ];


                insert(
                    'notification',
                    $salonNotificationData
                );


                sendFirebaseNotification(
                    @$bookings->salon_id,
                    $salonmsg,
                    $salontitle
                );


                /*
                |--------------------------------------------------------------------------
                | RESPONSE
                |--------------------------------------------------------------------------
                */

                return response()->json([
                    'result' => 1,
                    'msg' => 'Booked successfully.',
                    'data' => $bookings
                ], 200);
            }


            return response()->json([
                'result' => -1,
                'msg' => 'Something Went Wrong'
            ]);


        } catch (\Exception $e) {

            return response()->json([
                'result' => -1,
                'msg' => 'An error occurred while processing your request: '
                    . $e->getMessage()
            ], 500);
        }
    }
	
	public function sendTestMail()
	{
		$maildata['subjecttext'] = 'Twoja wizyta czeka na potwierdzenie. W ciągu 24h otrzymasz od nas wiadomość.';
		$maildata['deartext'] = 'Hej';
		/* $maildata['para1text'] = 'Z radością informujemy, że Twoja wizyta została pomyślnie zarezerwowana z Hollywood Hair.'; */
		$maildata['para2text'] = 'Szczegóły Twojej rezerwacji:';
		$maildata['bookingdatetext'] = 'Data wizyty';
		/* $maildata['visittypetext'] = 'Typ wizyty'; */
		/* $maildata['prepaymentamttext'] = 'Kwota przedpłaty'; */
		$maildata['slottext'] = 'Godziny zabiegu';
		$maildata['servicetext'] = 'Usługa';
		$maildata['footerpara1text'] = 'Jeśli masz dodatkowe pytania lub potrzebujesz pomocy, skontaktuj się z nami pod numerem:';
		$maildata['contactno1'] = 'SALONY POLSKA : +48578903292';
		$maildata['contactno2'] = 'SALONY HISZPANIA : +34651439815';
		$maildata['footerpara2text'] = 'Dziękujemy za wybór Hollywood Hair, mamy nadzieję, że będzie to dla Ciebie wyjątkowe doświadczenie.';
		$maildata['footerpara3text'] = 'Pozdrawiamy';

		$maildata['to'] = 'shekhar.designoweb@gmail.com';
		$maildata['subject'] = 'Appointment Booked';
		$maildata['view_name'] = 'bookinginfo';
		$maildata['name'] = 'Shekhar';
		$maildata['email'] = 'shekhar.designoweb@gmail.com';
		/* $maildata['booking_details'] = $bookings; */

		if (!empty($maildata['email'])) {
			sendMail($maildata);
		}
		return response()->json(['message' => 'API executed successfully.']);
	}

    public function getBookingDetails($lang = "en", $booking_id)
    {
        try {
            if (!empty($booking_id)) {
                // $data['customer'] =  $customerexist  = select('customers', '*', [['status', '!=', 'Deleted'], ['custmer_id', '=', $request->post('email')]])->first();
                // $data['booking'] = select('booking', '*', [['booking_id', '=', $booking_id]])->first();
                // $data['oldagreement']  = select('agreement', '*', [['booking_id', '=', $booking_id], ['agreement_id', '=', @$oldbookingdata->agreement_id]])->first();
                // $data['agreementdocument']  = select('agreement_documents', '*', [['booking_id', '=', $booking_id], ['document_type', '=', 'agreement']])->first();
                // $data['signature']  = select('agreement_documents', '*', [['booking_id', '=', $booking_id], ['document_type', '=', 'signature']])->first();
                $bookings = select('booking', '*', [['booking_id', '=', $booking_id]])->first();
                if (!empty($bookings)) {

                    $services = json_decode($bookings->services);
                    $slots = json_decode($bookings->slots);
                    if (empty($services)) {
                        $bookings->servicedetails  = [];
                    } else {
                        $bookings->servicedetails = SalonModel::getServices($services, $lang);
                    }
                    if (empty($slots)) {
                        $bookings->slotsdetails = [];
                    } else {
                        $bookings->slotsdetails = SalonModel::getSlots($slots, $lang);
                    }
                    $bookings->signature = select('agreement_documents', '*', [['booking_id', '=', $booking_id], ['document_type', '=', 'signature']])->first();
                    $bookings->agreementdocument = select('agreement_documents', '*', [['booking_id', '=', $booking_id], ['document_type', '=', 'agreement']])->first();
                    $bookings->customer = @select('customers', '*', [['status', '!=', 'Deleted'], ['customer_id', '=', $bookings->customer_id]])->first();

                    $bookings->salon_details = @select('salon', '*', [['status', '!=', 'Deleted'], ['salon_id', '=', $bookings->salon_id]])->first();
                    $bookings->worker_name = @select('salon_worker', '*', [['status', '!=', 'Deleted'], ['worker_id', '=', $bookings->worker_id]])->first();
                }
                return response()->json(['result' => 1, 'msg' => 'Booking Details.', 'data' => $bookings], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function getBookings(Request $request, $lang = "en", $status = null, $salon_id = null, $visit_type = null, $booking_date = null)
    {
        try {
			$user = !empty($request->query('u')) ? $request->query('u') : null;
			$user_id = !empty($request->query('uid')) ? $request->query('uid') : null;
            if ($lang == 0) {
                $lang = 0;
            }
            $keyword = $request->query('keyword');
            $type = $request->query('filter');
            $month = $request->query('month');
            $year = $request->query('year');
            $is_paginate = $request->query('is_paginate');
            $salons_ids = SalonModel::getSalonByName($keyword)->map(function ($item) {
                return $item->salon_id;
            })->toArray();
            $worker_ids = SalonModel::getWorkerByName($keyword)->map(function ($item) {
                return $item->worker_id;
            })->toArray();

            $customer_ids = SalonModel::getPhoneByName($keyword)->map(function ($item) {
                return $item->customer_id;
            })->toArray();

            $bookings = SalonModel::getAllBookings($status, $salon_id, $visit_type, $booking_date, $keyword, $salons_ids, $worker_ids, $customer_ids, $type, $month, $year, $is_paginate, $user, $user_id);

            if (($bookings->isNotEmpty())) {
                foreach ($bookings as $key => $val) {
                    $services = json_decode($val->services);
                    if (empty($services)) {
                        $val->servicedetails  = [];
                    } else {
                        $val->servicedetails = @SalonModel::getServices($services, $lang);
                    }
                    $slots = json_decode($val->slots);
                    if (empty($slots)) {
                        $val->slotsdetails = [];
                    } else {
                        $val->slotsdetails = @SalonModel::getSlots($slots, $lang);
                    }
                    $val->signature = select('agreement_documents', '*', [['booking_id', '=', $val->booking_id], ['document_type', '=', 'signature']])->first();
                    $val->agreementdocument = select('agreement_documents', '*', [['booking_id', '=', $val->booking_id], ['document_type', '=', 'agreement']])->first();
                    $val->customer = @select('customers', '*', [['status', '=', 'Active'], ['customer_id', '=', $val->customer_id]])->first();
					$val->customer_name = !empty($val->customer->customer_name) ? $val->customer->customer_name : null;
                    $salon_details = select('salon', '*', [['status', '=', 'Active'], ['salon_id', '=', $val->salon_id]])->first();
                    $val->salon_details = !empty($salon_details) ? $salon_details : null;
                    if (isset($val->salon_details->salon_thumbnail) && !str_contains($val->salon_details->salon_thumbnail, 'amazonaws.com')) {
                        $val->salon_details->salon_thumbnail = !empty($val->salon_details->salon_thumbnail) ? baseURL(@$val->salon_details->salon_thumbnail) : null;
                    }
                    $val->worker_name = @select('salon_worker', 'worker_name', [['status', '=', 'Active'], ['worker_id', '=', $val->worker_id]])->first()->worker_name;
					
					/* if (!empty($val->customer_id) && is_int($val->customer_id)) {
						$total_bookings = SalonModel::getTotalBookings((int)$val->customer_id);
						$val->total_bookings = $total_bookings['total_bookings'] ?? 0;
						$val->services_taken = $total_bookings['services_taken'] ?? [];
					} */
                }
            }
            return response()->json(['result' => 1, 'msg' => 'Booking Details.', 'data' => $bookings], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
	
	public function getBookingsV2(Request $request, $lang = "en", $status = null, $salon_id = null, $visit_type = null, $booking_date = null)
	{
		try {
			$keyword = $request->query('keyword');
			$type = $request->query('filter');
			$month = $request->query('month');
			$year = $request->query('year');
			$is_paginate = $request->query('is_paginate');
			$user = $request->query('u');
			$user_id = $request->query('uid');
            
			$salons_ids = SalonModel::getSalonByName($keyword)->pluck('salon_id')->toArray();
			$worker_ids = SalonModel::getWorkerByName($keyword)->pluck('worker_id')->toArray();
			$customer_ids = SalonModel::getPhoneByName($keyword)->pluck('customer_id')->toArray();

			$bookings = SalonModel::getBookingsV2($status, $salon_id, $visit_type, $booking_date, $keyword, $salons_ids, $worker_ids, $customer_ids, $type, $month, $year, $is_paginate, $user, $user_id);

			return response()->json(['result' => 1, 'msg' => 'All booking fetched', 'data' => $bookings], 200);
		} catch (\Exception $e) {
			return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
		}
	}
	
	public function getCalenderBookings(Request $request, $salon_id = null, $booking_date = null)
    {
        try {
            $month = $request->query('month');
            $year = $request->query('year');
            $is_paginate = $request->query('is_paginate');

            $bookings = SalonModel::getCalenderBookings($salon_id, $month, $year, $is_paginate, $booking_date);
			
			if (!empty($bookings)) {
				foreach ($bookings as $booking) {
					$services = json_decode($booking->services);
					$booking->services_taken = [];
                    if (empty($services)) {
                        $booking->services_taken  = [];
                    } else {
                        $bookingServices = SalonModel::getServices($services);
						foreach ($bookingServices as $service) {
							$booking->services_taken[]  = $service->service_name;
						}
                    }
				}
			}
			
            return response()->json(['result' => 1, 'msg' => 'Calender bookings list fetched', 'data' => $bookings], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
	
	public function getCalenderBookingDetails(Request $request)
    {
        try {
            $booking_id = $request->post('booking_id');
            $result = SalonModel::getCalenderBookingDetails($booking_id);
			
			if (!empty($result)) {
				foreach ($result as $booking) {
					$services = json_decode($booking->services);
					$booking->services_taken = [];
                    if (empty($services)) {
                        $booking->services_taken  = [];
                    } else {
                        $bookingServices = SalonModel::getServices($services);
						foreach ($bookingServices as $service) {
							$booking->services_taken[]  = $service->service_name;
						}
                    }
				}
			}
			
            return response()->json(['result' => 1, 'msg' => 'Calender booking details fetched', 'data' => $result], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
	
	public function getUnAssignedBookings(Request $request)
	{
		try {
			$keyword = $request->query('keyword');
            $bookings = SalonModel::getUnAssignedBookings($keyword);
            if (!empty($bookings)) {
                foreach ($bookings as $key => $val) {
					$salon_details = select('salon', '*', [['status', '=', 'Active'], ['salon_id', '=', $val->salon_id]])->first();
                    $val->salon_details = !empty($salon_details) ? $salon_details : null;
                    $worker_name = @select('salon_worker', '*', [['worker_id', '=', $val->worker_id]])->first()->worker_name;
					$val->worker_name = !empty($worker_name) ? $worker_name : 'No worker assigned';
                }
            }
            return response()->json(['result' => 1, 'msg' => 'Bookings fetched successfully', 'data' => $bookings], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred: ' . $e->getMessage()], 500);
        }
	}

    public function getCustomerBookingsByShopifyId(Request $request)
    {
        try {
            $lang = "en";
            $shopify_user_id = $request->post('shopify_user_id');
            $customer = @select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $shopify_user_id]])->first();
            if (!empty($customer)) {
                $bookings = select('booking', '*', ['customer_id' => @$customer->customer_id, 'status' => 'Active']);
                foreach ($bookings as $key => $val) {
                    $services = json_decode($val->services);
                    $slots = json_decode($val->slots);
                    if (empty($services)) {
                        $val->servicedetails  = [];
                    } else {
                        //dd($services); die;
                        $val->servicedetails = @SalonModel::getServices($services, $lang);
                    }
                    if (empty($slots)) {
                        $val->slotsdetails = [];
                    } else {
                        $val->slotsdetails = @SalonModel::getSlots($slots, $lang);
                    }
                    $val->signature = select('agreement_documents', '*', [['booking_id', '=', $val->booking_id], ['document_type', '=', 'signature']])->first();
                    $val->agreementdocument = select('agreement_documents', '*', [['booking_id', '=', $val->booking_id], ['document_type', '=', 'agreement']])->first();
                    $val->customer = @select('customers', '*', [['status', '=', 'Active'], ['customer_id', '=', $val->customer_id]])->first();
                    $salon_details = select('salon', '*', [['status', '=', 'Active'], ['salon_id', '=', $val->salon_id]])->first();
                    $val->salon_details = !empty($salon_details) ? $salon_details : null;
                    if (isset($val->salon_details->salon_thumbnail) && !str_contains($val->salon_details->salon_thumbnail, 'amazonaws.com')) {
                        $val->salon_details->salon_thumbnail = !empty($val->salon_details->salon_thumbnail) ? baseURL(@$val->salon_details->salon_thumbnail) : null;
                    }
                    $val->worker_name = @select('salon_worker', '*', [['status', '=', 'Active'], ['worker_id', '=', $val->worker_id]])->first()->worker_name;
                }

                return response()->json(['result' => 1, 'msg' => 'Customer booking found.', 'data' => $bookings], 200);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Bookings not found']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function getAvailability(Request $request)
    {
        $salon_id = $request->post('salon_id');
        //$date = date('Y-m-d', strtotime($request->post('date')));
        $lang = $request->post('lang');
        $lang = !empty($lang) ? $lang : "en";

        $allslots = select('slots', ['slot_id', 'slot_name_' . $lang . ' as slot_name', 'slot_time'], [['status', '=', 'Active'], ['salon_id', '=', @$salon_id]]);

        if ($allslots->isNotEmpty()) {
            /* if (!empty($date)) {
				$bookings = select('booking', '*', [['salon_id', '=', $salon_id], ['booking_date', '=', @$date]]);
			} else {
				$bookings = select('booking', '*', [['salon_id', '=', $salon_id]]);
			}
			$bookings = select('booking', '*', [['salon_id', '=', $salon_id]]);
			foreach ($bookings as $value) {
				$allslots = select('slots', ['slot_id', 'slot_name_'.$lang.' as slot_name', 'slot_time'], [['status', '=', 'Active'], ['salon_id', '=', @$value->salon_id]])->toArray();
				
				$avail = select('booking_availability', '*', [['salon_id', '=', $salon_id], ['booking_date', '=', @$date]])->map(function($item){
					return $item->slot_id;
				})->toArray();
			   
				if(!empty($avail) && !empty($allslots)){
					foreach($allslots as $row){
						$row->is_avail = !in_array($row->slot_id,$avail);
					}
				}else{
					foreach($allslots as $row){
						$row->is_avail = true;
					}
				}
			}  */
            return response()->json(['result' => 1, 'msg' => 'Slots', 'data' => $allslots], 200);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No slots available', 'data' => []], 200);
        }
    }

    // public function updateBookingStatus(Request $request)
    // {
    //     $booking_id = $request->post('booking_id');
    //     $booking_status = $request->post('booking_status');
    //     update('booking', 'booking_id', $booking_id, ['booking_status' => $booking_status]);
    //     $booking = select('booking', '*', ['booking_id' => $booking_id])->first();
    //     $customer_id = $booking->customer_id;

    //     $title = "";
    //     $msg = "";

    //     if ($booking_status == 'pending') {
    //         $title = "Booking Pending";
    //         $msg = "Your booking is pending and waiting for confirmation.";
    //     } elseif ($booking_status == 'accepted') {
    //         $title = "Booking Accepted";
    //         $msg = "Your booking has been accepted. We look forward to serving you!";
    //     } elseif ($booking_status == 'inprogress') {
    //         $title = "Service In Progress";
    //         $msg = "Your service is in progress. We appreciate your patience.";
    //     } elseif ($booking_status == 'completed') {
    //         $title = "Service Completed";
    //         $msg = "Your service has been completed successfully. Thank you for choosing our service!";
    //     } elseif ($booking_status == 'rescheduled') {
    //         $title = "Booking Rescheduled";
    //         $msg = "Your booking has been rescheduled successfully. Please check the updated details.";
    //         sendFirebaseNotification(@$booking->salon_id, $msg, $title);
    //     } elseif ($booking_status == 'cancelled') {
    //         $title = "Booking Cancelled";
    //         $msg = "Your booking has been cancelled.";
    //         sendFirebaseNotification(@$booking->salon_id, $msg, $title);
    //     }

    //     if (!empty($customer_id)) {
    //         if (!empty($booking)) {
    //             $services = !empty($booking->services) ? json_decode($booking->services) : null;
    //             $slots = !empty($booking->slots) ? json_decode($booking->slots) : null;

    //             if (empty($services)) {
    //                 $booking->servicedetails = [];
    //             } else {
    //                 $booking->servicedetails = SalonModel::getServices($services);
    //             }

    //             if (empty($slots)) {
    //                 $booking->slotsdetails = [];
    //             } else {
    //                 $booking->slotsdetails = SalonModel::getSlots($slots);
    //             }
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Static translations
    //         |--------------------------------------------------------------------------
    //         | Google Translate has been removed to avoid 429 Too Many Requests.
    //         */
    //         $translations = [
    //             'Booking Pending' => [
    //                 'es' => [
    //                     'title' => 'Reserva pendiente',
    //                     'message' => 'Tu reserva está pendiente y esperando confirmación.',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Rezerwacja oczekująca',
    //                     'message' => 'Twoja rezerwacja oczekuje na potwierdzenie.',
    //                 ],
    //             ],

    //             'Booking Accepted' => [
    //                 'es' => [
    //                     'title' => 'Reserva aceptada',
    //                     'message' => 'Tu reserva ha sido aceptada. ¡Esperamos poder atenderte!',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Rezerwacja zaakceptowana',
    //                     'message' => 'Twoja rezerwacja została zaakceptowana. Cieszymy się na Twoją wizytę!',
    //                 ],
    //             ],

    //             'Service In Progress' => [
    //                 'es' => [
    //                     'title' => 'Servicio en curso',
    //                     'message' => 'Tu servicio está en curso. Agradecemos tu paciencia.',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Usługa w trakcie realizacji',
    //                     'message' => 'Twoja usługa jest w trakcie realizacji. Dziękujemy za cierpliwość.',
    //                 ],
    //             ],

    //             'Service Completed' => [
    //                 'es' => [
    //                     'title' => 'Servicio completado',
    //                     'message' => 'Tu servicio se ha completado correctamente. ¡Gracias por elegir nuestro servicio!',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Usługa zakończona',
    //                     'message' => 'Twoja usługa została pomyślnie zakończona. Dziękujemy za wybranie naszej usługi!',
    //                 ],
    //             ],

    //             'Booking Rescheduled' => [
    //                 'es' => [
    //                     'title' => 'Reserva reprogramada',
    //                     'message' => 'Tu reserva ha sido reprogramada correctamente. Consulta los detalles actualizados.',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Rezerwacja przełożona',
    //                     'message' => 'Twoja rezerwacja została pomyślnie przełożona. Sprawdź zaktualizowane szczegóły.',
    //                 ],
    //             ],

    //             'Booking Cancelled' => [
    //                 'es' => [
    //                     'title' => 'Reserva cancelada',
    //                     'message' => 'Tu reserva ha sido cancelada.',
    //                 ],
    //                 'pl' => [
    //                     'title' => 'Rezerwacja anulowana',
    //                     'message' => 'Twoja rezerwacja została anulowana.',
    //                 ],
    //             ],
    //         ];

    //         $subject_es = $title;
    //         $message_es = $msg;
    //         $subject_pl = $title;
    //         $message_pl = $msg;

    //         if (isset($translations[$title])) {
    //             $subject_es = $translations[$title]['es']['title'];
    //             $message_es = $translations[$title]['es']['message'];

    //             $subject_pl = $translations[$title]['pl']['title'];
    //             $message_pl = $translations[$title]['pl']['message'];
    //         }

    //         $notificationData = [
    //             'customer_id' => @$customer_id,
    //             'subject' => $title,
    //             'message' => $msg,
    //             'subject_es' => $subject_es,
    //             'message_es' => $message_es,
    //             'subject_pl' => $subject_pl,
    //             'message_pl' => $message_pl,
    //             'notification_type' => 'customer',
    //             'booking_id' => $booking_id
    //         ];

    //         $customer = @select('customers', '*', [['status', '=', 'Active'], ['customer_id', '=', @$customer_id]])->first();
    //         insert('notification', $notificationData);
    //         @sendCustomerFirebaseNotification(@$customer->shopify_user_id, $msg, $title);
    //         $this->sendBookingStatusMail($request, $booking_status, $customer, $booking);
    //     }

    //     return response()->json(['result' => 1, 'msg' => 'Status Updated Successfully', 'data' => null], 200);
    // }
    public function updateBookingStatus(Request $request)
    {
        try {

            $booking_id = $request->post('booking_id');
            $booking_status = $request->post('booking_status');

            /*
            |--------------------------------------------------------------------------
            | Validate basic request
            |--------------------------------------------------------------------------
            */

            if (empty($booking_id)) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Booking ID is required.'
                ]);
            }

            if (empty($booking_status)) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Booking status is required.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Get booking first
            |--------------------------------------------------------------------------
            */

            $booking = select(
                'booking',
                '*',
                [
                    ['booking_id', '=', $booking_id]
                ]
            )->first();

            if (empty($booking)) {
                return response()->json([
                    'result' => -1,
                    'msg' => 'Booking not found.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Update booking data
            |--------------------------------------------------------------------------
            |
            | Existing/legacy bookings:
            | - Continue using the existing booking flow.
            |
            | V2 bookings:
            | - worker_id is optional when accepting.
            | - If worker_id is provided, it will be updated.
            | - If worker_id is not provided, only booking_status is updated.
            | - booking_time is NOT handled here because it is already
            |   provided during booking creation.
            |
            */

            $updateData = [
                'booking_status' => $booking_status
            ];

            /*
            |--------------------------------------------------------------------------
            | Accepted Booking
            |--------------------------------------------------------------------------
            */

            if ($booking_status == 'accepted') {

                /*
                |--------------------------------------------------------------------------
                | New V2 booking
                |--------------------------------------------------------------------------
                |
                | category_id identifies a V2 booking.
                |
                */

                if (!empty($booking->category_id)) {

                    /*
                    |--------------------------------------------------------------------------
                    | Hair stylist / worker is optional
                    |--------------------------------------------------------------------------
                    |
                    | If worker_id is provided, assign the stylist.
                    | If not provided, only the status will be updated.
                    |
                    */

                    $worker_id = $request->post('worker_id');

                    if (!empty($worker_id)) {
                        $updateData['worker_id'] = $worker_id;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Legacy booking
                |--------------------------------------------------------------------------
                |
                | No changes to the existing legacy flow.
                |
                */
            }

            /*
            |--------------------------------------------------------------------------
            | Update booking status/details
            |--------------------------------------------------------------------------
            */

            update(
                'booking',
                'booking_id',
                $booking_id,
                $updateData
            );

            /*
            |--------------------------------------------------------------------------
            | Get updated booking
            |--------------------------------------------------------------------------
            */

            $booking = select(
                'booking',
                '*',
                [
                    ['booking_id', '=', $booking_id]
                ]
            )->first();

            $customer_id = $booking->customer_id;

            /*
            |--------------------------------------------------------------------------
            | Notification title/message
            |--------------------------------------------------------------------------
            */

            $title = "";
            $msg = "";

            if ($booking_status == 'pending') {

                $title = "Booking Pending";
                $msg = "Your booking is pending and waiting for confirmation.";

            } elseif ($booking_status == 'accepted') {

                $title = "Booking Accepted";
                $msg = "Your booking has been accepted. We look forward to serving you!";

            } elseif ($booking_status == 'inprogress') {

                $title = "Service In Progress";
                $msg = "Your service is in progress. We appreciate your patience.";

            } elseif ($booking_status == 'completed') {

                $title = "Service Completed";
                $msg = "Your service has been completed successfully. Thank you for choosing our service!";

            } elseif ($booking_status == 'rescheduled') {

                $title = "Booking Rescheduled";
                $msg = "Your booking has been rescheduled successfully. Please check the updated details.";

                sendFirebaseNotification(
                    @$booking->salon_id,
                    $msg,
                    $title
                );

            } elseif ($booking_status == 'cancelled') {

                $title = "Booking Cancelled";
                $msg = "Your booking has been cancelled.";

                sendFirebaseNotification(
                    @$booking->salon_id,
                    $msg,
                    $title
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Customer Notification
            |--------------------------------------------------------------------------
            */

            if (!empty($customer_id)) {

                if (!empty($booking)) {

                    $services = !empty($booking->services)
                        ? json_decode($booking->services)
                        : null;

                    $slots = !empty($booking->slots)
                        ? json_decode($booking->slots)
                        : null;

                    /*
                    |--------------------------------------------------------------------------
                    | Services
                    |--------------------------------------------------------------------------
                    */

                    if (empty($services)) {

                        $booking->servicedetails = [];

                    } else {

                        $booking->servicedetails =
                            SalonModel::getServices($services);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Legacy Slots
                    |--------------------------------------------------------------------------
                    */

                    if (empty($slots)) {

                        $booking->slotsdetails = [];

                    } else {

                        $booking->slotsdetails =
                            SalonModel::getSlots($slots);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | V2 Booking Time
                    |--------------------------------------------------------------------------
                    |
                    | V2 booking time is already stored during booking creation.
                    | We do not modify or convert it here.
                    |
                    */
                }

                /*
                |--------------------------------------------------------------------------
                | Static translations
                |--------------------------------------------------------------------------
                */

                $translations = [

                    'Booking Pending' => [
                        'es' => [
                            'title' => 'Reserva pendiente',
                            'message' => 'Tu reserva está pendiente y esperando confirmación.',
                        ],
                        'pl' => [
                            'title' => 'Rezerwacja oczekująca',
                            'message' => 'Twoja rezerwacja oczekuje na potwierdzenie.',
                        ],
                    ],

                    'Booking Accepted' => [
                        'es' => [
                            'title' => 'Reserva aceptada',
                            'message' => 'Tu reserva ha sido aceptada. ¡Esperamos poder atenderte!',
                        ],
                        'pl' => [
                            'title' => 'Rezerwacja zaakceptowana',
                            'message' => 'Twoja rezerwacja została zaakceptowana. Cieszymy się na Twoją wizytę!',
                        ],
                    ],

                    'Service In Progress' => [
                        'es' => [
                            'title' => 'Servicio en curso',
                            'message' => 'Tu servicio está en curso. Agradecemos tu paciencia.',
                        ],
                        'pl' => [
                            'title' => 'Usługa w trakcie realizacji',
                            'message' => 'Twoja usługa jest w trakcie realizacji. Dziękujemy za cierpliwość.',
                        ],
                    ],

                    'Service Completed' => [
                        'es' => [
                            'title' => 'Servicio completado',
                            'message' => 'Tu servicio se ha completado correctamente. ¡Gracias por elegir nuestro servicio!',
                        ],
                        'pl' => [
                            'title' => 'Usługa zakończona',
                            'message' => 'Twoja usługa została pomyślnie zakończona. Dziękujemy za wybranie naszej usługi!',
                        ],
                    ],

                    'Booking Rescheduled' => [
                        'es' => [
                            'title' => 'Reserva reprogramada',
                            'message' => 'Tu reserva ha sido reprogramada correctamente. Consulta los detalles actualizados.',
                        ],
                        'pl' => [
                            'title' => 'Rezerwacja przełożona',
                            'message' => 'Twoja rezerwacja została pomyślnie przełożona. Sprawdź zaktualizowane szczegóły.',
                        ],
                    ],

                    'Booking Cancelled' => [
                        'es' => [
                            'title' => 'Reserva cancelada',
                            'message' => 'Tu reserva ha sido cancelada.',
                        ],
                        'pl' => [
                            'title' => 'Rezerwacja anulowana',
                            'message' => 'Twoja rezerwacja została anulowana.',
                        ],
                    ],
                ];

                $subject_es = $title;
                $message_es = $msg;

                $subject_pl = $title;
                $message_pl = $msg;

                if (isset($translations[$title])) {

                    $subject_es =
                        $translations[$title]['es']['title'];

                    $message_es =
                        $translations[$title]['es']['message'];

                    $subject_pl =
                        $translations[$title]['pl']['title'];

                    $message_pl =
                        $translations[$title]['pl']['message'];
                }

                /*
                |--------------------------------------------------------------------------
                | Notification
                |--------------------------------------------------------------------------
                */

                $notificationData = [
                    'customer_id' => @$customer_id,
                    'subject' => $title,
                    'message' => $msg,
                    'subject_es' => $subject_es,
                    'message_es' => $message_es,
                    'subject_pl' => $subject_pl,
                    'message_pl' => $message_pl,
                    'notification_type' => 'customer',
                    'booking_id' => $booking_id
                ];

                $customer = @select(
                    'customers',
                    '*',
                    [
                        ['status', '=', 'Active'],
                        ['customer_id', '=', @$customer_id]
                    ]
                )->first();

                insert(
                    'notification',
                    $notificationData
                );

                @sendCustomerFirebaseNotification(
                    @$customer->shopify_user_id,
                    $msg,
                    $title
                );

                /*
                |--------------------------------------------------------------------------
                | Booking Status Email
                |--------------------------------------------------------------------------
                */

                $this->sendBookingStatusMail(
                    $request,
                    $booking_status,
                    $customer,
                    $booking
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'result' => 1,
                'msg' => 'Status Updated Successfully',
                'data' => $booking
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'result' => -1,
                'msg' => 'An error occurred while processing your request: '
                    . $e->getMessage()
            ], 500);
        }
    }

    public function sendBookingStatusMail(Request $request, $booking_status, $customer, $booking)
    {
        if ($booking_status == 'accepted' || $booking_status == 'cancelled') {
            $lang = (!empty($booking->preferred_lang) ? $booking->preferred_lang : ($request->post('lang') ?: 'en'));
            if ($lang == 'en') {
                $maildata['deartext'] = 'Dear';
                if ($booking_status == 'cancelled') {
                    $maildata['subjecttext'] = 'Your appointment has been cancelled!';
                    $maildata['footerpara1text'] = 'The date you selected is unavailable. We are very sorry but we cannot confirm your reservation.';
                    $maildata['footerpara2text'] = 'Please call the reception to find another convenient date: +48578903292';
                    /* $maildata['para1text'] = ''; */
                    $maildata['para2text'] = '';
                    $maildata['bookingdatetext'] = '';
                    /* $maildata['visittypetext'] = ''; */
                    /* $maildata['prepaymentamttext'] = ''; */
                    $maildata['slottext'] = '';
                    $maildata['servicetext'] = '';
                    $maildata['contactno1'] = '';
                    $maildata['contactno2'] = '';
                } else {
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
                }
                $maildata['footerpara3text'] = 'Best regards';
            }
            if ($lang == 'es') {
                $maildata['deartext'] = 'Estimado';
                if ($booking_status == 'cancelled') {
                    $maildata['subjecttext'] = '¡Tu cita ha sido cancelada!';
                    $maildata['footerpara1text'] = 'La fecha que seleccionó no está disponible. Lo sentimos mucho pero no podemos confirmar su reserva.';
                    $maildata['footerpara2text'] = 'Por favor llame a recepción para buscar otra fecha conveniente: +48578903292';
                    $maildata['para2text'] = '';
                    $maildata['bookingdatetext'] = '';
                    $maildata['slottext'] = '';
                    $maildata['servicetext'] = '';
                    $maildata['contactno1'] = '';
                    $maildata['contactno2'] = '';
                } else {
                    $maildata['subjecttext'] = 'Nos complace informarle que su cita se ha reservado correctamente.';
                    $maildata['para2text'] = 'Los datos de tu reserva:';
                    $maildata['bookingdatetext'] = 'Fecha de reserva';
                    $maildata['slottext'] = 'Ranura';
                    $maildata['servicetext'] = 'Servicio';
                    $maildata['footerpara1text'] = 'Si tiene preguntas adicionales o necesita ayuda, contáctenos en:';
                    $maildata['contactno1'] = 'SALA DE EXPOSICIONES POLONIA : +48578903292';
                    $maildata['contactno2'] = 'SALAS DE EXPOSICIÓN ESPAÑA : +34651439815';
                    $maildata['footerpara2text'] = 'Gracias por elegir Hollywood Hair, esperamos que sea una experiencia única para ti.';
                }
                $maildata['footerpara3text'] = 'Atentamente';
            }
            if ($lang == 'pl') {
                $maildata['deartext'] = 'Hej';
                if ($booking_status == 'cancelled') {
                    $maildata['subjecttext'] = 'Twoja wizyta została odwołana!';
                    $maildata['footerpara1text'] = 'Wybrany przez Ciebie termin jest  niedostępny. Bardzo nam przykro, ale nie możemy potwierdzić rezerwacji. ';
                    $maildata['footerpara2text'] = 'Prosimy o telefoniczny kontakt z recepcją w celu znalezienia innego dogodnego terminu : +48578903292';
                    /* $maildata['para1text'] = ''; */
                    $maildata['para2text'] = '';
                    $maildata['bookingdatetext'] = '';
                    /* $maildata['visittypetext'] = ''; */
                    /* $maildata['prepaymentamttext'] = ''; */
                    $maildata['slottext'] = '';
                    $maildata['servicetext'] = '';
                    $maildata['contactno1'] = '';
                    $maildata['contactno2'] = '';
                } else {
                    $maildata['subjecttext'] = 'Z radością informujemy, że Twoja wizyta została pomyślnie zarezerwowana.';
                    /* $maildata['para1text'] = 'Z radością informujemy, że Twoja wizyta została pomyślnie zarezerwowana z Hollywood Hair.'; */
                    $maildata['para2text'] = 'Szczegóły Twojej rezerwacji:';
                    $maildata['bookingdatetext'] = 'Data wizyty';
                    /* $maildata['visittypetext'] = 'Typ wizyty'; */
                    /* $maildata['prepaymentamttext'] = 'Kwota przedpłaty'; */
                    $maildata['slottext'] = 'Godziny zabiegu';
                    $maildata['servicetext'] = 'Usługa';
					$maildata['deposittext'] = 'ZADATEK';
					$maildata['depositinfo'] = "Wpłać ZADATEK 300 zł jeśli zarezerwowałaś wizytę na: przedłużanie włosów, zagęszczanie włosów, rekonstrukcję włosów, podciąganie po innym salonie lub termin weekendowy.\nDopiero po wpłacie, wizyta będzie w pełni potwierdzona.\n\nLink do wpłaty online: https://hollywoodhair.pl/products/zadatek-przed-zabiegiem\nRegulamin: https://hollywoodhair.pl/pages/regulamin-uslug-fryzjerskich";
                    $maildata['footerpara1text'] = 'Jeśli masz dodatkowe pytania lub potrzebujesz pomocy, skontaktuj się z nami pod numerem:';
                    $maildata['contactno1'] = 'SALONY POLSKA : +48578903292';
                    $maildata['contactno2'] = 'SALONY HISZPANIA : +34651439815';
                    $maildata['footerpara2text'] = 'Dziękujemy za wybór Hollywood Hair, mamy nadzieję, że będzie to dla Ciebie wyjątkowe doświadczenie.';
                }
                $maildata['footerpara3text'] = 'Pozdrawiamy';
            }

            $maildata['to'] = $customer->email;
            if ($booking_status == 'cancelled') {
                $maildata['subject'] = 'Booking Cancelled';
            } else {
                $maildata['subject'] = 'Booking Accepted';
            }
            $maildata['view_name'] = 'bookinginfo';
            $maildata['name'] = $customer->customer_name;
            $maildata['email'] = $customer->email;
            $maildata['booking_details'] = $booking;

            if (!empty($maildata['email'])) {
                sendMail($maildata);
            }
        }
    }

    public function updateSalonStatus(Request $request)
    {
        $salon_id = $request->post('salon_id');
        $status = $request->post('status');
        update('salon', 'salon_id', $salon_id, ['satus' => $status]);
        return response()->json(['result' => 1, 'msg' => 'Status Updated Successfully', 'data' => null], 200);
    }

    public function updateBookingSchedule(Request $request)
    {
        $booking_id = $request->post('booking_id');
        $date_from = $request->post('booking_date');
        $formatted_date = date('Y-m-d H:i:s', strtotime($date_from));
        $slots =  json_encode($request->post('slots'));
        update('booking', 'booking_id', $booking_id, ['slots' => $slots, 'booking_date' => $formatted_date, 'booking_status' => 'pending']);
        $slotdetails = $request->post('slots');
        if (!empty($slotdetails)) {
            // delete('booking_availability', 'booking_id', $booking_id);
            foreach ($slotdetails as $val) {
                $temp['booking_id'] = $booking_id;
                $temp['salon_id'] =  $request->post('salon_id');
                $temp['booking_date'] = $formatted_date;
                $temp['slot_id'] = $val;
                insert('booking_availability', $temp);
            }
        }
        return response()->json(['result' => 1, 'msg' => 'Reschedule Successfully', 'data' => null], 200);
    }

    public function deleteSalon(Request $req)
    {
        $salon_id = $req->post('salon_id');
        update('salon', 'salon_id', $salon_id, ['status' => 'Deleted']);
        return response()->json(['result' => 1, 'msg' => 'Salon deleted Successfully.', 'data' => null], 200);
    }

    public function sendCustomMail()
    {
        $maildata['to'] = 'ambuj.designoweb@gmail.com';
        $maildata['subject'] = 'Activation Salon and Credentials';
        $maildata['view_name'] = 'welcome';
        $maildata['name'] = 'Ambuj';
        $maildata['email'] = 'ambuj.designoweb@gmail.com';
        $maildata['user_name'] = 12345;
		if (!empty($maildata['to'])) {
			sendMail($maildata);
		}
    }

    public function getCustomerDetails(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ], [
                'required' => 'The :attribute field is required'
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            $email = $request->post('email');

            $customer = select('customers', '*', ['email' => $email, 'status' => 'Active'])->first();

            if (!empty($customer)) {
                return response()->json(['result' => 1, 'msg' => 'Customer details fetched successfully.', 'data' => $customer]);
            } else {
                return response()->json(['result' => 0, 'msg' => 'No record found with this Email!', 'data' => null]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function getSalonStats(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'salon_id' => 'required'
            ], [
                'required' => 'The :attribute field is required'
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            }

            $salon_id = $request->post('salon_id');

            $currentDate = date('Y-m-d');

            $totalbookings = select('booking', '*', ['salon_id' => $salon_id, 'status' => 'Active'])->count();
            $todaysbookings = select('booking', '*', ['salon_id' => $salon_id, 'booking_date' => $currentDate, 'status' => 'Active'])->count();
            $totalcustomers = select('customers', '*', ['status' => 'Active'])->count();
            $salonworkers = select('salon_worker', '*', ['salon_id' => $salon_id, 'status' => 'Active'])->count();

            $stats = [
                'totalbookings' => !empty($totalbookings) ? $totalbookings : 0,
                'todaysbookings' => !empty($todaysbookings) ? $todaysbookings : 0,
                'totalcustomers' => !empty($totalcustomers) ? $totalcustomers : 0,
                'salonworkers' => !empty($salonworkers) ? $salonworkers : 0
            ];

            if (!empty($stats)) {
                return response()->json(['result' => 1, 'msg' => 'Salon stats fetched successfully.', 'data' => $stats]);
            } else {
                return response()->json(['result' => 0, 'msg' => 'Something went wrong!', 'data' => null]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function getBookingPerSalon($lang = "en", $salon_id)
    {
        try {
            $bookings = select('booking', '*');

            if (($bookings->isNotEmpty())) {
                foreach ($bookings as $key => $val) {
                    $services = json_decode($val->services);
                    $slots = json_decode($val->slots);
                    if (empty($services)) {
                        $val->servicedetails = [];
                    } else {

                        $val->servicedetails = @SalonModel::getServices($services, $lang);
                    }
                    if (empty($slots)) {
                        $val->slotsdetails = [];
                    } else {
                        $val->slotsdetails = @SalonModel::getSlots($slots, $lang);
                    }
                    $val->customer = @select('customers', '*', [['status', '!=', 'Deleted'], ['customer_id', '=', $val->customer_id]])->first();
                    $val->salon_details = @select('salon', '*', [['status', '!=', 'Deleted'], ['salon_id', '=', $val->salon_id]])->first();
                    $val->worker_name = @select('salon_worker', '*', [['status', '!=', 'Deleted'], ['worker_id', '=', $val->worker_id]])->first()->worker_name;
                }

                if (!empty($salon_id)) {
                    $bookings = $bookings->where('salon_id', $salon_id)->reindex();
                }
                if (!empty($booking_date)) {
                    $bookings = $bookings->where('booking_date', $booking_date)->reindex();
                }
            }
            return response()->json(['result' => 1, 'msg' => 'Booking Details.', 'data' => $bookings], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function updateB(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_id' => 'required',
            ], [
                'required' => 'This :attribute is required',
                'unique' => 'The :attribute has already been taken',
            ]);

            // If validation fails, return an error response
            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()], 400);
            }

            $booking_id = $request->post('booking_id');
            $contract_signed_status = $request->post('contract_signed_status');

            $contract_signed = null;

            if ($contract_signed_status == 'yes') {
                $contract_signed = 'yes';
            } else if ($contract_signed_status == 'no') {
                $contract_signed = 'no';
            }

            update('booking', 'booking_id', $booking_id, ['contract_signed' => $contract_signed]);

            $update = [];

            if (!empty($request->post('pre_payment_made'))) {
                $update['pre_payment_made'] = $request->post('pre_payment_made');
            }
            if (!empty($request->post('pre_payment_amt'))) {
                $update['pre_payment_amt'] = $request->post('pre_payment_amt');
            }
            if (!empty($request->post('currency_id'))) {
                $update['currency_id'] = $request->post('currency_id');
            }
            if (!empty($request->post('currency_code'))) {
                $update['currency_code'] = $request->post('currency_code');
            }

            // Update other fields only if there are fields to update
            if (!empty($update)) {
                SalonModel::updateData($update, $booking_id);
            }

            return response()->json(['result' => 1, 'msg' => 'Data Updated Successfully']);
        } catch (\Exception $e) {
            // Handle the exception
            return response()->json(['result' => -1, 'msg' => 'An error occurred: ' . $e->getMessage()]);
        }
    }


    public function addNote(Request $request)
    {
        $note = $request->post('note');
        $booking_id = $request->post('booking_id');

        $result = SalonModel::addNote($booking_id, $note);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Note Added Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Something Went Wrong']);
        }
    }

    public function confirmBooking(Request $request)
    {
        $booking_id = $request->post('booking_id');
        $is_double_confirmed = $request->post('is_double_confirmed');

        $result = SalonModel::confirm($booking_id, $is_double_confirmed);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Booking Confirmed', 'data' => $result]);
        } else {
            return response()->json(['result' => 1, 'msg' => 'Booking not Confirmed']);
        }
    }

    public function deleteBooking($booking_id)
    {
        $result = SalonModel::deleteBooking($booking_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Booking Deleted Successfully', 'data' => 'true']);
        } else {
            return response()->json(['result' => 1, 'msg' => 'Try again later']);
        }
    }

    public function updateWeekend(Request $request)
    {
        $weekend = $request->input('weekend_show');
        $salon_id = $request->input('salon_id');
        $result =    update('salon', 'salon_id', $salon_id, ['weekend_show' => $weekend]);
        return response()->json(['result' => 1, 'msg' => 'Update Confirmed', 'data' => null]);
    }

    public function updateBooking(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'booking_id' => 'required',
                'salon_id' => 'required',
            ], [
                'required' => 'The :attribute is required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'errors' => $validator->errors()->first()], 400);
            }

            $booking_id = $request->input('booking_id');
            $salon_id = $request->input('salon_id');
            $booking_date = $request->input('booking_date');
            $slots = $request->input('slots');
			$services = $request->input('services');
			$worker_id = $request->input('worker_id');
			$visit_type = $request->input('visit_type');
			
			if (empty($booking_id)) {
				if (!empty($worker_id)) {
					$existedBooking = select('booking', '*', [['worker_id', '=', $worker_id], ['slots', '=', @json_encode($slots)], ['booking_date', '=', date('Y-m-d', strtotime($booking_date))]])->first();
					if (!empty($existedBooking)) {
						return response()->json(['result' => -2, 'msg' => "This worker is not available on this slot for this booking date! Choose another one."]);
					}
				}
			}
			
            $servicetime = array_map(function ($item) {
                $result = select('services', ['service_time_taken', 'service_id'], [['service_id', '=', $item]])->first();
                return $result;
            }, $services);
			
            $salon_closing_time = @select('salon', ['salon_id', 'closing_time'], [['salon_id', '=', $request->post('salon_id')]])->first()->closing_time;
            $slot_array = @$request->post('slots')[0];
            $slot_time_data = select('slots', ['slot_time'], [['slot_id', '=', $slot_array]])->first();
            $total_time = 0;
            if (!empty($servicetime)) {
                foreach ($servicetime as $service) {
                    $time_taken = !empty($service->service_time_taken) ? $service->service_time_taken : null;
                    if ($time_taken === null || $time_taken === '') {
                        $time_taken = 0;
                    }
                    $total_time += $time_taken;
                }
            }
			
            $start_time = @Carbon::createFromFormat('H:i:s', $slot_time_data->slot_time);
            $end_time = @Carbon::createFromFormat('H:i:s', $salon_closing_time);
            $time_diff_in_minutes = $end_time->diffInMinutes($start_time);

            // Check if the booking date is today or a future date
            if (($time_diff_in_minutes < $total_time && (date('Y-m-d') == date('Y-m-d', strtotime($booking_date)) || strtotime($booking_date) > strtotime(date('Y-m-d'))))) {
                return response()->json(['result' => -2, 'msg' => "We're delighted to have you here, but please be mindful of the time. Closing time is near, and we'll be closing soon"]);
            }
			
			if (!empty($salon_id)) {
				$updateData['salon_id'] = $salon_id;
			}
			if (!empty($booking_date)) {
				$updateData['booking_date'] = $booking_date;
			}
			if (!empty($slots)) {
				$updateData['slots'] = $slots;
			}
			if (!empty($services)) {
				$updateData['services'] = $services;
			}
			if (!empty($worker_id)) {
				$updateData['worker_id'] = $worker_id;
			}
			if (!empty($visit_type)) {
				$updateData['visit_type'] = $visit_type;
			}
			$preferred_lang = $request->post('preferred_lang');
			$secondary_lang = $request->post('secondary_lang');
			if (!empty($preferred_lang)) {
				$updateData['preferred_lang'] = $preferred_lang;
			}
			if (!empty($secondary_lang)) {
				$updateData['secondary_lang'] = $secondary_lang;
			}
			
            $result = update('booking', 'booking_id', $booking_id, $updateData);

            if (!empty($slots)) {
                delete('booking_availability', 'booking_id', $booking_id);
                foreach ($slots as $val) {
                    $temp['booking_id'] = $booking_id;
                    $temp['salon_id'] =  $salon_id;
                    $temp['booking_date'] = date('Y-m-d', strtotime($booking_date));
                    $temp['slot_id'] = $val;
                    insert('booking_availability', $temp);
                }
            }
			/* if (!empty($services)) {
                delete('salon_services', 'salon_id', $salon_id);
                foreach ($services as $val) {
                    $temp1['service_id'] = $val;
					$temp1['salon_id'] =  $salon_id;
                    insert('salon_services', $temp1);
                }
            } */

            return response()->json(['result' => 1, 'msg' => 'Booking details updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    public function updateBookingV2(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required',
                'salon_id' => 'required',

                'category_id' => 'nullable',
                'services' => 'nullable',
                'booking_date' => 'nullable|date',
                'booking_time' => [
                    'nullable',
                    'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'
                ],
                'worker_id' => 'nullable',
            ], [
                'required' => 'The :attribute is required',
                'booking_time.regex' => 'The booking time must be in 24-hour HH:mm format.',
                'booking_date.date' => 'The booking date must be a valid date.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'result' => 0,
                    'errors' => $validator->errors()->first()
                ], 400);
            }

            $booking_id = $request->input('booking_id');
            $salon_id = $request->input('salon_id');

            /*
            |--------------------------------------------------------------------------
            | Get Existing Booking
            |--------------------------------------------------------------------------
            */

            $booking = select('booking', '*', [
                ['booking_id', '=', $booking_id],
                ['status', '=', 'Active']
            ])->first();

            if (empty($booking)) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Booking not found or inactive.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Request Data
            |--------------------------------------------------------------------------
            */

            $category_id = $request->input('category_id');
            $visit_type = $request->input('visit_type');
            $worker_id = $request->input('worker_id');

            $booking_date = $request->input('booking_date');
            $booking_time = $request->input('booking_time');

            $preferred_lang = $request->input('preferred_lang');
            $secondary_lang = $request->input('secondary_lang');

            /*
            |--------------------------------------------------------------------------
            | Normalize Booking Date
            |--------------------------------------------------------------------------
            */

            if (!empty($booking_date)) {
                $booking_date = date('Y-m-d', strtotime($booking_date));
            }

            /*
            |--------------------------------------------------------------------------
            | Normalize Services
            |--------------------------------------------------------------------------
            */

            $services = $request->input('services');

            if (is_string($services)) {
                $decodedServices = json_decode($services, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $services = $decodedServices;
                }
            }

            if (!empty($services) && !is_array($services)) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Services must be an array.'
                ], 400);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Salon
            |--------------------------------------------------------------------------
            */

            $salon = select('salon', '*', [
                ['salon_id', '=', $salon_id],
                ['status', '=', 'Active']
            ])->first();

            if (empty($salon)) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Salon not found or inactive.'
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Category
            |--------------------------------------------------------------------------
            |
            | Only validate category when it is supplied.
            |
            */

            if (!empty($category_id)) {

                $category = select('categories', '*', [
                    ['id', '=', $category_id],
                    ['status', '=', 'Active']
                ])->first();

                if (empty($category)) {
                    return response()->json([
                        'result' => 0,
                        'msg' => 'Category not found or inactive.'
                    ], 400);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Validate Services
            |--------------------------------------------------------------------------
            |
            | Services must:
            | - Exist
            | - Belong to selected category if category is supplied
            | - Not be archived
            |
            */

            if (!empty($services)) {

                $services = array_values(array_filter($services, function ($service) {
                    return $service !== null && $service !== '';
                }));

                if (empty($services)) {
                    return response()->json([
                        'result' => 0,
                        'msg' => 'At least one valid service is required.'
                    ], 400);
                }

                $serviceRecords = select(
                    'services',
                    ['service_id', 'category_id', 'service_time_taken'],
                    [
                        ['service_id', 'in', $services],
                        ['is_archived', '=', 'no']
                    ]
                )->get();

                if (count($serviceRecords) != count($services)) {
                    return response()->json([
                        'result' => 0,
                        'msg' => 'One or more selected services are invalid or archived.'
                    ], 400);
                }

                /*
                |--------------------------------------------------------------------------
                | Category Validation For Services
                |--------------------------------------------------------------------------
                */

                if (!empty($category_id)) {

                    foreach ($serviceRecords as $service) {

                        if ((string) $service->category_id !== (string) $category_id) {

                            return response()->json([
                                'result' => 0,
                                'msg' => 'One or more selected services do not belong to the selected category.'
                            ], 400);
                        }
                    }
                }

                $servicesJson = json_encode($services);
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Update Data
            |--------------------------------------------------------------------------
            |
            | Only update fields which are actually supplied.
            | Existing values remain unchanged when optional fields are omitted.
            |
            */

            $updateData = [];

            /*
            |--------------------------------------------------------------------------
            | Salon
            |--------------------------------------------------------------------------
            */

            if (!empty($salon_id)) {
                $updateData['salon_id'] = $salon_id;
            }

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            if ($request->has('category_id')) {
                $updateData['category_id'] = !empty($category_id)
                    ? $category_id
                    : null;
            }

            /*
            |--------------------------------------------------------------------------
            | Worker
            |--------------------------------------------------------------------------
            */

            if ($request->has('worker_id')) {
                $updateData['worker_id'] = !empty($worker_id)
                    ? $worker_id
                    : null;
            }

            /*
            |--------------------------------------------------------------------------
            | Visit Type
            |--------------------------------------------------------------------------
            */

            if ($request->has('visit_type')) {
                $updateData['visit_type'] = $visit_type;
            }

            /*
            |--------------------------------------------------------------------------
            | Booking Date
            |--------------------------------------------------------------------------
            */

            if ($request->has('booking_date')) {
                $updateData['booking_date'] = !empty($booking_date)
                    ? $booking_date
                    : null;
            }

            /*
            |--------------------------------------------------------------------------
            | Booking Time
            |--------------------------------------------------------------------------
            */

            if ($request->has('booking_time')) {
                $updateData['booking_time'] = !empty($booking_time)
                    ? $booking_time
                    : null;
            }

            /*
            |--------------------------------------------------------------------------
            | Services
            |--------------------------------------------------------------------------
            */

            if ($request->has('services')) {
                $updateData['services'] = !empty($services)
                    ? $servicesJson
                    : null;
            }

            /*
            |--------------------------------------------------------------------------
            | Languages
            |--------------------------------------------------------------------------
            */

            if ($request->has('preferred_lang')) {
                $updateData['preferred_lang'] = $preferred_lang;
            }

            if ($request->has('secondary_lang')) {
                $updateData['secondary_lang'] = $secondary_lang;
            }

            /*
            |--------------------------------------------------------------------------
            | Update Booking
            |--------------------------------------------------------------------------
            */

            if (!empty($updateData)) {
                update('booking', 'booking_id', $booking_id, $updateData);
            }

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'result' => 1,
                'msg' => 'Booking details updated successfully'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'result' => -1,
                'msg' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
	
	public function addSalonNote(Request $request)
    {
		try {
			$validator = Validator::make($request->all(), [
				'salon_id' => 'required',
				'added_date' => 'required'
			], [
				'required' => 'This :attribute is required',
			]);

			if ($validator->fails()) {
				return response()->json(['result' => 0, 'errors' => $validator->errors()->first()], 400);
			}
			
			$dateTime = new \DateTime($request->post('added_date'));
			$added_date = $dateTime->format('Y-m-d');
			
			$note = select('notes', '*', ['salon_id' => $request->post('salon_id'), 'added_date' => $added_date, 'status' => 'Active'])->first();
			
			if (!empty($note)) {
				update('notes', 'id', $note->id, ['note' => $request->post('note'), 'added_date' => $request->post('added_date')]);
			} else {
				$insertData = [
					'note' => $request->post('note'),
					'salon_id' => $request->post('salon_id'),
					'added_date' => $request->post('added_date')
				];
				insert('notes', $insertData);
			}

            if (true) {
				$addedNote = select('notes', '*', ['salon_id' => $request->post('salon_id'), 'added_date' => $request->post('added_date'), 'status' => 'Active'])->first();
                return response()->json(['result' => 1, 'msg' => 'Note added successful.', 'data' => $addedNote]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong!']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public function getSalonNote(Request $request, $salon_id, $added_date)
    {
		try {
			$dateTime = new \DateTime($added_date);
			$added_date = $dateTime->format('Y-m-d');
			
			$note = DB::table('notes')->where('salon_id', $salon_id)->where('status', 'Active')->where('added_date', 'like', $added_date . '%')->first();

            if ($note) {
                return response()->json(['result' => 1, 'msg' => 'Note fetched successfully.', 'data' => $note]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Not found!']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
    
    public function getCustomerBookingNotes(Request $request)
    {
        try{
            $customer_id = $request->query('customer_id');
            if (!$customer_id) {
                return response()->json(['result' => -1,'msg' => 'customer_id is required' ]);
            }

            $result = SalonModel::getCustomerBookingNotes($customer_id);
            if ($result->isNotEmpty()) {
                return response()->json(['result' => 1,'msg' => 'Booking notes fetched successfully','data' => $result]);
            } else {
                return response()->json(['result' => 0,'msg' => 'No bookings found']);
            }
        } catch (\Exception $e) {
            // Handle the exception
            return response()->json(['result' => -1, 'msg' => 'An error occurred: ' . $e->getMessage()]);
        }
    }


    private function validateSalonCategorySlots($bookingType,array $bookingSlots,$openingTime,$closingTime) 
    {
        if (empty($bookingSlots)) {
            return 'At least one booking slot is required.';
        }


        /*
        * Fixed Time
        */
        if ($bookingType === 'fixed_time') {

            $usedTimes = [];


            foreach ($bookingSlots as $slot) {

                $startTime =
                    $slot['start_time'] ?? null;


                if (empty($startTime)) {
                    return 'Start time is required.';
                }


                /*
                * Fixed booking accepts only start_time.
                */
                if (
                    isset($slot['end_time']) &&
                    !empty($slot['end_time'])
                ) {
                    return
                        'End time is not allowed for fixed-time booking.';
                }


                /*
                * Must remain inside salon timings.
                */
                if (
                    $startTime < $openingTime ||
                    $startTime >= $closingTime
                ) {
                    return
                        "Booking time {$startTime} must be within salon opening and closing time.";
                }


                /*
                * Duplicate time.
                */
                if (
                    in_array(
                        $startTime,
                        $usedTimes,
                        true
                    )
                ) {
                    return
                        "Duplicate booking time {$startTime}.";
                }


                $usedTimes[] =
                    $startTime;
            }


            return null;
        }


        /*
        * Time Window
        */
        if ($bookingType === 'time_window') {

            $windows = [];


            foreach ($bookingSlots as $slot) {

                $startTime =
                    $slot['start_time'] ?? null;

                $endTime =
                    $slot['end_time'] ?? null;


                if (
                    empty($startTime) ||
                    empty($endTime)
                ) {
                    return
                        'Start time and end time are required for time-window booking.';
                }


                /*
                * Window must have positive duration.
                */
                if ($endTime <= $startTime) {
                    return
                        "End time must be greater than start time for {$startTime}.";
                }


                /*
                * Entire window must fall inside
                * salon opening and closing time.
                */
                if (
                    $startTime < $openingTime ||
                    $endTime > $closingTime
                ) {
                    return
                        "Booking window {$startTime}-{$endTime} must be within salon opening and closing time.";
                }


                $windows[] = [
                    'start_time' =>
                        $startTime,

                    'end_time' =>
                        $endTime
                ];
            }


            /*
            * Sort by start time before overlap check.
            */
            usort(
                $windows,
                function ($a, $b) {
                    return strcmp(
                        $a['start_time'],
                        $b['start_time']
                    );
                }
            );


            /*
            * Check overlapping windows.
            *
            * Allowed:
            *
            * 10:00 - 14:00
            * 14:00 - 18:00
            *
            * Invalid:
            *
            * 10:00 - 14:00
            * 13:00 - 17:00
            */
            for (
                $i = 1;
                $i < count($windows);
                $i++
            ) {

                $previous =
                    $windows[$i - 1];

                $current =
                    $windows[$i];


                if (
                    $current['start_time'] <
                    $previous['end_time']
                ) {
                    return
                        "Booking windows {$previous['start_time']}-{$previous['end_time']} and {$current['start_time']}-{$current['end_time']} overlap.";
                }
            }


            return null;
        }


        return 'Invalid category booking type.';
    }
}
