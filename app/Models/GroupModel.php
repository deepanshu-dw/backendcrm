<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class GroupModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_name_en',
        'group_name_es',
        'group_name_pl',
        // Add more attributes that you want to be fillable
    ];

    public static function addGroup($insert)
    {
        DB::table('groups')->insert($insert);
        return DB::getPdo()->lastInsertId();
    }

    public static function getGroupDetail($lang, $group_id)
    {
        $group = DB::table('groups');
        if (!empty($lang)) {
            $group->select('group_name_' . $lang . ' as group_name', 'group_id');
        }
        $group->where('group_id', $group_id);
        $group->where('status', '!=', 'Deleted');
        
        return $group->first();
    }

    public static function updateGroup($update, $group_id)
    {
        return DB::table('groups')
            ->where('group_id', $group_id)
            ->update($update);
    }

    public static function getAllGroups($lang)
    {
        $group = DB::table('groups');
        if (!empty($lang)) {
            $group->select('group_name_' . $lang . ' as group_name', 'group_id');
        }
        $group->where('status', '!=', 'Deleted');
        
        return $group->get();
    }

    public static function deleteGroup($group_id)
    {
        return DB::table('groups')
            ->where('group_id', $group_id)
            ->update(['status' => 'Deleted']);
    }
}
