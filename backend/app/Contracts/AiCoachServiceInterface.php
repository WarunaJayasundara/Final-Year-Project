<?php

namespace App\Contracts;

use App\Models\User;

interface AiCoachServiceInterface
{
    /**
     * Produce the coach's reply to the student's latest chat message.
     *
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $history  Prior turns, oldest first (excludes the new $message).
     */
    public function chat(User $user, string $message, array $history, string $locale): string;
}
