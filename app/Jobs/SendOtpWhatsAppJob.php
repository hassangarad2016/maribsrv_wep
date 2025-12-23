<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EnjazatikWhatsAppService;
use Illuminate\Support\Facades\Log;
class SendOtpWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $phone, $message;

    public function __construct($phone, $message)
    {
        $this->phone = $phone;
        $this->message = $message;
    }

    public function handle(EnjazatikWhatsAppService $whatsApp)
    {
        Log::info("SendOtpWhatsAppJob started: {$this->phone}");
        echo "Running job for {$this->phone}\n";

        $response = $whatsApp->sendMessage($this->phone, $this->message);
        if (($response['status'] ?? false) !== true) {
            Log::warning('SendOtpWhatsAppJob failed', [
                'phone' => $this->phone,
                'response' => $response,
            ]);
        } else {
            Log::info('SendOtpWhatsAppJob delivered', ['phone' => $this->phone]);
        }

        Log::info("SendOtpWhatsAppJob finished.");
    }
    
}
