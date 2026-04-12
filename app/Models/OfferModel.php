<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class OfferModel extends Model
{
    use HasFactory;

    public static function addOffer($insert){
    $data =DB::table('offer_management')->insert($insert);
    return $data;
    }
    public static function getAllOffers()
    {
        return DB::table('offer_management')
        ->select('offer_management.*','salon.salon_id','salon.salon_name_en as salon_name')
        ->leftJoin('salon','offer_management.salon_id','=','salon.salon_id')
        ->where('offer_management.status','=', 'Active')
        ->get();
    }

    public static function getOfferDetailByOfferId($offer_id){
          return DB::table('offer_management')
          ->where('offer_id', $offer_id)
          ->get()
          ->first();

    }

    public static function updateOffer($update, $offer_id)
    {
        return DB::table('offer_management')
            ->where('offer_id', $offer_id)
            ->update($update);
    }
    
    public static function deleteOffer($offer_id)
    {
        return DB::table('offer_management')
            ->where('offer_id', $offer_id)
            ->update(['status' => 'Deleted']);
    }
	
    public static function getOfferBySalonId($salon_id)
    {
		return DB::table('offer_management')
			->select('offer_management.*','salon.salon_name_en as salon_name')
            ->join('salon','offer_management.salon_id','=','salon.salon_id')
			->where('offer_management.salon_id', $salon_id)
			->where('offer_management.status', '!=', 'Deleted')
			->get();
    }
	
	public static function getCustomerHistoryBySalon($salon_id, $lang, $customer_id=null)
    {
        $data = DB::table('booking')
            ->where('salon_id', $salon_id);
        if (!empty($customer_id)) {
            $data->where('customer_id', $customer_id);
        }
        return $data = $data->get();
    }
}
