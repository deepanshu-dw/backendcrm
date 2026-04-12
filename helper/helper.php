	<?php
/*
|--------------------------------------------------------------------------
| This File Contain Common Functions
|--------------------------------------------------------------------------
|    @author Ambuj Mishra <ambuj.designoweb@gmail.com>
|    @copyright 2023
|    @Create 19.04.2023
|
*/


use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request as REQ;
use App\Models\SalonModel;
use App\Models\CustomerModel;
use Illuminate\Support\Str;
/*
*-----------------------------------------------------------------------------
* This Function is give the admin details
* @return array
* -----------------------------------------------------------------------------
*/

function baseURL($filePath)
{
    return env('AWS_S3_URL') . $filePath;
}

function admin_detail()
{
    $admin_detail = DB::table('admin')->where('id', session()->get('admin_id'))->first();
    return $admin_detail;
}

/**
 * This function is used for encryption for any id or any unique value to converts into encryption string
 * @param mixed $id
 * @return mixed $res
 */

function encryptionID($id)
{
    $res = substr(uniqid(), 0, 10) . $id . substr(uniqid(), 0, 10);
    return $res;
}

/**
 * This function is used for decryption for any id or any unique value to converts into decryption string
 * @param mixed $result_id is the encryption string or id
 * @return mixed $result_id  its orginal value after decryption
 */

function decryptionID($result_id)
{
    $id = substr($result_id, 10);
    $result_id = substr($id, 0, -10);
    return $result_id;
}

/**
 *--------------- Common function Insert Data function-----------------------
 * @param  string $table this is table name.
 * @param array $data this is array that would be inserted into database.
 * @return int  last insert id of record.
 */

function insert($table, $data = [])
{
    // Start a database transaction
   
        $result = DB::table($table)->insert($data);
        $id = DB::getPdo()->lastInsertId();
        return $id;
       
    
}


/**
 *--------------------Common function Update Data function-------------------------
 * @param  string $table this is table name.
 * @param array $data this is array that would be Update into database.
 * @param  string $wherecol database column name that is used for the where condition
 * @param   string $wherevalue  that is value which is in the condition that is matched
 * @param  string $wherecondition this is operator of the where condition
 * @return int  last updted id of record.
 */

function update($table, $wherecol, $wherevalue, $data, $wherecondition = '=')
{
    $affected_row = DB::table($table)->where($wherecol, $wherecondition, $wherevalue)->update($data);
    return $affected_row;
}

/**
 *--------------- Common function Delete Data function----------------------------------------
 * @param  string $table this is table name.
 * @param  string $wherecol database column name that is used for the where condition.
 * @param  string $wherevalue  that is value which is in the condition that is matched.
 * @return int $affected_row last deleted id of record.
 */

function delete($table, $wherecol, $wherevalue)
{
    $affected_row = DB::table($table)->where($wherecol, $wherevalue)->delete();
    return $affected_row;
}

/**
 * Perform a delete operation on a database table based on multiple conditions.
 *
 * @param string $table The name of the database table to delete from.
 * @param array $conditions An associative array of column-value pairs as delete conditions.
 * @return int The number of affected rows after the delete operation.
 */
function deleteMultiple($table, $conditions = [])
{
    $query = DB::table($table);
    foreach ($conditions as $column => $value) {
        $query->where($column, $value);
    }
    $affected_rows = $query->delete();
    return $affected_rows;
}

/**
 * --------------------Common Function For Change Status ( we can user for the soft delete )------------------------
 *
 * @param mixed $id unique value that is primary key or unique value.
 * @param mixed $status which is the status to be updated.
 * @param string $table table name.
 * @param mixed $status_variable  for status change variable.
 * @param  string $wherecol database column name that is used for the where condition.
 * @param  string $wherevalue  that is value which is in the condition that is matched.
 * @return int last update id.
 */

function change_status($id, $status, $table, $wherecol, $status_variable, $wherecondition = '=')
{
    $data = array(
        $status_variable => $status,
    );
    $affected_row = DB::table($table)->where($wherecol, $wherecondition, $id)->update($data);
    return $affected_row;
}

