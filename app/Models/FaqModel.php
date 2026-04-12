<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FaqModel extends Model
{
    use HasFactory;

    public static function addFaq($insert)
    {
        DB::table('faqs')->insert($insert);
        return DB::getPdo()->lastInsertId();
    }

    public static function getAllfaqs()
    {
        return DB::table('faqs')
            ->select('*')
            ->where('status', '!=', 'Deleted')
            ->get();
    }

    public static function getfaqDetailById($faq_id)
    {
        return DB::table('faqs')
            ->where('faq_id', $faq_id)
            ->get()->first();
    }

    public static function updateFaq($update, $faq_id)
    {
        return DB::table('faqs')
            ->where('faq_id', $faq_id)
            ->update($update);
    }

    public static function deleteFaq($faq_id)
    {
        return DB::table('faqs')
            ->where('faq_id', $faq_id)
            ->update(['status' => 'Deleted']);
    }
}
