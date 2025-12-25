<?php

namespace App\Jobs;

use App\Data\Notifications\NotificationIntent;
use App\Models\User;
use App\Services\NotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
    }

    public function handle(NotificationDispatchService $dispatchService): void
    {
        $user = User::query()->find($this->userId);
        if (!$user || (int) $user->notification !== 1) {
            return;
        }

        $displayName = trim((string) $user->name);
        $title = $displayName !== ''
            ? "أهلًا {$displayName} 👋"
            : 'أهلًا 👋';

        if ($user->account_type === User::ACCOUNT_TYPE_SELLER) {
            $body = "تم إنشاء حسابك بنجاح. متجرك الآن قيد المراجعة من قبل فريقنا.\n"
                . "يمكنك استخدام باقي مميزات وخدمات التطبيق بشكل طبيعي الى ان تتم الموافقه على المتجر الخاص بك وعلى الاغلب سيتم التواصل معك قبل ذلك.\n"
                . 'حظاً طيباً .. شكراً لانضمامك';
        } else {
            $body = "تم إنشاء حسابك بنجاح. استكشف التطبيق وابدأ أول تجربة الآن.\n"
                . 'حظاً طيباً .. شكراً لانضمامك';
        }

        $intent = new NotificationIntent(
            userId: $user->id,
            type: 'account.welcome',
            title: $title,
            body: $body,
            deeplink: 'marib://notifications',
            entity: 'account',
            entityId: $user->id,
            data: [
                'user_id' => (string) $user->id,
                'account_type' => (string) $user->account_type,
            ],
            meta: [
                'source' => 'welcome-automation',
            ],
        );

        $dispatchService->dispatch($intent);
    }
}