/**
 * --------------------Common Function For Change  multiple Status ( we can user for the soft delete )------------------------
 *
 * @param mixed $id unique value that is primary key or unique value.
 * @param mixed $status which is the status to be updated.
 * @param string $table table name.
 * @param mixed $status_variable  for status change variable.
 * @param array $wherecondition  array for the where condition like [['user_id,'=',$value]].
 * @return int  $affected_row last update id.
 */

function change_status_version2($id, $status, $table, $wherecondition, $status_variable)
{
    $data = array(
        $status_variable => $status,
    );
    $affected_row = DB::table($table)->where($wherecondition)->update($data);
    return $affected_row;
}

/**
 * -----------Common function for select the records for the database---------------------------
 *
 * @param string $table -table name
 * @param string|array $col  specify the columns name in array like ['name','id'] or '*'.
 * @param array $where  this is optional or put the where condition.
 * @return array|object $result
 */
function select($table, $col = '*', $where = null)
{
    $data = DB::table($table);
    if (!empty($col)) {
        $data->addSelect($col);
    }
    if (!empty($where)) {
        $data->where($where);
    }
    return $data->get();
}

function selectSpecificCol($table, $col = '*', $where = null)
{
    $data = DB::table($table);
    if (!empty($col)) {
        $data->select(...$col);
    }
    if (!empty($where)) {
        $data->where($where);
    }
    return $data->get();
}


/**
 * -----------Common function for select the records for the database---------------------------
 *
 * @param string $table -table name
 * @param string|array $col  specify the columns name in array like ['name','id'] or '*'.
 * @param array $where  this is optional or put the where condition.
 * @return array|object $result
 */
function selectWithJoin($table, $jointable, $col = '*', $firsttableprimaykey = 'id', $secondtableprimaykey = 'id', $join = 'inner', $where = null,$multiple=false)
{
    $data = DB::table($table);
    if (!empty($col)) {
        $data->addSelect($col);
    }
    $data->join($jointable, $table . '.'.$firsttableprimaykey, '=', $jointable . '.'.$secondtableprimaykey, $join);
    if (!empty($where)) {
        if($multiple){
            return $data->where($where)->get();
        }else{
            return $data->where($where)->get()->first();
        }
        //return $data->where($where)->get()->first();
    }
    return $data->get();
}


/**
 * -------------------Common Function to use date into readable format
 *  @var date $end   from date to calculate days
 *  @return string
 */

function convertToHoursMinsSec($updated_at_str)
{
    $updated_at = DateTime::createFromFormat("Y-m-d H:i:s", $updated_at_str);
    $last_message_time = DateTime::createFromFormat("Y-m-d H:i:s", now());

    $time_difference = $last_message_time->diff($updated_at);
    $days_difference = $time_difference->days;

    if ($days_difference < 1) {
        return "less than a day ago";
    } elseif ($days_difference == 1) {
        return "1 day ago";
    } elseif ($days_difference < 7) {
        return "{$days_difference} days ago";
    } elseif ($days_difference < 30) {
        $weeks = floor($days_difference / 7);
        return "{$weeks} week" . ($weeks > 1 ? 's' : '') . " ago";
    } elseif ($days_difference < 365) {
        $months = floor($days_difference / 30);
        return "{$months} month" . ($months > 1 ? 's' : '') . " ago";
    } else {
        $years = floor($days_difference / 365);
        return "{$years} year" . ($years > 1 ? 's' : '') . " ago";
    }
}




/**
 *--------------------------------Common function for calculate days b/w two dates-------
 *  @var  $date from date
 * -------------------------------------------------------------------------------------
 */

function dayBytwodates($date)
{
    $date = strtotime($date);
    $date2 = time();
    $datediff = $date2 - $date;
    $days = floor(($datediff) / (60 * 60 * 24));
    if ($days < 0) {
        return 0;
    }
    return $days;
}

/**
 *--------------------------------Common function for calculate Mintus b/w two dates-------
 *  @param $date1   From date
 *  @param $date2   To date
 * -------------------------------------------------------------------------------------
 */
