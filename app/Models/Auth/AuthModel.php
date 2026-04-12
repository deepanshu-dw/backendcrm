<?php
namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuthModel extends Model
{
    use HasFactory;

    /**
     * 
     * Defaults Model Settings
     * 
     */
    protected $table = 'admins';

    protected $primaryKey = 'admin_id';

    const CREATED_AT = 'creates_at';
    const UPDATED_AT = 'updated_at';

    public static $table_use = 'admins';

    public $timestamps = false;

    /**
     * 
     * Admin Register
     * @return \Illuminate\Http\JsonResponse
     * 
     */
    public static function AdminRegister($data)
    {
		$id = DB::table(self::$table_use)->insertGetId($data);
		//$id = DB::getPdo()->lastInsertId();
		return $id;
    }

    /**
     * 
     * Admin Login
     * @return \Illuminate\Http\JsonResponse
     * 
     */

    public static function AdminLogin($email, $pass)
    {
        DB::beginTransaction();
        try {
            $result = DB::table(self::$table_use)->where('admin_email', $email)->where('admin_password', $pass)->where('status', '!=', 'Deleted')->first();
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollback();
        }
    }

    /**
     * Update the admin's password using a database transaction.
     *
     * @param int    $admin_id
     * @param string $pass
     * @return int|bool The number of affected rows or false on failure.
     */
    public  static function updateAdminPassword($admin_id, $pass)
    {
        // Step 1: Begin a database transaction
        DB::beginTransaction();

        try {
            // Step 2: Update the admin's password
            $result = DB::table(self::$table_use)->where('admin_id', $admin_id)->update(['admin_password' => $pass]);

            // Step 3: Commit the transaction
            DB::commit();

            // Step 4: Return the result of the update operation
            return $result;
        } catch (\Exception $e) {
            // Step 5: Rollback the transaction on exception
            DB::rollback();
        }

        // Return false if an exception occurred
        return false;
    }

    /**
     * Get details of a specific admin.
     *
     * @param  int  $admin_id
     * @return \Illuminate\Http\JsonResponse
     */
    public static function getAdminDetails($admin_id)
    {
        try {
            $adminDetails = DB::table(self::$table_use)->where('admin_id', $admin_id)->first();
            return $adminDetails;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public static function getAllAdmins($type = null, $keyword = null)
	{
		try {
			$data = DB::table(self::$table_use);
			if (!empty($type)) {
				$data->where('role', $type);
			}
			$data->where('status', '!=', 'Deleted');
			if (!empty($keyword)) {
				$data->where(function ($query) use ($keyword) {
					$query->where('admin_name', 'LIKE', '%' . $keyword . '%')
						  ->orWhere('admin_email', 'LIKE', '%' . $keyword . '%')
						  ->orWhere('admin_phone', 'LIKE', '%' . $keyword . '%')
						  ->orWhere('admin_address', 'LIKE', '%' . $keyword . '%');
				});
			}
			$result = $data->get();
			return $result;
		} catch (\Exception $e) {
			return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
		}
	}

	
	public static function updateAdminDetails($admin_id, $data)
    {
        try {
			DB::beginTransaction();
            $result = DB::table(self::$table_use)->where('admin_id', $admin_id)->update($data);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
			return false;
        }
    }
    
	public static function getAdminByEmailOrUsername($admin_email)
    {
        try {
            $result = DB::table('admins')
                ->where('admin_email', $admin_email)
                ->where('status', 'Active')
                ->first();
            return $result;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
	
	public static function updateStatus($admin_id,$status)
    {
        try {
             DB::table('admins')
                ->where('admin_id', $admin_id)
                ->update(['status' => $status]);
            return true;
        } catch (\Exception $e) {
            return response()->json(['result' => -1, 'msg' => $e->getMessage()]);
        }
    }
}
