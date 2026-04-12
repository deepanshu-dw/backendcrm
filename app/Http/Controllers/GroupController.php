<?php

namespace App\Http\Controllers;

use App\Models\GroupModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    public function addGroup(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'group_name_en' => 'required',
            'group_name_es' => 'required',
            'group_name_pl' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $insert = [
            'group_name_en' => $request->post('group_name_en'),
            'group_name_es' => $request->post('group_name_es'),
            'group_name_pl' => $request->post('group_name_pl'),
        ];

        $result = GroupModel::addGroup($insert);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Group Added Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Try Again Later']);
        }
    }

    public function getGroupDetail($lang, $group_id)
    {
        $result = GroupModel::getGroupDetail($lang, $group_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Data Found']);
        }
    }

    public function updateGroup(Request $request, $group_id)
    {
        $Validator = Validator::make($request->all(), [
            'group_name_en' => 'required',
            'group_name_es' => 'required',
            'group_name_pl' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $Update = [
            'group_name_en' => $request->post('group_name_en'),
            'group_name_es' => $request->post('group_name_es'),
            'group_name_pl' => $request->post('group_name_pl'),
        ];

        $result = GroupModel::updateGroup($Update, $group_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Group Updated Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }
    public function getAllGroups($lang)
    {
        $result = GroupModel::getAllGroups($lang);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Groups Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Group']);
        }
    }

    public function deleteGroup(Request $request, $group_id)
    {
        $groupdetails = GroupModel::getGroupDetail('en', $group_id);
        if (empty($group_id)) {
            return response()->json(['result' => -1, 'msg' => 'Group is required']);
        }
        if (empty($groupdetails)) {
            return response()->json(['result' => -1, 'msg' => 'Enter valid Id ']);
        }

        $result = GroupModel::deleteGroup($group_id);

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Group deleted successfully.', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ']);
        }
    }
}
