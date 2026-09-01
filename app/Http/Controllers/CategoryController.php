<?php

namespace App\Http\Controllers;

use App\Models\CategoryModel;
use App\Models\ServiceModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function addCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:100",
            "description" => "required|string|max:500",
            "booking_type" => "required|in:fixed_time,time_window",
            "service_ids" => "sometimes|nullable",
        ], [
            "name.required" => "Category Name is required.",
            "name.string"   => "Category Name must be a string.",
            "name.max"      => "Category Name is too long.",

            "description.required" => "Description is required.",
            "description.string"   => "Description must be a string.",
            "description.max"      => "Description is too long.",

            "booking_type.required" => "Booking Type is required.",
            "booking_type.in"       => "Invalid booking type.",

            "service_ids" => "Invalid service IDs.",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "result" => 0,
                "errors" => $validator->errors()->first(),
            ]);
        }

        $categoryName = trim($request->input("name"));

        if (CategoryModel::categoryNameExists($categoryName)) {
            return response()->json([
                "result" => 0,
                "msg" => "Category already exists. Please enter a unique Category name.",
            ]);
        }

        $serviceIds = $this->prepareServiceIds(
            $request->input("service_ids"),
            false
        );

        if (!empty($serviceIds)) {
            $invalidServiceResponse = $this->validateServicesForCategory(
                $serviceIds
            );

            if ($invalidServiceResponse) {
                return $invalidServiceResponse;
            }
        }

        $categoryInsert = [
            "name" => $categoryName,
            "description" => trim($request->input("description")),
            "booking_type" => $request->input("booking_type"),
            "status" => "Active",
            "created_at" => now(),
            "updated_at" => now(),
        ];

        $categoryId = CategoryModel::addCategory($categoryInsert);

        if (!$categoryId) {
            return response()->json([
                "result" => -1,
                "msg" => "Category not added.",
            ]);
        }

        if (!empty($serviceIds)) {
            $assignedCount = CategoryModel::assignServicesToCategory(
                $categoryId,
                $serviceIds
            );

            if ((int) $assignedCount !== count($serviceIds)) {
                CategoryModel::removeAllCategoryServices($categoryId);
                CategoryModel::permanentlyDeleteCategory($categoryId);

                return response()->json([
                    "result" => -1,
                    "msg" => "Category services could not be assigned.",
                ]);
            }
        }

        $adminId = $request->input("admin_id");

        if ($adminId) {
            DB::table("activity_logs")->insert([
                "admin_id" => $adminId,
                "action" => "Category Added",
                "description" => empty($serviceIds)
                    ? "Category created without services: {$categoryInsert['name']}"
                    : "Category created with services: {$categoryInsert['name']}",
                "created_at" => now(),
            ]);
        }

        return response()->json([
            "result" => 1,
            "msg" => "Category added successfully.",
            "data" => [
                "category_id" => (int) $categoryId,
                "service_ids" => $serviceIds,
            ],
        ]);
    }

    public function getAllCategory(Request $request)
    {
        $keyword = $request->query("keyword");
        $type = $request->query("type", "all");

        $categories = CategoryModel::getAllCategories("Active", $keyword, $type);

        if ($categories->isEmpty()) {
            return response()->json([
                "result" => -1,
                "msg" => "No categories found.",
                "data" => [],
            ]);
        }

        $categoryIds = $categories
            ->pluck("id")
            ->map(function ($categoryId) {
                return (int) $categoryId;
            })
            ->toArray();

        $services = CategoryModel::getServicesByCategoryIds($categoryIds);

        $servicesByCategory = $services->groupBy("category_id");

        foreach ($categories as $category) {
            $category->services = $servicesByCategory
                ->get($category->id, collect())
                ->values();
        }

        return response()->json([
            "result" => 1,
            "msg" => "Categories found.",
            "data" => $categories,
        ]);
    }

    public function getCategoryById($categoryId)
    {
        if (!is_numeric($categoryId) || (int) $categoryId <= 0) {
            return response()->json([
                "result" => 0,
                "msg" => "Please provide a valid category ID.",
            ]);
        }

        $categoryId = (int) $categoryId;

        $category = CategoryModel::getCategoryById(
            $categoryId,
            "Active"
        );

        if (!$category) {
            return response()->json([
                "result" => -1,
                "msg" => "Category not found.",
            ]);
        }

        $category->services = CategoryModel::getServicesByCategoryId(
            $categoryId
        );

        return response()->json([
            "result" => 1,
            "msg" => "Category found.",
            "data" => $category,
        ]);
    }

    public function updateCategory(Request $request, $categoryId)
    {
        if (!is_numeric($categoryId) || (int) $categoryId <= 0) {
            return response()->json([
                "result" => 0,
                "msg" => "Please provide a valid category ID.",
            ]);
        }

        $categoryId = (int) $categoryId;

        $category = CategoryModel::getCategoryById($categoryId);

        if (!$category) {
            return response()->json([
                "result" => -1,
                "msg" => "Category not found.",
            ]);
        }

        $validator = Validator::make($request->all(), [
            "name" => "sometimes|required|string|max:100",
            "description" => "sometimes|required|string|max:500",
            "image_url" => "sometimes|nullable|string|max:500",
            "booking_type" => "sometimes|required|in:fixed_time,time_window",
            "status" => "sometimes|required|in:Active,Inactive",
            "service_ids" => "sometimes|required",
        ],[
            "name.required" => "Category Name is required.",
            "name.string"   => "Category Name must be a string.",
            "name.max"      => "Category Name is too long.",

            "description.required" => "Description is required.",
            "description.string"   => "Description must be a string.",
            "description.max"      => "Description is too long.",

            "booking_type.required" => "Booking Type is required.",
            "booking_type.in"       => "Invalid booking type.",

            "service_ids" => "Invalid service IDs.",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "result" => 0,
                "errors" => $validator->errors()->first(),
            ]);
        }

        $allowedFields = [
            "name",
            "description",
            "image_url",
            "booking_type",
            "status",
            "service_ids",
        ];

        if (!$request->hasAny($allowedFields)) {
            return response()->json([
                "result" => 0,
                "msg" => "Please provide at least one field to update.",
            ]);
        }

        if ($request->has("name")) {
            $categoryName = trim($request->input("name"));

            if (
                CategoryModel::categoryNameExists(
                    $categoryName,
                    $categoryId
                )
            ) {
                return response()->json([
                    "result" => 0,
                    "msg" => "Category already exists. Please enter a unique Category name.",
                ]);
            }
        }

        $serviceIds = null;

        if ($request->has("service_ids")) {
            $serviceIds = $this->prepareServiceIds(
                $request->input("service_ids"),
                true
            );

            if ($serviceIds === null) {
                return response()->json([
                    "result" => 0,
                    "msg" => "Service IDs must be a valid array.",
                ]);
            }

            $invalidServiceResponse = $this->validateServicesForCategory(
                $serviceIds,
                $categoryId
            );

            if ($invalidServiceResponse) {
                return $invalidServiceResponse;
            }
        }

        $categoryUpdate = [];

        if ($request->has("name")) {
            $categoryUpdate["name"] = $categoryName;
        }

        if ($request->has("description")) {
            $categoryUpdate["description"] = $request->input(
                "description"
            );
        }

        if ($request->exists("image_url")) {
            $categoryUpdate["image_url"] = $request->input(
                "image_url"
            );
        }

        if ($request->has("booking_type")) {
            $categoryUpdate["booking_type"] = $request->input(
                "booking_type"
            );
        }

        if ($request->has("status")) {
            $categoryUpdate["status"] = $request->input("status");
        }

        if ($serviceIds !== null) {
            $servicesUpdated = CategoryModel::syncCategoryServices(
                $categoryId,
                $serviceIds
            );

            if (!$servicesUpdated) {
                return response()->json([
                    "result" => -1,
                    "msg" => "Category services could not be updated.",
                ]);
            }
        }

        if (!empty($categoryUpdate)) {
            $categoryUpdate["updated_at"] = now();

            CategoryModel::updateCategory(
                $categoryId,
                $categoryUpdate
            );
        }

        $adminId = $request->input("admin_id");

        if ($adminId) {
            $updatedCategoryName =
                $categoryUpdate["name"] ?? $category->name;

            DB::table("activity_logs")->insert([
                "admin_id" => $adminId,
                "action" => "Category Updated",
                "description" => "Category updated with services: {$updatedCategoryName}",
                "created_at" => now(),
            ]);
        }

        return response()->json([
            "result" => 1,
            "msg" => "Category updated successfully.",
            "data" => [
                "category_id" => $categoryId,
                "service_ids" => $serviceIds,
            ],
        ]);
    }

    public function deleteCategory(Request $request, $categoryId)
    {
        if (!is_numeric($categoryId) || (int) $categoryId <= 0) {
            return response()->json([
                "result" => 0,
                "msg" => "Please provide a valid category ID.",
            ]);
        }

        $categoryId = (int) $categoryId;

        $category = CategoryModel::getCategoryById($categoryId);

        if (!$category) {
            return response()->json([
                "result" => -1,
                "msg" => "Category not found.",
            ]);
        }

        if ($category->status === "Inactive") {
            return response()->json([
                "result" => -1,
                "msg" => "Category is already deleted.",
            ]);
        }

        CategoryModel::removeAllCategoryServices($categoryId);

        $result = CategoryModel::deleteCategory($categoryId);

        if (!$result) {
            return response()->json([
                "result" => -1,
                "msg" => "Category could not be deleted.",
            ]);
        }

        $adminId = $request->input("admin_id");

        if ($adminId) {
            DB::table("activity_logs")->insert([
                "admin_id" => $adminId,
                "action" => "Category Deleted",
                "description" => "Category marked inactive: {$category->name}",
                "created_at" => now(),
            ]);
        }

        return response()->json([
            "result" => 1,
            "msg" => "Category deleted successfully.",
        ]);
    }

    private function prepareServiceIds(
        $serviceIds,
        bool $allowEmpty = false
    ): ?array {
        if (is_string($serviceIds)) {
            $serviceIds = json_decode($serviceIds, true);
        }

        if (!is_array($serviceIds)) {
            return null;
        }

        if (empty($serviceIds)) {
            return $allowEmpty ? [] : null;
        }

        $serviceIds = array_values(
            array_unique(array_map("intval", $serviceIds))
        );

        foreach ($serviceIds as $serviceId) {
            if ($serviceId <= 0) {
                return null;
            }
        }

        return $serviceIds;
    }

    private function validateServicesForCategory(
        array $serviceIds,
        $currentCategoryId = null
    ) {
        foreach ($serviceIds as $serviceId) {
            $service = ServiceModel::getServices($serviceId)->first();

            if (!$service || $service->status !== "Active") {
                return response()->json([
                    "result" => 0,
                    "msg" => "Please provide a valid or active service.",
                    "invalid_service_id" => $serviceId,
                ]);
            }

            $assignedCategoryId = $service->category_id ?? null;

            if (
                !empty($assignedCategoryId) &&
                (int) $assignedCategoryId !== (int) $currentCategoryId
            ) {
                return response()->json([
                    "result" => 0,
                    "msg" => "Service is already assigned to another category.",
                    "invalid_service_id" => $serviceId,
                    "assigned_category_id" => (int) $assignedCategoryId,
                ]);
            }
        }

        return null;
    }
}