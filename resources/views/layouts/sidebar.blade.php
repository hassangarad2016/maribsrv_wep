{{-- =========================
     Sidebar (RTL)
     Admin navigation powered by Spatie permissions (@can / @canany)
========================= --}}
<div id="sidebar" class="active">
  <div class="sidebar-wrapper active">

    {{-- ---------- с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ?сЬЩ?Ъ?Г??Щ?Ъ?Г??у?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ? / с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ?сЬЩ?Ъ?Г??Щ?Ъ?G??у?с·Щ·с?Щ?сЬЩ?Ъ?G??Щ?с·Э?с·Щ·с?Щ?с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ? ---------- --}}
    <div class="sidebar-header position-relative">
      <div class="d-block">
        <div class="logo text-center">
          <a href="{{ url('home') }}">
            <img src="{{ $company_logo ?? '' }}" data-custom-image="{{ url('assets/images/logo/sidebar_logo.png') }}" alt="Logo" />
          </a>
        </div>
      </div>
    </div>

    {{-- ---------- с·Щ·с?Щ?сЬЩ?Ъ?G??Щ?с?G??с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ?сЬЩ?Ъ?G??Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ? с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ?сЬЩ?Ъ?G??Щ?Ъ?G??у?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ?с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ?с·Щ·с?Щ·с·Щ?с?Щ? ---------- --}}
    <div class="sidebar-menu" style="direction: rtl; text-align: right;">
      @php
        $badgeCounts = $sidebarBadgeCounts ?? [];
        $badgeFor = function ($key) use ($badgeCounts) {
          $value = $badgeCounts[$key] ?? 0;
          return is_numeric($value) ? (int) $value : 0;
        };
      @endphp
      <ul class="menu">

        @if(!auth()->check() || auth()->user()->account_type !== \App\Models\User::ACCOUNT_TYPE_SELLER)
          <li class="sidebar-item">
            <a href="{{ route('home') }}" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-house"></i>
                <span class="menu-item">{{ __('Dashboard') }}</span>
              </span>
            </a>
          </li>
        @endif


        @canany(['category-list','category-create','category-update','category-delete',
                 'custom-field-list','custom-field-create','custom-field-update','custom-field-delete',
                 'item-list','item-create','item-update','item-delete',
                 'tip-list','tip-create','tip-update','tip-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-megaphone"></i>
                <span class="menu-item">&#x0625;&#x062F;&#x0627;&#x0631;&#x0629; &#x0627;&#x0644;&#x0625;&#x0639;&#x0644;&#x0627;&#x0646;&#x0627;&#x062A;</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['category-list','category-create','category-update','category-delete'])
                <li class="submenu-item">
                  <a href="{{ route('category.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-list-task"></i>
                      <span class="menu-item">{{ __('Categories') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['custom-field-list','custom-field-create','custom-field-update','custom-field-delete'])
                <li class="submenu-item">
                  <a href="{{ route('custom-fields.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-columns-gap"></i>
                      <span class="menu-item">{{ __('Custom Fields') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['item-list','item-create','item-update','item-delete'])
                <li class="submenu-item">
                  <a href="{{ route('item.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-ui-radios-grid"></i>
                      <span class="menu-item">{{ __('Items') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['tip-list','tip-create','tip-update','tip-delete'])
                <li class="submenu-item">
                  <a href="{{ route('tips.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-lightbulb"></i>
                      <span class="menu-item">{{ __('Tips') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany

        @canany(['item-listing-package-list','item-listing-package-create','item-listing-package-update','item-listing-package-delete',
                 'advertisement-package-list','advertisement-package-create','advertisement-package-update','advertisement-package-delete',
                 'user-package-list','payment-transactions-list',
                 'manual-payments-list','manual-payments-review','wallet-manage'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-credit-card-2-front"></i>
                <span class="menu-item">{{ __('Package Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['item-listing-package-list','item-listing-package-create','item-listing-package-update','item-listing-package-delete',
                       'advertisement-package-list','advertisement-package-create','advertisement-package-update','advertisement-package-delete'])
                <li class="submenu-item">
                  <a href="{{ route('package.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-list"></i>
                      <span class="menu-item">{{ __('Item Listing Package') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['advertisement-package-list','advertisement-package-create','advertisement-package-update','advertisement-package-delete'])
                <li class="submenu-item">
                  <a href="{{ route('package.advertisement.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-badge-ad"></i>
                      <span class="menu-item">{{ __('Advertisement Package') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('user-package-list')
                <li class="submenu-item">
                  <a href="{{ route('package.users.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-person-badge-fill"></i>
                      <span class="menu-item">{{ __('User Packages') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @can('payment-transactions-list')
                <li class="submenu-item">
                  <a href="{{ route('package.payment-transactions.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-cash-coin"></i>
                      <span class="menu-item">{{ __('Payment Transactions') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @canany(['manual-payments-list','manual-payments-review'])
                @php($badgeCount = $badgeFor('manual_payments'))
                <li class="submenu-item">
                  <a href="{{ route('payment-requests.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-wallet2"></i>
                      <span class="menu-item">{{ __('sidebar.manual_payment_requests') }}</span>
                    </span>
                    @if($badgeCount > 0)
                      <span class="menu-badge">{{ $badgeCount }}</span>
                    @endif
                  </a>
                </li>
              @endcanany

              @can('wallet-manage')
                <li class="submenu-item">
                  <a href="{{ route('wallet.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-wallet-fill"></i>
                      <span class="menu-item">{{ __('Wallet') }}</span>
                    </span>
                  </a>
                </li>
                @php($badgeCount = $badgeFor('wallet_withdrawals'))
                <li class="submenu-item">
                  <a href="{{ route('wallet.withdrawals.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-arrow-down-circle"></i>
                      <span class="menu-item">{{ __('Wallet Withdrawal Requests') }}</span>
                    </span>
                    @if($badgeCount > 0)
                      <span class="menu-badge">{{ $badgeCount }}</span>
                    @endif
                  </a>
                </li>
              @endcan
            </ul>
          </li>
        @endcanany

        @canany(['wifi-cabin-manage',
                 'currency-rate-list','currency-rate-create','currency-rate-edit','currency-rate-delete',
                 'metal-rate-list','metal-rate-create','metal-rate-edit','metal-rate-delete','metal-rate-schedule'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-stack"></i>
                <span class="menu-item">{{ __('Various Services') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @can('wifi-cabin-manage')
                <li class="submenu-item">
                  <a href="{{ route('wifi.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-wifi"></i>
                      <span class="menu-item">{{ __('WiFi Cabin Management') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @canany(['currency-rate-list','currency-rate-create','currency-rate-edit','currency-rate-delete'])
                <li class="submenu-item">
                  <a href="{{ route('currency.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-currency-exchange"></i>
                      <span class="menu-item">{{ __('Currency Rates') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['metal-rate-list','metal-rate-create','metal-rate-edit','metal-rate-delete','metal-rate-schedule'])
                <li class="submenu-item">
                  <a href="{{ route('metal-rates.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-gem"></i>
                      <span class="menu-item">{{ __('Metal Rates') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany


        @canany(['seller-verification-field-list','seller-verification-field-create','seller-verification-field-update','seller-verification-field-delete',
                 'seller-verification-request-list','seller-verification-request-create','seller-verification-request-update','seller-verification-request-delete',
                 'seller-review-list','seller-review-update','seller-review-delete',
                 'seller-store-settings-manage'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-shield-check"></i>
                <span class="menu-item">&#x0625;&#x062F;&#x0627;&#x0631;&#x0629; &#x0627;&#x0644;&#x0628;&#x0627;&#x0626;&#x0639;&#x064A;&#x0646; &#x0648;&#x0627;&#x0644;&#x0645;&#x062A;&#x0627;&#x062C;&#x0631;</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['seller-verification-field-list','seller-verification-field-create','seller-verification-field-update','seller-verification-field-delete',
                       'seller-verification-request-list','seller-verification-request-create','seller-verification-request-update','seller-verification-request-delete'])
                @php($badgeCount = $badgeFor('verification_requests'))
                <li class="submenu-item">
                  <a href="{{ route('seller-verification.dashboard') }}">
                    <span class="menu-text">
                      <i class="bi bi-shield-lock"></i>
                      <span class="menu-item">{{ __('ЯщелЯаЯв Ящъэгчб') }}</span>
                    </span>
                    @if($badgeCount > 0)
                      <span class="menu-badge">{{ $badgeCount }}</span>
                    @endif
                  </a>
                </li>
              @endcanany

              @canany(['seller-review-list','seller-review-update','seller-review-delete'])
                <li class="submenu-item">
                  <a href="{{ route('seller-review.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-star-half"></i>
                      <span class="menu-item">{{ __('Seller Review') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['seller-review-list','seller-review-update','seller-review-delete'])
                <li class="submenu-item">
                  <a href="{{ route('seller-review.report') }}">
                    <span class="menu-text">
                      <i class="bi bi-list-stars"></i>
                      <span class="menu-item">{{ __('Seller Review Report') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('seller-store-settings-manage')
                <li class="submenu-item">
                  <a href="{{ route('seller-store-settings.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-gear"></i>
                      <span class="menu-item">{{ __('Store Settings') }}</span>
                    </span>
                  </a>
                </li>
                <li class="submenu-item">
                  <a href="{{ route('merchant-stores.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-shop-window"></i>
                      <span class="menu-item">{{ __('sidebar.merchant_directory') }}</span>
                    </span>
                  </a>
                </li>
              @endcan
            </ul>
          </li>
        @endcanany

        @if(auth()->check() && auth()->user()->account_type === \App\Models\User::ACCOUNT_TYPE_SELLER)
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-shop"></i>
                <span class="menu-item">{{ __('sidebar.store_dashboard') }}</span>
              </span>
            </a>
            <ul class="submenu">
              <li class="submenu-item">
                <a href="{{ route('merchant.dashboard') }}">
                  <span class="menu-text">
                    <i class="bi bi-shop"></i>
                    <span class="menu-item">{{ __('sidebar.store_dashboard') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.orders.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span class="menu-item">{{ __('sidebar.store_orders') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.manual-payments.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-bank"></i>
                    <span class="menu-item">{{ __('sidebar.store_manual_payments') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.wallet.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-wallet2"></i>
                    <span class="menu-item">{{ __('sidebar.store_wallet') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.products.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-bag-check"></i>
                    <span class="menu-item">{{ __('sidebar.product_management') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.coupons.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-ticket-perforated"></i>
                    <span class="menu-item">{{ __('sidebar.coupons_menu') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.reports.orders') }}">
                  <span class="menu-text">
                    <i class="bi bi-graph-up"></i>
                    <span class="menu-item">{{ __('sidebar.orders_reports_menu') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.reports.sales') }}">
                  <span class="menu-text">
                    <i class="bi bi-cash-coin"></i>
                    <span class="menu-item">{{ __('sidebar.sales_reports_menu') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.reports.customers') }}">
                  <span class="menu-text">
                    <i class="bi bi-people"></i>
                    <span class="menu-item">{{ __('sidebar.customers_reports_menu') }}</span>
                  </span>
                </a>
              </li>
              <li class="submenu-item">
                <a href="{{ route('merchant.settings') }}">
                  <span class="menu-text">
                    <i class="bi bi-gear"></i>
                    <span class="menu-item">{{ __('sidebar.store_settings') }}</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endif

        @canany(['slider-list','slider-create','slider-update','slider-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-house-gear"></i>
                <span class="menu-item">{{ __('Home Screen Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['slider-list','slider-create','slider-update','slider-delete'])
                <li class="submenu-item">
                  <a href="{{ route('slider.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-sliders2"></i>
                      <span class="menu-item">{{ __('Slider') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
              <li class="submenu-item">
                <a href="{{ route('featured-ads-configs.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-stars"></i>
                    <span class="menu-item">ЭущЯыЯв ъъякб</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endcanany

        @canany(['country-list','country-create','country-update','country-delete',
                 'state-list','state-create','state-update','state-delete',
                 'city-list','city-create','city-update','city-delete',
                 'area-list','area-create','area-update','area-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-geo-alt"></i>
                <span class="menu-item">{{ __('Place/Location Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['country-list','country-create','country-update','country-delete'])
                <li class="submenu-item">
                  <a href="{{ route('countries.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-globe"></i>
                      <span class="menu-item">{{ __('Countries') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['state-list','state-create','state-update','state-delete'])
                <li class="submenu-item">
                  <a href="{{ route('states.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-geo-fill"></i>
                      <span class="menu-item">{{ __('States') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['city-list','city-create','city-update','city-delete'])
                <li class="submenu-item">
                  <a href="{{ route('cities.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-building"></i>
                      <span class="menu-item">{{ __('Cities') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['area-list','area-create','area-update','area-delete'])
                <li class="submenu-item">
                  <a href="{{ route('area.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-geo"></i>
                      <span class="menu-item">{{ __('Areas') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany


        @canany(['report-reason-list','report-reason-create','report-reason-update','report-reason-delete',
                 'user-report-list','user-report-create','user-report-update','user-report-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-flag"></i>
                <span class="menu-item">{{ __('Reports Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['report-reason-list','report-reason-create','report-reason-update','report-reason-delete'])
                <li class="submenu-item">
                  <a href="{{ route('report-reasons.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-flag"></i>
                      <span class="menu-item">{{ __('Report Reasons') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['user-report-list','user-report-create','user-report-update','user-report-delete'])
                <li class="submenu-item">
                  <a href="{{ route('report-reasons.user-reports.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-person"></i>
                      <span class="menu-item">{{ __('User Reports') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('reports-orders')
                <li class="submenu-item">
                  <a href="{{ route('reports.payment-requests') }}">
                    <span class="menu-text">
                      <i class="bi bi-graph-up"></i>
                      <span class="menu-item">{{ __('Payment Request Analytics') }}</span>
                    </span>
                  </a>
                </li>
              @endcan
            </ul>
          </li>
        @endcanany


        @canany([
            'computer-ads-list','computer-ads-create','computer-ads-update','computer-ads-delete',
            'computer-requests-list','computer-requests-create','computer-requests-update','computer-requests-delete',
            'computer-orders-list','orders-list','staff-list','staff-create','staff-update','staff-delete',
            'reports-orders','reports-sales','reports-customers','reports-statuses','chat-monitor-list'
        ])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-laptop"></i>
                <span class="menu-item">{{ __("sidebar.computer_section_title") }}</span>
              </span>
            </a>
            <ul class="submenu">
              <li class="submenu-item">
                <a href="{{ route('item.computer') }}">
                  <span class="menu-text">
                    <i class="bi bi-laptop"></i>
                    <span class="menu-item">{{ __('sidebar.computer_section_menu') }}</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endcanany


        @canany(['shein-products-list','shein-products-create','shein-products-update','shein-products-delete',
                 'shein-orders-list','shein-orders-create','shein-orders-update','shein-orders-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-bag-heart"></i>
                <span class="menu-item">{{ __("sidebar.shein_section_title") }}</span>
              </span>
            </a>
            <ul class="submenu">
              <li class="submenu-item">
                <a href="{{ route('item.shein.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-bag-heart"></i>
                    <span class="menu-item">{{ __('sidebar.shein_section_menu') }}</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endcanany


        @canany(['challenge-list','challenge-create','challenge-edit','challenge-delete',
                 'referral-list',
                 'notifications-send',
                 'customer-list','customer-create','customer-update','customer-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-trophy"></i>
                <span class="menu-item">{{ __('Promotional Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['challenge-list','challenge-create','challenge-edit','challenge-delete'])
                <li class="submenu-item">
                  <a href="{{ route('challenges.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-trophy"></i>
                      <span class="menu-item">{{ __('Challenges') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('referral-list')
                <li class="submenu-item">
                  <a href="{{ route('referrals.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-people"></i>
                      <span class="menu-item">{{ __('Referrals & Points') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @can('notifications-send')
                <li class="submenu-item">
                  <a href="{{ route('notification.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-bell"></i>
                      <span class="menu-item">{{ __('Send Notification') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @canany(['customer-list','customer-create','customer-update','customer-delete'])
                <li class="submenu-item">
                  <a href="{{ route('customer.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-people"></i>
                      <span class="menu-item">{{ __('Customers') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany


        @canany(['role-list','role-create','role-update','role-delete',
                 'staff-list','staff-create','staff-update','staff-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-people"></i>
                <span class="menu-item">{{ __('Staff Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['role-list','role-create','role-update','role-delete'])
                <li class="submenu-item">
                  <a href="{{ route('roles.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-person-bounding-box"></i>
                      <span class="menu-item">{{ __('Role') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['staff-list','staff-create','staff-update','staff-delete'])
                <li class="submenu-item">
                  <a href="{{ route('staff.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-gear"></i>
                      <span class="menu-item">{{ __('Staff Management') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany

        @canany([
            'service-list','service-create','service-update','service-delete',
            'service-requests-list','service-requests-create','service-requests-update','service-requests-delete'
        ])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-tools"></i>
                <span class="menu-item">{{ __("sidebar.services_section_title") }}</span>
              </span>
            </a>
            <ul class="submenu">
              @php($serviceCategories = $sidebarServiceCategories ?? collect())
              @forelse($serviceCategories as $serviceCategory)
                <li class="submenu-item">
                  <a href="{{ route('services.category', $serviceCategory->id) }}">
                    <span class="menu-text">
                      <i class="bi bi-grid"></i>
                      <span class="menu-item">{{ $serviceCategory->name }}</span>
                    </span>
                  </a>
                </li>
              @empty
                <li class="submenu-item">
                  <a href="{{ route('services.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-tools"></i>
                      <span class="menu-item">{{ __('sidebar.services_section_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endforelse
            </ul>
          </li>
        @endcanany


        @canany(['blog-list','blog-create','blog-update','blog-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-pencil"></i>
                <span class="menu-item">{{ __('Travel Management') }}</span>
              </span>
            </a>
            <ul class="submenu">
              <li class="submenu-item">
                <a href="{{ route('blog.index') }}">
                  <span class="menu-text">
                    <i class="bi bi-pencil"></i>
                    <span class="menu-item">{{ __('Travel') }}</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endcanany


        @can('chat-monitor-list')
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="menu-item">{{ __("sidebar.chat_monitor_title") }}</span>
              </span>
            </a>
            <ul class="submenu">
              <li class="submenu-item">
                <a href="{{ route('chat-monitor.index') }}?locale={{ App::getLocale() }}">
                  <span class="menu-text">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span class="menu-item">{{ __('sidebar.chat_monitor_menu') }}</span>
                  </span>
                </a>
              </li>
            </ul>
          </li>
        @endcan


        @canany(['orders-list','orders-create','orders-update','orders-delete',
                 'delivery-prices-list','delivery-prices-create','delivery-prices-update','delivery-prices-delete',
                 'reports-orders','reports-sales','reports-customers','reports-statuses'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-truck"></i>
                <span class="menu-item">{{ __("sidebar.orders_delivery_title") }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['orders-list','orders-create','orders-update','orders-delete'])
                <li class="submenu-item">
                  <a href="{{ route('orders.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-cart-fill"></i>
                      <span class="menu-item">{{ __('sidebar.platform_orders_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['delivery-prices-list','delivery-prices-create','delivery-prices-update','delivery-prices-delete'])
                <li class="submenu-item">
                  <a href="{{ route('delivery-prices.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-truck"></i>
                      <span class="menu-item">{{ __('sidebar.delivery_prices_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('manual-payments-review')
                @php($badgeCount = $badgeFor('delivery_requests'))
                <li class="submenu-item">
                  <a href="{{ route('delivery.requests.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-send-plus"></i>
                      <span class="menu-item">{{ __('sidebar.delivery_requests_menu') }}</span>
                    </span>
                    @if($badgeCount > 0)
                      <span class="menu-badge">{{ $badgeCount }}</span>
                    @endif
                  </a>
                </li>
                <li class="submenu-item">
                  <a href="{{ route('delivery.agents.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-person-lines-fill"></i>
                      <span class="menu-item">{{ __('sidebar.delivery_agents_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @canany(['coupon-list','coupon-create','coupon-edit'])
                <li class="submenu-item">
                  <a href="{{ route('coupons.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-ticket-perforated"></i>
                      <span class="menu-item">{{ __('sidebar.coupons_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @can('reports-orders')
                <li class="submenu-item">
                  <a href="{{ route('reports.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-graph-up"></i>
                      <span class="menu-item">{{ __('sidebar.orders_reports_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @can('reports-sales')
                <li class="submenu-item">
                  <a href="{{ route('reports.sales') }}">
                    <span class="menu-text">
                      <i class="bi bi-cash-stack"></i>
                      <span class="menu-item">{{ __('sidebar.sales_reports_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @can('reports-customers')
                <li class="submenu-item">
                  <a href="{{ route('reports.customers') }}">
                    <span class="menu-text">
                      <i class="bi bi-people-fill"></i>
                      <span class="menu-item">{{ __('sidebar.customers_reports_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcan

              @can('reports-statuses')
                <li class="submenu-item">
                  <a href="{{ route('reports.statuses') }}">
                    <span class="menu-text">
                      <i class="bi bi-pie-chart-fill"></i>
                      <span class="menu-item">{{ __('sidebar.statuses_reports_menu') }}</span>
                    </span>
                  </a>
                </li>
              @endcan
            </ul>
          </li>
        @endcanany


        @canany(['faq-create','faq-list','faq-update','faq-delete','contact-us-list','contact-us-update','contact-us-delete'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-question-circle"></i>
                <span class="menu-item">{{ __('FAQ') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @canany(['contact-us-list','contact-us-update','contact-us-delete'])
                <li class="submenu-item">
                  <a href="{{ route('contact-us.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-person-bounding-box"></i>
                      <span class="menu-item">{{ __('User Queries') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany

              @canany(['faq-create','faq-list','faq-update','faq-delete'])
                <li class="submenu-item">
                  <a href="{{ route('faq.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-question-square-fill"></i>
                      <span class="menu-item">{{ __('FAQs') }}</span>
                    </span>
                  </a>
                </li>
              @endcanany
            </ul>
          </li>
        @endcanany


        @canany(['settings-update'])
          <li class="sidebar-item has-sub sidebar-group">
            <a href="#" class="sidebar-link">
              <span class="menu-text">
                <i class="bi bi-gear"></i>
                <span class="menu-item">{{ __('System Settings') }}</span>
              </span>
            </a>
            <ul class="submenu">
              @can('settings-update')
                <li class="submenu-item">
                  <a href="{{ route('settings.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-gear"></i>
                      <span class="menu-item">{{ __('Settings') }}</span>
                    </span>
                  </a>
                </li>
                <li class="submenu-item">
                  <a href="{{ route('settings.legal-numbering.index') }}">
                    <span class="menu-text">
                      <i class="bi bi-hash"></i>
                      <span class="menu-item">{{ __('Legal Numbering') }}</span>
                    </span>
                  </a>
                </li>
              @endcan
            </ul>
          </li>
        @endcanany

      </ul>
    </div>
  </div>
</div>
