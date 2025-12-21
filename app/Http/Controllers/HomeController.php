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
use Carbon\Carbon;
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
        [
            'counts' => $counts,
            'recentItems' => $recentItems,
            'recentUsers' => $recentUsers,
            'recentPayments' => $recentPayments,
            'recentManualRequests' => $recentManualRequests,
            'timeline' => $timeline,
        ] = $this->buildDashboardData();

        return view('home', compact(
            'counts',
            'recentItems',
            'recentUsers',
            'recentPayments',
            'recentManualRequests',
            'timeline'
        ));
    }

    public function metrics()
    {
        return response()->json($this->buildDashboardData());
    }

    private function buildDashboardData(): array
    {
        $itemStatusCount = function ($statuses) {
            if (! Schema::hasColumn('items', 'status')) {
                return null;
            }

            return Item::whereIn('status', (array) $statuses)->count();
        };

        $user_count = User::role('User')->withTrashed()->count();
        $onlineUsers = $this->onlineUsersCount();

        $counts = [
            'users_total'          => $user_count,
            'users_online'         => $onlineUsers,
            'items_total'          => Item::count(),
            'items_approved'       => $itemStatusCount(['approved']),
            'items_pending'        => $itemStatusCount(['pending', 'awaiting_approval', 'under_review']),
            'categories_total'     => Category::count(),
            'custom_fields'        => CustomField::count(),
            'payments_pending'     => null,
            'payments_failed'      => null,
            'manual_requests'      => null,
            'unread_reports'       => null,
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

        $timeline = $this->buildActivityTimeline();

        return compact(
            'counts',
            'recentItems',
            'recentUsers',
            'recentPayments',
            'recentManualRequests',
            'timeline'
        );
    }

    private function onlineUsersCount(): ?int
    {
        $window = Carbon::now()->subMinutes(5);

        if (Schema::hasColumn('users', 'last_seen_at')) {
            return User::where('last_seen_at', '>=', $window)->count();
        }

        if (Schema::hasColumn('users', 'updated_at')) {
            return User::where('updated_at', '>=', $window)->count();
        }

        return null;
    }

    private function buildActivityTimeline(): array
    {
        $now = Carbon::now();
        $labels = [];
        $itemsSeries = [];
        $paymentsSeries = [];
        $manualSeries = [];

        for ($i = 11; $i >= 0; $i--) {
            $start = $now->copy()->subHours($i + 1);
            $end = $now->copy()->subHours($i);
            $labels[] = $end->format('H:i');

            $itemsSeries[] = Item::whereBetween('created_at', [$start, $end])->count();

            if (class_exists(PaymentTransaction::class)) {
                $paymentsSeries[] = PaymentTransaction::whereBetween('created_at', [$start, $end])->count();
            } else {
                $paymentsSeries[] = 0;
            }

            if (class_exists(ManualPaymentRequest::class)) {
                $manualSeries[] = ManualPaymentRequest::whereBetween('created_at', [$start, $end])->count();
            } else {
                $manualSeries[] = 0;
            }
        }

        return [
            'labels' => $labels,
            'series' => [
                ['name' => 'الإعلانات', 'data' => $itemsSeries],
                ['name' => 'المدفوعات', 'data' => $paymentsSeries],
                ['name' => 'التحويلات اليدوية', 'data' => $manualSeries],
            ],
        ];
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
