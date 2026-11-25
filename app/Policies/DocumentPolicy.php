<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->id === $document->user_id;  // I can only open document that is belong to me
    } 

    public function download(
        User $user,
        Document $document
    ): bool {
        // I can only open document that has passed the scan test
        // and that is clean
        return $user->id === $document->user_id && $document->status === 'clean';
    }
}
