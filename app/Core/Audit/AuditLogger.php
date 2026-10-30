<?php

namespace App\Core\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    private const SECRET_PATTERN = '/(pass(word)?|secret|token|key|salt|signature|code_hash|recovery)/i';

    /** @param array<string, mixed> $meta */
    public function log(string $action, ?Model $subject = null, array $meta = [], ?int $actorId = null): void
    {
        $request = app()->runningInConsole() ? null : request();

        try {
            AuditLog::query()->create([
                'actor_id' => $actorId ?? $request?->user()?->getAuthIdentifier(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
                'correlation_id' => $request?->attributes->get('correlation_id'),
                'meta' => $meta === [] ? null : self::redact($meta),
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the business operation; surface it in the log instead.
            Log::error('audit.write_failed', ['action' => $action, 'exception' => $e::class]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_PATTERN, $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }
}