function minutesCalculation($date1, $date2)
{
    $timestamp1 = strtotime($date1);
    $timestamp2 = strtotime($date2);
    $datetime1 = new DateTime("@$timestamp1");
    $datetime2 = new DateTime("@$timestamp2");
    $interval = $datetime1->diff($datetime2);
    $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    return $total_minutes;
}

/**
 * startWithNumber function To use number anythnig starts with
 * @param mixed $str
 * @return boolean
 */

function startWithNumber($str)
{
    return preg_match('~^(\d+)~', $str, $m) === 1;
}

/**
 *--------------------- Logic for Currency conversion ---------------------------
 * @param float|int $n number for convert
 * @param integer $precision
 * @return string
 */

function number_format_short($n, $precision = 1)
{
    if ($n < 900) {
        // 0 - 900
        $n_format = number_format($n, $precision);
        $suffix = '';
    } else if ($n < 900000) {
        // 0.9k-850k
        $n_format = number_format($n / 1000, $precision);
        $suffix = ' k';
    } else if ($n < 900000000) {
        // 0.9m-850m
        $n_format = number_format($n / 1000000, $precision);
        $suffix = ' m';
    } else if ($n < 900000000000) {
        // 0.9b-850b
        $n_format = number_format($n / 1000000000, $precision);
        $suffix = ' b';
    } else {
        // 0.9t+
        $n_format = number_format($n / 1000000000000, $precision);
        $suffix = ' t';
    }

    // Remove unecessary zeroes after decimal. '1.0' -> '1';
    // Intentionally does not affect partials, eg '1.50' -> '1.50'
    if ($precision > 0) {
        $dotzero = '.' . str_repeat('0', $precision);
        $n_format = str_replace($dotzero, '', $n_format);
    }

    return $n_format . $suffix;
}

/**
 *function to calculate  Financial year range
 *
 * @param  string $year
 * @param  string $month
 * @return string $response
 */
function get_financial_year_range($year, $month)
{
    if ($month < 4) {
        $year = $year - 1;
    }
    $start_date = date('y', strtotime(($year) . '-04-01'));
    $end_date = date('y', strtotime(($year + 1) . '-03-31'));
    $response = $start_date . '-' . $end_date;
    return $response;
}

/**
 * encrypt string with a key
 */

function encryptionString($string)
{
    $ciphering = 'AES-128-CTR';
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    $encryption_iv = '1234567891011121';
    $encryption_key = 'yuvi';
    $encryption = openssl_encrypt($string, $ciphering, $encryption_key, $options, $encryption_iv);
    return $encryption;
}

/**
 * Decrypt string with a key
 */

function decryptionString($encryption)
{
    $options = 0;
    $ciphering = 'AES-128-CTR';
    $decryption_iv = '1234567891011121';
    $decryption_key = 'yuvi';
    $decryption = openssl_decrypt($encryption, $ciphering, $decryption_key, $options, $decryption_iv);
    return $decryption;
}

//**************************** Calculate Last 7 days*****************************/

function getLastNDays($days, $format = 'd-m')
{
    $m = date('m');
    $de = date('d');
    $y = date('Y');
    $dateArray = array();
    for ($i = 0; $i <= $days - 1; $i++) {
        $dateArray[] = date($format, mktime(0, 0, 0, $m, ($de - $i), $y));
    }
    return array_reverse($dateArray);
}

/**
 *Change Date Format
 *
 */

function changeDateFormat($date, $type = '')
{
    if ($type == 'short') {
        $format = 'd-m-Y';
    } else {
        $format = 'd-m-Y | H:i a';
    }
    return date($format, strtotime($date));
}

/**
 * Validate the date : date is valid or not
 */

function validateDate($mystring)
{
    $invaliddate = '1970';
    if (strpos($mystring, $invaliddate) !== false) {
        return true;
    } else {
        return false;
    }
}

/**
 * Common function for return global currency symbole
 * @return string
 */
function siteCurrency()
{
    return '€ ';
}

