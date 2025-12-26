<?php

namespace App\Services;
use App\Models\Category;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServiceAuthorizationService
{
    public const ADMIN_ROLES = ['Super Admin', 'Admin'];

    public function userHasFullAccess(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES) || $user->can('service-managers-manage');
    }

    /**
     * @param  Service|int|null  $service
     */
    public function userCanManageService(User $user, Service|int|null $service): bool
    {
        if ($this->userHasFullAccess($user)) {
            return true;
        }

        if ($service === null) {
            return false;
        }

        $serviceModel = $service instanceof Service ? $service : Service::find($service);

        if (! $serviceModel) {
            return false;
        }

        if ($this->userManagesService($user, $serviceModel)) {
            return true;
        }

        if ($this->userOwnsService($user, $serviceModel)) {
            return true;
        }


        return $this->userCanManageCategory($user, $serviceModel->category_id);
    }

    public function ensureUserCanManageService(User $user, Service $service): void
    {
        if (!$this->userCanManageService($user, $service)) {
            abort(403, __('You are not authorized to manage this service.'));
        }
    }


    public function userCanManageCategory(User $user, Category|int|null $category): bool
    {
        if ($this->userHasFullAccess($user)) {
            return true;
        }

        if ($category === null) {
            return false;
        }

        $categoryModel = $category instanceof Category ? $category : Category::find($category);

        if (!$categoryModel) {
            return false;
        }

        return in_array((int) $categoryModel->id, $this->getManagedCategoryIds($user), true);
    }

    public function ensureUserCanManageCategory(User $user, Category $category): void
    {
        if (!$this->userCanManageCategory($user, $category)) {
            abort(403, __('You are not authorized to manage this category.'));
        }
    }

    public function restrictServiceQuery(Builder $query, User $user, string $column = 'services.category_id'): Builder

    {
        if ($this->userHasFullAccess($user)) {
            return $query;
        }

        $managedServiceIds = $this->getManagedServiceIds($user);


        if ($column === 'services.id') {
            if (empty($managedServiceIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereIn($column, $managedServiceIds);
        }

        $categoryIds = $this->getManagedCategoryIds($user);

        if (empty($categoryIds) && empty($managedServiceIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($column, $categoryIds, $managedServiceIds) {
            $appliedCategoryCondition = false;

            if (! empty($categoryIds)) {
                $builder->whereIn($column, $categoryIds);
                $appliedCategoryCondition = true;
            }

            if (! empty($managedServiceIds)) {
                $method = $appliedCategoryCondition ? 'orWhereIn' : 'whereIn';
                $builder->{$method}('services.id', $managedServiceIds);
            }
        });
    
    }

    public function restrictServiceRequestQuery(Builder $query, User $user): Builder
    {
        if ($this->userHasFullAccess($user)) {
            return $query;
        }

        $categoryIds = $this->getManagedCategoryIds($user);
        $serviceIds = $this->getManagedServiceIds($user);

        if (empty($categoryIds) && empty($serviceIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($categoryIds, $serviceIds) {
            $appliedCondition = false;

            if (! empty($serviceIds)) {
                $builder->whereIn('service_requests.service_id', $serviceIds);
                $appliedCondition = true;
            }

            if (! empty($categoryIds)) {
                $method = $appliedCondition ? 'orWhereHas' : 'whereHas';
                $builder->{$method}('service', static function (Builder $serviceQuery) use ($categoryIds) {
                    $serviceQuery->whereIn('category_id', $categoryIds);
                });
            }
        });
    }

    public function getManagedServiceIds(User $user): array
    {

        $serviceIds = $this->getExplicitManagedServiceIds($user);

        $categoryIds = $this->getManagedCategoryIds($user);

        if (! empty($categoryIds)) {
            $serviceIds = array_merge($serviceIds, Service::query()
                ->whereIn('category_id', $categoryIds)
                ->pluck('id')
                ->all());
        }

        $ownedServiceIds = $this->getOwnedServiceIds($user);

        if (! empty($ownedServiceIds)) {
            $serviceIds = array_merge($serviceIds, $ownedServiceIds);
        }

        return collect($serviceIds)


            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function getExplicitManagedServiceIds(User $user): array
    {
        return $user->managedServices()
            ->pluck('services.id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }


    public function getOwnedServiceIds(User $user): array
    {
        return Service::query()
            ->where(static function (Builder $builder) use ($user) {
                $builder->where('owner_id', $user->getKey());

                $builder->orWhere(static function (Builder $directQuery) use ($user) {
                    $directQuery
                        ->where('direct_to_user', true)
                        ->where('direct_user_id', $user->getKey());
                });
            })
            ->pluck('id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function userOwnsService(User $user, Service $service): bool
    {
        if ((int) $service->owner_id === $user->getKey()) {
            return true;
        }

        if ($service->direct_to_user && (int) $service->direct_user_id === $user->getKey()) {
            return true;
        }

        return false;
    }

    private function userManagesService(User $user, Service $service): bool
    {
        return $user->managedServices()
            ->where('services.id', $service->getKey())
            ->exists();
    }


    public function getManagedCategoryIds(User $user): array
    {
        $userCategoryIds = $user->managedCategories()
            ->pluck('categories.id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $roleCategoryIds = $this->getRoleCategoryIds($user);

        return collect(array_merge($userCategoryIds, $roleCategoryIds))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function getRoleCategoryIds(User $user): array
    {
        $roleIds = $user->roles()->pluck('roles.id')->filter()->all();

        if (empty($roleIds)) {
            return [];
        }

        return DB::table('role_categories')
            ->whereIn('role_id', $roleIds)
            ->pluck('category_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function getVisibleCategoryIds(User $user): array
    {
        $categoryIds = $this->getManagedCategoryIds($user);

        $serviceCategoryIds = $user->managedServices()
            ->pluck('services.category_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return collect(array_merge($categoryIds, $serviceCategoryIds))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
