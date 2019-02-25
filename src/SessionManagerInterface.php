<?php

namespace Cilefen\Middleware;

interface SessionManagerInterface
{
    public function startSession(string $user);
    public function isAuthenticated(): bool;
    public function serverUrl(): string;
}
