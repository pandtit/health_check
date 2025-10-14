<?php

namespace Pandtit\HealthCheck\Contracts;

interface WatcherContract
{
    public function register(): void;
}