/**
 * Common  function to Upload Any Single File On aws
 *
 * @param Request $request Request for the form
 * @param string $file_name name of the file
 * @param string $path path of the file where to be store
 * @return string|boolean
 */
function singleAwsUpload($request, $file_name, $path = 'images/new_uploads')
{
    if (!$request->hasFile($file_name)) {
        return false;
    }
    $file = $request->file($file_name);
    $filePath = Storage::disk('s3')->put($path, $file);
    if (!$filePath) {
        return false;
    }
    $fileUrl = Storage::disk('s3')->url($filePath);
    return $fileUrl;
}

/**
 * Common  function to Upload Any Multiple File On aws
 *
 * @param Request $request Request for the form
 * @param string $file_name name of the file
 * @param string $path path of the file where to be store
 * @return string|boolean
 */


function AwsSingleUpload($file, $folder = 'uploads')
{
    if (!$file) {
        throw new Exception('File is required');
    }

    if (empty($folder)) {
        throw new Exception('S3 folder cannot be empty');
    }

    $extension = $file->getClientOriginalExtension();
    $filename = Str::uuid().'.'.$extension;
    $path = $folder.'/'.$filename;

   Storage::disk('s3')->put(
    $path,
    file_get_contents($file),
    [
        'ACL' => 'public-read',
        'visibility' => 'public'
    ]
);


    return [
        'path' => $path,
        'url' => Storage::disk('s3')->url($path),
        'filename' => $filename
    ];
}

