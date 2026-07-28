<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CategoryModel extends Model
{
    use HasFactory;
    public static function addCategory($insert)
    {
        DB::table('categories')
        ->insert($insert);
        return DB::getPdo()
        ->lastInsertId();
    }

    public static function addCategoryServices(array $insert): bool
    {
        return DB::table('category_services')->insert($insert);
    }

    public static function getAllCategories($status = null)
    {
        $query = DB::table('categories')
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public static function getCategoryById($categoryId)
    {
        return DB::table('categories')
            ->where('id', $categoryId)
            ->first();
    }

    public static function getCategoryServices($categoryId)
    {
        return DB::table('category_services as cs')
            ->join('services as s', 's.service_id', '=', 'cs.service_id')
            ->where('cs.category_id', $categoryId)
            ->select(
                'cs.category_id',
                'cs.service_id',
                's.*'
            )
            ->get();
    }

    public static function getServicesByCategoryIds(array $categoryIds)
    {
        if (empty($categoryIds)) {
            return collect();
        }

        return DB::table('category_services as cs')
            ->join('services as s', 's.service_id', '=', 'cs.service_id')
            ->whereIn('cs.category_id', $categoryIds)
            ->select(
                'cs.category_id',
                'cs.service_id',
                's.*'
            )
            ->get();
    }

    public static function updateCategory(
        $categoryId,
        array $update
    ): int {
        return DB::table('categories')
            ->where('id', $categoryId)
            ->update($update);
    }

    public static function syncCategoryServices(
        $categoryId,
        array $serviceIds
    ): bool {
        $existingServiceIds = DB::table('category_services')
            ->where('category_id', $categoryId)
            ->pluck('service_id')
            ->map(function ($serviceId) {
                return (int) $serviceId;
            })
            ->toArray();

        $serviceIds = array_values(
            array_unique(
                array_map('intval', $serviceIds)
            )
        );

        $serviceIdsToAdd = array_diff(
            $serviceIds,
            $existingServiceIds
        );

        $serviceIdsToRemove = array_diff(
            $existingServiceIds,
            $serviceIds
        );

        if (!empty($serviceIdsToRemove)) {
            DB::table('category_services')
                ->where('category_id', $categoryId)
                ->whereIn('service_id', $serviceIdsToRemove)
                ->delete();
        }

        if (!empty($serviceIdsToAdd)) {
            $insert = [];

            foreach ($serviceIdsToAdd as $serviceId) {
                $insert[] = [
                    'category_id' => $categoryId,
                    'service_id' => $serviceId,
                ];
            }

            return DB::table('category_services')->insert($insert);
        }

        return true;
    }

    public static function deleteCategory($categoryId): int
    {
        return DB::table('categories')
            ->where('id', $categoryId)
            ->update([
                'status' => 'Inactive',
                'updated_at' => now(),
            ]);
    }
}
