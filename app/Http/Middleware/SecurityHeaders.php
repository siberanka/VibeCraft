<?php

namespace App\Http\Middleware;

use App\Core\Security\CspNonce;
use App\Core\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function __construct(private CspNonce $nonce, private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=(), payment=(self)');
        $headers->remove('X-Powered-By');
        if ($request->is('api/*')) { $headers->set('Cache-Control', 'no-store'); }

        if ($request->isSecure() && $this->settings->bool('security.hsts')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $type = (string) $headers->get('Content-Type');
        if ($type === '' || str_starts_with($type, 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy($request));
        }

        return $response;
    }

    private function policy(Request $request): string
    {
        $nonce = "'nonce-".$this->nonce->get()."'";
        $script = ["'self'", $nonce];
        $frame = [];
        $connect = ["'self'"];
        $formAction = ["'self'"];

        switch ($this->settings->get('captcha.provider')) {
            case 'turnstile':
                $script[] = 'https://challenges.cloudflare.com';
                $frame[] = 'https://challenges.cloudflare.com';
                break;
            case 'hcaptcha':
                array_push($script, 'https://hcaptcha.com', 'https://*.hcaptcha.com');
                array_push($frame, 'https://hcaptcha.com', 'https://*.hcaptcha.com');
                array_push($connect, 'https://hcaptcha.com', 'https://*.hcaptcha.com');
                break;
            case 'recaptcha':
                array_push($script, 'https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/');
                $frame[] = 'https://www.google.com/recaptcha/';
                break;
        }

        // Form posts that redirect to a hosted checkout must be allowed by form-action.
        if ($this->settings->bool('payments.stripe.enabled')) {
            $formAction[] = 'https://checkout.stripe.com';
        }
        if ($this->settings->bool('payments.paytr.enabled')) {
            $frame[] = 'https://www.paytr.com';
            $script[] = 'https://www.paytr.com';
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', array_unique($script)),
            "style-src 'self' ".$nonce,
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.implode(' ', array_unique($connect)),
            'frame-src '.($frame === [] ? "'none'" : implode(' ', array_unique($frame))),
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            'form-action '.implode(' ', array_unique($formAction)),
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