function multipleAwsUploads($request, $file_name, $path)
{
    if ($request->file($file_name)) {
        $data = [];
        foreach ($request->file($file_name) as $file) {
            $maxFileSizeBytes = 20 * 1024 * 1024; // Convert 20 MB to bytes
            if ($file->getSize() > $maxFileSizeBytes) {
                return response()->json(['result' => 0, 'msg' => 'Image size should be 20 MB or less.'], 400);
            }
			$imageName = time() . '.' . $file->extension();
			$path = \Storage::disk('s3')->put('images', $file);
			$path = \Storage::disk('s3')->url($path);
			sleep(1);
			$data[] = $path;
        }
        if (!empty($data)) {
            return $data;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

/**
 * Common  function to Upload Any Single File On aws
 *
 * @param Request $request Request for the form
 * @param string $file_name name of the file
 * @param string $path path of the file where to be store
 * @return string|boolean
 */

function singleUpload($request, $file_name, $path)
{
    if ($request->hasfile($file_name)) {
        $file = $request->file($file_name);
        $name = time() . '.' . $file->extension();
        sleep(1);
        $file->move(base_path('') . $path, $name);
        return $name;
    } else {
        return false;
    }
}
/**
 * Common  function to Upload Any Multiple File On aws
 *
 * @param Request $request Request for the form
 * @param string $file_name name of the file
 * @param string $path path of the file where to be store
 * @return string|boolean|array
 */

function multipleUploads($request, $file_name, $path)
{
    if ($request->file($file_name)) {
        $data = [];
        foreach ($request->file($file_name) as $file) {
            $name = time() . '.' . $file->extension();
            sleep(1);
            $file->move(base_path('') . $path, $name);
            $data[] = $name;
        }
        return $data;
    } else {
        return false;
    }
}

/**
 * Common function for the calculate age
 *
 * @param string $birthdate
 * @return string
 */
function calculateAge($birthdate)
{
    $today = new DateTime();
    $diff = $today->diff(new DateTime($birthdate));
    return $diff->y;
}

/**
 * Common function for delete the object on aws
 *
 * @param string $url
 * @throws  Exception Aws\Exception\AwsException;
 * @return void
 */
function deleteImageAws($url)
{
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1', // Replace with your desired region
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ]);
    $bucketName = env('AWS_BUCKET');
    // Replace with the name of your S3 bucket
    $imageKey = $url;
    // Replace with the object key of the image you want to delete

    try {
        $result = $s3Client->deleteObject([
            'Bucket' => $bucketName,
            'Key' => $imageKey,
        ]);
    } catch (AwsException $e) {
    }
}
// for creating for the images key

function bucketKey($imageurl)
{
    return str_replace(env('BUCKET_URL'), '', $imageurl);
}

/**
 * Generate Unique Id function
 *
 * @var mixed $nstr new string
 * @var  mixed $str old string
 * @return string|int
 */
function uniqueId()
{
    $str = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNIPQRSTUVWXYZ';
    $nstr = str_shuffle($str);
    $unique_id = substr($nstr, 0, 10);
    return $unique_id;
}

/**
 * Generate Auth Token function
 * @return string|int
 */
function generateToken()
{
    $token = openssl_random_pseudo_bytes(16);
    $token = bin2hex($token);
    return $token;
}

/**
 * This handel all authorization function
 *
 * @param Request $request
 * @return mixed|boolean
 */
function authorizationHandler(Request $request, $user_id = null)
{
    $token = $request->header('token');
    if (!empty($user_id)) {
        $user = select('users', '*', [['user_id', '=', $user_id]])->first();
    } else {
        if (empty($token)) {
            response()->json(['result' => -2, 'msg' => 'Header token  is Required !!'], 401)->send();
            return false;
        } else {
            $user_token = select('users_authentication', '*', [['user_token', '=', $token]])->first();
            if (!empty(($user_token))) {
                $user = select('users', '*', [['user_id', '=', $user_token->user_id]])->first();
            }
        }
    }

    if (!empty($user)) {
        if ($user->is_verified == 'no') {
            header('HTTP/1.1 402 User Account has not been verified yet.', true, 402);
            response()->json(['result' => -2, 'msg' => 'Please verify Yourself We have resend verification link to your email id. Please check your mail.'], 401)->send();
            return false;
        }
        if ($user->status == 'Deleted') {
            header('HTTP/1.1 402 User Account has been deleted.', true, 402);
            response()->json(['result' => -2, 'msg' => 'Your account has been deleted.'], 401)->send();
            return false;
        }
        if ($user->status == 'Disable') {
            header('HTTP/1.1 402 User Account has been deleted.', true, 402);
            response()->json(['result' => -2, 'msg' => 'Your account is Disabled'], 401)->send();
            return false;
        }
        if ($user->status == 'Blocked') {
            header('HTTP/1.1 402 User Account Is Blocked.', true, 402);
            response()->json(['result' => -2, 'msg' => 'Your account is blocked.'], 401)->send();
            return false;
        }
        if ($user->status == 'Inactive') {
            header('HTTP/1.1 402 User Account Is Inactive.', true, 402);
            return response()->json(['result' => -2, 'msg' => 'Your account has been inactive by admin.'], 401)->send();
        }
        return $user;
    } else {
        response()->json(['result' => -2, 'msg' => 'Invalid token  !!'], 401)->send();
        return false;
    }
}

/**
 * Generate Otp function
 *
 * @return int
 */
function generateOtp()
{
    return 1234;
    // return rand(1111, 9999);
}

/**
 * Send Notification function
 *
 * @param Request $request
 * @param int $user_id
 * @param mixed $body
 * @param mixed $title
 * @param array $data
 * @return void
 */
function sendNotfication(Request $request, $user_id, $body, $title, $data = null)
{
    $token = select('users_authentication', '*', [['user_id', '=', $user_id]])->first();;
    $api_key = env('API_ACCESS_KEY');
    $url = 'https://fcm.googleapis.com/fcm/send';
    $notification = array('title' => $title, 'body' => $body, 'sound' => 'default', 'badge' => '1');
    $arrayToSend = array('to' => $token->firebase_token, 'notification' => $notification, 'priority' => 'high');
    $json = json_encode($arrayToSend);
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: key=' . $api_key;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //Send the request
    $response = curl_exec($ch);
    //Close reques

    if ($response === false) {
        die('FCM Send Error: ' . curl_error($ch));
    }
    curl_close($ch);
    // here is the notification send message
}

function createSlug($productName)
{
    // Remove special characters
    $slug = preg_replace('/[^a-zA-Z0-9]+/', ' ', $productName);

    // Convert to lowercase
    $slug = strtolower($slug);

    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);

    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');

    return $slug;
}

