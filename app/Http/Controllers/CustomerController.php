<?php

namespace App\Http\Controllers;

use App\Models\CustomerModel;
use App\Models\SalonModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    public function addCustomer(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'customer_name' => 'required',
            // 'email' => 'required|email',
            'phone' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }

        $admin_id = $request->input('admin_id');
        $insert = [
            'customer_name' => $request->post('customer_name'),
            'gender' => $request->post('gender'),
            'age' => $request->post('age'),
            'surname' => $request->post('surname'),
            'email' => $request->post('email') ?? null,
            'phone' => $request->post('phone'),
            'note' => $request->post('note'),
            'pesel' => $request->post('pesel'),
            'created_at' => date('Y-m-d h:i:s'),
            'updated_at' => date('Y-m-d h:i:s'),
        ];
        $result = CustomerModel::addCustomer($insert);

        if ($result) {
            DB::table('activity_logs')->insert([
                'admin_id'   => $admin_id,
                'action'     => 'Customer Created',
                'description'=> "New Customer created: {$insert['customer_name']}])",
                'created_at' => now(),
            ]);
            return response()->json(['result' => 1, 'msg' => 'Customer Added Successfully', 'data' => $insert]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Customer Not Added']);
        }
    }

    /* public function getAllCustomers(Request $request, $salon_id = null, $customer_id = null)
    {
        $keyword = $request->query('keyword');
        $result = CustomerModel::getAllCustomer($keyword, $customer_id);
        if (!empty($result)) {
            foreach ($result as $row) {
				$bookings = select('booking', '*', ['customer_id' => $row->customer_id]);
				if (!empty($bookings)) {
					foreach ($bookings as $booking) {
						$agreement_documents = select('agreement_documents', ['document_file', 'contract_file'], [['booking_id', '=', $booking->booking_id], ['booking_id', '!=', null]]);
						$row->agreement_documents = $agreement_documents;

						if ($agreement_documents->isNotEmpty()) {
							foreach ($agreement_documents as $col) {
								$col->signature = !empty($col->document_file) ? url('uploads/') . '/' . $col->document_file : null;
								$col->path = !empty($col->contract_file) ? url('uploads/') . '/' . $col->contract_file : null;
							}
						}
					}
				}
				
                $agreementData = select('agreement', ['pathToPDF', 'pathToSignature'], [['mongo_customer_id', '=', @$row->mongo_id]]);
                $row->customers = $agreementData;

                if ($agreementData->isNotEmpty()) {
                    foreach ($agreementData as $col) {
                        $col->signature = !empty($col->pathToSignature) ? url('uploads/') . '/' . $col->pathToSignature : null;
                        $col->path = !empty($col->pathToPDF) ? url('uploads/') . '/' . $col->pathToPDF : null;
                    }
                }
            }
        }

        if (!empty($salon_id)) {
            $customers = CustomerModel::getCustomerHistoryBySalon($salon_id, $lang = "en");
            $customer_ids = $customers->map(function ($item) {
                return $item->customer_id;
            });

            $result1 = $result->whereIn('customer_id', $customer_ids)->reindex();
        }

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    } */
	
	public function getAllCustomers(Request $request, $salon_id = null, $customer_id = null)
    {
        $keyword = $request->query('keyword');
		$customers = CustomerModel::getAllCustomer($keyword, $customer_id);

		if ($customers->isNotEmpty()) {
			// Collect customer IDs
			$customer_ids = $customers->pluck('customer_id');

			// Fetch all agreement documents for all bookings related to each customer
			$agreement_documents = DB::table('agreement_documents')
				->select('agreement_documents.*', 'booking.customer_id')
				->join('booking', 'booking.booking_id', '=', 'agreement_documents.booking_id')
				->whereIn('booking.customer_id', $customer_ids)
				->whereNotNull('agreement_documents.contract_file')
				->where('agreement_documents.contract_file', '!=', '')
				->where('agreement_documents.contract_file', '!=', null)
				->get();

			// Group agreement documents by customer_id
			$documents_by_customer = $agreement_documents->groupBy('customer_id');

			// Attach agreement documents to the respective customers
			foreach ($customers as $customer) {
				$customer->agreement_documents = $documents_by_customer->get($customer->customer_id, collect())->map(function ($document) {
					if (isset($document->document_file) && !str_contains($document->document_file, 'amazonaws.com')) {
						$document->signature = !empty($document->document_file) ? baseURL($document->document_file) : null;
					} else {
						$document->signature = !empty($document->document_file) ? $document->document_file : null;
					}
					if (isset($document->contract_file) && !str_contains($document->contract_file, 'amazonaws.com')) {
						$document->path = !empty($document->contract_file) ? baseURL($document->contract_file) : null;
					} else {
						$document->path = !empty($document->contract_file) ? $document->contract_file : null;
					}
					/* $document->signature = !empty($document->document_file) ? baseURL($document->document_file) : null;
					$document->path = !empty($document->contract_file) ? baseURL($document->contract_file) : null; */
					$document->contract_date = !empty($document->created_at) ? $document->created_at : null;
					if (!empty($document->booking_id)) {
						$booking = select('booking', '*', [['booking_id', '=', $document->booking_id]])->first();
						if (!empty($booking->salon_id)) {
							$salon_details = select('salon', 'salon_name_en', [['status', '=', 'Active'], ['salon_id', '=', $booking->salon_id]])->first();
							$document->salon_name = !empty($salon_details->salon_name_en) ? $salon_details->salon_name_en : null;
						}
					}
					return $document;
				});
				if (!empty($customer->customer_id) && is_int($customer->customer_id)) {
					$total_bookings = SalonModel::getTotalBookings((int)$customer->customer_id);
					$customer->total_bookings = $total_bookings['total_bookings'] ?? 0;
					$customer->services_taken = $total_bookings['services_taken'] ?? [];
				}
			}

			// Filter by salon if salon_id is provided
			if (!empty($salon_id)) {
				$customers_from_salon = CustomerModel::getCustomerHistoryBySalon($salon_id, 'en')->pluck('customer_id');
				$customers = $customers->whereIn('customer_id', $customers_from_salon->toArray());
			}

			return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $customers]);
		}

		return response()->json(['result' => -1, 'msg' => 'No Data Found']);
    }
	
	public function getAllCustomerDocuments(Request $request)
	{
		$keyword = $request->query('keyword');
		$customers = CustomerModel::getAllCustomerDocuments($keyword);

		if ($customers->isNotEmpty()) {
			// Collect customer IDs
			$customer_ids = $customers->pluck('customer_id');

			// Fetch agreement documents related to the customers
			$agreement_documents = DB::table('agreement_documents')
				->select('agreement_documents.*', 'booking.customer_id')
				->join('booking', 'booking.booking_id', '=', 'agreement_documents.booking_id')
				->whereIn('booking.customer_id', $customer_ids)
				->whereNotNull('agreement_documents.contract_file')
				->where('agreement_documents.contract_file', '!=', '')
				->get();

			// Group documents by customer ID
			$documents_by_customer = $agreement_documents->groupBy('customer_id');

			// Attach agreement documents to respective customers
			foreach ($customers as $customer) {
				$customer->agreement_documents = $documents_by_customer->get($customer->customer_id, collect())->map(function ($document) {
					$document->signature = (!empty($document->document_file) && !str_contains($document->document_file, 'amazonaws.com'))
						? baseURL($document->document_file)
						: $document->document_file;

					$document->path = (!empty($document->contract_file) && !str_contains($document->contract_file, 'amazonaws.com'))
						? baseURL($document->contract_file)
						: $document->contract_file;

					$document->contract_date = $document->created_at ?? null;

					// Fetch salon name if available
					if (!empty($document->booking_id)) {
						$booking = DB::table('booking')->where('booking_id', $document->booking_id)->first();
						if (!empty($booking->salon_id)) {
							$salon_details = DB::table('salon')
								->where([['status', '=', 'Active'], ['salon_id', '=', $booking->salon_id]])
								->select('salon_name_en')
								->first();
							$document->salon_name = $salon_details->salon_name_en ?? null;
						}
					}

					return $document;
				});
			}

			return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $customers]);
		}

		return response()->json(['result' => -1, 'msg' => 'No Data Found']);
	}

    public function getCustomerDetail($customer_id, $salon_id = null)
    {
        try {
            $customerDetail = CustomerModel::getCustomerDetail($customer_id);
            $customerHistory = CustomerModel::getCustomerHistory($customer_id, $salon_id);

            $last_visit = null;

            if (!empty($customerHistory)) {
                foreach ($customerHistory as $val) {
                    if (!empty($val->date)) {
                        $last_visit = $val->date;
                    }
                }
            }

            if (!empty($customerHistory['records'])) {
                foreach ($customerHistory['records'] as $val) {
                    $worker_details = select('salon_worker', '*', [['status', '=', 'Active'], ['worker_id', '=', $val->worker_id]])->first();
                    $val->worker_name = !empty($worker_details->worker_name) ? $worker_details->worker_name : null;
                }
            }

            return response()->json(['result' => 1, 'msg' => 'Data srgjdsnd found', 'customerDetail' => $customerDetail, 'customerHistory' => $customerHistory]);
        } catch (\Exception $e) {
            // \Log::error('Error in getCustomerDetail: ' . $e->getMessage());
            return response()->json(['result' => 0, 'msg' => 'Error fetching customer data']);
        }
    }


    public function updateCustomer(Request $request, $customer_id)
    {
        $Validator = Validator::make($request->all(), [
            'customer_name' => 'required',
            // 'email' => 'required|email',
            'phone' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $update = [
            'customer_name' => $request->post('customer_name'),
            'gender' => $request->post('gender'),
            'surname' => $request->post('surname'),
            'age' => $request->post('age'),
            'email' => $request->post('email') ?? null,
            'phone' => $request->post('phone'),
            'note' => $request->post('note'),
            'pesel' => $request->post('pesel'),
            'created_at' => date('Y-m-d h:i:s'),
            'updated_at' => date('Y-m-d h:i:s'),
        ];
        $result = CustomerModel::updateCustomer($update, $customer_id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Customer Updated Successfully', 'data' => $update]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }

    public function deleteCustomer($customer_id)
    {
        $result = CustomerModel::deleteCustomer($customer_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Customer Deleted Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Record Found']);
        }
    }

    public function searchCustomer(Request $request)
    {
        $keyword = $request->post('keywords');
        $salon_id = $request->post('salon_id');
        $lang = !empty($request->post('lang')) ? $request->post('lang') : "en";

        // Check if the keyword is empty
        if (empty($keyword)) {
            return response()->json(['result' => -1, 'msg' => 'Please enter a keyword']);
        }

        $customers = CustomerModel::getCustomerHistoryBySalon($salon_id, $lang);
        $customer_ids = $customers->map(function ($item) {
            return $item->customer_id;
        });

        $result = CustomerModel::searchCustomer($keyword)->whereIn('customer_id', $customer_ids)->reindex();

        if ($result->isNotEmpty()) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Record Found']);
        }
    }


    // public function searchCustomerByEmail(Request $request)
    // {
    //     $keyword = $request->post('email');
    //     $lang = !empty($request->post('lang')) ? $request->post('lang') : "en";
    //     $result = CustomerModel::searchCustomerByEmail($keyword);
    //     if ($result) {
    //         return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
    //     } else {
    //         return response()->json(['result' =>-1, 'msg' => 'No Record Found']);
    //     }
    // }

    public function searchCustomerByEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => -1, 'msg' => 'Keyword is required'], 400);
        }
        $keyword = $request->post('keyword');

        $lang = !empty($request->post('lang')) ? $request->post('lang') : "en";

        $resultByEmail = CustomerModel::searchCustomerByEmailName($keyword);
        $resultByPhone = CustomerModel::searchCustomerByPhone($keyword);

        // $resultByName = CustomerModel::searchCustomerByName($keyword);
        $resultBySurname = CustomerModel::searchCustomerBySurname($keyword);

        if ($resultByEmail->isNotEmpty()) {
            $cid = $resultByEmail->map(function ($item) {
                return $item->customer_id;
            })->toArray();
            $booking = CustomerModel::getBookingsCustomerID($cid);
            return response()->json(['result' => 1, 'msg' => 'Customer data found successfully', 'data' => $booking]);
        }
        if ($resultByPhone->isNotEmpty()) {
            $cids = $resultByPhone->map(function ($item) {
                return $item->customer_id;
            })->toArray();

            $bookings = CustomerModel::getBookingsCustomerID($cids);
            return response()->json(['result' => 1, 'msg' => 'Customer data found successfully', 'data' => $bookings]);
        }
        if ($resultBySurname->isNotEmpty()) {
            return response()->json(['result' => 1, 'msg' => 'Customer data found successfully', 'data' => $resultBySurname]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No customer record found for the provided keyword']);
        }
    }
    public function getCustomerHistoryBySalon($salon_id, $lang = 'en', $customer_id = null)
    {
        // dd("getCustomerHistoryBySalon");
        $result = CustomerModel::getCustomerHistoryBySalon($salon_id, $lang, $customer_id);
        if ($result->isNotEmpty()) {
            foreach ($result as $row) {
                $row->customer = select('customers', '*', [['customer_id', '=', $row->customer_id]])->first();
                $row->service_details = CustomerModel::getAllServices($lang, (explode(',', str_replace(['"', '/', '\\', '[', ']'], "", $row->services))));
                $row->slot_details = CustomerModel::getAllSlotDetails($lang, (explode(',', str_replace(['"', '/', '\\', '[', ']'], "", $row->slots))));
                $worker_details = select('salon_worker', '*', [['status', '=', 'Active'], ['salon_id', '=', $salon_id]])->first();
                $row->worker_name = !empty($worker_details->worker_name) ? $worker_details->worker_name : null;
                $payment_history = select('payment_history', '*', [['status', '=', 'Active'], ['booking_id', '=', @$row->booking_id]])->first();
                $row->payment_history = !empty($payment_history) ? $payment_history : null;
                $pricing_details = select('booking_pricing', '*', [['status', '=', 'Active'], ['booking_id', '=', @$row->booking_id]]);
                if (!empty($pricing_details)) {
                    foreach ($pricing_details as $p) {
                        $p->service_name_en = select('services', 'service_name_en', [['status', '=', 'Active'], ['service_id', '=', @$p->service_id]])->first()->service_name_en;
                        $p->service_name_es = select('services', 'service_name_es', [['status', '=', 'Active'], ['service_id', '=', @$p->service_id]])->first()->service_name_es;
                        $p->service_name_pl = select('services', 'service_name_pl', [['status', '=', 'Active'], ['service_id', '=', @$p->service_id]])->first()->service_name_pl;
                    }
                    $row->pricing_details = !empty($pricing_details) ? $pricing_details : null;
                }
            }
        }
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    }

    public function getCustomerHistory($customer_id, $salon_id = null)
    {

        $result = CustomerModel::getCustomerHistory($customer_id, $salon_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    }

    public function setFireToken(Request $request)
    {
        $customer_id = $request->post('customer_id');
        $firebase_token = $request->post('firebase_token');
        $device_type = $request->post('device_type');
        $customer = @select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $customer_id]])->first();

        $check = CustomerModel::checkFireToken($customer_id);
        //dd($check);

        if (!empty($check)) {
            CustomerModel::updateFireToken($check->customer_id, $firebase_token, $device_type);
        } else {
            $result = CustomerModel::insertFireToken($customer_id, $firebase_token, $device_type);
        }
        return response()->json(['result' => 1, 'msg' => 'Token Id Updated']);
    }

    public function getImageGenerationCount(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'customer_id' => 'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $customer_id = $request->post('customer_id');
        $result = select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $customer_id]])->first();
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Image generation count found', 'data' => ['image_generation_count' => $result->image_generation_count]]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No data found!']);
        }
    }

    public function setImageGenerationCount(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'customer_id' => 'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $customer_id = $request->post('customer_id');

        $count = select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $customer_id]])->first();
        $image_count = $count->image_generation_count;

        if ($image_count < 5) {
            $newCount = $image_count + 1;
            $result = update('customers', 'shopify_user_id', $customer_id, ['image_generation_count' => $newCount, 'updated_at' => now()]);
            return response()->json(['result' => 1, 'msg' => "Count updated to $newCount"]);
        } else {
            return response()->json(['result' => 1, 'msg' => 'Image generation limit exceeded!']);
        }
    }

    public function resetImageGenerationCount(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'customer_id' => 'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $customer_id = $request->post('customer_id');

        $customer = select('customers', '*', [['status', '=', 'Active'], ['shopify_user_id', '=', $customer_id]])->first();

        if ($customer) {
            $result = update('customers', 'shopify_user_id', $customer_id, ['image_generation_count' => 0, 'updated_at' => now()]);
            return response()->json(['result' => 1, 'msg' => "Count reset to 0"]);
        } else {
            return response()->json(['result' => 1, 'msg' => 'No customer found!']);
        }
    }
}
