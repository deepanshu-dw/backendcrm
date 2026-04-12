<?php

namespace App\Http\Controllers;

use App\Models\FaqModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    public function addFaq(Request $request)
    {
        $Validator = Validator::make($request->all(), [
            'question' => 'required',
            'answer' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $insert = [
            'question' => $request->post('question'),
            'answer' => $request->post('answer'),
        ];
        $result = FaqModel::addFaq($insert);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Faq added successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Oops... Something went wrong!']);
        }
    }

    public function updateFaq(Request $request, $faq_id)
    {
        $Validator = Validator::make($request->all(), [
            'question' => 'required',
            'answer' => 'required',
        ]);
        if ($Validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $Validator->errors()->first()]);
        }
        $update = [
            'question' => $request->post('question'),
            'answer' => $request->post('answer'),
        ];
        $result = FaqModel::updateFaq($update, $faq_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Faq Updated successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }

    public function getAllfaqs()
    {
        $result = FaqModel::getAllfaqs();

        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No data Found']);
        }
    }

    public function getfaqDetailById($faq_id)
    {
        $result = FaqModel::getfaqDetailById($faq_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Data Found', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No data Found']);
        }
    }

    public function deleteFaq($faq_id)
    {
        $result = FaqModel::deleteFaq($faq_id);
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Faq deleted successfully']);
        } else {
            return response()->json(['result' => -1, 'msg' => 'Oops... Something went wrong!']);
        }
    }
}
