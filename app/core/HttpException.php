<?php

declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    /** @param array<string,mixed> $errors */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $errors = [],
        public readonly array $extra = []
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status));
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Permintaan tidak valid.',
            401 => 'Belum masuk atau sesi berakhir.',
            403 => 'Anda tidak memiliki akses.',
            404 => 'Halaman tidak ditemukan.',
            419 => 'Token keamanan tidak valid. Muat ulang halaman.',
            429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
            500 => 'Terjadi kesalahan pada server.',
            default => 'Terjadi kesalahan.',
        };
    }
}
