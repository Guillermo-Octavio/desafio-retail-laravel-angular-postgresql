<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    Route::middleware('api')->prefix('api/test-correlation')->group(function () {
        Route::get('echo', fn () => ['correlation_id' => Context::get('correlation_id')]);
        Route::get('crash', fn () => throw new RuntimeException('boom'));
    });
});

it('uses the correlation id sent by the client and adds it to the log context', function (string $correlationId) {
    $this->getJson('/api/test-correlation/echo', ['X-Correlation-Id' => $correlationId])
        ->assertOk()
        ->assertHeader('X-Correlation-Id', $correlationId)
        ->assertExactJson(['correlation_id' => $correlationId]);
})->with([
    'simple' => ['corr-123'],
    'tracing format with symbols' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
    'longest allowed' => [str_repeat('c', 64)],
    'visible punctuation' => ['tenant:42/request=abc'],
]);

it('generates a UUID when the client sends none', function () {
    $response = $this->getJson('/api/test-correlation/echo');

    $correlationId = $response->headers->get('X-Correlation-Id');
    expect(Str::isUuid($correlationId))->toBeTrue()
        ->and($response->json('correlation_id'))->toBe($correlationId);
});

it('replaces an unusable correlation id and logs only its length', function (string $received) {
    $correlationIdWhenLogged = null;
    Log::shouldReceive('warning')->once()
        ->with('correlation_id.replaced', ['received_length' => strlen($received)])
        ->andReturnUsing(function () use (&$correlationIdWhenLogged) {
            $correlationIdWhenLogged = Context::get('correlation_id');
        });

    $response = $this->getJson('/api/test-correlation/echo', ['X-Correlation-Id' => $received]);

    $correlationId = $response->headers->get('X-Correlation-Id');
    expect(Str::isUuid($correlationId))->toBeTrue()
        ->and($correlationIdWhenLogged)->toBe($correlationId);
})->with([
    'too long' => [str_repeat('c', 65)],
    'contains a space' => ['corr 123'],
    'not ASCII' => ['corr-ñandú'],
    'control character' => ["corr\t123"],
]);

it('returns the correlation id on error responses too', function (string $path, int $status) {
    $this->getJson($path, ['X-Correlation-Id' => 'corr-456'])
        ->assertStatus($status)
        ->assertHeader('X-Correlation-Id', 'corr-456');
})->with([
    'unknown route' => ['/api/does-not-exist', 404],
    'unexpected failure' => ['/api/test-correlation/crash', 500],
]);
