<?php

namespace App\Http\Controllers\Api\Sections\Items\GetItem;

use App\Events\ManualPaymentRequestCreated;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserPresenceUpdated;
use App\Events\UserTyping;
use App\Http\Resources\ItemCollection;
use App\Http\Resources\ManualPaymentRequestResource;
use App\Http\Resources\PaymentTransactionResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\SliderResource;
use App\Services\SliderMetricService;
use App\Models\ManualPaymentRequestHistory;
use App\Services\DepartmentAdvertiserService;
use App\Services\TelemetryService;
use App\Models\Area;
use App\Models\BlockUser;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\City;
use App\Models\ContactUs;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\Faq;
use App\Models\Favourite;
use App\Models\FeaturedAdsConfig;
use App\Models\FeaturedItems;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\ItemOffer;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\Package;
use App\Models\ManualBank;
use App\Models\ManualPaymentRequest;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\ReportReason;
use App\Models\SellerRating;
use App\Models\SeoSetting;
use App\Models\Service;
use App\Models\ServiceCustomField;
use App\Models\ServiceCustomFieldValue;
use App\Models\ServiceRequest;
use App\Models\ServiceReview;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Pagination\AbstractPaginator;
use App\Services\DelegateNotificationService;
use App\Models\Slider;
use App\Models\SocialLogin;
use App\Models\State;
use App\Models\Tip;
use App\Models\TipTranslation;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Models\UserReports;
use App\Models\VerificationField;
use App\Models\VerificationFieldRequest;
use App\Models\VerificationFieldValue;
use App\Models\VerificationPlan;
use App\Models\VerificationRequest;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Models\ReferralAttempt;
use App\Services\SliderEligibilityService;
use App\Models\ServiceReviewReport;
use App\Policies\SectionDelegatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\UploadedFile;
use App\Models\CurrencyRateQuote;
use App\Events\CurrencyCreated;
use App\Events\CurrencyRatesUpdated;
use App\Models\Governorate;
use App\Models\CurrencyRate;
use App\Models\Challenge;
use App\Models\Referral;
use App\Models\DepartmentTicket;
use App\Services\DepartmentSupportService;
use App\Enums\NotificationFrequency;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPreference;
use App\Models\RequestDevice;
use App\Models\Order;
use App\Services\CachingService;
use App\Services\DelegateAuthorizationService;
use App\Services\DepartmentReportService;
use App\Services\FileService;
use App\Services\InterfaceSectionService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\PaymentFulfillmentService;
use App\Services\WalletService;
use App\Services\ResponseService;
use App\Services\ServiceAuthorizationService;
use App\Services\Location\MaribBoundaryService;
use App\Services\ReferralAuditLogger;
use DateTimeInterface;
use App\Services\Pricing\ActivePricingPolicyCache;

use App\Models\Pricing\PricingPolicy;
use App\Models\Pricing\PricingDistanceRule;
use App\Models\Pricing\PricingWeightTier;
use App\Models\DeliveryPrice;



use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\WalletWithdrawalRequestResource;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;


use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\OTP;
use App\Models\PendingSignup;
use App\Jobs\SendOtpWhatsAppJob;
use App\Services\EnjazatikWhatsAppService;
use Throwable;
use Exception;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use App\Services\ImageVariantService;

use JsonException;

