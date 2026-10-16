<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Util\ExcludeList;
use Tests\TestCase;

// Laravel reads the optional .env with @file_get_contents; a checkout without one must not report it.
ExcludeList::addDirectory(dirname(__DIR__).'/vendor/vlucas/phpdotenv');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
