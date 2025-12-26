<?php

namespace App\Http\Controllers;
use App\Events\OrderNoteUpdated;
use App\Models\ManualBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Order;
use App\Models\ManualPaymentRequest;
use App\Models\OrderHistory;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\OrderPaymentGroup;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\DepartmentReportService;
use App\Services\DelegateNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;


class OrderController extends Controller
{




    /**
     * ط¥ظ†ط´ط§ط، ظ…ط«ظٹظ„ ط¬ط¯ظٹط¯ ظ„ظ„ظ…طھط­ظƒظ…
     */


    public function __construct(
        private readonly DepartmentReportService $departmentReportService,
        private readonly DelegateNotificationService $delegateNotificationService,
    )

    {
        // ظ„ط§ ط­ط§ط¬ط© ظ„ظ€ middleware ظ‡ظ†ط§طŒ ط³ظٹطھظ… ظپط­طµ ط§ظ„طµظ„ط§ط­ظٹط§طھ ظپظٹ ظƒظ„ ط¯ط§ظ„ط©
    }

    /**
     * ط¹ط±ط¶ ظ‚ط§ط¦ظ…ط© ط§ظ„ط·ظ„ط¨ط§طھ
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        ResponseService::noAnyPermissionThenRedirect(['orders-list']);
        
        $manualPaymentRequestColumns = $this->manualPaymentRequestSelectColumns();
        $manualBankColumns = ManualBank::relationSelectColumns();


        $query = Order::with([
            'user' => static fn ($query) => $query->withTrashed(),
            'seller' => static fn ($query) => $query->withTrashed(),
            'items.item.category',
            'latestManualPaymentRequest' => static function ($query) use ($manualPaymentRequestColumns, $manualBankColumns) {
                
                $query->select($manualPaymentRequestColumns);
                $query->with([
                    'manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {
                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);


            },
            'latestPaymentTransaction' => static function ($query) use ($manualBankColumns) {
                $query->select([
                    'payment_transactions.id',
                    'payment_transactions.payable_id',
                    'payment_transactions.payable_type',
                    'payment_transactions.payment_gateway',
                    'payment_transactions.payment_gateway_name',
                    'payment_transactions.gateway_label',
                    'payment_transactions.channel_label',
                    'payment_transactions.payment_gateway_label',
                    'payment_transactions.payment_status',
                    'payment_transactions.amount',
                    'payment_transactions.currency',
                    'payment_transactions.manual_payment_request_id',
                    'payment_transactions.meta',
                ]);
                $query->with([
                    'manualPaymentRequest.manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {
                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);
            
            },
        ])
        
        ->withCount(['openManualPaymentRequests as pending_manual_payment_requests_count'])

        
            ->where(function ($query) {
                $query->whereNull('department')
                    ->orWhereNotIn('department', [
                        DepartmentReportService::DEPARTMENT_SHEIN,
                        DepartmentReportService::DEPARTMENT_COMPUTER,
                    ]);
            });
        // 
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨
        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);

        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط§ظ„طھط§ط±ظٹط®
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„ط¨ط­ط«
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // طھط±طھظٹط¨ ط§ظ„ظ†طھط§ط¦ط¬
        $query->orderBy('created_at', 'desc');

        // طھظ‚ط³ظٹظ… ط§ظ„ظ†طھط§ط¦ط¬
        $orders = $query->paginate(15);

   
        $orderStatuses = $this->allowedOrderStatuses();

   
        $users = User::customers()->orWhereNull('account_type')->orderBy('name')->get();
        
       
        $sellers = User::sellers()->orderBy('name')->get();

        $statusLabels = $this->buildStatusLabels($orderStatuses);
        $deliveryPaymentTimingLabels = $this->deliveryPaymentTimingLabels();
        $deliveryPaymentStatusLabels = $this->deliveryPaymentStatusLabels();

        return view('orders.index', compact(
            'orders',
            'orderStatuses',
            'users',
            'sellers',
            'statusLabels',
            'deliveryPaymentTimingLabels',
            'deliveryPaymentStatusLabels'
        ));
    
    }


    public function indexShein(Request $request)
    {
        ResponseService::noAnyPermissionThenRedirect(['shein-orders-list']);
        // ط¥ط¹ط¯ط§ط¯ ط§ظ„ط§ط³طھط¹ظ„ط§ظ… ظ…ط¹ طھط­ظ…ظٹظ„ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ ظˆطھطµظپظٹط© ط­ط³ط¨ ط§ظ„ظپط¦ط© ط§ظ„ط£ظ… ط±ظ‚ظ… 4
        $department = DepartmentReportService::DEPARTMENT_SHEIN;
        $categoryIds = $this->departmentReportService->resolveCategoryIds($department);

        $manualPaymentRequestColumns = $this->manualPaymentRequestSelectColumns();
        $manualBankColumns = ManualBank::relationSelectColumns();


        $query = Order::with([
            'user' => static fn ($query) => $query->withTrashed(),
            'seller' => static fn ($query) => $query->withTrashed(),
            'items.item.category',
            'latestManualPaymentRequest' => static function ($query) use ($manualPaymentRequestColumns, $manualBankColumns) {
                $query->select($manualPaymentRequestColumns);
                $query->with([
                    'manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {
                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);
            },
            'latestPaymentTransaction' => static function ($query) use ($manualBankColumns) {
                $query->select([
                    'payment_transactions.id',
                    'payment_transactions.payable_id',
                    'payment_transactions.payable_type',
                    'payment_transactions.payment_gateway',
                    'payment_transactions.payment_gateway_name',
                    'payment_transactions.gateway_label',
                    'payment_transactions.channel_label',
                    'payment_transactions.payment_gateway_label',
                    'payment_transactions.payment_status',
                    'payment_transactions.amount',
                    'payment_transactions.currency',
                    'payment_transactions.manual_payment_request_id',
                    'payment_transactions.meta',
                ]);
                $query->with('manualPaymentRequest.manualBank:id,name,bank_name,beneficiary_name');
                $query->with([
                    'manualPaymentRequest.manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {

                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);
            
            },
        ])
        
        ->withCount(['openManualPaymentRequests as pending_manual_payment_requests_count'])
            ->where(function ($query) use ($department, $categoryIds) {
                $query->where('department', $department);

                if ($categoryIds !== []) {
                    $query->orWhereHas('items.item', static function ($query) use ($categoryIds) {
                        $query->whereIn('category_id', $categoryIds);
                    });
                }
            });

        // 
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط§ظ„طھط§ط¬ط±
        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨
        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط­ط§ظ„ط© ط§ظ„ط¯ظپط¹
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);

        }

        // طھط·ط¨ظٹظ‚ ط§ظ„طھطµظپظٹط© ط­ط³ط¨ ط§ظ„طھط§ط±ظٹط®
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // طھط·ط¨ظٹظ‚ ط§ظ„ط¨ط­ط«
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // طھط±طھظٹط¨ ط§ظ„ظ†طھط§ط¦ط¬
        $query->orderBy('created_at', 'desc');

        // طھظ‚ط³ظٹظ… ط§ظ„ظ†طھط§ط¦ط¬
        $orders = $query->paginate(15);

        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط¨ط§طھ
        $orderStatuses = $this->allowedOrderStatuses();

        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ظ‚ط§ط¦ظ…ط© ط§ظ„ظ…ط³طھط®ط¯ظ…ظٹظ† ظ„ظ„ظپظ„طھط± (ط§ظ„ط¹ظ…ظ„ط§ط،)
        $users = User::customers()->orWhereNull('account_type')->orderBy('name')->get();
        


        $sheinOrdersConstraint = static function ($query) use ($department, $categoryIds) {
            $query->where(function ($query) use ($department, $categoryIds) {
                $query->where('department', $department);

                if ($categoryIds !== []) {
                    $query->orWhereHas('items.item', static function ($itemQuery) use ($categoryIds) {
                        $itemQuery->whereIn('category_id', $categoryIds);
                    });
                }
            });
        };

        $paymentGroups = OrderPaymentGroup::query()
            ->withCount(['orders as shein_orders_count' => $sheinOrdersConstraint])
            ->withSum(['orders as shein_orders_total_amount' => $sheinOrdersConstraint], 'final_amount')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(static fn ($group) => (int) ($group->shein_orders_count ?? 0) > 0)
            ->values();

        return view('orders.shein', compact('orders', 'orderStatuses', 'users', 'categoryIds', 'department', 'paymentGroups'));
    
    }
    public function indexComputer(Request $request)

    {
        ResponseService::noAnyPermissionThenRedirect(['computer-orders-list']);

        $department = DepartmentReportService::DEPARTMENT_COMPUTER;
        $categoryIds = $this->departmentReportService->resolveCategoryIds($department);

        $manualPaymentRequestColumns = $this->manualPaymentRequestSelectColumns();
        $manualBankColumns = ManualBank::relationSelectColumns();

        $query = Order::with([
            'user' => static fn ($query) => $query->withTrashed(),
            'seller' => static fn ($query) => $query->withTrashed(),
            'items.item.category',
            'latestManualPaymentRequest' => static function ($query) use ($manualPaymentRequestColumns, $manualBankColumns) {
                $query->select($manualPaymentRequestColumns);

                $query->with([
                    'manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {
                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);
            },
            'latestPaymentTransaction' => static function ($query) use ($manualBankColumns) {
                $query->select([
                    'payment_transactions.id',
                    'payment_transactions.payable_id',
                    'payment_transactions.payable_type',
                    'payment_transactions.payment_gateway',
                    'payment_transactions.payment_gateway_name',
                    'payment_transactions.gateway_label',
                    'payment_transactions.channel_label',
                    'payment_transactions.payment_gateway_label',
                    'payment_transactions.payment_status',
                    'payment_transactions.amount',
                    'payment_transactions.currency',
                    'payment_transactions.manual_payment_request_id',
                    'payment_transactions.meta',
                ]);
                $query->with([
                    'manualPaymentRequest.manualBank' => static function (Builder|BelongsTo $manualBankQuery) use ($manualBankColumns): void {
                        $manualBankQuery->select($manualBankColumns);
                    },
                ]);
            
            },
        ])
            ->withCount(['openManualPaymentRequests as pending_manual_payment_requests_count'])
            ->where(function ($query) use ($department, $categoryIds) {

                
                $query->where('department', $department);

                if ($categoryIds !== []) {
                    $query->orWhereHas('items.item', static function ($query) use ($categoryIds) {
                        $query->whereIn('category_id', $categoryIds);
                    });
                }
            });

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }



        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);


        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $query->orderBy('created_at', 'desc');

        $orders = $query->paginate(15);

        $orderStatuses = $this->allowedOrderStatuses();

        $users = User::customers()->orWhereNull('account_type')->orderBy('name')->get();

        return view('orders.computer', compact('orders', 'orderStatuses', 'users', 'categoryIds', 'department'));

    }
    

    /**
     * ط¹ط±ط¶ ظ†ظ…ظˆط°ط¬ ط¥ظ†ط´ط§ط، ط·ظ„ط¨ ط¬ط¯ظٹط¯
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        ResponseService::noAnyPermissionThenRedirect(['orders-create']);
        
        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ظ‚ط§ط¦ظ…ط© ط§ظ„ظ…ط³طھط®ط¯ظ…ظٹظ† (ط§ظ„ط¹ظ…ظ„ط§ط،)
        $users = User::customers()->orWhereNull('account_type')->orderBy('name')->get();
        
        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ظ‚ط§ط¦ظ…ط© ط§ظ„طھط¬ط§ط±
        $sellers = User::sellers()->orderBy('name')->get();

        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط¨ط§طھ
        $orderStatuses = $this->allowedOrderStatuses();

        $paymentMethods = $this->allowedPaymentMethods();

        return view('orders.create', compact('users', 'sellers', 'orderStatuses', 'paymentMethods'));    }

    /**
     * طھط®ط²ظٹظ† ط·ظ„ط¨ ط¬ط¯ظٹط¯
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        ResponseService::noAnyPermissionThenRedirect(['orders-create']);
        
        // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ط¨ظٹط§ظ†ط§طھ
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'seller_id' => 'nullable|exists:users,id',
            'department' => ['nullable', Rule::in(array_keys($this->departmentReportService->availableDepartments()))],
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => ['nullable', Rule::in(array_keys($this->allowedPaymentMethods()))],
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $itemsPayload = collect($request->items ?? []);
        $itemIds = $itemsPayload->pluck('item_id')->filter()->unique()->all();

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $department = $this->resolveOrderDepartment(
            $this->normalizeDepartment($request->input('department')),
            $itemsPayload,
            $items
        );



        try {
            // ط¨ط¯ط، ط§ظ„ظ…ط¹ط§ظ…ظ„ط©
            DB::beginTransaction();

            // ط­ط³ط§ط¨ ط§ظ„ظ…ط¨ط§ظ„ط؛
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['price'] * $item['quantity'];
            }

            $taxAmount = $totalAmount * 0.15; // 15% ط¶ط±ظٹط¨ط©
            $finalAmount = $totalAmount + $taxAmount;

            // ط¥ظ†ط´ط§ط، ط§ظ„ط·ظ„ط¨
            $order = Order::create([
                'user_id' => $request->user_id,
                'seller_id' => $request->seller_id,
                'department' => $department,
                'order_number' => null,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'final_amount' => $finalAmount,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'order_status' => Order::STATUS_PROCESSING,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'notes' => $request->notes,
            ]);

            $order = $order->refreshOrderNumber();

            try {
                $this->delegateNotificationService->notifyNewOrder($order);
            } catch (Throwable $exception) {
                Log::error('delegate_notifications.new_order_failed', [
                    'order_id' => $order->getKey(),
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                ]);
            }

            
            // ط¥ط¶ط§ظپط© ط¹ظ†ط§طµط± ط§ظ„ط·ظ„ط¨
            foreach ($request->items as $itemData) {
                $item = $items->get($itemData['item_id']);

                if (! $item instanceof Item) {
                    throw ValidationException::withMessages([
                        'items' => __('ظ„ظ… ظٹطھظ… ط§ظ„ط¹ط«ظˆط± ط¹ظ„ظ‰ ط£ط­ط¯ ط§ظ„ط¹ظ†ط§طµط± ط§ظ„ظ…ط­ط¯ط¯ط©.'),
                    ]);
                }
                
                $subtotal = $itemData['price'] * $itemData['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'price' => $itemData['price'],
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $subtotal,
                    'options' => $itemData['options'] ?? null,
                ]);
            }

            // ط¥ط¶ط§ظپط© ط³ط¬ظ„ ط§ظ„ط·ظ„ط¨
            OrderHistory::create([
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'status_to' => Order::STATUS_PROCESSING,
                'comment' => 'طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ط·ظ„ط¨',
            ]);

            // طھط£ظƒظٹط¯ ط§ظ„ظ…ط¹ط§ظ…ظ„ط©
            DB::commit();

            return redirect()->route('orders.show', $order->id)
                ->with('success', 'طھظ… ط¥ظ†ط´ط§ط، ط§ظ„ط·ظ„ط¨ ط¨ظ†ط¬ط§ط­');
        } catch (\Exception $e) {
            // ط§ظ„طھط±ط§ط¬ط¹ ط¹ظ† ط§ظ„ظ…ط¹ط§ظ…ظ„ط© ظپظٹ ط­ط§ظ„ط© ط­ط¯ظˆط« ط®ط·ط£
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'ط­ط¯ط« ط®ط·ط£ ط£ط«ظ†ط§ط، ط¥ظ†ط´ط§ط، ط§ظ„ط·ظ„ط¨: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * ط¹ط±ط¶ طھظپط§طµظٹظ„ ط§ظ„ط·ظ„ط¨
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط§ظ„ط·ظ„ط¨ ظ…ط¹ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ
        $order = Order::with([
            'user' => static fn ($query) => $query->withTrashed(),
            'seller' => static fn ($query) => $query->withTrashed(),
            'items',
            'history.user' => static fn ($query) => $query->withTrashed(),
            'latestManualPaymentRequest.manualBank',
            'latestPaymentTransaction.manualPaymentRequest.manualBank',

        ])
        
        ->findOrFail($id);

        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط¨ط§طھ
        $orderStatuses = $this->allowedOrderStatuses();
        $paymentStatusOptions = Order::paymentStatusLabels();

        $statusLabels = $this->buildStatusLabels($orderStatuses);
        $paymentStatusOptions = Order::paymentStatusLabels();
        $deliveryPaymentTimingLabels = $this->deliveryPaymentTimingLabels();
        $deliveryPaymentStatusLabels = $this->deliveryPaymentStatusLabels();






        $cartSnapshot = is_array($order->cart_snapshot) ? $order->cart_snapshot : [];
        $cartItemsSnapshot = collect(data_get($cartSnapshot, 'items', []))
            ->filter(static fn ($item) => is_array($item))
            ->mapWithKeys(static function (array $item): array {
                $itemId = data_get($item, 'item_id');
                $variantId = data_get($item, 'variant_id');

                $key = sprintf('%s:%s', $itemId ?? 'null', $variantId ?? 'null');

                return [$key => $item];
            });

        $normalizeImageUrl = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            if (! is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);

            if ($value === '') {
                return null;
            }

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            if (Str::startsWith($value, '//')) {
                return 'https:' . $value;
            }

            return asset(ltrim($value, '/'));
        };

        $normalizeExternalUrl = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            if (! is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);

            if ($value === '') {
                return null;
            }

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            if (Str::startsWith($value, '//')) {
                return 'https:' . $value;
            }

            return Str::contains($value, '.') ? 'https://' . ltrim($value, '/') : null;
        };

        $orderItemsDisplayData = $order->items->map(function (OrderItem $orderItem) use ($cartItemsSnapshot, $normalizeImageUrl, $normalizeExternalUrl) {
            $itemSnapshot = is_array($orderItem->item_snapshot) ? $orderItem->item_snapshot : [];
            $pricingSnapshot = is_array($orderItem->pricing_snapshot) ? $orderItem->pricing_snapshot : [];

            $snapshotKey = sprintf('%s:%s', $orderItem->item_id ?? 'null', $orderItem->variant_id ?? 'null');
            $cartSnapshotItem = $cartItemsSnapshot->get($snapshotKey, []);

            if (! is_array($cartSnapshotItem)) {
                $cartSnapshotItem = [];
            }

            $options = $orderItem->options ?? $orderItem->attributes ?? data_get($itemSnapshot, 'attributes', []);

            if (is_string($options)) {
                $decoded = json_decode($options, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $options = $decoded;
                }
            }

            $options = is_array($options) ? $options : [];

            $optionsDisplay = collect($options)
                ->map(static function ($value, $key) {
                    $normalizedKey = Str::of((string) $key)->lower()->replace(['_', '-', ' '], '');
                    $normalizedKeyValue = (string) $normalizedKey;
                    $isColorKey = Str::contains($normalizedKeyValue, ['color', 'colour', 'لون']);
                    $isSizeKey = Str::contains($normalizedKeyValue, ['size', 'مقاس', 'المقاس']);
                    $isAttrKey = Str::startsWith($normalizedKeyValue, ['attr', 'attribute']);

                    if (is_array($value)) {
                        $value = collect($value)
                            ->map(static fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                            ->filter()
                            ->implode(', ');
                    } elseif (is_bool($value)) {
                        $value = $value ? 'نعم' : 'لا';
                    }

                    if (is_scalar($value)) {
                        $value = trim((string) $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($value === null || $value === '') {
                        return null;
                    }

                    $colorValue = null;
                    if (is_string($value)) {
                        $colorCandidate = trim($value);
                        if (preg_match('/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $colorCandidate) === 1) {
                            $colorValue = '#' . ltrim($colorCandidate, '#');
                        }
                    }

                    if (! $isColorKey && $colorValue) {
                        $isColorKey = true;
                    }

                    $label = Str::of((string) $key)
                        ->replace('_', ' ')
                        ->squish()
                        ->title();

                    if ($isColorKey) {
                        $label = 'اللون';
                    } elseif ($isSizeKey || $isAttrKey) {
                        $label = 'المقاس';
                        $isSizeKey = true;
                    }

                    $displayValue = $value;
                    if ($isColorKey && $colorValue) {
                        $displayValue = null;
                    }

                    return [
                        'label' => (string) $label,
                        'value' => $displayValue,
                        'raw_value' => $value,
                        'is_color' => $isColorKey,
                        'color_value' => $colorValue,
                        'is_size' => $isSizeKey,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $advertiserSource = collect([
                data_get($cartSnapshotItem, 'stock_snapshot.department_advertiser'),
                data_get($itemSnapshot, 'stock_snapshot.department_advertiser'),
                data_get($itemSnapshot, 'department_advertiser'),
                data_get($pricingSnapshot, 'department_advertiser'),
                data_get($pricingSnapshot, 'advertisement'),
            ])->first(static fn ($data) => is_array($data) && collect($data)->filter(fn ($value) => filled($value))->isNotEmpty());

            $advertiserFields = [];

            if (is_array($advertiserSource)) {
                $labelOverrides = [
                    'name' => 'الاسم',
                    'contact_number' => 'رقم التواصل',
                    'message_number' => 'رقم الرسائل',
                    'location' => 'الموقع',
                    'notes' => 'ملاحظات',
                    'reference' => 'مرجع',
                    'id' => 'معرف الإعلان',
                ];

                foreach ($advertiserSource as $field => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (is_array($value)) {
                        $value = collect($value)
                            ->map(static fn ($item) => is_scalar($item) ? trim((string) $item) : null)
                            ->filter()
                            ->implode(', ');
                    } elseif (is_bool($value)) {
                        $value = $value ? 'نعم' : 'لا';
                    }

                    if (is_scalar($value)) {
                        $value = trim((string) $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($value === '') {
                        continue;
                    }

                    $label = $labelOverrides[$field] ?? Str::of((string) $field)
                        ->replace('_', ' ')
                        ->squish()
                        ->title();

                    $advertiserFields[] = [
                        'label' => (string) $label,
                        'value' => $value,
                    ];
                }
            }

            $thumbnailCandidates = [
                $orderItem->item?->image,
                data_get($itemSnapshot, 'image'),
                data_get($itemSnapshot, 'thumbnail'),
                data_get($itemSnapshot, 'stock_snapshot.image'),
                data_get($itemSnapshot, 'stock_snapshot.thumbnail'),
                data_get($itemSnapshot, 'stock_snapshot.images.0'),
                data_get($cartSnapshotItem, 'stock_snapshot.image'),
                data_get($cartSnapshotItem, 'stock_snapshot.images.0'),
                data_get($cartSnapshotItem, 'image'),
            ];

            $thumbnailUrl = collect($thumbnailCandidates)
                ->map($normalizeImageUrl)
                ->filter()
                ->first();

            if (! $thumbnailUrl) {
                $thumbnailUrl = asset('assets/images/no_image_available.png');
            }

            $productUrl = null;

            if ($orderItem->item_id) {
                if (Route::has('item.details')) {
                    $productUrl = route('item.details', ['item' => $orderItem->item_id]);
                } else {
                    $productUrl = url(sprintf('item/%s/details', $orderItem->item_id));
                }
            }

            $reviewUrl = data_get($itemSnapshot, 'review_link')
                ?? data_get($itemSnapshot, 'review_url')
                ?? data_get($cartSnapshotItem, 'review_link')
                ?? data_get($cartSnapshotItem, 'review_url')
                ?? $orderItem->item?->review_link;
            $reviewUrl = $normalizeExternalUrl($reviewUrl);

            $variantLabel = data_get($itemSnapshot, 'variant_name')
                ?? data_get($cartSnapshotItem, 'variant_name');

            return [
                'id' => $orderItem->getKey(),
                'item_id' => $orderItem->item_id,
                'variant_id' => $orderItem->variant_id,
                'name' => $orderItem->item_name ?? $orderItem->item?->name,
                'price' => (float) $orderItem->price,
                'quantity' => (float) $orderItem->quantity,
                'subtotal' => (float) $orderItem->subtotal,
                'options' => $optionsDisplay,
                'has_options' => ! empty($optionsDisplay),
                'advertiser' => $advertiserFields,
                'has_advertiser' => ! empty($advertiserFields),
                'thumbnail_url' => $thumbnailUrl,
                'product_url' => $productUrl,
                'review_url' => $reviewUrl,
                'variant_label' => $variantLabel,
                'currency' => $orderItem->currency ?? data_get($pricingSnapshot, 'currency') ?? data_get($cartSnapshotItem, 'currency'),


            ];
        })->values();



        $statusHistoryUserIds = collect($order->status_history ?? [])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $statusHistoryUsers = $statusHistoryUserIds->isEmpty()
            ? collect()
            : User::withTrashed()->whereIn('id', $statusHistoryUserIds)->get()->keyBy('id');


        $order->load([
            'paymentGroups.orders' => static function ($query) {
                $query->select('orders.id', 'orders.order_number', 'orders.final_amount')
                    ->with(['items' => static function ($itemQuery) {
                        $itemQuery->select('order_items.id', 'order_items.order_id', 'order_items.quantity');
                    }]);
            },
            'manualPaymentRequests' => static function ($query) {
                $query->orderByDesc('id')
                    ->with('paymentTransaction');
            },
        ]);

        $paymentGroups = $order->paymentGroups;

        $availablePaymentGroups = OrderPaymentGroup::query()
            ->select(['id', 'name', 'note', 'created_at'])
            ->withCount('orders')
            ->whereDoesntHave('orders', static function ($query) use ($order) {
                $query->where('orders.id', $order->getKey());
            })
            ->orderBy('name')
            ->get();


        $manualPaymentRequests = $order->manualPaymentRequests;

        $pendingManualPaymentRequest = $manualPaymentRequests
        
        ->first(static fn (ManualPaymentRequest $request) => $request->isOpen());
        $latestManualPaymentRequest = $manualPaymentRequests->first();



        return view('orders.show', compact(
            'order',
            'orderStatuses',
            'statusLabels',
            'deliveryPaymentTimingLabels',
            'deliveryPaymentStatusLabels',
            'statusHistoryUsers',
            'paymentStatusOptions',
            'orderItemsDisplayData',
            'paymentGroups',
            'availablePaymentGroups',
            'pendingManualPaymentRequest',
            'latestManualPaymentRequest'
        
        ));
    }

    /**
     * ط¹ط±ط¶ ظ†ظ…ظˆط°ط¬ طھط¹ط¯ظٹظ„ ط§ظ„ط·ظ„ط¨
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط§ظ„ط·ظ„ط¨ ظ…ط¹ ط§ظ„ط¹ظ„ط§ظ‚ط§طھ
        $order = Order::with([
                'user' => static fn ($query) => $query->withTrashed(),
                'items',
                'history.user' => static fn ($query) => $query->withTrashed(),
                'manualPaymentRequests.paymentTransaction',
                'latestManualPaymentRequest.manualBank',
                'latestPaymentTransaction.manualPaymentRequest.manualBank',

            ])
            
            ->findOrFail($id);

        $manualPaymentRequests = $order->manualPaymentRequests;

        $pendingManualPaymentRequest = $manualPaymentRequests
        ->first(static fn (ManualPaymentRequest $request) => $request->isOpen());
        $latestManualPaymentRequest = $manualPaymentRequests->first();


        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ظ‚ط§ط¦ظ…ط© ط§ظ„ظ…ط³طھط®ط¯ظ…ظٹظ†
        $users = User::orderBy('name')->get();

        // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط¨ط§طھ
        $orderStatuses = $this->allowedOrderStatuses($order, true);

        $paymentStatusOptions = Order::paymentStatusLabels();

        return view(
            'orders.edit',
            compact(
                'order',
                'users',
                'orderStatuses',
                'paymentStatusOptions',
                'pendingManualPaymentRequest',
                'latestManualPaymentRequest'
            )
        
        );


    
    }

    /**
     * طھط­ط¯ظٹط« ط§ظ„ط·ظ„ط¨
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        ResponseService::noAnyPermissionThenRedirect(['orders-update']);
        
        // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط§ظ„ط¨ظٹط§ظ†ط§طھ
        $request->validate([
            'order_status' => ['required', 'string', Rule::in(Order::statusValues())],
            'shipping_address' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'notes' => 'nullable|string',
            'comment' => 'nullable|string',
            'notify_customer' => 'nullable|boolean',
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'url', 'max:2048'],
            'delivery_proof_image_path' => ['nullable', 'string', 'max:2048'],
            'delivery_proof_signature_path' => ['nullable', 'string', 'max:2048'],
            'delivery_proof_otp_code' => ['nullable', 'string', 'max:64'],

        ]);

        try {
            // ط¨ط¯ط، ط§ظ„ظ…ط¹ط§ظ…ظ„ط©
            DB::beginTransaction();

            // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط§ظ„ط·ظ„ط¨
            $order = Order::findOrFail($id);







            $pendingManualPaymentRequest = $order->latestPendingManualPaymentRequest();

            if (
                $pendingManualPaymentRequest
                && $request->order_status !== $order->order_status
            ) {
                DB::rollBack();

                $reviewUrl = route('payment-requests.review', $pendingManualPaymentRequest->getKey());
                $message = sprintf(
                    'ظ„ط§ ظٹظ…ظƒظ† طھط¹ط¯ظٹظ„ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ظ„ظˆط¬ظˆط¯ ط·ظ„ط¨ ط¯ظپط¹ ظٹط¯ظˆظٹ #%d ظ‚ظٹط¯ ط§ظ„ظ…ط±ط§ط¬ط¹ط©. ظٹط±ط¬ظ‰ ط¥طھظ…ط§ظ… ط§ظ„ظ…ط±ط§ط¬ط¹ط© ط¹ط¨ط± %s.',
                    $pendingManualPaymentRequest->getKey(),
                    $reviewUrl
                );

                return redirect()->back()
                    ->with('error', $message)
                    ->withInput();
            }


            if (! $order->hasSuccessfulPayment() && $request->order_status !== $order->order_status) {
                DB::rollBack();

                return redirect()->back()
                    ->with('error', 'ظ„ط§ ظٹظ…ظƒظ† طھط¹ط¯ظٹظ„ ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ظ‚ط¨ظ„ طھط£ظƒظٹط¯ ط§ظ„ط¯ظپط¹ ط¨ظ†ط¬ط§ط­.')
                    ->withInput();
            }

            
            // ط­ظپط¸ ط§ظ„ط­ط§ظ„ط© ط§ظ„ط³ط§ط¨ظ‚ط©
            $previousStatus = $order->order_status;


            if ($request->order_status !== $previousStatus && ! $order->hasSuccessfulPayment()) {
                DB::rollBack();

                return redirect()->back()
                    ->with('error', 'ظ„ط§ ظٹظ…ظƒظ† طھط­ط¯ظٹط« ط­ط§ظ„ط© ط§ظ„ط·ظ„ط¨ ظ‚ط¨ظ„ ط¥طھظ…ط§ظ… ط§ظ„ط¯ظپط¹ ط¨ظ†ط¬ط§ط­.')
                    ->withInput();
            }

            
            // طھط­ط¯ظٹط« ط¨ظٹط§ظ†ط§طھ ط§ظ„ط·ظ„ط¨
            $trackingAttributes = $this->prepareTrackingAttributes($request);

            $order->fill(array_merge([


                'order_status' => $request->order_status,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'notes' => $request->notes,
            ], $trackingAttributes));

            $noteWasUpdated = $order->isDirty('notes');
            $updatedNote = $order->notes;

            $order->save();



            
            // ط¥ط¶ط§ظپط© ط³ط¬ظ„ ظ„ظ„ط·ظ„ط¨ ط¥ط°ط§ طھط؛ظٹط±طھ ط§ظ„ط­ط§ظ„ط©
            if ($previousStatus !== $request->order_status) {
                OrderHistory::create([
                    'order_id' => $order->id,
                    'user_id' => Auth::id(),
                    'status_from' => $previousStatus,
                    'status_to' => $request->order_status,
                    'comment' => $request->comment,
                    'notify_customer' => $request->has('notify_customer'),
                ]);

                // ط¥ط°ط§ ظƒط§ظ†طھ ط§ظ„ط­ط§ظ„ط© "ظ…ظƒطھظ…ظ„"طŒ ظ†ظ‚ظˆظ… ط¨طھط­ط¯ظٹط« طھط§ط±ظٹط® ط§ظ„ط¥ظƒظ…ط§ظ„
                if ($request->order_status === 'delivered') {
                    $order->update(['completed_at' => now()]);
                }

                if ($request->has('notify_customer') && $request->boolean('notify_customer') && filled($request->comment)) {
                    event(new OrderNoteUpdated(
                        $order->fresh('user'),
                        $request->comment,
                        Auth::id(),
                        'history_comment'
                    ));
                }
            }

            if ($noteWasUpdated && filled($updatedNote)) {
                event(new OrderNoteUpdated(
                    $order->fresh('user'),
                    $updatedNote,
                    Auth::id(),
                    'order_note'
                ));

            }

            // طھط£ظƒظٹط¯ ط§ظ„ظ…ط¹ط§ظ…ظ„ط©
            DB::commit();

            return redirect()->route('orders.show', $order->id)
                ->with('success', 'طھظ… طھط­ط¯ظٹط« ط§ظ„ط·ظ„ط¨ ط¨ظ†ط¬ط§ط­');
        } catch (\Exception $e) {
            // ط§ظ„طھط±ط§ط¬ط¹ ط¹ظ† ط§ظ„ظ…ط¹ط§ظ…ظ„ط© ظپظٹ ط­ط§ظ„ط© ط­ط¯ظˆط« ط®ط·ط£
            DB::rollBack();
            
            return redirect()->back()
                ->with('error', 'ط­ط¯ط« ط®ط·ط£ ط£ط«ظ†ط§ط، طھط­ط¯ظٹط« ط§ظ„ط·ظ„ط¨: ' . $e->getMessage())
                ->withInput();
        }
    }




    /**
     * ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط­ط§ظ„ط§طھ ط§ظ„ط·ظ„ط¨ ط§ظ„ظ…ط³ظ…ظˆط­ ط¨ظ‡ط§.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\OrderStatus>
     */
    private function allowedOrderStatuses(?Order $contextOrder = null, ?bool $includeReserve = null)
    {
        $includeReserve = $includeReserve ?? request()->boolean('include_reserve_statuses');

        $query = OrderStatus::query()
            ->whereIn('code', Order::statusValues());

        if ($includeReserve) {
            $query->where(function ($builder) {
                $builder->where('is_active', true)
                    ->orWhere('is_reserve', true);
            });
        } else {
            $query->where('is_active', true);
        }

        $statuses = $query->orderBy('sort_order')->get();

        if ($contextOrder !== null) {
            $currentStatus = $contextOrder->order_status;

            if (is_string($currentStatus) && $currentStatus !== '') {
                $currentStatusEntry = OrderStatus::where('code', $currentStatus)->get();

                if ($currentStatusEntry->isNotEmpty()) {
                    $statuses = $statuses
                        ->concat($currentStatusEntry)
                        ->unique('code')
                        ->sortBy('sort_order')
                        ->values();
                }
            }
        }

        return $statuses;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareTrackingAttributes(Request $request): array
    {
        $status = (string) $request->input('order_status', '');

        if (! in_array($status, [Order::STATUS_OUT_FOR_DELIVERY, Order::STATUS_DELIVERED], true)) {
            return [];
        }

        $attributes = [];

        foreach (['tracking_number', 'carrier_name', 'tracking_url'] as $field) {
            if ($request->has($field)) {
                $attributes[$field] = $this->normalizeNullableString($request->input($field));
            }
        }

        if ($status === Order::STATUS_DELIVERED) {
            foreach (['delivery_proof_image_path', 'delivery_proof_signature_path', 'delivery_proof_otp_code'] as $field) {
                if ($request->has($field)) {
                    $attributes[$field] = $this->normalizeNullableString($request->input($field));
                }
            }
        }

        return $attributes;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function allowedPaymentMethods(): array
    {
        return [
            'wallet' => __('Wallet'),
            'east_yemen_bank' => __('East Yemen Bank'),
            'manual_bank' => __('Bank Transfer'),
        ];
    }



    /**
     * ط­ط°ظپ ط§ظ„ط·ظ„ط¨
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        ResponseService::noAnyPermissionThenRedirect(['orders-delete']);
        
        try {
            // ط§ظ„ط­طµظˆظ„ ط¹ظ„ظ‰ ط§ظ„ط·ظ„ط¨
            $order = Order::findOrFail($id);
            
            // ط­ط°ظپ ط§ظ„ط·ظ„ط¨ (ط³ظٹطھظ… ط­ط°ظپ ط§ظ„ط¹ظ†ط§طµط± ظˆط§ظ„ط³ط¬ظ„ طھظ„ظ‚ط§ط¦ظٹظ‹ط§ ط¨ط³ط¨ط¨ onDelete('cascade'))
            $order->delete();

            return redirect()->route('orders.index')
                ->with('success', 'طھظ… ط­ط°ظپ ط§ظ„ط·ظ„ط¨ ط¨ظ†ط¬ط§ط­');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'ط­ط¯ط« ط®ط·ط£ ط£ط«ظ†ط§ط، ط­ط°ظپ ط§ظ„ط·ظ„ط¨: ' . $e->getMessage());
        }
    }


    /**
     * @param  \Illuminate\Support\Collection<int, OrderStatus>  $orderStatuses
     * @return array<string, string>
     */
    private function buildStatusLabels($orderStatuses): array
    {
        $labels = array_merge(Order::getStatusList(), Order::paymentStatusLabels());


        foreach ($orderStatuses as $status) {
            $code = (string) $status->code;

            if ($code === '') {
                continue;
            }

            $labels[$code] = $status->name ?: ($labels[$code] ?? Str::of($code)->replace('_', ' ')->headline());
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function deliveryPaymentTimingLabels(): array
    {
        return [
            'pay_now' => 'ط§ظ„ط¯ظپط¹ ط§ظ„ط¢ظ†',
            'now' => 'ط§ظ„ط¯ظپط¹ ط§ظ„ط¢ظ†',
            'pay_on_delivery' => 'ط§ظ„ط¯ظپط¹ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…',
            'on_delivery' => 'ط§ظ„ط¯ظپط¹ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function deliveryPaymentStatusLabels(): array
    {
        return [
            'pending' => 'ظ‚ظٹط¯ ط§ظ„ط¯ظپط¹',
            'paid' => 'ظ…ط¯ظپظˆط¹',
            'waived' => 'ظ…ط¹ظپظ‰',
            'due_on_delivery' => 'ظ…ط³طھط­ظ‚ ط¹ظ†ط¯ ط§ظ„طھط³ظ„ظٹظ…',
            'due_now' => 'ظ…ط³طھط­ظ‚ ط§ظ„ط¢ظ†',
            'partial' => 'ظ…ط¯ظپظˆط¹ ط¬ط²ط¦ظٹط§ظ‹',
            'failed' => 'ظپط´ظ„ ط§ظ„ط¯ظپط¹',
        ];
    }


    private function resolveOrderDepartment(?string $requestedDepartment, Collection $itemsPayload, Collection $items): string
    {
        $resolvedDepartments = collect();
        $categoryCache = [];

        foreach ($itemsPayload as $itemPayload) {
            $itemId = $itemPayload['item_id'] ?? null;

            if ($itemId === null) {
                continue;
            }

            $item = $items->get($itemId);

            if (! $item instanceof Item) {
                continue;
            }

            $department = $this->resolveItemDepartment($item, $categoryCache);

            if ($department !== null) {
                $resolvedDepartments->push($department);
            }
        }

        $resolvedDepartments = $resolvedDepartments->unique()->values();

        if ($requestedDepartment !== null) {
            $mismatch = $resolvedDepartments->first(static fn ($department) => $department !== $requestedDepartment);

            if ($mismatch !== null) {
                throw ValidationException::withMessages([
                    'department' => __('ط§ظ„ظ‚ط³ظ… ط§ظ„ظ…ط­ط¯ط¯ ظ„ط§ ظٹطھط·ط§ط¨ظ‚ ظ…ط¹ ط¹ظ†ط§طµط± ط§ظ„ط³ظ„ط©.'),
                ]);
            }

            return $requestedDepartment;
        }

        if ($resolvedDepartments->count() > 1) {
            throw ValidationException::withMessages([
                'items' => __('ظ„ط§ ظٹظ…ظƒظ† ط¥طھظ…ط§ظ… ط§ظ„ط·ظ„ط¨ ط¨ط³ط¨ط¨ ط§ط®طھظ„ط§ظپ ط§ظ„ط£ظ‚ط³ط§ظ… ط¯ط§ط®ظ„ ط§ظ„ط³ظ„ط©.'),
            ]);
        }

        if ($resolvedDepartments->count() === 1) {
            return (string) $resolvedDepartments->first();
        }

        return $this->normalizeDepartment(config('cart.default_department'))
            ?? DepartmentReportService::DEPARTMENT_STORE;
    }

    private function resolveItemDepartment(Item $item, array &$categoryCache): ?string
    {
        $availableDepartments = array_keys($this->departmentReportService->availableDepartments());
        $itemCategoryIds = $this->gatherItemCategoryIds($item);

        foreach ($availableDepartments as $department) {
            if (! array_key_exists($department, $categoryCache)) {
                $categoryCache[$department] = $this->departmentReportService->resolveCategoryIds($department);
            }

            $departmentCategories = $categoryCache[$department];

            if ($departmentCategories !== [] && array_intersect($itemCategoryIds, $departmentCategories) !== []) {
                return $department;
            }
        }

        $interfaceType = $item->interface_type ?? null;

        if ($interfaceType !== null) {
            $mappedDepartment = $this->normalizeDepartment(config('cart.interface_map.' . $interfaceType));

            if ($mappedDepartment !== null) {
                return $mappedDepartment;
            }
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function gatherItemCategoryIds(Item $item): array
    {
        $categoryIds = [];

        if ($item->category_id !== null) {
            $categoryIds[] = (int) $item->category_id;
        }

        $rawList = $item->all_category_ids;

        if (is_string($rawList) && trim($rawList) !== '') {
            foreach (explode(',', $rawList) as $value) {
                $normalized = trim($value);

                if ($normalized === '') {
                    continue;
                }

                if (! is_numeric($normalized)) {
                    continue;
                }

                $categoryIds[] = (int) $normalized;
            }
        }

        return array_values(array_unique($categoryIds));
    }

    private function normalizeDepartment(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->trim()->lower();

        $stringValue = (string) $normalized;

        return $stringValue === '' ? null : $stringValue;
    }

    private function manualPaymentRequestSelectColumns(): array
    {
        $columns = [
            'manual_payment_requests.id',
            'manual_payment_requests.payable_id',
            'manual_payment_requests.payable_type',
            'manual_payment_requests.status',
            'manual_payment_requests.amount',
            'manual_payment_requests.currency',
            'manual_payment_requests.created_at',
            'manual_payment_requests.reviewed_at',
        ];

        if (Schema::hasColumn('manual_payment_requests', 'manual_bank_id')) {
            $columns[] = 'manual_payment_requests.manual_bank_id';
        }

        if (Schema::hasColumn('manual_payment_requests', 'bank_name')) {
            $columns[] = 'manual_payment_requests.bank_name';
        }

        if (Schema::hasColumn('manual_payment_requests', 'meta')) {
            $columns[] = 'manual_payment_requests.meta';
        }

        return $columns;
    }
}







