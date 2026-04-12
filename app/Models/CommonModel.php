<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommonModel extends Model
{
    use HasFactory;
	
    public static function getAllBanner()
    {
        return DB::table('banner_logos')
            ->select('*')
            ->where('status', '!=', 'Deleted')
            ->get();
    }

    public static function addBanner($bannerImage)
    {
        return DB::table('banner_logos')
            ->insert($bannerImage);
    }

    public static function updateBanner($update, $banner_id)
    {
        return DB::table('banner_logos')
            ->where('banner_id', $banner_id)
            ->where('status', '!=', 'Deleted')
            ->update($update);
    }

    public static function getAllCurrencies()
    {
        return DB::table('currency')
            ->select('*')
            ->get();
    }

    public static function getBannerById($banner_id)
    {
        return DB::table('banner_logos')
            ->where('banner_id', $banner_id)
            ->where('status', '!=', 'Deleted')
            ->get()
            ->first();
    }

    public static function deleteBanner($banner_id)
    {
        return DB::table('banner_logos')
            ->where('banner_id', $banner_id)
            ->update(['status' => 'Deleted']);
    }

    public static function getActivityLogs()
    {
        return DB::table('activity_logs')
        ->join('admins', 'activity_logs.admin_id', '=', 'admins.admin_id')
        ->select('activity_logs.*', 'admins.admin_name', 'admins.admin_email')
        ->get();
    }
}
