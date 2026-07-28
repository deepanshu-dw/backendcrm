<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HairController;
use App\Http\Controllers\NotificationManagement;
use App\Http\Controllers\SalonController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CommonController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\Welcome;
use Aws\Api\Service;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

/**
 * ===================================================================================================================================
 *
 *                                            *Common Routes*
 *
 * ====================================================================================================================================
 */
// \URL::forceScheme('https');

Route::post('sendPushNotiication', [AuthController::class, 'sendPushNotfication']);

Route::get('/clear-cache', function () {
    \Artisan::call('route:clear');
    \Artisan::call('optimize:clear');
    return 'Route cache cleared';
});

/**
 * ===================================================================================================================================
 *
 *                                            *User Routes*
 *
 * ====================================================================================================================================
 */

Route::middleware('admin.check')->group(function () {
Route::group(['prefix' => 'auth'], function () {
    Route::post('adminRegister', [AuthController::class, 'adminRegister']);
    Route::post('adminLogin', [AuthController::class, 'adminLogin']);
    Route::post('changePassword', [AuthController::class, 'changePassword']);
    Route::post('forgotPassword', [AuthController::class, 'forgotPassword']);
    Route::post('/sendOtp', [AuthController::class, 'resendOtp']);
    Route::post('/verifyOtp', [AuthController::class, 'verifyOtp']);
    Route::post('resetPassword', [AuthController::class, 'resetPassword']);
    Route::get('getAdminDetails/{admin_id}', [AuthController::class, 'getAdminDetails']);
    Route::get('getAllAdmins/{type?}', [AuthController::class, 'getAllAdmins']);
    Route::post('updateAdminDetails', [AuthController::class, 'updateAdminDetails']);

    Route::post('updateStatus', [AuthController::class, 'updateStatus']);
});

Route::group(['prefix' => 'salons'], function () {
    Route::get('/', [SalonController::class, 'getAllSalons']);
    Route::post('/add', [SalonController::class, 'addSalon']);
    Route::post('/addSalonV2', [SalonController::class, 'addSalonV2']);
    Route::post('/update/{salon_id}', [SalonController::class, 'updateSalon']);
    Route::get('/getSalonDetails/{salon_id}/{lang?}', [SalonController::class, 'getSalonDetails']);
    Route::post('/setFirebaseToken', [SalonController::class, 'setFirebaseToken']);
    Route::post('/delete', [SalonController::class, 'deleteSalon']);

    Route::post('/login', [SalonController::class, 'salonLogin']);
    Route::post('/changePassword', [SalonController::class, 'changePassword']);
    Route::post('/forgotPassword', [SalonController::class, 'forgotPassword']);
    Route::post('/resetPassword', [SalonController::class, 'resetPassword']);

    Route::post('/sendOtp', [SalonController::class, 'resendOtp']);
    Route::post('/verifyOtp', [SalonController::class, 'verifyOtp']);
    Route::post('/booking', [SalonController::class, 'booking']);
    Route::get('/booking/{lang?}/{booking_id?}', [SalonController::class, 'getBookingDetails']);
    Route::get('/allbooking/{lang?}/{status?}/{salon_id?}/{visit_type?}/{booking_date?}', [SalonController::class, 'getBookings']);
	Route::get('/allbookingV2/{lang?}/{status?}/{salon_id?}/{visit_type?}/{booking_date?}', [SalonController::class, 'getBookingsV2']);
	Route::get('/getCalenderBookings/{salon_id?}/{booking_date?}', [SalonController::class, 'getCalenderBookings']);
	Route::post('/getCalenderBookingDetails', [SalonController::class, 'getCalenderBookingDetails']);
	Route::get('/getUnAssignedBookings', [SalonController::class, 'getUnAssignedBookings']);
    Route::post('/getCustomerBookingsByShopifyId', [SalonController::class, 'getCustomerBookingsByShopifyId']);
    Route::get('/bookings/{lang?}/{salon_id}', [SalonController::class, 'getBookingPerSalon']);
    Route::get('/deleteBooking/{booking_id}', [SalonController::class, 'deleteBooking']);

	Route::get('/sendTestMail', [SalonController::class, 'sendTestMail']);

    Route::post('/getAvailability', [SalonController::class, 'getAvailability']);
    Route::post('/updateBookingStatus', [SalonController::class, 'updateBookingStatus']);
    Route::post('/updateSalonStatus', [SalonController::class, 'updateSalonStatus']);
    Route::post('updateBookingSchedule', [SalonController::class, 'updateBookingSchedule']);
    Route::get('sendCustomMail', [SalonController::class, 'sendCustomMail']);
    Route::post('getCustomerDetails', [SalonController::class, 'getCustomerDetails']);
    Route::post('addNote', [SalonController::class, 'addNote']);
    Route::get('getCustomerBookingNotes', [SalonController::class,'getCustomerBookingNotes']);//add this to server

    Route::post('/confirm', [SalonController::class, 'confirmBooking']);
    Route::post('/updateB', [SalonController::class, 'updateB']);
    //Route::post('getSalonStats',[SalonController::class,'getSalonStats']);
    Route::post('/updateWeekend', [SalonController::class, 'updateWeekend']);
    Route::post('/updateBooking', [SalonController::class, 'updateBooking']);
	
	Route::post('/addSalonNote', [SalonController::class, 'addSalonNote']);
	Route::get('/getSalonNote/{salon_id}/{added_date}', [SalonController::class, 'getSalonNote']);
});

Route::group(['prefix' => 'notifications'], function () {
    Route::get('/', [NotificationManagement::class, 'getAllNotifications']);
    Route::post('add', [NotificationManagement::class, 'addNotification']);
    Route::post('update/{notification_id}', [NotificationManagement::class, 'updateNotification']);
    Route::post('translateAndUpdateFromTable', [NotificationManagement::class, 'translateAndUpdateFromTable']);
    Route::get('/getNotification', [NotificationManagement::class, 'getNotification']);
	Route::get('/getNotificationForWeb', [NotificationManagement::class, 'getNotificationForWeb']);
    Route::post('/deleteNotification/{notification_id}', [NotificationManagement::class, 'deleteNotification']);

    Route::post('/getCustomerNotifications', [NotificationManagement::class, 'getCustomerNotifications']);
    Route::get('/send-Notification', [NotificationManagement::class, 'sendNotification']);
    Route::post('/getCustomerOfferNotifications', [NotificationManagement::class, 'getCustomerOfferNotifications']);
    Route::post('/updateOfferNotificationStatus', [NotificationManagement::class, 'updateOfferNotificationStatus']);
	Route::post('/updateNotificationStatus', [NotificationManagement::class, 'updateNotificationStatus']);
});

//----------------------------------Slot APIs-------------------------------------------------

Route::group(['prefix' => 'slots'], function () {
    Route::get('/{lang}', [SlotController::class, 'getAllSlotDetails']);
    Route::get('/{lang}/{slot_id}', [SlotController::class, 'getSlotDetail']);
    Route::post('/addSlot', [SlotController::class, 'addSlot']);
    Route::post('/updateSlot/{slot_id}', [SlotController::class, 'updateSlot']);
    Route::get('/deleteSlot/{slot_id}', [SlotController::class, 'deleteSlot']);
});

// ---------------------------------Slots per Salon---------------------------------------------
Route::group(['prefix' => 'slot'], function () {
    Route::get('/{lang}', [SlotController::class, 'getAllSalonSlotDetails']);
    Route::get('/{lang}/{salon_id}/{worker_id?}/{booking_date?}', [SlotController::class, 'getSalonSlot']);
    Route::post('/getSlotsByServices', [SlotController::class, 'getSlotsByServices']);
    Route::post('/addSalonSlot', [SlotController::class, 'addSalonSlot']);
    Route::post('/updateSalonSlot', [SlotController::class, 'updateSalonSlot']);
    Route::get('/deleteSalonSlot/{salon_id}/{slot_id}', [SlotController::class, 'deleteSalonSlot']);
	Route::post('/deleteSalonsSlot', [SlotController::class, 'deleteSalonsSlot']);
});

// ---------------------------------Group Apis---------------------------------------------------

Route::group(['prefix' => 'groups'], function () {
    Route::get('/{lang}', [GroupController::class, 'getAllGroups']);
    Route::post('/addGroup', [GroupController::class, 'addGroup']);
    Route::get('/getGroupDetail/{lang}/{group_id}', [GroupController::class, 'getGroupDetail']);
    Route::post('/updateGroup/{group_id}', [GroupController::class, 'updateGroup']);
    Route::post('/deleteGroup/{group_id}', [GroupController::class, 'deleteGroup']);
});

// ----------------------------------Salon Worker--------------------------------------------------

Route::group(['prefix' => 'worker'], function () {
    Route::get('/', [WorkerController::class, 'getAllSalonWorkers']);
    Route::get('/getSalonWorker/{worker_id}', [WorkerController::class, 'getSalonWorker']);
    Route::post('/addSalonWorker', [WorkerController::class, 'addSalonWorker']);
    Route::post('/updateSalonWorker', [WorkerController::class, 'updateSalonWorker']);
    Route::post('/deleteSalonWorker/{worker_id}', [WorkerController::class, 'deleteSalonWorker']);
    Route::get('/getWorkerBySalon/{salon_id}', [WorkerController::class, 'getWorkerBySalon']);
    Route::post('/search', [WorkerController::class, 'searchWorker']);
});

// ------------------------------------Hair APIs----------------------------------------------------

Route::group(['prefix' => 'hair'], function () {
    Route::get('/{lang}', [HairController::class, 'getAllHairColor']);
    Route::post('/addHairColor', [HairController::class, 'addHairColor']);
    Route::get('/getHairColor/{lang}/{hair_id}', [HairController::class, 'getHairColor']);
    Route::post('/updateHairColor/{hair_id}', [HairController::class, 'updateHairColor']);
    Route::post('/deleteHairColor/{hair_id}', [HairController::class, 'deleteHairColor']);
});

// -------------------------------------Service Apis------------------------------------------------

Route::group(['prefix' => 'service'], function () {
    Route::get('getAll/{lang}', [ServiceController::class, 'getAllServices']);
    Route::post('/addService', [ServiceController::class, 'addService']);
    Route::get('/getServiceDetail/{lang}/{service_id}', [ServiceController::class, 'getServiceDetail']);
    Route::post('/updateService/{service_id}', [ServiceController::class, 'updateService']);
    Route::post('/deleteService/{service_id}', [ServiceController::class, 'deleteService']);

    Route::get('/subServices', [ServiceController::class, 'subServices']);
    Route::post('/addSubServices', [ServiceController::class, 'addSubServices']);
    Route::post('/updateSubServices/{id}', [ServiceController::class, 'updateSubServices']);
    Route::get('/getSubServicesById/{id?}', [ServiceController::class, 'getSubServicesById']);
    Route::post('/deleteSubService/{id}', [ServiceController::class, 'deleteSubService']);
});

Route::group(['prefix' => 'category'], function () {
    Route::get('/getAll', [CategoryController::class, 'getAllCategory']);
    Route::post('/addCategory', [CategoryController::class, 'addCategory']);
    Route::get('/getCategoryDetails/{categoryId}', [CategoryController::class, 'getCategoryById']);
    Route::post('/updateCategory/{categoryId}', [CategoryController::class, 'updateCategory']);
    Route::post('/deleteCategory/{categoryId}', [CategoryController::class, 'deleteCategory']);

    // Route::get('/subServices', [ServiceController::class, 'subServices']);
    // Route::post('/addSubServices', [ServiceController::class, 'addSubServices']);
    // Route::post('/updateSubServices/{id}', [ServiceController::class, 'updateSubServices']);
    // Route::get('/getSubServicesById/{id?}', [ServiceController::class, 'getSubServicesById']);
    // Route::post('/deleteSubService/{id}', [ServiceController::class, 'deleteSubService']);
});

// -------------------------------------Customer Apis------------------------------------------------
Route::get('customer/version/{salon_id?}/{customer_id?}', [CustomerController::class, 'getAllCustomersV2']);
Route::group(['prefix' => 'customers'], function () {
    Route::post('/addCustomer', [CustomerController::class, 'addCustomer']);
    Route::get('/getCustomerDetail/{customer_id}/{salon_id?}', [CustomerController::class, 'getCustomerDetail']);
	Route::get('/getAllCustomerDocuments', [CustomerController::class, 'getAllCustomerDocuments']);
    Route::get('/{salon_id?}/{customer_id?}', [CustomerController::class, 'getAllCustomers'])->name("ss");
    Route::post('/updateCustomer/{customer_id}', [CustomerController::class, 'updateCustomer']);
    Route::post('/deleteCustomer/{customer_id}', [CustomerController::class, 'deleteCustomer']);
    Route::post('/search', [CustomerController::class, 'searchCustomer']);
    Route::get('/getCustomerHistoryBySalon/{salon_id?}/{lang?}/{customer_id?}', [CustomerController::class, 'getCustomerHistoryBySalon']);
    Route::get('/getCustomerHistory/{customer_id}/{salon_id?}', [CustomerController::class, 'getCustomerHistory']);
    Route::post('/searchCustomerByEmail', [CustomerController::class, 'searchCustomerByEmail']);
    Route::post('/setFireToken', [CustomerController::class, 'setFireToken']);
    Route::post('/getImageGenerationCount', [CustomerController::class, 'getImageGenerationCount']);
    Route::post('/setImageGenerationCount', [CustomerController::class, 'setImageGenerationCount']);
    Route::post('/resetImageGenerationCount', [CustomerController::class, 'resetImageGenerationCount']);
});

Route::group(['prefix' => 'common'], function () {
    Route::get('get-activity-logs', [CommonController::class, 'getActivityLogs']);
    Route::get('dashboard', [CommonController::class, 'dashbord']);
    Route::post('updateStaticPages', [CommonController::class, 'updateSiteSetting']);
	Route::middleware('encrypt-decrypt')->group(function () {
		Route::get('getStaticPages/{type?}', [CommonController::class, 'getSettings']);
	});
    Route::get('/getAllCurrencies', [CommonController::class, 'getAllCurrencies']);
    Route::post('upload-file', [CommonController::class, 'uploadFile']);
    Route::post('add-banner', [CommonController::class, 'addBanner']);
    Route::post('update-banner/{banner_id}', [CommonController::class, 'updateBanner']);
    Route::get('getAllBanner', [CommonController::class, 'getAllBanner']);
    Route::get('getBannerById/{banner_id}', [CommonController::class, 'getBannerById']);
    Route::get('delete-banner/{banner_id}', [CommonController::class, 'deleteBanner']);


});

// --------------------------------FAQs Apis----------------------------------------

Route::group(['prefix' => 'faqs'], function () {
    Route::get('/', [FaqController::class, 'getAllfaqs']);
    Route::post('addFaq', [FaqController::class, 'addFaq']);
    Route::post('updateFaq/{faq_id}', [FaqController::class, 'updateFaq']);
    Route::get('getfaqDetail/{faq_id?}', [FaqController::class, 'getfaqDetailById']);
    Route::post('deletefaq/{faq_id}', [FaqController::class, 'deleteFaq']);
});

Route::post('process', [Welcome::class, 'submitCareerForm']);
Route::post('contactprocess', [Welcome::class, 'submitComplaintForm']);
Route::post('contactForm', [Welcome::class, 'contactForm']);
Route::post('sendMail', [Welcome::class, 'sendMail']);

// ------------------------------Offer Management Apis-------------------------------

Route::group(['prefix' => 'offers'], function () {
    Route::get('/', [OfferController::class, 'getAllOffers']);
    Route::post('addOffer', [OfferController::class, 'addOffer']);
    Route::post('updateOffer/{offer_id}', [OfferController::class, 'UpdateOffer']);
    Route::get('getOfferDetail/{offer_id?}', [OfferController::class, 'getOfferDetailByOfferId']);
    Route::post('deleteOffer/{offer_id}', [OfferController::class, 'deleteOffer']);
    Route::get('salon/{salon_id}', [OfferController::class, 'getOfferBySalonId']);
});

// ------------------------------Payment  Apis----------------------------------------

Route::group(['prefix' => 'payment'], function () {
    Route::get('/', [ServiceController::class, 'getAllPaymentHistory']);
    Route::get('/getPayment/{salon_id}', [ServiceController::class, 'getPaymentHistoryBySalonId']);
    Route::get('/byCustomer/{customer_id}', [ServiceController::class, 'getPaymentHistoryByCustomerId']);
    Route::post('/addPayment', [ServiceController::class, 'addPayment']);
    Route::get('/Salon-Stats/{salon_id}', [ServiceController::class, 'getSalonStats']);
    Route::get('/all-Salon-Stats', [ServiceController::class, 'getAllSalonStats']);
    Route::get('/salon-stats', [ServiceController::class, 'getSalonStatistics']);
	Route::get('/salon-stats-v2', [ServiceController::class, 'getSalonStatisticsV2']);
});
});

