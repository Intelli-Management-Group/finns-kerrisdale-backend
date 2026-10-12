<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use App\Http\Requests\Public\SubscribeRequest;
use App\Models\ContactSubmission;
use App\Models\Subscriber;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class FormController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email'));
        $existing = Subscriber::where('email', $email)->first();

        if ($existing && $existing->status === 'confirmed') {
            // Same answer as a new signup, so the form can't be used to check who is subscribed.
            return ApiResponse::success(null, 'Thank you for subscribing!');
        }

        Subscriber::updateOrCreate(['email' => $email], [
            'status' => 'confirmed',
            'token' => Str::random(40),
            'source' => $request->input('source', 'website'),
            'ip_address' => $request->ip(),
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
        ]);

        return ApiResponse::success(null, 'Thank you for subscribing!', 201);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $sub = Subscriber::where('token', $token)->first();
        if (! $sub) {
            return ApiResponse::error('This unsubscribe link is invalid or has expired.', 404);
        }
        $sub->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        return ApiResponse::success(null, 'You have been unsubscribed.');
    }

    public function contact(ContactRequest $request): JsonResponse
    {
        $submission = ContactSubmission::create($request->safe()->except('website') + [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        $to = $this->settings->get('notification_email');
        if ($to) {
            try {
                Mail::raw(
                    "New message from the website\n\nName: {$submission->name}\nEmail: {$submission->email}\nPhone: {$submission->phone}\nSubject: {$submission->subject}\n\n{$submission->message}",
                    fn ($m) => $m->to($to)->replyTo($submission->email, $submission->name)->subject('Website contact: '.($submission->subject ?: $submission->name))
                );
            } catch (Throwable $e) {
                Log::warning('Contact notification email failed', ['error' => $e->getMessage()]);
            }
        }

        return ApiResponse::success(null, 'Thank you! Your message has been sent.', 201);
    }
}