function updateV2($table, $where, $data)
{
    $affected_row = DB::table($table)->where($where)->update($data);
    return $affected_row;
}

function mintusCalculataion($date1, $date2)
{
    $timestamp1 = strtotime($date1);
    $timestamp2 = strtotime($date2);
    $datetime1 = new DateTime("@$timestamp1");
    $datetime2 = new DateTime("@$timestamp2");
    $interval = $datetime1->diff($datetime2);
    $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    return $total_minutes;
}

// Financial Year
function get_finacial_year_range($year, $month)
{
    $year = $year;
    $month = $month;
    if ($month < 4) {
        $year = $year - 1;
    }
    $start_date = date('y', strtotime(($year) . '-04-01'));
    $end_date = date('y', strtotime(($year + 1) . '-03-31'));
    $response = $start_date . '-' . $end_date;
    return $response;
}


function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $apiKey = env('GOOGLE_API');
    $url = "https://maps.googleapis.com/maps/api/directions/json?origin={$lat1},{$lon1}&destination={$lat2},{$lon2}&key={$apiKey}";

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data["status"] === "OK") {
        // Extract the distance in meters
        $distance = $data["routes"][0]["legs"][0]["distance"]["value"];

        // Convert meters to kilometers
        $distance_km = $distance / 1000;

        return @round($distance_km, 2) . ' km';
    } else {
        return '0 km';
    }
}

function getUserToken($user_id)
{
    return DB::table('users_authentication')->select('firebase_token')->where('user_id', $user_id)->get()->first();
}

/* function sendFirebaseNotification($user_id, $message, $title, $page = null)
{
    $key = '';
    $token = getUserToken($user_id);
    $url = 'https://fcm.googleapis.com/fcm/send';
    $data = [
        "rdirect_page" => $page,
        "message" => $message,
        "title" => $title,
    ];

    $fields = array(
        'registration_ids' => array(
            $token->firebase_token,
        ),
        'data' => $data,
    );
    $fields = json_encode($fields);
    $headers = array(
        'Authorization: key=' . $key,
        'Content-Type: application/json',
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);
    curl_close($ch);
    return response()->json(['result' => 1, 'msg' => 'Notification Sent.']);
} */

function generateRandomUserId($length = 8)
{
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomUserId = '';

    for ($i = 0; $i < $length; $i++) {
        $randomUserId .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomUserId;
}



function sendMail($data)
{
    $from = 'info@hollywoodhair.pl';
    $to = $data['to'];
    $subject = $data['subject'];
    $view = 'mail.' . $data['view_name'];
	try {
        Mail::send($view, $data, function ($message) use ($from, $to, $subject) {
            $message->from($from, 'Hollywood Hair');
            $message->to($to);
            $message->subject($subject);
        });

        // Optionally return a success status
        return true;
    } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
        // Handle SMTP-specific exceptions
        \Log::error("SMTP Error: " . $e->getMessage());
    } catch (\Exception $e) {
        // Handle general exceptions
        \Log::error("General Error: " . $e->getMessage());
    }

    // Return false in case of error but allow the API to proceed
    return false;
}

function sendMail1($data)
{
	try {
		$from = 'info@hollywoodhair.pl';
		$to = $data['to'];
		$subject = $data['subject'];
		$view = 'mail.' . $data['view_name'];
		$res = Mail::send($view, $data, function ($message) use ($from, $to, $subject) {
			$message->from($from, 'Hollywood Hair');
			$message->to($to);
			$message->subject($subject);
		});
		return true;
	} catch (\Exception $e) {
		return response()->json(['result' => -1, 'msg' => 'Exception: ' . $e->getMessage()]);
	}
}

function sendMailWithAttachment($data)
{
    $from = 'info@hollywoodhair.pl';
    $to = $data['to'];
    $subject = $data['subject'];
    $view = 'mail.' . $data['view_name'];
    Mail::send($view, $data, function ($message) use ($from, $to, $subject,$data) {
        $message->from($from, 'Hollywood Hair');
        $message->to($to);
        $message->subject($subject);
        // Check if an attachment is provided
        if (isset($data['upload_resume'])) {
            $message->attach($data['upload_resume']);
        }
    });
    return true;
}


