<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Hentikan request dengan 403 jika user login bukan admin.
     */
    protected function pastikanAdmin(): void
    {
        abort_unless(optional(auth()->user())->role === 'admin', 403, 'Hanya admin yang boleh melakukan aksi ini');
    }
}
