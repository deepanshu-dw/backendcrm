<?php

namespace App\Http\Controllers;

use App\Models\SlotModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SlotController extends Controller
{
    public function addSlot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slot_name_es' => 'required',
            'slot_name_pl' => 'required',
            'slot_name_en' => 'required',
            'slot_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()], 400);
        }
        $insert = [
            'slot_name_es' => $request->post('slot_name_es'),
            'slot_name_en' => $request->post('slot_name_en'),
            'slot_name_pl' => $request->post('slot_name_pl'),
            'slot_time' => $request->post('slot_time'),
        ];
        $result = slotModel::addSlot($insert);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot Added Successfully ', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Try After Sometime']);
        }
    }
    public function getSlotDetail($lang, $slot_id)
    {
        $result = SlotModel::getSlotDetail($lang, $slot_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slots Available', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Slots Available']);
        }
    }

    public function updateSlot(Request $request, $slot_id)
    {
        $validator = validator::make($request->all(), [
            'slot_name_en' => 'required',
            'slot_name_pl' => 'required',
            'slot_name_es' => 'required',
            'slot_time' => 'required',
        ]);

        $update = [
            'slot_name_en' => $request->post('slot_name_en'),
            'slot_name_pl' => $request->post('slot_name_pl'),
            'slot_name_es' => $request->post('slot_name_es'),
            'slot_time' => $request->post('slot_time'),
        ];
        $result = SlotModel::updateSlot($update, $slot_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot Updated Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }

    public function deleteSlot(Request $request, $slot_id)
    {
        $slotdetails = SlotModel::getSlotDetail('en', $slot_id);
        if (empty($slot_id)) {
            return response()->json(['result' => -1, 'msg' => 'Slot is required']);
        }
        if (empty($slotdetails)) {
            return response()->json(['result' => -1, 'msg' => 'id is not present in db']);
        }

        $result = SlotModel::deleteSlot($slot_id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot deleted successfully.', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ']);
        }
    }

    public function getAllSlotDetails($lang)
    {
        $result = SlotModel::getAllSlotDetails($lang);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slots Available', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Slots Available']);
        }
    }
	
	    
	// ----------------------------------------------------------------------------slots per salon-----------------------------------------------------------------------------
    public function addSalonSlot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required',
            'slot_name_es' => 'required',
            'slot_name_pl' => 'required',
            'slot_name_en' => 'required',
            'slot_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()], 400);
        }
        $insert = [
            'salon_id' => $request->post('salon_id'),
            'slot_name_es' => $request->post('slot_name_es'),
            'slot_name_en' => $request->post('slot_name_en'),
            'slot_name_pl' => $request->post('slot_name_pl'),
            'slot_time' => $request->post('slot_time'),
        ];
        $result = slotModel::addSalonSlot($insert);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot Added Successfully ', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Try Again Later']);
        }
    }

    public function updateSalonSlot(Request $request)
    {
        $validator = validator::make($request->all(), [
            'slot_name_en' => 'required',
            'slot_name_pl' => 'required',
            'slot_name_es' => 'required',
            // 'slot_time' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()], 400);
        }

        $salon_id =  $request->post('salon_id');
		$slot_id =  $request->post('slot_id');
		
		$slot = select('slots', 'slot_id', ['slot_id' => $slot_id, 'status' => 'Active'])->first();
		if (empty($slot)) {
			return response()->json(['result' => -1, 'msg' => 'Slot not available with this Id!']);
		}
		
        $update = [
            'slot_name_en' => $request->post('slot_name_en'),
            'slot_name_pl' => $request->post('slot_name_pl'),
            'slot_name_es' => $request->post('slot_name_es'),
            'slot_time' => $request->post('slot_time'),
        ];
        $result = SlotModel::updateSalonSlot($update,$salon_id, $slot_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot Updated Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }
     public function getAllSalonSlotDetails($lang)
    {
        $result = SlotModel::getAllSalonSlotDetails($lang);
        if ($result->isNotEmpty()) {
            foreach($result as $row){
                $row->formate_date = date('H:i',strtotime($row->slot_time));
            }
            return response()->json(['result' => 1, 'msg' => 'Slots Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Slots ']);
        }
    }

    public function deleteSalonSlot($salon_id, $slot_id)
    {
		if (empty($salon_id)) {
            return response()->json(['result' => -1, 'msg' => 'Salon is required']);
        }
		
        /* $slotdetails = SlotModel::getSalonSlot('en', $salon_id);
        if (empty($slotdetails)) {
            return response()->json(['result' => -1, 'msg' => 'id is not present in db']);
        } */
		
		$slot = select('slots', '*', [['salon_id', '=', $salon_id], ['status', '!=', 'Deleted']])->first();
		
		if (!empty($slot)) {
			return response()->json(['result' => -1, 'msg' => 'Slot is not available with this Id!']);
		}

        $result = SlotModel::deleteSalonSlot($salon_id, $slot_id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Slot deleted successfully.', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ']);
        }
    }
	
	public function deleteSalonsSlot(Request $request)
    {
		try {
			$validator = Validator::make($request->all(), [
				'slot_id' => 'required'
			], [
				'required' => 'This :attribute is required'
			]);

			if ($validator->fails()) {
				return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
			}

            $result = update('slots', 'slot_id', $request->post('slot_id'), ['status' => 'Deleted']);
			return response()->json(['result' => 1, 'msg' => 'Salon deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }

    public function getSalonSlot($lang, $salon_id, $worker_id = null, $booking_date = null)
    {
		if (!empty($worker_id) && !empty($booking_date)) {
			$existingBooking = DB::table('booking')
				->where('salon_id', $salon_id)
				->where('worker_id', $worker_id)
				->where('booking_date', 'LIKE', $booking_date . '%')
				->where('status', 'Active')
				->orderByDesc('booking_id')
				->first();
				
			$existingSlots = !empty($existingBooking->slots) ? $existingBooking->slots : null;
			
			if ($existingSlots) {
				$result = SlotModel::getSalonSlot($lang, $salon_id)->whereNotIn('slot_id', json_decode($existingSlots))->reindex();
			} else {
				$result = SlotModel::getSalonSlot($lang, $salon_id);
			}
		} else {
			$result = SlotModel::getSalonSlot($lang, $salon_id);
		}
		
        if ($result) {
            foreach($result as $row){
                $row->formate_date = !empty($row->slot_time) ? date('H:i',strtotime($row->slot_time)) : null;
            }
            return response()->json(['result' => 1, 'msg' => 'Slots found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()]);
        }
    }


public function getSlotsByServices(Request $request)
{
//    dd('dhsaj');
    try {
        $salon_id = $request->post('salon_id');
        $booking_date = $request->post('booking_date');
        $services_array = $request->post('services'); 
        $lang = $request->post('lang', 'en'); 

   

        // if (empty($salon_id) || empty($booking_date) || empty($services_array) || !is_array($services_array)) {
        //     return response()->json([
        //         'result' => -1, 
        //         'msg' => 'Missing or invalid parameters. Make sure salon_id, booking_date, and services[] are provided.'
        //     ]);
        // }

        // ✅ Step 1: Calculate total service time
        $total_time = 0;
        foreach ($services_array as $service_id) {
            $service = DB::table('services')
                ->select('service_time_taken', 'service_id')
                ->where('service_id', $service_id)
                ->first();

            if (!$service) {
                return response()->json(['result' => -1, 'msg' => "Invalid service ID: $service_id"]);
            }

            $total_time += !empty($service->service_time_taken) ? $service->service_time_taken : 0;
        }

        // ✅ Step 2: Get salon info
        $salon = DB::table('salon')
            ->select('salon_id', 'closing_time')
            ->where('salon_id', $salon_id)
            ->first();

        if (!$salon) {
            return response()->json(['result' => -1, 'msg' => 'Invalid salon ID']);
        }

        $salon_closing_time = Carbon::createFromFormat('H:i:s', $salon->closing_time);
        $salon_closing_minutes = ($salon_closing_time->hour * 60) + $salon_closing_time->minute;

        $slots = DB::table('slots')
        ->select(
            'slot_name_en',
            'slot_name_es',
            'slot_name_pl',
            'slot_time',
            'slot_id',
            'salon_id'
        )
        ->where('salon_id', $salon_id)
        ->where('status', '!=', 'Deleted')
        ->get();


        // ✅ Step 4: Filter slots by closing time
        $slots = $slots->filter(function ($slot) use ($total_time, $salon_closing_minutes) {
            $slot_time_parts = explode(':', $slot->slot_time);
            $slot_hour = (int)$slot_time_parts[0];
            $slot_minute = (int)$slot_time_parts[1];

            $slot_start_minutes = ($slot_hour * 60) + $slot_minute;
            $service_end_minutes = $slot_start_minutes + $total_time;

            return $service_end_minutes <= $salon_closing_minutes;
        })->values();

        // ✅ Step 5: Format slot time
        foreach ($slots as $row) {
            $row->formate_date = !empty($row->slot_time) ? date('H:i', strtotime($row->slot_time)) : null;
        }

        // ✅ Step 6: Return final response
        return response()->json([
            'result' => 1,
            'msg' => 'Filtered slots based on selected services',
            'data' => $slots
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'result' => -1,
            'msg' => 'An error occurred: ' . $e->getMessage()
        ]);
    }
}




}