function sendMailWithAttachmentV2($data)
{
    $from = 'info@hollywoodhair.pl';
    $to = $data['to'];
    $subject = $data['subject'];
    $view = 'mail.' . $data['view_name'];
    Mail::send($view, $data, function ($message) use ($from, $to, $subject,$data) {
        $message->from($from, 'Hollywood Hair');
        $message->to($to);
        $message->subject($subject);
        // Check if an attachment is provided
        if (isset($data['upload_file'])) {
            $message->attach($data['upload_file']);
        }
    });
    return true;
}


/**
 * Encryption Algorithm using AES-256-CBC Encryption:
 *
 * Algorithm Steps:
 *
 * 1. Retrieve the 'Private-Key' header value from the request.
 * 2. If the 'Private-Key' matches the predefined PRIVATEKEY:
 *    - Return the data as is (no encryption needed).
 * 3. If the 'Private-Key' does not match PRIVATEKEY:
 *    - Encode the input data as JSON.
 *    - Generate a random Initialization Vector (IV) for AES-256-CBC.
 *    - Use the 'Private-Key' header value as the encryption key.
 *    - Perform AES encryption on the JSON-encoded data using AES-256-CBC mode.
 *    - Concatenate the IV and encrypted payload and base64 encode the result.
 *    - Return the encrypted data.
 *
 * @param mixed $data The data to be encrypted.
 * @return string The encrypted data (base64-encoded).
 */
function encryptData($data)
{
	$secretKey = env('ENCRYPTION_KEY');
    $headerKey = REQ::header('Private-Key');
    if ($headerKey && $headerKey == env('PRIVATE_KEY')) {
        return $data;
    }
    $iv = random_bytes(16); // Generate 16-byte IV
	$encryptedData = openssl_encrypt(
		json_encode($data),
		'aes-256-cbc',
		$secretKey,
		OPENSSL_RAW_DATA,
		$iv
	);
	return base64_encode($iv . $encryptedData);
}
/**
 * Decryption Algorithm using AES-256-CBC Encryption:
 *
 * Algorithm Steps:
 *
 * 1. Decode the encrypted data from base64.
 * 2. Extract the Initialization Vector (IV) and encrypted payload.
 * 3. Set the encryption key (replace 'ENCRIPTIONKEY' with your actual key).
 * 4. Initialize the AES decryption cipher using the encryption key and IV.
 * 5. Perform AES decryption on the encrypted payload.
 * 6. Obtain the decrypted binary data.
 * 7. Convert the decrypted binary data to the desired character encoding (e.g., UTF-8)
 *    to retrieve the original plaintext.
 * 8. Process and use the decrypted plaintext as needed.
 *
 * @param string $encryptedData The encrypted data (base64-encoded).
 * @return string The decrypted plaintext.
 */
function decryptData($encryptedData)
{
    $key = ENCRIPTIONKEY;
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encryptedPayload = substr($data, $ivLength);
    $decryptedData = openssl_decrypt($encryptedPayload, 'aes-256-cbc', $key, 0, $iv);
    return json_decode($decryptedData);
}

function getUserByToken($token)
{
    try {
        DB::beginTransaction();
        $data = DB::table('users')
            ->select('users.*', 'users_authentication.user_token', 'users_authentication.firebase_token')
            ->join('users_authentication', 'users.user_id', '=', 'users_authentication.user_id')
            ->where('users_authentication.user_token', $token)->first();
        DB::commit();
        return $data;
    } catch (\Exception $e) {
        DB::rollback();
        return false;
    }
}

