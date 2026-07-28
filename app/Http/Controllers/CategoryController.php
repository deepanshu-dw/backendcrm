<?php

namespace App\Http\Controllers;

use App\Models\ServiceModel;
use App\Models\CategoryModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function addCategory(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'description' => 'required|string',
            'display_order' => 'required|integer|min:0',
            'category_services' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => 0,
                'errors' => $validator->errors()->first(),
            ]);
        }

        $adminId = $request->input('admin_id');
        $serviceIds = $request->input('category_services');

       
        if (is_string($serviceIds)) {
            $serviceIds = json_decode($serviceIds, true);
        }

        if (!is_array($serviceIds) || empty($serviceIds)) {
            return response()->json([
                'result' => 0,
                'msg' => 'Category services must be a valid non-empty array.',
            ]);
        }

       
        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));

        foreach ($serviceIds as $serviceId) {
            if ($serviceId <= 0) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Please provide a valid service.',
                    'invalid_service_id' => $serviceId,
                ]);
            }

            $service = ServiceModel::getServices($serviceId)->first();

            if (!$service || $service->status !== 'Active') {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Please provide a valid or active service.',
                    'invalid_service_id' => $serviceId,
                ]);
            }
        }

        $categoryInsert = [
            'name' => trim($request->input('name')),
            'description' => $request->input('description'),
            'display_order' => (int) $request->input('display_order'),
        ];

        $categoryId = CategoryModel::addCategory($categoryInsert);

        if (!$categoryId) {
            return response()->json([
                'result' => -1,
                'msg' => 'Category not added.',
            ]);
        }

        $categoryServiceInsert = [];

        foreach ($serviceIds as $serviceId) {
            $categoryServiceInsert[] = [
                'category_id' => $categoryId,
                'service_id' => $serviceId,
            ];
        }

        $servicesInserted = CategoryModel::addCategoryServices(
            $categoryServiceInsert
        );

        if (!$servicesInserted) {
            DB::table('categories')
                ->where('id', $categoryId)
                ->delete();

            return response()->json([
                'result' => -1,
                'msg' => 'Category services could not be added.',
            ]);
        }

        if ($adminId) {
            DB::table('activity_logs')->insert([
                'admin_id' => $adminId,
                'action' => 'Category Added',
                'description' => "Category created with services: {$categoryInsert['name']}",
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'result' => 1,
            'msg' => 'Category added successfully.',
            'data' => [
                'category_id' => (int) $categoryId,
                'service_ids' => $serviceIds,
            ],
        ]);
    }

    public function getAllCategory(Request $request)
    {
        $status = $request->query('status');

        if (!empty($status) &&!in_array($status, ['Active', 'Inactive'])) {
            return response()->json([
                'result' => 0,
                'msg' => 'Status must be Active or Inactive.',
            ]);
        }

        $categories = CategoryModel::getAllCategories($status);

        if ($categories->isEmpty()) {
            return response()->json([
                'result' => -1,
                'msg' => 'No categories found.',
                'data' => [],
            ]);
        }

        $categoryIds = $categories
            ->pluck('id')
            ->map(function ($categoryId) {
                return (int) $categoryId;
            })
            ->toArray();

        $services = CategoryModel::getServicesByCategoryIds($categoryIds);

        $servicesByCategory = $services->groupBy('category_id');

        foreach ($categories as $category) {
            $category->category_services =
                $servicesByCategory->get(
                    $category->id,
                    collect()
                )->values();
        }

        return response()->json([
            'result' => 1,
            'msg' => 'Categories found.',
            'data' => $categories,
        ]);
    }

    public function getCategoryById($categoryId)
    {
        if (!is_numeric($categoryId) || $categoryId <= 0) {
            return response()->json([
                'result' => 0,
                'msg' => 'Please provide a valid category ID.',
            ]);
        }

        $category = CategoryModel::getCategoryById($categoryId);

        if (!$category) {
            return response()->json([
                'result' => -1,
                'msg' => 'Category not found.',
            ]);
        }

        $category->category_services =
            CategoryModel::getCategoryServices(
                $categoryId
            );

        return response()->json([
            'result' => 1,
            'msg' => 'Category found.',
            'data' => $category,
        ]);
    }

    public function updateCategory(Request $request, $categoryId)
    {
        if (!is_numeric($categoryId) || (int) $categoryId <= 0) {
            return response()->json([
                'result' => 0,
                'msg' => 'Please provide a valid category ID.',
            ]);
        }

        $categoryId = (int) $categoryId;

        $category = CategoryModel::getCategoryById($categoryId);

        if (!$category) {
            return response()->json([
                'result' => -1,
                'msg' => 'Category not found.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:150',
            'description' => 'sometimes|required|string',
            'image_url' => 'sometimes|nullable|string|max:500',
            'display_order' => 'sometimes|required|integer|min:0',
            'status' => 'sometimes|required|in:Active,Inactive',
            'category_services' => 'sometimes|required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => 0,
                'errors' => $validator->errors()->first(),
            ]);
        }

        $allowedFields = [
            'name',
            'description',
            'image_url',
            'display_order',
            'status',
            'category_services',
        ];

        if (!$request->hasAny($allowedFields)) {
            return response()->json([
                'result' => 0,
                'msg' => 'Please provide at least one field to update.',
            ]);
        }

        $categoryUpdate = [];

        if ($request->has('name')) {
            $categoryUpdate['name'] = trim($request->input('name'));
        }

        if ($request->has('description')) {
            $categoryUpdate['description'] = $request->input('description');
        }

        if ($request->exists('image_url')) {
            $categoryUpdate['image_url'] = $request->input('image_url');
        }

        if ($request->has('display_order')) {
            $categoryUpdate['display_order'] =
                (int) $request->input('display_order');
        }

        if ($request->has('status')) {
            $categoryUpdate['status'] = $request->input('status');
        }

        if (!empty($categoryUpdate)) {
            $categoryUpdate['updated_at'] = now();

            CategoryModel::updateCategory(
                $categoryId,
                $categoryUpdate
            );
        }

        $serviceIds = null;

        if ($request->has('category_services')) {
            $serviceIds = $this->prepareServiceIds(
                $request->input('category_services')
            );

            if (!$serviceIds) {
                return response()->json([
                    'result' => 0,
                    'msg' => 'Category services must be a valid non-empty array.',
                ]);
            }

            $invalidServiceResponse = $this->validateServices(
                $serviceIds
            );

            if ($invalidServiceResponse) {
                return $invalidServiceResponse;
            }

            $servicesUpdated = CategoryModel::syncCategoryServices(
                $categoryId,
                $serviceIds
            );

            if (!$servicesUpdated) {
                return response()->json([
                    'result' => -1,
                    'msg' => 'Category services could not be updated.',
                ]);
            }
        }

        $adminId = $request->input('admin_id');

        if ($adminId) {
            DB::table('activity_logs')->insert([
                'admin_id' => $adminId,
                'action' => 'Category Updated',
                'description' => "Category updated: {$category->name}",
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'result' => 1,
            'msg' => 'Category updated successfully.',
        ]);
    }

    public function deleteCategory(Request $request,$categoryId) {
        if (!is_numeric($categoryId) || $categoryId <= 0) {
            return response()->json([
                'result' => 0,
                'msg' => 'Please provide a valid category ID.',
            ]);
        }

        $category = CategoryModel::getCategoryById($categoryId);

        if (!$category) {
            return response()->json([
                'result' => -1,
                'msg' => 'Category not found.',
            ]);
        }

        if ($category->status === 'Inactive') {
            return response()->json([
                'result' => -1,
                'msg' => 'Category is already deleted.',
            ]);
        }

        $result = CategoryModel::deleteCategory($categoryId);

        if (!$result) {
            return response()->json([
                'result' => -1,
                'msg' => 'Category could not be deleted.',
            ]);
        }

        $adminId = $request->input('admin_id');

        if ($adminId) {
            DB::table('activity_logs')->insert([
                'admin_id' => $adminId,
                'action' => 'Category Deleted',
                'description' => "Category marked inactive: {$category->name}",
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'result' => 1,
            'msg' => 'Category deleted successfully.',
        ]);
    }
}
