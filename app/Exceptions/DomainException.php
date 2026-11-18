<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function workspaceForbidden(string $message = 'Anda tidak memiliki akses ke workspace ini.'): self
    {
        return new self('WS_FORBIDDEN', $message, 403);
    }

    public static function templateInvalid(string $message, array $details = []): self
    {
        return new self('TEMPLATE_INVALID', $message, 422, $details);
    }

    public static function numberConflict(string $message = 'Terjadi bentrok penomoran surat. Silakan coba lagi.'): self
    {
        return new self('NUMBER_CONFLICT', $message, 409);
    }

    public static function bulkJobFailed(string $message, array $details = []): self
    {
        return new self('BULK_JOB_FAILED', $message, 422, $details);
    }

    public static function revisionReasonRequired(string $message = 'Alasan revisi wajib diisi.'): self
    {
        return new self('REVISION_REASON_REQUIRED', $message, 422);
    }

    public static function invalidTransition(string $message): self
    {
        return new self('INVALID_STATUS_TRANSITION', $message, 422);
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $this->errorCode,
                    'message' => $this->getMessage(),
                    'details' => (object) $this->details,
                ],
            ], $this->httpStatus);
        }

        return null;
    }
}
