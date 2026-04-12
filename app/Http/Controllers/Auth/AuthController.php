<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Auth\AuthModel as Auth;


class AuthController extends Controller
{


    /**
     * Register Admin.
     * @var $requestdata all the data from the form
     * @param \Illuminate\Http\Request $request The HTTP request object.
     *
     * @return \Illuminate\Http\JsonResponse The JSON response containing the result of the registration.
     */
	
	public function adminRegister(Request $request)
    {
        $requestdata = $request->all();
        $validator = Validator::make($requestdata, [
            'admin_email' => 'required|email|unique:admins,admin_email',
            'role'  => 'required|in:admin,hr,subadmin',
			'salons'  => 'required',
        ], [
            'required' => 'This :Attribute is Required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors->has('admin_email')) {
                return response()->json(['result' => 0, 'errors' => 'This email is already in use.']);
            }
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
        }
        try {
          $admin_profile = null;
            if (!empty($request->hasfile('admin_profile'))) {
                $admin_profile = singleAwsUpload($request, 'admin_profile');
            }
			
            $data = array(
                // 'reference_id'     => $request->post('reference_id'),
                'admin_name'       => $request->post('admin_name'),
                'admin_email'      => $request->post('admin_email'),
                'admin_phone'      => $request->post('admin_phone'),
                'admin_password'   => hash('sha256', $request->post('admin_password')),
                'admin_address'    => $request->post('admin_address'),
                'admin_profile'    => $admin_profile,
				'salons'    	   => !empty($request->post('salons')) ? json_encode($request->post('salons')) : null,
                'role'             => $request->post('role'),
                'created_at'       => date('Y-m-d h:i:s'),
                'updated_at'       => date('Y-m-d h:i:s'),
            );
            $result = Auth::AdminRegister($data);
            if ($result) {
                $data = Auth::where('admin_id', $result)->get()->first()->toArray();
                return response()->json(['result' => 1, 'msg' => 'Admin Register Successfully', 'data' => $data]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Admin Something went wrong', 'data' => NUll]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage(), 'data' => null]);
        }
	}

    public function adminLogin(Request $request)
    {

        $requestdata = $request->all();
        $validator = Validator::make($requestdata, [
            'admin_email'    => 'required|email',
            'admin_password' => 'required'
        ], [
            'required' => 'This :Attribute is Required',
        ]);
        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
            return false;
        }
        try {
            $email = $request->post('admin_email');
            $password = hash('sha256', $request->post('admin_password'));
            $result = Auth::adminLogin($email, $password);
            if ($result) {
                if ($result->status == "Active") {
					$token = generateToken();
                    update('admins', 'admin_id', $result->admin_id, ['admin_token' => $token, 'since_last_login' => now(), 'updated_at' => now()]);
					$result->admin_token = $token;
                    return response()->json(['result' => 1, 'msg' => 'Admin Login Successfully', 'data' => $result]);
                } elseif ($result->status == "Inactive") {
                    return response()->json(['result' => -1, 'msg' => 'The User is currently Inactive', 'data' => null]);
                }
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email or Password is wrong', 'data' => null]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage(), 'data' => null]);
        }
    }


    /**
     * Update the admin's password using a database transaction.
     *
     * @param int    $admin_id
     * @param string $new_password
     * @param string $confirm_password
     * @return int|bool The number of affected rows or false on failure.
     */
    public function changePassword(Request $request)
    {
        $requestdata = $request->all();
        $validator = Validator::make($requestdata, [
            'admin_id'       => 'required',
            'current_password' => 'required',
            'new_password' => 'required|min:6|same:confirm_password',
            'confirm_password' => 'required_with:new_password',
        ], [
            'required' => 'This :attribute is required',
            'min' => 'The :attribute must be at least :min characters.',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
        }

        try {
            $admin_id = $request->post('admin_id');
            $admin = Auth::where('admin_id', $admin_id)->first();
            if ($admin) {
                if (hash('sha256', $request->post('current_password')) !== $admin->admin_password) {
                    return response()->json(['result' => -1, 'msg' => 'Incorrect current password']);
                }
                $password = hash('sha256', $request->post('new_password'));
                $updatepasword = Auth::updateAdminPassword($admin_id, $password);
                if ($updatepasword) {
                    return response()->json(['result' => 1, 'msg' => 'Password changed successfully']);
                }
                return response()->json(['result' => 1, 'msg' => 'Password Already Updated']);
            } else {
                return response()->json(['result' => 1, 'msg' => 'Invalid Admin id']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
    
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'admin_email' => 'required',
            ], [
                'required' => 'This :Attribute is Required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }
            $admin_email = $request->post('admin_email');

            $checkemail = Auth::getAdminByEmailOrUsername($admin_email);            
            if (!empty($checkemail)) {
                if ($checkemail->status == 'Deleted') {
                    header('HTTP/1.1 402 User Account has been deleted.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been deleted.'], 401);
                }

                if ($checkemail->status == 'Blocked') {
                    header('HTTP/1.1 402 User Account Is Blocked.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account is blocked.'], 401);
                }

                if ($checkemail->status == 'Inactive') {
                    header('HTTP/1.1 402 User Account Is Inactive.', true, 402);
                    return response()->json(['result' => -2, 'msg' => 'Your account has been inactive by admin.'], 401);
                }

                $otp = generateOtp();
                $maildata['admin_name'] = $checkemail->admin_name;
                $maildata['to'] = $checkemail->admin_email;
                $maildata['message'] = 'Your verifiation OTP is ' . $otp;
                $maildata['subject'] = 'OTP Verifiation Email For Forgot Password';
                $maildata['view_name'] = 'mail.otpmail';
                update('admins', 'admin_id', $checkemail->admin_id, ['otp' => $otp]);
                // sendMail($maildata);
                return response()->json(['result' => 1, 'msg' => 'Otp Sent on your mail.', 'data' => (['admin_id' => $checkemail->admin_id, 'admin_email' => $checkemail->admin_email, 'otp' => $otp])], 200);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email does not exist'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function resendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'admin_id' => 'required',
            ], [
                'required' => 'This :Attribute is Required',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $admin_id = $request->post('admin_id');

            $checkemail = Auth::getAdminDetails($admin_id);
          
            if (!empty($checkemail)) {
                $status = @$checkemail->status;

                if (in_array($status, ['Deleted', 'Blocked', 'Inactive'])) {
                    return response()->json(['result' => -2, 'msg' => "User Account Is $status."], 402);
                }

                $result = Auth::getAdminDetails($checkemail->admin_id);
                $otp = generateOtp();
                $maildata = [
                    'name' => $result->admin_name,
                    'to' => $result->admin_email,
                    'message' => 'Your verification OTP is ' . $otp,
                    'subject' => 'OTP Verification Email For Forgot Password',
                    'view_name' => 'mail.otpmail',
                ];
                update('admins', 'admin_id', $checkemail->admin_id, ['otp' => $otp]);
                // sendMail($maildata);
                return response()->json(['result' => 1, 'msg' => 'Otp sent successfully.', 'data' => (['admin_id' => $result->admin_id, 'admin_email' => $checkemail->admin_email, 'otp' => $otp])], 200);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Email does not exist'], 401);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'admin_id' => 'required',
                'otp' => 'required',
            ], [
                'required' => 'The :attribute field is required.',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $admin_id = $request->post('admin_id');
            $otp = $request->post('otp');

            $adminDetails = Auth::getAdminDetails($admin_id);

            if (empty($adminDetails)) {
                return response()->json(['result' => -1, 'msg' => 'Admin does not exist'], 401);
            }

            if (@$adminDetails->otp == $otp) {
                return response()->json(['result' => 1, 'msg' => 'OTP verified successfully.', 'data' => (['admin_id' => $adminDetails->admin_id])], 200);
            }

            return response()->json(['result' => -1, 'msg' => 'The OTP entered is incorrect!'], 401);
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }
    
    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'admin_id' => 'required',
                'admin_password' => 'required',
                'confirm_password' => 'required|same:admin_password',
            ], [
                'required' => 'The :attribute field is Required',
                'same' => 'The :attribute field must match the password field',
            ]);

            if ($validator->fails()) {
                return response()->json(['result' => 0, 'msg' => $validator->errors()->first()]);
            }

            $admin_id = $request->post('admin_id');
            $admin_password = $request->post('admin_password');

            $result = update('admins', 'admin_id', $admin_id, ['admin_password' => hash('sha256', $admin_password)]);

            if ($result > 0) {
                return response()->json(['result' => 1, 'msg' => 'Password reset successfully.'], 200);
            } else {
                return response()->json(['result' => 0, 'msg' => 'Already updated!']);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => 'An error occurred while processing your request: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get details of a specific admin.
     *
     * @param  int  $admin_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAdminDetails($admin_id)
    {
        if (empty($admin_id)) {
            return response()->json(['result' => 0, 'msg' => 'Admin id Required']);
        }

        try {
            $result = Auth::getAdminDetails($admin_id);
            if ($result) {
				if (isset($result->admin_profile) && !str_contains($result->admin_profile, 'amazonaws.com')) {
					$result->admin_profile = baseURL($result->admin_profile);
				}
               
                return response()->json(['result' => 1, 'msg' => 'Admin Login Successfully', 'data' => $result]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Admin Something went wrong', 'data' => NUll]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public function getAllAdmins(Request $request, $type=null)
    {
        try {
			$keyword = $request->query('keyword');
			$result = !empty($type) ? Auth::getAllAdmins($type, $keyword) : Auth::getAllAdmins(null, $keyword);
			
            if ($result) {
				foreach ($result as $value) {
					if (isset($value->admin_profile) && !str_contains($value->admin_profile, 'amazonaws.com')) {
						$value->admin_profile = baseURL($value->admin_profile);
					}
				}
				
                return response()->json(['result' => 1, 'msg' => 'Admin List Fetched Successfully', 'data' => $result]);
            } else {
                return response()->json(['result' => -1, 'msg' => 'Something went wrong!', 'data' => NUll]);
            }
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public function updateAdminDetails(Request $request)
    {
        try {
			$validator = Validator::make($request->all(), [
				'admin_id'       => 'required',
			], [
				'required' => 'The :attribute field is required',
			]);

			if ($validator->fails()) {
				return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
			}

			$formData = $request->post();
			$admin_detail = Auth::where('admin_id', $formData['admin_id'])->first();
			
			$profile_pic = $request->file('admin_profile');
			if(empty($profile_pic)){
				$admin_profile = $admin_detail->admin_profile;
			}else{
				$admin_profile = singleAwsUpload($request, 'admin_profile');
			}
			
			$update_data = [
				'admin_name'      => $formData['admin_name'],
				'admin_phone'     => $formData['admin_phone'],
				'admin_profile'   => $admin_profile,
                'role'            => $request->post('role'),
				'admin_address'   => $formData['admin_address'],
				'updated_at'      => now(),
			];
			if ($request->has('salons')) {
				$update_data['salons'] = json_encode($request->post('salons'));
			}
			
			$result = Auth::updateAdminDetails($formData['admin_id'], $update_data);
			if($result){
				return response()->json(['result' => 1, 'msg' => 'Profile updated succesfully'], 200);
			}else{
				return response()->json(['result' => 0, 'msg' => 'No changes were found!']);
			}
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	   
	public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'admin_id'       => 'required',
            'status' => 'required|in:Active,Inactive,Deleted'
        ], [
            'required' => 'The :attribute field is required',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 0, 'errors' => $validator->errors()->first()]);
        }

        $admin_id = $request->post('admin_id');
        $status = $request->post('status');
    
        $result =Auth::updateStatus( $admin_id, $status);
    
        if ($result) {
            return response()->json(['result' => 1, 'msg' => 'Status Change Successfully', 'data' => $result]);
        } else {
            return response()->json(['result' => -1, 'msg' => 'No Changes Updated']);
        }
    }
}
