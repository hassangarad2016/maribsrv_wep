<?php

namespace App\Http\Controllers\Api\Sections\Sections\getFeaturedSections;

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


trait GetFeaturedSectionsAction
{
    public function getFeaturedSections(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'interface_type' => ['nullable', 'string', 'max:191'],
                'section_type'   => ['nullable', 'string', 'max:191'],
                'slug'           => ['nullable', 'string', 'max:191'],
                'limit'          => ['nullable', 'integer', 'min:1', 'max:50'],
                'filters'        => ['nullable'],
                'filters.*'      => ['nullable', 'string', 'max:191'],
                'page'           => ['nullable', 'integer', 'min:1'],
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $requestContext = [
                'interface_type' => $request->input('interface_type'),
                'section_type' => $request->input('section_type'),
                'slug' => $request->input('slug'),
                'limit' => $request->input('limit'),
                'page' => $request->input('page'),
                'filters' => $request->input('filters'),
            ];
            $requestUser = $request->user() ?? Auth::user();

            try {
                Log::info('API Controller -> getFeaturedSections request', [
                    'user_id' => $requestUser?->getAuthIdentifier(),
                    'context' => $requestContext,
                ]);
                $defaultFilters = config('interface_sections.default_filters', ['latest']);
                if (! is_array($defaultFilters) || $defaultFilters === []) {
                    $defaultFilters = ['latest'];
                }
                $filterPool = array_values(array_unique(array_merge($defaultFilters, [
                    'featured',
                    'latest',
                    'most_viewed',
                    'price_range',
                    'premium',
                    'highest_price',
                    'lowest_price',
                ])));

                $filtersInput = $request->input('filters');
                if (is_string($filtersInput)) {
                    $filtersInput = array_map('trim', explode(',', $filtersInput));
                }

                $filters = [];
                foreach (Arr::wrap($filtersInput) as $filterValue) {
                    if (! is_string($filterValue)) {
                        continue;
                    }
                    $normalized = (string) Str::of($filterValue)
                        ->lower()
                        ->replaceMatches('/[\s]+/u', '_')
                        ->replace('-', '_')
                        ->trim('_');

                    if ($normalized !== '' && in_array($normalized, $filterPool, true)) {
                        $filters[] = $normalized;
                    }
                }

                if ($filters === []) {
                    $filters = $defaultFilters;
                }

                $filters = array_values(array_unique($filters));

                $page = $request->integer('page') ?? 1;
                $page = max(1, $page);

                $minItemsFirstPage = 6;
                $minItemsNextPage = 4;
                $configLimit = (int) config('interface_sections.section_item_limit', 12);

                $limitDefault = $page === 1
                    ? max($minItemsFirstPage, $configLimit)
                    : max($minItemsNextPage, min($configLimit, 8));

                $minRequired = $page === 1 ? $minItemsFirstPage : $minItemsNextPage;
                $limit = $request->integer('limit');
                $limit = $limit !== null ? max($minRequired, min($limit, 50)) : $limitDefault;
                $offset = ($page - 1) * $limit;

                $sectionTypeInput = $request->input('section_type') ?? $request->input('interface_type');
                $sectionType = InterfaceSectionService::normalizeSectionType($sectionTypeInput);
                if ($sectionType === null || $sectionType === '') {
                    $sectionType = InterfaceSectionService::defaultSectionType() ?? 'homepage';
                }

                $allowedSectionTypes = InterfaceSectionService::allowedSectionTypes();
                if (! in_array($sectionType, $allowedSectionTypes, true)) {
                    $sectionType = InterfaceSectionService::defaultSectionType() ?? 'homepage';
                }

                $categoryIds = InterfaceSectionService::categoryIdsForSection($sectionType);
                if (is_array($categoryIds) && $categoryIds !== []) {
                    $categoryIds = $this->expandCategoryIdsWithDescendants(array_map('intval', $categoryIds));
                } else {
                    $categoryIds = null;
                }

                $rootIdentifiers = InterfaceSectionService::rootIdentifiers();
                $rootIdentifier = $rootIdentifiers[$sectionType] ?? null;
                $interfaceVariants = InterfaceSectionService::sectionTypeVariants($sectionType);

                $rootId = $request->integer('root_id') ?? $request->integer('root_category_id');

                $relations = [
                    'user:id,name,email,mobile,profile,country_code,show_personal_details',
                    'category:id,name,image',
                    'gallery_images:id,image,item_id,thumbnail_url,detail_image_url',
                    'featured_items',
                    'favourites',
                    'item_custom_field_values.custom_field',
                    'area:id,name',
                ];

                $titleMap = [
                    'featured'      => __('Featured Items'),
                    'premium'       => __('Featured Items'),
                    'latest'        => __('Latest Listings'),
                    'most_viewed'   => __('Popular Items'),
                    'price_range'   => __('Budget Friendly'),
                    'highest_price' => __('Highest Price'),
                    'lowest_price'  => __('Lowest Price'),
                ];

                $configs = FeaturedAdsConfig::query()
                    ->where('enabled', true)
                    ->when($sectionType, function ($query) use ($sectionType) {
                        $query->where(function ($inner) use ($sectionType) {
                            $inner->whereNull('interface_type')
                                ->orWhere('interface_type', $sectionType);
                        });
                    })
                    ->when($rootIdentifier, function ($query) use ($rootIdentifier) {
                        $query->where(function ($inner) use ($rootIdentifier) {
                            $inner->whereNull('root_identifier')
                                ->orWhere('root_identifier', $rootIdentifier);
                        });
                    })
                    ->when($rootId, function ($query) use ($rootId) {
                        $query->where(function ($inner) use ($rootId) {
                            $inner->whereNull('root_category_id')
                                ->orWhere('root_category_id', $rootId);
                        });
                    })
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get();

                if ($configs->isEmpty()) {
                    // لا توجد إعدادات مخصصة: اعرض أحدث الإعلانات بدون تقييد القسم/الفئة
                    $applyInterfaceFilter = false;
                    $categoryIds = null;
                    $baseQuery = Item::query()
                        ->approved()
                        ->with($relations)
                        ->withCount('favourites')
                        ->withCount('featured_items');

                    $items = (clone $baseQuery)
                        ->orderByDesc('items.created_at')
                        ->skip($offset)
                        ->limit($limit)
                        ->get();

                    $sections = [];

                    if ($items->isNotEmpty()) {
                        $sectionData = array_values((new ItemCollection($items))->toArray($request));
                        $sections[] = [
                            'id' => null,
                            'title' => $titleMap['latest'] ?? 'Latest Listings',
                            'style' => 'list',
                            'section_type' => $sectionType,
                            'filter' => 'latest',
                            'slug' => $request->input('slug')
                                ?? Str::slug(($sectionType ?: 'all') . '-latest'),
                            'sequence' => 1,
                            'root_identifier' => $rootIdentifier,
                            'total_data' => count($sectionData),
                            'min_price' => $items->min('price'),
                            'max_price' => $items->max('price'),
                            'has_more' => $items->count() === $limit,
                            'section_data' => $sectionData,
                        ];
                    }

                    Log::info('API Controller -> getFeaturedSections fallback response', [
                        'user_id' => $requestUser?->getAuthIdentifier(),
                        'interface_type' => $sectionType,
                        'sections_count' => count($sections),
                        'sections' => array_map(static function ($section) {
                            return [
                                'title' => $section['title'] ?? null,
                                'filter' => $section['filter'] ?? null,
                                'items' => $section['total_data'] ?? null,
                            ];
                        }, $sections),
                    ]);

                    ResponseService::successResponse(
                        'Featured sections fetched successfully.',
                        [
                            'interface_type' => $sectionType,
                            'filters' => $filters,
                            'page' => $page,
                            'per_page' => $limit,
                            'sections' => array_values($sections),
                        ]
                    );

                    return;
                }

                $allSections = [];
                $sequenceOffset = 0;

                foreach ($configs as $config) {
                    $sectionTypeForConfig = $sectionType;
                    $interfaceVariantsForConfig = $interfaceVariants;
                    $rootIdentifierForConfig = $rootIdentifier;
                    $categoryIdsForConfig = $categoryIds;
                    $filtersForConfig = $filters;
                    $rootIdForConfig = $rootId;

                    if (is_string($config->interface_type) && $config->interface_type !== '') {
                        $sectionTypeForConfig = InterfaceSectionService::normalizeSectionType($config->interface_type);
                        $interfaceVariantsForConfig = InterfaceSectionService::sectionTypeVariants($sectionTypeForConfig);
                    }

                    if (is_string($config->root_identifier) && $config->root_identifier !== '') {
                        $rootIdentifierForConfig = $config->root_identifier;
                    }

                    $categoryIdsOverride = null;
                    if (! empty($config->root_category_id)) {
                        $categoryIdsOverride = $this->collectCategoryTreeIds((int) $config->root_category_id);
                    } elseif (! empty($rootIdForConfig)) {
                        $categoryIdsOverride = $this->collectCategoryTreeIds((int) $rootIdForConfig);
                    }

                    if ($categoryIdsOverride !== null) {
                        $categoryIdsForConfig = $categoryIdsOverride;
                    }

                    if ($categoryIdsForConfig === []) {
                        $categoryIdsForConfig = null;
                    }

                    $preferredOrder = $config->order_mode;
                    if (is_string($preferredOrder)) {
                        $normalizedOrder = (string) Str::of($preferredOrder)
                            ->lower()
                            ->replaceMatches('/[\s]+/u', '_')
                            ->replace('-', '_')
                            ->trim('_');

                        if ($normalizedOrder !== '' && in_array($normalizedOrder, $filterPool, true)) {
                            $filtersForConfig = [$normalizedOrder];
                        }
                    }

                    $applyInterfaceFilterConfig = $sectionTypeForConfig !== null && $sectionTypeForConfig !== 'all';

                    $makeBaseQuery = function (bool $withInterfaceFilter = true) use ($categoryIdsForConfig, $relations, $sectionTypeForConfig, $interfaceVariantsForConfig) {
                        $query = Item::query()
                            ->approved()
                            ->with($relations)
                            ->withCount('favourites')
                            ->withCount('featured_items');

                        if ($categoryIdsForConfig !== null) {
                            $query->whereIn('category_id', $categoryIdsForConfig);
                        }

                    if ($withInterfaceFilter && $sectionTypeForConfig !== null && $sectionTypeForConfig !== 'all') {
                        $query->where(function ($inner) use ($interfaceVariantsForConfig, $categoryIdsForConfig) {
                            $inner->whereIn('interface_type', $interfaceVariantsForConfig);
                            if ($categoryIdsForConfig !== null) {
                                $inner->orWhere(function ($legacy) use ($categoryIdsForConfig) {
                                    $legacy->whereNull('interface_type')
                                        ->whereIn('category_id', $categoryIdsForConfig);
                                });
                            } else {
                                $inner->orWhereNull('interface_type');
                            }
                        });
                    }

                    return $query;
                };

                    $baseQuery = $makeBaseQuery($applyInterfaceFilterConfig);
                    $sectionsForConfig = [];

                    foreach ($filtersForConfig as $index => $filter) {
                        $query = clone $baseQuery;

                        switch ($filter) {
                            case 'featured':
                            case 'premium':
                                $query->whereHas('featured_items')->orderByDesc('items.updated_at');
                                break;
                            case 'most_viewed':
                                $query->orderByDesc('items.clicks');
                                break;
                            case 'highest_price':
                                $query->orderByDesc('items.price');
                                break;
                            case 'price_range':
                            case 'lowest_price':
                                $query->orderBy('items.price');
                                break;
                            case 'latest':
                            default:
                                $query->orderByDesc('items.created_at');
                                break;
                        }

                        $items = $query->skip($offset)->limit($limit)->get();

                        if ($items->isEmpty()) {
                            continue;
                        }

                        $sectionData = array_values((new ItemCollection($items))->toArray($request));

                        $sectionsForConfig[] = [
                            'id' => null,
                            'title' => $config->title ?? $titleMap[$filter] ?? Str::title(str_replace('_', ' ', $filter)),
                            'style' => $config->style_key ?? 'list',
                            'section_type' => $sectionTypeForConfig,
                            'filter' => $filter,
                            'slug' => $config->slug
                                ?? $request->input('slug')
                                ?? Str::slug($sectionTypeForConfig . '-' . $filter),
                            'sequence' => $sequenceOffset + $index + 1,
                            'root_identifier' => $config->root_identifier ?? $rootIdentifierForConfig,
                            'total_data' => count($sectionData),
                            'min_price' => $items->min('price'),
                            'max_price' => $items->max('price'),
                            'has_more' => $items->count() === $limit,
                            'section_data' => $sectionData,
                        ];
                    }

                    if ($sectionsForConfig === [] && $categoryIdsForConfig !== null && $applyInterfaceFilterConfig) {
                        // If interface filtering produced no data, fall back to category-only so the banner still renders inside the same section tree.
                        $baseQuery = $makeBaseQuery(false);
                        foreach ($filtersForConfig as $index => $filter) {
                            $query = clone $baseQuery;

                            switch ($filter) {
                                case 'featured':
                                case 'premium':
                                    $query->whereHas('featured_items')->orderByDesc('items.updated_at');
                                    break;
                                case 'most_viewed':
                                    $query->orderByDesc('items.clicks');
                                    break;
                                case 'highest_price':
                                    $query->orderByDesc('items.price');
                                    break;
                                case 'price_range':
                                case 'lowest_price':
                                    $query->orderBy('items.price');
                                    break;
                                case 'latest':
                                default:
                                    $query->orderByDesc('items.created_at');
                                    break;
                            }

                            $items = $query->skip($offset)->limit($limit)->get();
                            if ($items->isEmpty()) {
                                continue;
                            }

                            $sectionData = array_values((new ItemCollection($items))->toArray($request));

                            $sectionsForConfig[] = [
                                'id' => null,
                                'title' => $config->title ?? $titleMap[$filter] ?? Str::title(str_replace('_', ' ', $filter)),
                                'style' => $config->style_key ?? 'list',
                                'section_type' => $sectionTypeForConfig,
                                'filter' => $filter,
                                'slug' => $config->slug
                                    ?? $request->input('slug')
                                    ?? Str::slug($sectionTypeForConfig . '-' . $filter),
                                'sequence' => $sequenceOffset + $index + 1,
                                'root_identifier' => $config->root_identifier ?? $rootIdentifierForConfig,
                                'total_data' => count($sectionData),
                                'min_price' => $items->min('price'),
                                'max_price' => $items->max('price'),
                                'has_more' => $items->count() === $limit,
                                'section_data' => $sectionData,
                            ];
                        }
                    }

                    if ($sectionsForConfig === [] && $page === 1) {
                        $baseQuery ??= $makeBaseQuery($applyInterfaceFilterConfig);

                        $fallbackItems = (clone $baseQuery)
                            ->orderByDesc('items.created_at')
                            ->skip($offset)
                            ->limit($limit)
                            ->get();

                        if ($fallbackItems->isNotEmpty()) {
                            $sectionData = array_values((new ItemCollection($fallbackItems))->toArray($request));
                            $sectionsForConfig[] = [
                                'id' => null,
                                'title' => $config->title ?? $titleMap['latest'] ?? 'Latest Listings',
                                'style' => $config->style_key ?? 'list',
                                'section_type' => $sectionTypeForConfig,
                                'filter' => 'latest',
                                'slug' => $config->slug
                                    ?? $request->input('slug')
                                    ?? Str::slug($sectionTypeForConfig . '-latest'),
                                'sequence' => $sequenceOffset + 1,
                                'root_identifier' => $config->root_identifier ?? $rootIdentifierForConfig,
                                'total_data' => count($sectionData),
                                'min_price' => $fallbackItems->min('price'),
                                'max_price' => $fallbackItems->max('price'),
                                'has_more' => $fallbackItems->count() === $limit,
                                'section_data' => $sectionData,
                            ];
                        }
                    }

                    if ($sectionsForConfig === []) {
                        $singleItemQuery = Item::query()
                            ->approved()
                            ->with($relations)
                            ->withCount('favourites')
                            ->withCount('featured_items');

                        if ($categoryIdsForConfig !== null) {
                            $singleItemQuery->whereIn('category_id', $categoryIdsForConfig);
                        } elseif ($applyInterfaceFilterConfig) {
                            $singleItemQuery->whereIn('interface_type', $interfaceVariantsForConfig);
                        }

                        $singleItem = $singleItemQuery
                            ->orderByDesc('items.created_at')
                            ->first();

                        $sectionData = $singleItem
                            ? array_values((new ItemCollection([$singleItem]))->toArray($request))
                            : [];

                        $sectionsForConfig[] = [
                            'id' => null,
                            'title' => $config->title ?? $titleMap['latest'] ?? 'Latest Listings',
                            'style' => $config->style_key ?? 'list',
                            'section_type' => $sectionTypeForConfig,
                            'filter' => $config->order_mode ?? 'latest',
                            'slug' => $config->slug
                                ?? $request->input('slug')
                                ?? Str::slug($sectionTypeForConfig . '-latest'),
                            'sequence' => $sequenceOffset + 1,
                            'root_identifier' => $config->root_identifier ?? $rootIdentifierForConfig,
                            'total_data' => count($sectionData),
                            'min_price' => $singleItem?->price,
                            'max_price' => $singleItem?->price,
                            'has_more' => false,
                            'section_data' => $sectionData,
                        ];
                    }

                    $sequenceOffset += count($sectionsForConfig);
                    $allSections = array_merge($allSections, $sectionsForConfig);
                }

                // إذا لم تُرجع أيّ الأقسام من الإعدادات، قدّم قسمًا افتراضيًا بأحدث الإعلانات بدون أي قيود
                if ($allSections === []) {
                    $fallbackItems = Item::query()
                        ->approved()
                        ->with($relations)
                        ->withCount('favourites')
                        ->withCount('featured_items')
                        ->orderByDesc('items.created_at')
                        ->skip($offset)
                        ->limit($limit)
                        ->get();

                    if ($fallbackItems->isNotEmpty()) {
                        $sectionData = array_values((new ItemCollection($fallbackItems))->toArray($request));
                        $allSections[] = [
                            'id' => null,
                            'title' => $titleMap['latest'] ?? 'Latest Listings',
                            'style' => 'list',
                            'section_type' => 'all',
                            'filter' => 'latest',
                            'slug' => $request->input('slug') ?? Str::slug('all-latest'),
                            'sequence' => 1,
                            'root_identifier' => null,
                            'total_data' => count($sectionData),
                            'min_price' => $fallbackItems->min('price'),
                            'max_price' => $fallbackItems->max('price'),
                            'has_more' => $fallbackItems->count() === $limit,
                            'section_data' => $sectionData,
                        ];
                    }
                }

                $hasNonFeaturedSections = collect($allSections)->contains(static function (array $section) {
                    $filter = $section['filter'] ?? null;
                    if (! is_string($filter) || $filter === '') {
                        return true;
                    }

                    return strtolower($filter) !== 'featured';
                });

                if (! $hasNonFeaturedSections) {
                    $fallbackQuery = Item::query()
                        ->approved()
                        ->with($relations)
                        ->withCount('favourites')
                        ->withCount('featured_items');

                    if ($categoryIds !== null) {
                        $fallbackQuery->whereIn('category_id', $categoryIds);
                    }

                    if ($sectionType !== null && $sectionType !== 'all') {
                        $fallbackQuery->where(static function ($inner) use ($interfaceVariants, $categoryIds) {
                            $inner->whereIn('interface_type', $interfaceVariants);
                            if ($categoryIds !== null) {
                                $inner->orWhere(static function ($legacy) use ($categoryIds) {
                                    $legacy->whereNull('interface_type')
                                        ->whereIn('category_id', $categoryIds);
                                });
                            } else {
                                $inner->orWhereNull('interface_type');
                            }
                        });
                    }

                    $fallbackItems = $fallbackQuery
                        ->orderByDesc('items.created_at')
                        ->skip($offset)
                        ->limit($limit)
                        ->get();

                    if ($fallbackItems->isEmpty()) {
                        $fallbackItems = Item::query()
                            ->approved()
                            ->with($relations)
                            ->withCount('favourites')
                            ->withCount('featured_items')
                            ->orderByDesc('items.created_at')
                            ->skip($offset)
                            ->limit($limit)
                            ->get();
                    }

                    if ($fallbackItems->isNotEmpty()) {
                        $fallbackSectionData = array_values((new ItemCollection($fallbackItems))->toArray($request));
                        $allSections[] = [
                            'id' => null,
                            'title' => $titleMap['latest'] ?? 'Latest Listings',
                            'style' => 'list',
                            'section_type' => $sectionType,
                            'filter' => 'latest',
                            'slug' => $request->input('slug')
                                ?? Str::slug(($sectionType ?: 'all') . '-latest'),
                            'sequence' => count($allSections) + 1,
                            'root_identifier' => $rootIdentifier,
                            'total_data' => count($fallbackSectionData),
                            'min_price' => $fallbackItems->min('price'),
                            'max_price' => $fallbackItems->max('price'),
                            'has_more' => $fallbackItems->count() === $limit,
                            'section_data' => $fallbackSectionData,
                        ];
                    }

                }

                Log::info('featured_sections.response', [
                    'requested_interface' => $requestContext['interface_type'],
                    'resolved_section_type' => $sectionType,
                    'filters' => $filters,
                    'sections_count' => count($allSections),
                    'section_filters' => array_map(static fn ($section) => $section['filter'] ?? null, $allSections),
                    'page' => $page,
                ]);

                Log::info('API Controller -> getFeaturedSections response', [
                    'user_id' => $requestUser?->getAuthIdentifier(),
                    'interface_type' => $sectionType,
                    'sections_count' => count($allSections),
                    'sample_sections' => array_map(static function ($section) {
                        return [
                            'title' => $section['title'] ?? null,
                            'filter' => $section['filter'] ?? null,
                            'items' => $section['total_data'] ?? null,
                            'has_more' => $section['has_more'] ?? null,
                        ];
                    }, array_slice($allSections, 0, 5)),
                ]);

                ResponseService::successResponse(
                    'Featured sections fetched successfully.',
                    [
                        'interface_type' => $sectionType,
                        'filters' => $filters,
                        'page' => $page,
                        'per_page' => $limit,
                        'sections' => array_values($allSections),
                    ]
                );

                return;
            } catch (Throwable $th) {
                Log::error('API Controller -> getFeaturedSections failed', [
                    'exception' => $th,
                    'user_id' => $requestUser?->getAuthIdentifier(),
                    'context' => $requestContext,
                ]);

                ResponseService::errorResponse('Unable to load featured sections.', null, 500);
            }
        }
}