function userAuthentication(Request $request)
{
    $token = $request->header('token');

    if (empty($token)) {
        return response()->json(['result' => -2, 'msg' => 'Header token is required!'], 401);
    }

    $user = getUserByToken($token);

    if (empty($user) || $user == null) {
        return response()->json(['result' => -2, 'msg' => 'Invalid token!'], 401);
    }

    if (property_exists($user, 'is_verified') && $user->is_verified === 'no') {
        return response()->json(['result' => -2, 'msg' => 'Please verify yourself. We have resent the verification link to your email. Please check your mail.'], 401);
    }

    if ($user->status === 'Deleted') {
        return response()->json(['result' => -2, 'msg' => 'Your account has been deleted.'], 401);
    }

    if ($user->status === 'Disabled') {
        return response()->json(['result' => -2, 'msg' => 'Your account is disabled.'], 401);
    }

    if ($user->status === 'Blocked') {
        return response()->json(['result' => -2, 'msg' => 'Your account is blocked.'], 401);
    }

    if ($user->status === 'Inactive') {
        return response()->json(['result' => -2, 'msg' => 'Your account has been inactive by admin.'], 401);
    }

    return response()->json(['result' => 1, 'msg' => 'Authentication successful', 'data' => $user]);
}

function customURL($path){
	return url('uploads/' . $path);
}

function formatDate($date)
{
    $carbonDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date);
    return $carbonDate->format('M d, Y');
}
function addBasePath($result, $col, $is_multi =false, $path = '/uploads') {
    $basepath = url($path) . '/';
   
    if ($is_multi) {
        if(!empty($result->$col)){
        $result->$col = $basepath . $result->$col;
        }
    } else {
        if ($result->isNotEmpty()) {
            foreach ($result as $val) {
    
                if(!empty($val->$col)){
                $val->$col = $basepath . $val->$col;
                }
            }
        }
    }

    return $result;
}

function sendFirebaseNotification($salon_id, $message, $title, $type ="normal")
{
    $token = SalonModel::getTokenBySalonId($salon_id);
    //dd($token->firebase_token);
    if (@$token->firebase_token == null) {
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => 'Invalid Firebase token']);
    }
    
    $url = 'https://fcm.googleapis.com/fcm/send';
    $typeno = "normal";
    if ($type == "Offer") {
        $typeno = "offer";
    }
    $data = [
        "message" => $message,
		"body" => $message,
        "title" => $title,
        'type'  => $typeno
    ];

    $fields = [
        'registration_ids' => [
            $token->firebase_token,
        ],
        'data' => $data,
		'notification' => $data
    ];

    $fields = json_encode($fields);

    $headers = array(
        'Authorization: key=' . env('API_ACCESS_KEY'),
        'Content-Type: application/json',
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);

    if ($result === FALSE) {
        $error_message = curl_error($ch);
        curl_close($ch);
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => $error_message]);
    }

    curl_close($ch);
	// dd($result); die;

    $result_array = json_decode($result, true);

    if (isset($result_array['success']) && $result_array['success'] > 0) {

        return response()->json(['result' => 1, 'msg' => 'Notification Sent Successfully']);
    } else {
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => $result]);
    }
}

function sendCustomerFirebaseNotification($customer_id, $message, $title,$type = "normal")
{
    $token = CustomerModel::getTokenByCustomerId($customer_id);
    if (@$token->firebase_token == null) {
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => 'Invalid Firebase token']);
    }
    $url = 'https://fcm.googleapis.com/fcm/send';
    $typeno = "normal";
    if ($type == "Offer") {
        $typeno = "offer";
    }
    $data = [
        "message" => $message,
		"body" => $message,
        "title" => $title,
        'type'  => $typeno,
    ];

    $fields = [
        'registration_ids' => [
            $token->firebase_token,
        ],
        'data' => $data,
		'notification' => $data
    ];
 
    $fields = json_encode($fields);

    $headers = array(
        'Authorization: key=' . env('API_ACCESS_KEY'),
        'Content-Type: application/json',
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $result = curl_exec($ch);

    if ($result === FALSE) {
        $error_message = curl_error($ch);
        curl_close($ch);
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => $error_message]);
    }

    curl_close($ch);

    $result_array = json_decode($result, true);

    if (isset($result_array['success']) && $result_array['success'] > 0) {
        return response()->json(['result' => 1, 'msg' => 'Notification Sent Successfully']);
    } else {
        return response()->json(['result' => 0, 'msg' => 'Failed to send notification', 'error' => $result]);
    }
}
