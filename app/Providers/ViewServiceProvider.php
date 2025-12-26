<?php

namespace App\Providers;
use App\Models\Category;
use App\Models\DeliveryRequest;
use App\Models\ManualPaymentRequest;
use App\Models\VerificationRequest;
use App\Models\WalletWithdrawalRequest;
use App\Services\ServiceAuthorizationService;

use App\Services\CachingService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class ViewServiceProvider extends ServiceProvider {
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {


        /*** Header File ***/
        View::composer('layouts.topbar', static function (\Illuminate\View\View $view) {
            $languages = CachingService::getLanguages();
            $defaultLanguage = CachingService::getDefaultLanguage();

            // Get current language from session or set to default if not set
            $currentLanguage = Session::get('language', $defaultLanguage);
            $view->with([
                'languages' => $languages,
                'currentLanguage' => $currentLanguage,
            ]);
            // $view->with('languages', CachingService::getLanguages() );
        });

        View::composer('layouts.sidebar', static function (\Illuminate\View\View $view) {
            $settings = CachingService::getSystemSettings('company_logo');
            $pendingCount = 0;
            $user = Auth::user();

            if ($user && $user->can('wallet-manage')) {
                $pendingCount = WalletWithdrawalRequest::query()
                    ->where('status', WalletWithdrawalRequest::STATUS_PENDING)
                    ->count();
            }

            $badgeCounts = [
                'wallet_withdrawals' => $pendingCount,
                'manual_payments' => 0,
                'verification_requests' => 0,
                'delivery_requests' => 0,
            ];

            if ($user && $user->canAny(['manual-payments-list', 'manual-payments-review'])) {
                $badgeCounts['manual_payments'] = ManualPaymentRequest::query()
                    ->whereIn('status', ManualPaymentRequest::OPEN_STATUSES)
                    ->count();
            }

            if ($user && $user->canAny([
                'seller-verification-request-list',
                'seller-verification-request-create',
                'seller-verification-request-update',
                'seller-verification-request-delete',
            ])) {
                $badgeCounts['verification_requests'] = VerificationRequest::query()
                    ->whereIn('status', ['pending', 'resubmitted'])
                    ->count();
            }

            if ($user && $user->can('manual-payments-review')) {
                $badgeCounts['delivery_requests'] = DeliveryRequest::query()
                    ->where('status', DeliveryRequest::STATUS_PENDING)
                    ->count();
            }

            $serviceCategories = collect();
            $servicePermissions = [
                'service-list',
                'service-create',
                'service-update',
                'service-delete',
                'service-reviews-list',
                'service-requests-list',
                'service-requests-create',
                'service-requests-update',
                'service-requests-delete',
            ];

            if ($user && $user->canAny($servicePermissions)) {
                $serviceCategoryIds = [2, 4, 5, 8, 114, 174, 175, 176, 177, 180, 181];
                $categoryQuery = Category::query()
                    ->whereIn('id', $serviceCategoryIds)
                    ->orderBy('name')
                    ->select(['id', 'name']);

                $serviceAuthorizationService = app(ServiceAuthorizationService::class);
                if (! $serviceAuthorizationService->userHasFullAccess($user)) {
                    $managedCategoryIds = $serviceAuthorizationService->getVisibleCategoryIds($user);

                    if (empty($managedCategoryIds)) {
                        $categoryQuery = null;
                    } else {
                        $categoryQuery->whereIn('id', $managedCategoryIds);
                    }
                }

                if ($categoryQuery) {
                    $serviceCategories = $categoryQuery->get();
                }
            }

            $view->with([
                'company_logo' => $settings ?? '',
                'pendingWalletWithdrawalCount' => $pendingCount,
                'sidebarBadgeCounts' => $badgeCounts,
                'sidebarServiceCategories' => $serviceCategories,
            ]);
        
        
        });

        View::composer('layouts.main', static function (\Illuminate\View\View $view) {
            $settings = CachingService::getSystemSettings('favicon_icon');
            $view->with('favicon', $settings ?? '');
            $view->with('lang', Session::get('language'));
        });

        View::composer('auth.login', static function (\Illuminate\View\View $view) {
            $favicon_icon = CachingService::getSystemSettings('favicon_icon');
            $company_logo = CachingService::getSystemSettings('company_logo');
            $login_image = CachingService::getSystemSettings('login_image');
            $view->with('company_logo', $company_logo ?? '');
            $view->with('favicon', $favicon_icon ?? '');
            $view->with('login_bg_image', $login_image ?? '');
        });
    }


}
