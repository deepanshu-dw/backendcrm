<?php

namespace App\Http\Controllers;

use App\Models\OfferModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OfferController extends Controller
{
    public function addOffer(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'offer_name' => 'required',
            'service_category' => 'required',
            // 'is_percentage' => 'required',
            // 'percentage' => 'required',
            'upto' => 'required',
            // 'coupon_code' => 'required',
            'offer_type' => 'required',
            // 'salon_id'=> 'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
		$salon_id = $request->post('salon_id');
        $offer_name = $request->post('offer_name');
		$insert=[
          'offer_name'=>$offer_name,
          'service_category'=>$request->post('service_category'),
          'is_percentage'=>$request->post('is_percentage'),
          'percentage'=>$request->post('percentage'),
          'upto'=>$request->post('upto'),
          'coupon_code'=>$request->post('coupon_code'),
          'offer_type'=>$request->post('offer_type'),
          'salon_id'=>$salon_id,
          'offer_start_date'=>$request->post('offer_start_date'),
          'offer_end_date'=>$request->post('offer_end_date')         
        ];   
        
        $result = OfferModel::addOffer($insert);
        if($result){
            $type = $request->post('offer_type');
            if ($type == 'salon') {
                sendFirebaseNotification($salon_id, $offer_name, 'You have a new offer', 'Offer');     
            }
            if ($type == 'general') {
                // dd($request->post('offer_name'));
                $salons = select('salon_authentication','salon_id');
                if($salons->isNotEmpty()){
                    foreach($salons as $row){
                        sendFirebaseNotification($row->salon_id, $request->post('offer_name'), 'You have a new offer', 'Offer');  
                    }
                }

                    
            }

            return response()->json(['result'=> 1 , 'msg'=>'Offer Added Successfully', 'data'=>$result]);   
        }else{
            return response()->json(['result'=> -1 , 'msg'=>'Try Again Later', 'data'=>$result]);
        }
    }

    public function getAllOffers()
    {
        $offers = OfferModel::getAllOffers();
        if (!empty($offers)) {
            $formattedOffers = $offers->map(function ($offer) {
                
            $offer->offer_start_date = Carbon::parse($offer->offer_start_date)->format('d-m-Y');
            $offer->offer_end_date = Carbon::parse($offer->offer_end_date)->format('d-m-Y');
            return $offer;                    
            });
                return response()->json(['result' => 1, 'msg' => 'Offer Found', 'data' => $offers]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'No Offer']);
            }
        
    }

    public function getOfferDetailByOfferId($offer_id)
    {
        $result = OfferModel::getOfferDetailByOfferId($offer_id);
        if($result){
            return response()->json(['result'=> 1 , 'msg'=>'Offer Found', 'data'=>$result]);
        }else{
            return response()->json(['result'=> -1 , 'msg'=>'No Offer', 'data'=>$result]);
        }
    }

    public function updateOffer(Request $request,$offer_id)
    {
        $Validator = Validator::make($request->all(), [
            'offer_name' => 'required',
            'service_category' => 'required',
            // 'is_percentage' => 'required',
            // 'percentage' => 'required',
            'upto' => 'required',
            'coupon_code' => 'required',
            'offer_type' => 'required',
            // 'salon_id'=> 'required'
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $update=[
          'offer_name'=>$request->post('offer_name'),
          'service_category'=>$request->post('service_category'),
          'is_percentage'=>$request->post('is_percentage'),
          'percentage'=>$request->post('percentage'),
          'upto'=>$request->post('upto'),
          'coupon_code'=>$request->post('coupon_code'),
          'offer_type'=>$request->post('offer_type'),
          'salon_id'=>$request->post('salon_id'),
          'offer_start_date'=>$request->post('offer_start_date'),
          'offer_end_date'=>$request->post('offer_end_date')         
        ];

        $result=OfferModel::updateOffer($update,$offer_id);
        if($result){
            return response()->json(['result'=> 1 , 'msg'=>'Offer Updated Successfully', 'data'=>$result]);
        }else{
            return response()->json(['result'=> -1 , 'msg'=>'No Changes Updated ', 'data'=>$result]);
        }
    }

    public function deleteOffer($offer_id)
    {
        $result = OfferModel::deleteOffer($offer_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Offer deleted successfully']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Oops... Something went wrong!']);
        }
    }
	
    public function getOfferBySalonId($salon_id)
    {
        $result = OfferModel::getOfferBySalonId($salon_id);
    
        if ($result->isNotEmpty()) {
            return response()->json(['result' => 1, 'msg' => 'Offer Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Offer', 'data' => null]);
        }
    }

}
