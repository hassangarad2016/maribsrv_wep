<?php

namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\User;
use App\Models\PaymentTransaction;
use App\Models\ManualPaymentRequest;
use App\Models\Notifications;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class HomeController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('auth');
    }


    public function index() {

        $items = Item::select('id','name','price','latitude','longitude','city','state','country','image')->where('clicks','>',0)->where('status', 'approved')->inRandomOrder()->limit(50)->get();
        $categories = Category::withCount('items')->with('translations')->whereHas('items')->get();

        $category_name = array();
        $category_item_count = array();

        foreach ($categories as $value) {
            $category_name[] = "'" . $value->translated_name . "'";
            $category_item_count[] = $value->items_count;
        }

        $categories_count = Category::count();
        $user_count = User::role('User')->withTrashed()->count();
        $item_count = Item::count();
        $custom_field_count = CustomField::count();

        $itemStatusCount = function ($statuses) {
            if (! Schema::hasColumn('items', 'status')) {
                return null;
            }

            return Item::whereIn('status', (array) $statuses)->count();
        };

        $counts = [
            'users_total'        => $user_count,
            'items_total'        => $item_count,
            'items_approved'     => $itemStatusCount(['approved']),
            'items_pending'      => $itemStatusCount(['pending', 'awaiting_approval', 'under_review']),
            'categories_total'   => $categories_count,
            'custom_fields'      => $custom_field_count,
            'payments_pending'   => null,
            'payments_failed'    => null,
            'manual_requests'    => null,
            'unread_reports'     => null,
            'notifications_unread' => null,
        ];

        if (class_exists(PaymentTransaction::class)) {
            $counts['payments_pending'] = PaymentTransaction::whereIn('payment_status', ['pending', 'initiated', 'processing'])->count();
            $counts['payments_failed'] = PaymentTransaction::whereIn('payment_status', ['failed', 'expired'])->count();
        }

        if (class_exists(ManualPaymentRequest::class)) {
            $counts['manual_requests'] = ManualPaymentRequest::whereIn('status', ['pending', 'awaiting_review'])->count();
        }

        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'status')) {
            $counts['unread_reports'] = DB::table('reports')->where('status', 'unread')->count();
        }

        if (class_exists(Notifications::class)) {
            if (Schema::hasColumn('notifications', 'is_read')) {
                $counts['notifications_unread'] = Notifications::where('is_read', 0)->count();
            } elseif (Schema::hasColumn('notifications', 'read_at')) {
                $counts['notifications_unread'] = Notifications::whereNull('read_at')->count();
            }
        }

        $recentItems = Item::select('id', 'name', 'status', 'created_at')->latest()->take(8)->get();
        $recentUsers = User::select('id', 'name', 'email', 'created_at')->latest()->take(8)->get();
        $recentPayments = class_exists(PaymentTransaction::class)
            ? PaymentTransaction::select('id', 'payment_gateway', 'payment_status', 'amount', 'currency', 'created_at')->latest()->take(8)->get()
            : collect();
        $recentManualRequests = class_exists(ManualPaymentRequest::class)
            ? ManualPaymentRequest::select('id', 'status', 'amount', 'currency', 'created_at')->latest()->take(5)->get()
            : collect();

        return view('home', compact(
            'category_item_count',
            'category_name',
            'items',
            'counts',
            'recentItems',
            'recentUsers',
            'recentPayments',
            'recentManualRequests'
        ));


    }

    public function changePasswordIndex() {
        return view('change_password.index');
    }


    public function changePasswordUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'old_password'     => 'required',
            'new_password'     => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();
            if (!Hash::check($request->old_password, Auth::user()->password)) {
                ResponseService::errorResponse("Incorrect old password");
            }
            $user->password = Hash::make($request->confirm_password);
            $user->update();
            ResponseService::successResponse('Password Change Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "HomeController --> changePasswordUpdate");
            ResponseService::errorResponse();
        }


    }


    public function changeProfileIndex() {
        return view('change_profile.index');
    }

    public function changeProfileUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'    => 'required',
            'email'   => 'required|email|unique:users,email,' . Auth::user()->id,
            'profile' => 'nullable|mimes:jpeg,jpg,png'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $user = Auth::user();
            $data = [
                'name'  => $request->name,
                'email' => $request->email
            ];
            if ($request->hasFile('profile')) {
                $data['profile'] = $request->file('profile')->store('admin_profile', 'public');
            }
            $user->update($data);
            ResponseService::successResponse('Profile Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "HomeController --> updateProfile");
            ResponseService::errorResponse();
        }

    }
    public function getMapsData()
    {
        $apiKey = env('PLACE_API_KEY');

        $url = "https://maps.googleapis.com/maps/api/js?" . http_build_query([
            'libraries' => 'places',
            'key' => $apiKey, // Use the API key from the .env file
            // Add any other parameters you need here
        ]);

        return file_get_contents($url);
    }
}