trait GetItemTrait
{
    public function getItem(Request $request) {
        if ($request->query->has('view')) {
            $request->query->set('view', strtolower((string) $request->query('view')));
        }

        // â•ھط­â•ھâ–‘â•ھط¯ â”کآ„â”کأ  â”کأ¨â”کآڈâ•ھâ–’â•ھâ”‚â”کآ„ â•ھط«â”کأ¨ â”کأ â”کآڈâ•ھâ•£â•ھâ–’â”کظ‘â”کآپ â•ھط«â”کأھ â”کآپâ”کآ„â•ھط¯â•ھط²â•ھâ–’ â•ھط«â•ھâ”‚â•ھط¯â•ھâ”‚â”کأ¨â•ھط±â•ھأ® â•ھط«â•ھâ•£â”کآگâ•ھآ» â•ھâ–’â•ھآ»â•ھط¯â”کأ¯ â”کآپâ•ھط¯â•ھâ–’â•ھâ•‘â•ھط¯â”کأ¯ â•ھط°â•ھآ»â”کآ„ â•ھآ«â•ھâ•–â•ھط«
        $guardFields = [
            'id',
            'category_id',
            'category_ids',
            'store_id',
            'user_id',
            'slug',
            'custom_fields',
        ];

        $isMyItemsRequest = $request->is('api/my-items');

        $hasIdentifier = false;
        foreach ($guardFields as $field) {
            if ($request->filled($field)) {
                $hasIdentifier = true;
                break;
            }
        }

        if (! $hasIdentifier && ! $request->filled('view') && ! $isMyItemsRequest) {
            return ResponseService::successResponse('OK', [
                'data' => [],
                'total' => 0,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'limit'          => 'nullable|integer',
            'offset'         => 'nullable|integer',
            'per_page'       => 'nullable|integer',
            'id'             => 'nullable',
            'custom_fields'  => 'nullable',
            'category_id'    => 'nullable',
            'category_ids'   => 'nullable|array',
            'category_ids.*' => 'integer',
            'store_id'       => 'nullable|integer|exists:stores,id',
            'user_id'        => 'nullable',
            'min_price'      => 'nullable',
            'max_price'      => 'nullable',
            'sort_by'        => [
                'nullable',
                Rule::in([
                    'latest',
                    'most_viewed',
                    'new-to-old',
                    'old-to-new',
                    'price-high-to-low',
                    'price-low-to-high',
                    'default',
                ]),
            ],
            
            'posted_since'   => 'nullable|in:all-time,today,within-1-week,within-2-week,within-1-month,within-3-month',
            'promoted'       => 'nullable|boolean',
            'interface_type' => ['nullable', Rule::in(self::interfaceTypes(includeLegacy: true))],
            'view'           => ['nullable', Rule::in(['summary', 'detail'])],
            'sw_lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'sw_lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'ne_lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'ne_lng'         => ['nullable', 'numeric', 'between:-180,180'],


        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //TODO : need to simplify this whole module

            $viewMode = strtolower((string) $request->query('view', 'detail'));
            $isSummaryView = $viewMode === 'summary';
            $isDetailView = ! $isSummaryView;

            if ($isSummaryView) {
                Log::info('getItem.params', $request->all());
            }

            // If this is an e_store context and store_id is missing, infer it from user_id
            if ($request->interface_type === 'e_store'
                && ! $request->filled('store_id')
                && $request->filled('user_id')) {
                $storeIdGuess = Store::where('user_id', $request->input('user_id'))
                    ->value('id');
                if ($storeIdGuess) {
                    $request->merge(['store_id' => $storeIdGuess]);
                }
            }

            // Prevent leaking cross-store items: if e_store context without store/user, return empty.
            if ($request->interface_type === 'e_store'
                && ! $request->filled('store_id')
                && ! $request->filled('user_id')) {
                return ResponseService::successResponse('OK', [
                    'data' => [],
                    'total' => 0,
                ]);
            }

            // Hard guard: in e_store context, enforce store or user filter.
            if ($request->interface_type === 'e_store') {
                if ($request->filled('store_id')) {
                    $request->merge(['store_id' => (int) $request->input('store_id')]);
                } elseif ($request->filled('user_id')) {
                    $request->merge(['user_id' => (int) $request->input('user_id')]);
                }
            }

            // Extra guard for store category requests (storefront root category = 3):
            if ((string) $request->input('category_id') === '3'
                && ! $request->filled('store_id')
                && ! $request->filled('user_id')) {
                return ResponseService::successResponse('OK', [
                    'data' => [],
                    'total' => 0,
                ]);
            }


        $interfaceTypeFilter = null;
        $interfaceTypeVariants = [];
        $interfaceTypeCategoryIds = [];
        $hasInterfaceTypeColumn = true;

        if ($request->filled('interface_type')) {
            $interfaceTypeFilter = InterfaceSectionService::normalizeSectionType($request->input('interface_type'));
            $itemColumns = $this->getItemColumnAvailability();
            $hasInterfaceTypeColumn = isset($itemColumns['interface_type']);

            if ($interfaceTypeFilter !== null && $interfaceTypeFilter !== 'all') {
                $interfaceTypeVariants = InterfaceSectionService::sectionTypeVariants($interfaceTypeFilter);
                $resolvedCategories = InterfaceSectionService::categoryIdsForSection($interfaceTypeFilter);
                if (is_array($resolvedCategories) && $resolvedCategories !== []) {
                    $interfaceTypeCategoryIds = array_values(array_filter(
                        $resolvedCategories,
                        static fn ($id) => is_int($id) && $id > 0
                    ));
                }
            }
        }


            $promotedFilter = null;

            if ($request->filled('promoted')) {
                $promotedFilter = filter_var($request->promoted, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }


            $summarySelectColumns = $this->filterItemSelectColumns([
                'items.id',
                'items.name',
                'items.slug',
                'items.price',
                'items.currency',
                'items.thumbnail_url',
                'items.image',
                'items.created_at',
                'items.updated_at',
                'items.city',
                'items.state',
                'items.country',
                'items.address',
                'items.latitude',
                'items.longitude',
                'items.status',
                'items.interface_type',
                'items.item_type',
                'items.user_id',
                'items.category_id',
                'items.product_link',
                'items.discount_type',
                'items.discount_value',
                'items.discount_start',
                'items.discount_end',
                'items.clicks',
            ]);



            $detailRelations = [
                'user:id,name,email,mobile,profile,created_at,is_verified,show_personal_details,country_code,account_type',
                'user.latestApprovedVerificationRequest:id,user_id,expires_at,status,approved_at',
                'category:id,name,image',
                'gallery_images:id,image,item_id,thumbnail_url,detail_image_url',
                'featured_items',
                'favourites',
                'item_custom_field_values.custom_field',
                'area:id,name',
                'store:id,name,slug',
                'store.policies:id,store_id,policy_type,title,content,is_required,is_active,display_order',
            ];

            if ($isDetailView) {
                $sql = Item::with($detailRelations)
                    ->withCount('favourites')
                    ->withAvg('review as ratings_avg', 'ratings')
                    ->withCount('review as ratings_count')
                    ->select('items.*');
            } else {
                $sql = Item::query()
                    ->select($summarySelectColumns)
                    ->withCount('featured_items as featured_items_count')
                    ->withCount('favourites as favourites_count');

                if (Auth::check()) {
                    $sql->withExists([
                        'favourites as is_favorited' => static function ($query) {
                            $query->where('user_id', Auth::id());
                        },
                    ]);
                }

            }

            $sql = $sql


                ->when($request->id, function ($sql) use ($request) {
                    $sql->where('id', $request->id);
                })->when(($request->category_id), function ($sql) use ($request) {
                    $category = Category::where('id', $request->category_id)->with('children')->get();
                    $categoryIDS = HelperService::findAllCategoryIds($category);
                    return $sql->whereIn('category_id', $categoryIDS);
                })->when(($request->category_slug), function ($sql) use ($request) {
                    $category = Category::where('slug', $request->category_slug)->with('children')->get();
                    $categoryIDS = HelperService::findAllCategoryIds($category);
                    return $sql->whereIn('category_id', $categoryIDS);


                    })->when($request->filled('category_ids'), function ($sql) use ($request) {
                    $categoryIds = $request->category_ids;
                    if (!is_array($categoryIds)) {
                        $categoryIds = array_filter(explode(',', (string) $categoryIds));
                    }
                    $categoryIds = array_values(array_filter(array_map('intval', $categoryIds)));

                    if (empty($categoryIds)) {
                        return $sql;
                    }

                    return $sql->whereIn('category_id', $categoryIds);
                })->when($interfaceTypeFilter !== null, function ($sql) use ($interfaceTypeFilter, $interfaceTypeVariants, $interfaceTypeCategoryIds, $hasInterfaceTypeColumn) {
                    if ($interfaceTypeFilter === 'all') {
                        return $sql;
                    }

                    if (! $hasInterfaceTypeColumn) {
                        if (! empty($interfaceTypeCategoryIds)) {
                            return $sql->whereIn('category_id', $interfaceTypeCategoryIds);
                        }
                        return $sql;
                    }

                    return $sql->where(function ($query) use ($interfaceTypeVariants, $interfaceTypeCategoryIds) {
                        $query->whereIn('interface_type', $interfaceTypeVariants);
                        if (! empty($interfaceTypeCategoryIds)) {
                            $query->orWhere(function ($inner) use ($interfaceTypeCategoryIds) {
                                $inner->whereNull('interface_type')
                                    ->whereIn('category_id', $interfaceTypeCategoryIds);
                            });
                        }
                    });
                })->when($promotedFilter === true, function ($sql) {
                    return $sql->whereHas('featured_items');


                })->when((isset($request->min_price) || isset($request->max_price)), function ($sql) use ($request) {
                    $min_price = $request->min_price ?? 0;
                    $max_price = $request->max_price ?? Item::max('price');
                    return $sql->whereBetween('price', [$min_price, $max_price]);
                })->when($request->posted_since, function ($sql) use ($request) {
                    return match ($request->posted_since) {
                        "today" => $sql->whereDate('created_at', '>=', now()),
                        "within-1-week" => $sql->whereDate('created_at', '>=', now()->subDays(7)),
                        "within-2-week" => $sql->whereDate('created_at', '>=', now()->subDays(14)),
                        "within-1-month" => $sql->whereDate('created_at', '>=', now()->subMonths()),
                        "within-3-month" => $sql->whereDate('created_at', '>=', now()->subMonths(3)),
                        default => $sql
                    };
                // Remove location filtering to show all items regardless of location
                // })->when($request->country, function ($sql) use ($request) {
                //     return $sql->where('country', $request->country);
                // })->when($request->state, function ($sql) use ($request) {
                //     return $sql->where('state', $request->state);
                // })->when($request->city, function ($sql) use ($request) {
                //     return $sql->where('city', $request->city);
                // })->when($request->area_id, function ($sql) use ($request) {
                //     return $sql->where('area_id', $request->area_id);
                })->when($request->user_id, function ($sql) use ($request) {
                    return $sql->where('user_id', $request->user_id);
                })->when($request->store_id, function ($sql) use ($request) {
                    return $sql->where('store_id', $request->store_id);
                })->when($request->slug, function ($sql) use ($request) {
                    return $sql->where('slug', $request->slug);
                })->when($this->requestHasBoundingBox($request), function ($sql) use ($request) {
                    return $this->applyBoundingBoxFilter($sql, $request);

                // Remove radius/location-based filtering to show all items
                // })->when($request->latitude && $request->longitude && $request->radius, function ($sql) use ($request) {
                //     $latitude = $request->latitude;
                //     $longitude = $request->longitude;
                //     $radius = $request->radius;

                //     // Calculate distance using Haversine formula
                //     $haversine = "(6371 * acos(cos(radians($latitude))
                //                     * cos(radians(latitude))
                //                     * cos(radians(longitude)
                //                     - radians($longitude))
                //                     + sin(radians($latitude))
                //                     * sin(radians(latitude))))";

                //     $sql->select('items.*')
                //         ->selectRaw("{$haversine} AS distance")
                //         ->withCount('favourites')
                //         ->where('latitude', '!=', 0)
                //         ->where('longitude', '!=', 0)
                //         ->having('distance', '<', $radius)
                //         ->orderBy('distance', 'asc');
                });


            //            // Other users should only get approved items
            //            if (!Auth::check()) {
            //                $sql->where('status', 'approved');
            //            }


            // Sort By
            $sortBy = $request->sort_by;


            $sql = match ($sortBy) {
                'most_viewed' => $sql->orderBy('clicks', 'DESC'),

                'old-to-new' => $sql->orderBy('created_at'),
                'price-high-to-low' => $sql->orderByDesc('price'),
                'price-low-to-high' => $sql->orderBy('price'),
                null, 'default', 'latest', 'new-to-old' => $sql->orderByDesc('created_at'),
                default => $sql->orderByDesc('created_at'),
            };


            // Status
            if (!empty($request->status)) {
                if (in_array($request->status, array('review', 'approved', 'rejected', 'sold out'))) {
                    $sql->where('status', $request->status);
                } elseif ($request->status == 'inactive') {
                    //If status is inactive then display only trashed items
                    $sql->onlyTrashed();
                } elseif ($request->status == 'featured') {
                    //If status is featured then display only featured items
                    $sql->where('status', 'approved')->has('featured_items');
                }
            }

            // Feature Section Filtration
            if (!empty($request->featured_section_id) || !empty($request->featured_section_slug)) {
                $supportedFilters = config('interface_sections.default_filters', ['latest']);
                if (!is_array($supportedFilters) || $supportedFilters === []) {
                    $supportedFilters = ['latest'];
                }

                $filter = $request->input('featured_filter');
                if (!is_string($filter) || $filter === '') {
                    $filter = $supportedFilters[0] ?? 'latest';
                }

                $filter = strtolower($filter);
                if (!in_array($filter, $supportedFilters, true)) {
                    $filter = $supportedFilters[0] ?? 'latest';
                }

                $sql = match ($filter) {
                    'most_viewed' => $sql->reorder()->orderBy('clicks', 'DESC'),
                    'price_high_to_low', 'price-high-to-low' => $sql->reorder()->orderBy('price', 'DESC'),
                    'price_low_to_high', 'price-low-to-high' => $sql->reorder()->orderBy('price', 'ASC'),
                    default => $sql->reorder()->orderBy('created_at', 'DESC'),
                };
            }


            if (!empty($request->search)) {
                $sql->search($request->search);
            }

            if (!empty($request->custom_fields)) {
                $sql->whereHas('item_custom_field_values', function ($q) use ($request) {
                    $having = '';
                    foreach ($request->custom_fields as $id => $value) {
                        foreach (explode(",", $value) as $column_value) {
                            $having .= "WHEN custom_field_id = $id AND value LIKE \"%$column_value%\" THEN custom_field_id ";
                        }
                    }
                    $q->where(function ($q) use ($request) {
                        foreach ($request->custom_fields as $id => $value) {
                            $q->orWhere(function ($q) use ($id, $value) {
                                foreach (explode(",", $value) as $value) {
                                    $q->where('custom_field_id', $id)->where('value', 'LIKE', "%" . $value . "%");
                                }
                            });
                        }
                    })->groupBy('item_id')->having(DB::raw("COUNT(DISTINCT CASE $having END)"), '=', count($request->custom_fields));
                });
            }
            if ($isMyItemsRequest && Auth::check()) {
                if ($isDetailView) {
                    $sql->with(['item_offers' => function ($q) {
                        $q->where('buyer_id', Auth::user()->id);
                    }, 'user_reports'         => function ($q) {
                        $q->where('user_id', Auth::user()->id);
                    }]);
                }
                $sql->where(['user_id' => Auth::user()->id])->withTrashed();
            } elseif ($isDetailView && Auth::check()) {
                $sql->with(['item_offers' => function ($q) {
                    $q->where('buyer_id', Auth::user()->id);
                }, 'user_reports'         => function ($q) {
                    $q->where('user_id', Auth::user()->id);
                }]);
                $sql->where('status', 'approved')->has('user')->onlyNonBlockedUsers()->getNonExpiredItems();
            } else {
                //  Other users should only get approved items
                $sql->where('status', 'approved')->getNonExpiredItems();
            }
            
            $perPage = $this->resolvePerPage($request, 15, 60);
            if (!empty($request->id)) {
                /*
                 * Collection does not support first OR find method's result as of now. It's a part of R&D
                 * So currently using this shortcut method get() to fetch the first data
                 */
                $result = $sql->get();
                if (count($result) == 0) {
                    ResponseService::errorResponse("No item Found");
                }
            } else {
                $result = $sql->paginate($perPage);


            }


            //                // Add three regular items
            //                for ($i = 0; $i < 3 && $regularIndex < $regularItemCount; $i++) {
            //                    $items->push($regularItems[$regularIndex]);
            //                    $regularIndex++;
            //                }
            //
            //                // Add one featured item if available
            //                if ($featuredIndex < $featuredItemCount) {
            //                    $items->push($featuredItems[$featuredIndex]);
            //                    $featuredIndex++;
            //                }
            //            }
            // Return success response with the fetched items
            if ($isSummaryView) {
                $collection = $result instanceof AbstractPaginator ? $result->getCollection() : collect($result);

                $etagPayload = [];
                $latestUpdatedAt = null;

                if ($collection->isNotEmpty()) {
                    $latestUpdatedAt = $collection
                        ->map(static fn ($item) => $item->updated_at)
                        ->filter()
                        ->max();
                }

                $sortedFilters = $request->query();
                if (is_array($sortedFilters)) {
                    ksort($sortedFilters);
                } else {
                    $sortedFilters = [];
                }

                try {
                    $etagPayload = json_encode([
                        'view' => 'summary',
                        'filters' => $sortedFilters,
                        'items' => $collection->map(static function ($item) {
                            return [
                                'id' => $item->id,
                                'updated_at' => optional($item->updated_at)->toJSON(),
                            ];
                        })->values()->all(),
                    ], JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $etagPayload = json_encode([
                        'view' => 'summary',
                        'filters' => [],
                        'items' => $collection->map(static function ($item) {
                            return [
                                'id' => $item->id,
                                'updated_at' => optional($item->updated_at)->timestamp,
                            ];
                        })->values()->all(),
                    ]);
                }

                $etag = '"' . sha1((string) $etagPayload) . '"';

                $lastModifiedHeader = null; 

                if ($latestUpdatedAt instanceof Carbon) {
                    $lastModifiedHeader = $latestUpdatedAt->copy()->setTimezone('UTC')->toRfc7231String();
                }

                $ifNoneMatch = $request->headers->get('If-None-Match');
                $etagMatches = false;

                if ($ifNoneMatch !== null) {
                    $candidateEtags = array_map('trim', explode(',', $ifNoneMatch));
                    $etagMatches = in_array('*', $candidateEtags, true) || in_array($etag, $candidateEtags, true);
                }

                $ifModifiedSince = $request->headers->get('If-Modified-Since');
                $modifiedSinceMatches = false;

                if ($ifNoneMatch === null && $ifModifiedSince !== null && $lastModifiedHeader !== null) {
                    $modifiedSince = strtotime($ifModifiedSince);
                    $lastModifiedTime = strtotime($lastModifiedHeader);

                    if ($modifiedSince !== false && $lastModifiedTime !== false) {
                        $modifiedSinceMatches = $modifiedSince >= $lastModifiedTime;
                    }
                }

                if ($etagMatches || $modifiedSinceMatches) {
                    $response = response()->noContent(HttpResponse::HTTP_NOT_MODIFIED);
                    $response->setEtag($etag);

                    if ($lastModifiedHeader !== null) {
                        $response->headers->set('Last-Modified', $lastModifiedHeader);
                    }

                    return $response;
                }

                $summaryData = $this->formatSummaryResult($result);

                $payload = [
                    'error' => false,
                    'message' => trans('Item Fetched Successfully'),
                    'data' => $summaryData,
                    'code' => config('constants.RESPONSE_CODE.SUCCESS'),
                ];

                $response = response()->json($payload);
                $response->setEtag($etag);

                if ($lastModifiedHeader !== null) {
                    $response->headers->set('Last-Modified', $lastModifiedHeader);
                }

                return $response;
            }

            $payload = [
                'error' => false,
                'message' => trans('Item Fetched Successfully'),
                'data' => (new ItemCollection($result))->toArray($request),
                'code' => config('constants.RESPONSE_CODE.SUCCESS'),
            ];

            return response()->json($payload);
        
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getItem");
            ResponseService::errorResponse();
        }
    }
}
