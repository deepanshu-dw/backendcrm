<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SlotModel extends Model
{
    use HasFactory;
    public static function addSlot($insert)
    {
         DB::table('slots')->insert($insert);
        return DB::getPdo()->lastInsertId();
    }

    public static function getSlotDetail($lang, $slot_id)
    {
        $slots = DB::table('slots');
        if(!empty($lang)){
             $slots->select('slot_name_' . $lang . ' as slot_name', 'slot_time', 'slot_id');
        }
       return  $slots->where('slot_id', $slot_id)
        ->where('status', '!=', 'Deleted')
        ->get()->first();

    }

    public static function updateSlot($update, $slot_id)
    {
        return DB::table('slots')->where('slot_id', $slot_id)->update($update);
    }

    public static function deleteSlot($slot_id)
    {
        return DB::table('slots')->where('slot_id', $slot_id)->update(['status' => 'Deleted']);

    }
    public static function getAllSlotDetails($lang )
    {
        $slots = DB::table('slots');
        if(!empty($lang)){
            $slots->select('slot_name_' . $lang . ' as slot_name', 'slot_time', 'slot_id');
        }
            
        return $slots ->where('status', '!=', 'Deleted')
            ->get();
       
    }
	
	// ---------------------------------------------------slots per salon-----------------------------------------------------------
    public static function addSalonSlot($insert)
    {
         DB::table('slots')->insert($insert);
        return DB::getPdo()->lastInsertId();
    }
     
    public static function updateSalonSlot($update,$salon_id, $slot_id)
    {
        return DB::table('slots')
        ->where('salon_id', $salon_id)
		->where('slot_id', $slot_id)
        ->update($update);
 
    }

    public static function getAllSalonSlotDetails($lang)
    {
        $slots = DB::table('slots');
        if(!empty($lang)){
            $slots->select('slot_name_' . $lang . ' as slot_name', 'slot_time', 'slot_id','salon_id');
        }
            
        return $slots ->where('status', '!=', 'Deleted')
            ->get();

    }

    public static function deleteSalonSlot($salon_id, $slot_id)
    {
        return DB::table('slots')
        ->where('salon_id', $salon_id)
		->where('slot_id', $slot_id)
        ->update(['status' => 'Deleted']);

    }

    public static function getSalonSlot($lang, $salon_id)
    {
        $data = DB::table('slots');
        if(!empty($lang)){
             $data->select('slot_name_' . $lang . ' as slot_name', 'slot_time', 'slot_id','salon_id');
        }
		return  $data->where('salon_id', $salon_id)
        ->where('status', '!=', 'Deleted')
        ->get();
    }
}
