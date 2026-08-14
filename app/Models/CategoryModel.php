<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CategoryModel extends Model
{
    use HasFactory;

    public static function addCategory(array $insert)
    {
        return DB::table("categories")->insertGetId($insert);
    }

    public static function categoryNameExists($name,$excludeCategoryId = null): bool {
        $query = DB::table("categories")
            ->where('status', 'Active')
            ->whereRaw("LOWER(TRIM(name)) = LOWER(?)", [trim($name)]);

        if (!empty($excludeCategoryId)) {
            $query->where("id", "!=", (int) $excludeCategoryId);
        }

        return $query->exists();
    }

    public static function getAllCategories($status = "Active",$keyword = null) {
        $query = DB::table("categories")
            ->orderBy("created_at", "desc");

        if (!empty($status)) {
            $query->where("status", $status);
        }

        if (!empty($keyword)) {
            $query->where(
                "name",
                "LIKE",
                "%" . trim($keyword) . "%"
            );
        }

        return $query->get();
    }

    public static function getCategoryById($categoryId,$status = "Active") {
        return DB::table("categories")
            ->where("id", $categoryId)
            ->where("status", $status)
            ->first();
    }

    public static function getServicesByCategoryId($categoryId)
    {
        return DB::table("services")
            ->where("category_id", $categoryId)
            ->orderBy("service_id", "desc")
            ->get();
    }

    public static function getServicesByCategoryIds($categoryIds, $status = "Active") {
        if (empty($categoryIds)) {
            return collect();
        }

        return DB::table("services")
            ->where("status", $status)
            ->whereIn("category_id", $categoryIds)
            ->orderBy("service_id", "desc")
            ->get();
    }

    public static function assignServicesToCategory($categoryId,array $serviceIds): int {
        if (empty($serviceIds)) {
            return 0;
        }

        return DB::table("services")
            ->whereIn("service_id", $serviceIds)
            ->whereNull("category_id")
            ->update([
                "category_id" => $categoryId,
                "updated_at" => now(),
            ]);
    }

    public static function removeServicesFromCategory($categoryId,array $serviceIds): int {
        if (empty($serviceIds)) {
            return 0;
        }

        return DB::table("services")
            ->where("category_id", $categoryId)
            ->whereIn("service_id", $serviceIds)
            ->update([
                "category_id" => null,
                "updated_at" => now(),
            ]);
    }

    public static function removeAllCategoryServices($categoryId): int {
        return DB::table("services")
            ->where("category_id", $categoryId)
            ->update([
                "category_id" => null,
                "updated_at" => now(),
            ]);
    }

    public static function syncCategoryServices($categoryId,array $requestedServiceIds): bool {
        $requestedServiceIds = array_values(
            array_unique(array_map("intval", $requestedServiceIds))
        );

        $currentServiceIds = DB::table("services")
            ->where("category_id", $categoryId)
            ->pluck("service_id")
            ->map(function ($serviceId) {
                return (int) $serviceId;
            })
            ->toArray();

        $serviceIdsToAssign = array_values(
            array_diff(
                $requestedServiceIds,
                $currentServiceIds
            )
        );

        $serviceIdsToRemove = array_values(
            array_diff(
                $currentServiceIds,
                $requestedServiceIds
            )
        );

        if (!empty($serviceIdsToAssign)) {
            $assignedCount = self::assignServicesToCategory(
                $categoryId,
                $serviceIdsToAssign
            );

            if ($assignedCount !== count($serviceIdsToAssign)) {
                return false;
            }
        }

        if (!empty($serviceIdsToRemove)) {
            self::removeServicesFromCategory(
                $categoryId,
                $serviceIdsToRemove
            );
        }

        return true;
    }

    public static function updateCategory(
        $categoryId,
        array $update
    ): int {
        return DB::table("categories")
            ->where("id", $categoryId)
            ->update($update);
    }

    public static function deleteCategory($categoryId): int
    {
        return DB::table("categories")
            ->where("id", $categoryId)
            ->update([
                "status" => "Inactive",
                "updated_at" => now(),
            ]);
    }

    public static function permanentlyDeleteCategory(
        $categoryId
    ): int {
        return DB::table("categories")
            ->where("id", $categoryId)
            ->delete();
    }
}