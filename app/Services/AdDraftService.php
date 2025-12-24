<?php

namespace App\Services;

use App\Http\Controllers\Api\Sections\Items\GeoDisabledCategoryIds\GeoDisabledCategoryIdsTrait;
use App\Http\Controllers\Api\Sections\Items\GetDepartmentCategoryMap\GetDepartmentCategoryMapTrait;
use App\Http\Controllers\Api\Sections\Items\HasVerifiedIndividualPrivileges\HasVerifiedIndividualPrivilegesTrait;
use App\Http\Controllers\Api\Sections\Items\IsGeoDisabledCategory\IsGeoDisabledCategoryTrait;
use App\Http\Controllers\Api\Sections\Items\ParseCategoryIdList\ParseCategoryIdListTrait;
use App\Http\Controllers\Api\Sections\Items\ProductLinkRequiredCategoryIds\ProductLinkRequiredCategoryIdsTrait;
use App\Http\Controllers\Api\Sections\Items\ProductLinkRequiredSections\ProductLinkRequiredSectionsTrait;
use App\Http\Controllers\Api\Sections\Items\ResolveInterfaceSectionForCategory\ResolveInterfaceSectionForCategoryTrait;
use App\Http\Controllers\Api\Sections\Items\ResolveSectionByCategoryId\ResolveSectionByCategoryIdTrait;
use App\Http\Controllers\Api\Sections\Items\ShouldAutoApproveSection\ShouldAutoApproveSectionTrait;
use App\Http\Controllers\Api\Sections\Items\ShouldRequireProductLink\ShouldRequireProductLinkTrait;
use App\Http\Controllers\Api\Sections\Items\ShouldSkipReviewForVerifiedUser\ShouldSkipReviewForVerifiedUserTrait;
use App\Models\AdDraft;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\User;
use App\Models\UserPurchasedPackage;
use App\Policies\SectionDelegatePolicy;
use App\Services\HelperService;
use App\Services\InterfaceSectionService;
use App\Services\ResponseService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdDraftService
{
    use GeoDisabledCategoryIdsTrait;
    use GetDepartmentCategoryMapTrait;
    use HasVerifiedIndividualPrivilegesTrait;
    use IsGeoDisabledCategoryTrait;
    use ParseCategoryIdListTrait;
    use ProductLinkRequiredCategoryIdsTrait;
    use ProductLinkRequiredSectionsTrait;
    use ResolveInterfaceSectionForCategoryTrait;
    use ResolveSectionByCategoryIdTrait;
    use ShouldAutoApproveSectionTrait;
    use ShouldRequireProductLinkTrait;
    use ShouldSkipReviewForVerifiedUserTrait;

    private array $departmentCategoryMap = [];
    private ?array $geoDisabledCategoryCache = null;
    private ?array $productLinkRequiredCategoryCache = null;
    private ?array $productLinkRequiredSectionCache = null;
    private ?array $interfaceSectionCategoryCache = null;

    public function __construct(
        private readonly DepartmentReportService $departmentReportService,
    )
    {
    }

    /**
     * @param array{current_step: string, payload: array, step_payload?: array, temporary_media?: array} $attributes
     */
    public function saveDraft(?int $draftId, int $userId, array $attributes): AdDraft
    {
        $payload = Arr::get($attributes, 'payload', []);
        $stepPayload = Arr::get($attributes, 'step_payload', []);
        $temporaryMedia = Arr::get($attributes, 'temporary_media', []);

        $values = [
            'current_step' => $attributes['current_step'],
            'payload' => $payload,
            'step_payload' => $stepPayload,
            'temporary_media' => $temporaryMedia,
        ];

        return DB::transaction(function () use ($draftId, $userId, $values) {
            if ($draftId !== null) {
                $draft = AdDraft::query()
                    ->whereKey($draftId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (! $draft) {
                    throw (new ModelNotFoundException())->setModel(AdDraft::class, [$draftId]);
                }

                $draft->fill($values);
                $draft->save();

                return $draft->fresh();
            }

            return AdDraft::create(array_merge($values, [
                'user_id' => $userId,
            ]));
        });
    }

    public function getDraftForUser(int $draftId, int $userId): AdDraft
    {
        $draft = AdDraft::query()
            ->whereKey($draftId)
            ->where('user_id', $userId)
            ->first();

        if (! $draft) {
            throw (new ModelNotFoundException())->setModel(AdDraft::class, [$draftId]);
        }

        return $draft;
    }
    /**
     * @param array $payload
     * @return array{draft_id:int,status:string,submitted_at:string,item_id?:int,item_status?:string}
     */
    public function publish(?int $draftId, int $userId, array $payload): array
    {
        return DB::transaction(function () use ($draftId, $userId, $payload) {
            $existingStepPayload = [];
            $existingTemporaryMedia = [];

            if ($draftId !== null) {
                $draft = AdDraft::query()
                    ->whereKey($draftId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if (! $draft) {
                    throw (new ModelNotFoundException())->setModel(AdDraft::class, [$draftId]);
                }

                $existingStepPayload = $draft->step_payload ?? [];
                $existingTemporaryMedia = $draft->temporary_media ?? [];
            }

            $values = [
                'current_step' => 'review',
                'payload' => $payload,
                'step_payload' => $existingStepPayload,
                'temporary_media' => $existingTemporaryMedia,
            ];

            if (! isset($draft)) {
                $draft = AdDraft::create(array_merge($values, [
                    'user_id' => $userId,
                ]));
            } else {
                $draft->fill($values);
                $draft->save();
            }

            $publication = $this->resolveDraftPublication($draft);
            if ($publication !== null) {
                return $publication;
            }

            $item = $this->publishDraftItem($draft, $userId, $payload);

            return [
                'draft_id' => $draft->id,
                'status' => 'queued',
                'item_id' => $item->id,
                'item_status' => $item->status,
                'submitted_at' => now()->toIso8601String(),
            ];
        });
    }

    private function resolveDraftPublication(AdDraft $draft): ?array
    {
        $publication = Arr::get($draft->step_payload, 'publication', []);
        $itemId = Arr::get($publication, 'item_id');
        if (! $itemId) {
            return null;
        }

        $item = Item::query()->whereKey($itemId)->first();
        if (! $item) {
            return null;
        }

        return [
            'draft_id' => $draft->id,
            'status' => 'queued',
            'item_id' => $item->id,
            'item_status' => $item->status,
            'submitted_at' => now()->toIso8601String(),
        ];
    }

    private function publishDraftItem(AdDraft $draft, int $userId, array $payload): Item
    {
        $categoryId = $this->resolveCategoryId($payload);
        if ($categoryId === null) {
            ResponseService::validationErrors([
                'payload.sub_category_id' => ['Category is required.'],
            ]);
        }

        $section = $this->resolveSectionByCategoryId($categoryId);
        $authorization = Gate::inspect('section.publish', $section);
        if ($authorization->denied()) {
            $message = $authorization->message() ?? SectionDelegatePolicy::FORBIDDEN_MESSAGE;
            ResponseService::errorResponse($message, null, 403);
        }

        $user = User::query()->find($userId);
        if (! $user) {
            throw (new ModelNotFoundException())->setModel(User::class, [$userId]);
        }

        $userPackage = $this->resolveUserPackage($userId);
        if (! $userPackage) {
            ResponseService::errorResponse('No Active Package found for Item Creation');
        }

        if ($this->shouldRequireProductLink($categoryId)) {
            $productLink = trim((string) Arr::get($payload, 'product_link', ''));
            if ($productLink === '') {
                ResponseService::validationErrors([
                    'payload.product_link' => ['Product link is required for this category.'],
                ]);
            }
        }

        $status = $this->resolveInitialItemStatus($user, $section);

        $name = trim((string) Arr::get($payload, 'title', ''));
        $description = trim((string) Arr::get($payload, 'description', ''));
        $price = (float) Arr::get($payload, 'price', 0);
        $currency = strtoupper((string) Arr::get($payload, 'currency', 'YER'));
        if ($currency === '') {
            $currency = 'YER';
        }

        $slug = HelperService::generateRandomSlug();
        $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug);
        if ($uniqueSlug === '') {
            $uniqueSlug = Str::lower(Str::random(12));
        }

        $location = Arr::get($payload, 'location', []);
        $latitude = Arr::get($location, 'latitude', Arr::get($payload, 'latitude'));
        $longitude = Arr::get($location, 'longitude', Arr::get($payload, 'longitude'));
        $address = Arr::get($location, 'address', Arr::get($payload, 'address'));

        $data = [
            'category_id' => $categoryId,
            'name' => Str::upper($name),
            'price' => $price,
            'description' => $description,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'contact' => Arr::get($payload, 'contact'),
            'show_only_to_premium' => filter_var(
                Arr::get($payload, 'show_only_to_premium', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'video_link' => $this->resolveVideoLink($payload, $draft->temporary_media ?? []),
            'country' => Arr::get($location, 'country', Arr::get($payload, 'country')),
            'state' => Arr::get($location, 'state', Arr::get($payload, 'state')),
            'city' => Arr::get($location, 'city', Arr::get($payload, 'city')),
            'area_id' => Arr::get($location, 'area_id', Arr::get($payload, 'area_id')),
            'all_category_ids' => $this->resolveAllCategoryIds($categoryId),
            'slug' => $uniqueSlug,
            'status' => $status,
            'user_id' => $userId,
            'expiry_date' => $userPackage->end_date,
            'currency' => $currency,
            'product_link' => Arr::get($payload, 'product_link'),
            'review_link' => Arr::get($payload, 'review_link'),
        ];

        $explicitInterfaceType = InterfaceSectionService::canonicalSectionTypeOrNull(
            Arr::get($payload, 'interface_type')
        );
        $resolvedInterfaceType = $explicitInterfaceType
            ?? $this->resolveInterfaceSectionForCategory($categoryId)
            ?? InterfaceSectionService::canonicalSectionTypeOrNull($section);

        if ($resolvedInterfaceType !== null) {
            $data['interface_type'] = $resolvedInterfaceType;
        }

        $images = $this->normalizeMediaImages($payload, $draft->temporary_media ?? []);
        $mainImage = $this->resolveMainImage($images);
        if ($mainImage !== null) {
            $data['image'] = $mainImage['path'];
            if (! empty($mainImage['thumbnail'])) {
                $data['thumbnail_url'] = $mainImage['thumbnail'];
            }
            if (! empty($mainImage['detail'])) {
                $data['detail_image_url'] = $mainImage['detail'];
            }
        }

        $item = Item::create($data);

        $this->storeGalleryImages($item->id, $images, $mainImage);
        $this->storeCustomFields($item->id, $categoryId, Arr::get($payload, 'custom_fields'));

        ++$userPackage->used_limit;
        $userPackage->save();

        $draft->step_payload = $this->mergePublicationMeta($draft->step_payload, [
            'item_id' => $item->id,
            'status' => $item->status,
            'published_at' => now()->toIso8601String(),
        ]);
        $draft->temporary_media = [];
        $draft->save();

        return $item->fresh();
    }

    private function resolveCategoryId(array $payload): ?int
    {
        $candidate = Arr::get($payload, 'sub_category_id') ?? Arr::get($payload, 'main_category_id');
        if (is_numeric($candidate)) {
            $value = (int) $candidate;
            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function resolveInitialItemStatus(User $user, ?string $section): string
    {
        if ($this->shouldAutoApproveSection($section) || $this->shouldSkipReviewForVerifiedUser($user)) {
            return 'approved';
        }

        return 'review';
    }

    private function resolveUserPackage(int $userId): ?UserPurchasedPackage
    {
        return UserPurchasedPackage::query()
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereDate('end_date', '>=', now()->toDateString())
                    ->orWhereNull('end_date');
            })
            ->where(function ($query) {
                $query->whereColumn('used_limit', '<', 'total_limit')
                    ->orWhereNull('total_limit');
            })
            ->whereHas('package', static function ($query) {
                $query->where('type', 'item_listing');
            })
            ->orderBy('end_date', 'asc')
            ->lockForUpdate()
            ->first();
    }

    private function resolveAllCategoryIds(int $categoryId): ?string
    {
        $category = Category::query()->find($categoryId);
        if (! $category) {
            return (string) $categoryId;
        }

        $ids = $category->ancestorsAndSelf()->pluck('id');
        if ($ids->isEmpty()) {
            return (string) $categoryId;
        }

        return $ids->implode(',');
    }

    private function normalizeMediaImages(array $payload, array $temporaryMedia): array
    {
        $media = Arr::get($payload, 'media', []);
        $images = Arr::get($media, 'images', []);

        if (empty($images)) {
            $images = Arr::get($temporaryMedia, 'pending', []);
        }

        $normalized = [];

        foreach (Arr::wrap($images) as $entry) {
            $normalizedEntry = $this->normalizeMediaEntry($entry);
            if ($normalizedEntry === null) {
                continue;
            }
            $normalized[] = $normalizedEntry;
        }

        return $normalized;
    }

    private function normalizeMediaEntry(mixed $entry): ?array
    {
        if (is_string($entry)) {
            $path = $this->normalizeStoragePath($entry);
            return $path ? ['path' => $path] : null;
        }

        if (! is_array($entry)) {
            return null;
        }

        $type = strtolower((string) Arr::get($entry, 'type', ''));
        if ($type !== '' && ! str_contains($type, 'image')) {
            return null;
        }

        $path = $this->normalizeStoragePath(
            $this->extractEntryValue($entry, [
                'path',
                'image',
                'url',
                'file',
                'file_path',
                'storage_path',
                'relative_path',
                'original',
                'original_path',
                'file.path',
                'file.url',
            ])
        );

        if (! $path) {
            return null;
        }

        $thumbnail = $this->normalizeStoragePath(
            $this->extractEntryValue($entry, [
                'thumbnail',
                'thumbnail_url',
                'thumbnail_path',
                'thumb',
                'thumb_url',
                'thumb_path',
            ])
        );

        $detail = $this->normalizeStoragePath(
            $this->extractEntryValue($entry, [
                'detail',
                'detail_url',
                'detail_path',
                'detail_image',
                'detail_image_url',
                'detail_image_path',
            ])
        );

        $isMain = (bool) Arr::get($entry, 'is_main', false);
        if (! $isMain) {
            $isMain = (bool) Arr::get($entry, 'isMain', false);
        }
        if (! $isMain) {
            $isMain = (bool) Arr::get($entry, 'main', false);
        }
        if (! $isMain) {
            $isMain = (bool) Arr::get($entry, 'is_primary', false);
        }

        return [
            'path' => $path,
            'thumbnail' => $thumbnail,
            'detail' => $detail,
            'is_main' => $isMain,
        ];
    }

    private function extractEntryValue(array $entry, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($entry, $key);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveMainImage(array $images): ?array
    {
        foreach ($images as $image) {
            if (! empty($image['is_main'])) {
                return $image;
            }
        }

        return $images[0] ?? null;
    }

    private function storeGalleryImages(int $itemId, array $images, ?array $mainImage): void
    {
        if ($images === []) {
            return;
        }

        $rows = [];
        $timestamp = now();
        $mainPath = $mainImage['path'] ?? null;

        foreach ($images as $image) {
            if ($mainPath !== null && $image['path'] === $mainPath) {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'image' => $image['path'],
                'thumbnail_url' => $image['thumbnail'] ?? null,
                'detail_image_url' => $image['detail'] ?? null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows !== []) {
            ItemImages::query()->insert($rows);
        }
    }

    private function storeCustomFields(int $itemId, int $categoryId, mixed $customFields): void
    {
        if ($customFields === null) {
            return;
        }

        $normalized = $this->normalizeCustomFields($customFields);
        if ($normalized === []) {
            return;
        }

        $allowedIds = $this->resolveAllowedCustomFieldIds($categoryId);
        if ($allowedIds->isEmpty()) {
            return;
        }

        $rows = [];
        $timestamp = now();

        foreach ($normalized as $key => $value) {
            $customFieldId = is_numeric($key) ? (int) $key : null;

            if ($customFieldId === null || ! $allowedIds->containsStrict($customFieldId)) {
                ResponseService::validationErrors([
                    "custom_fields.$key" => ['The selected custom field is invalid for this category.'],
                ]);
            }

            try {
                $encodedValue = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                ResponseService::validationErrors([
                    "custom_fields.$key" => ['Unable to process the provided custom field value.'],
                ]);
            }

            $rows[] = [
                'item_id' => $itemId,
                'custom_field_id' => $customFieldId,
                'value' => $encodedValue,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows !== []) {
            ItemCustomFieldValue::query()->insert($rows);
        }
    }

    private function normalizeCustomFields(mixed $customFields): array
    {
        if (is_string($customFields)) {
            $trimmed = trim($customFields);
            if ($trimmed === '') {
                return [];
            }
            try {
                $customFields = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
        }

        if (! is_array($customFields)) {
            return [];
        }

        if (Arr::isAssoc($customFields)) {
            return $customFields;
        }

        $normalized = [];
        foreach ($customFields as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $id = Arr::get($entry, 'id') ?? Arr::get($entry, 'custom_field_id');
            if ($id === null) {
                continue;
            }
            $normalized[$id] = Arr::get($entry, 'value', Arr::get($entry, 'selected'));
        }

        return $normalized;
    }

    private function resolveAllowedCustomFieldIds(int $categoryId): Collection
    {
        $category = Category::query()
            ->with(['custom_fields' => static function ($query) {
                $query->select('id', 'category_id', 'custom_field_id');
            }])
            ->find($categoryId);

        if (! $category) {
            return collect();
        }

        return $category->custom_fields
            ->pluck('custom_field_id')
            ->filter(static fn ($id) => $id !== null)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function resolveVideoLink(array $payload, array $temporaryMedia): ?string
    {
        $media = Arr::get($payload, 'media', []);
        $links = Arr::wrap(Arr::get($media, 'video_links', []));
        foreach ($links as $link) {
            $resolved = $this->normalizeVideoLink($link);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $tempLinks = Arr::wrap(Arr::get($temporaryMedia, 'video_links', []));
        foreach ($tempLinks as $link) {
            $resolved = $this->normalizeVideoLink($link);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $videos = Arr::wrap(Arr::get($media, 'videos', []));
        foreach ($videos as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $candidate = $this->extractEntryValue($entry, ['url', 'path', 'file', 'file_path', 'file.url', 'file.path']);
            $resolved = $this->normalizeVideoLink($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function normalizeVideoLink(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $path = $this->normalizeStoragePath($trimmed);
        if ($path === null) {
            return null;
        }

        return url(Storage::url($path));
    }

    private function normalizeStoragePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || str_starts_with($trimmed, 'data:')) {
            return null;
        }

        $trimmed = str_replace('\\', '/', $trimmed);
        $parsed = parse_url($trimmed);
        $path = is_array($parsed) && isset($parsed['path']) ? $parsed['path'] : $trimmed;
        $path = ltrim((string) $path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        } elseif (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        $path = trim($path);
        return $path === '' ? null : $path;
    }

    private function mergePublicationMeta(array $current, array $meta): array
    {
        $currentPublication = Arr::get($current, 'publication', []);
        if (! is_array($currentPublication)) {
            $currentPublication = [];
        }

        $current['publication'] = array_merge($currentPublication, $meta);

        return $current;
    }
}
