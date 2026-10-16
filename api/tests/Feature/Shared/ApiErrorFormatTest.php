<?php

declare(strict_types=1);

use App\Payments\Application\Exceptions\IdempotencyKeyInProgress;
use App\Payments\Application\Exceptions\IdempotencyKeyReused;
use App\Payments\Http\Exceptions\MissingIdempotencyKey;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/test-errors')->group(function () {
        Route::post('validation', fn (Request $request) => $request->validate([
            'amount' => ['required', 'integer'],
            'currency' => ['required'],
        ]));
        Route::get('reused', fn () => throw IdempotencyKeyReused::withDifferentContent());
        Route::get('in-progress', fn () => throw IdempotencyKeyInProgress::create());
        Route::get('missing-key', fn () => throw MissingIdempotencyKey::create());
        Route::get('crash', fn () => throw new RuntimeException('database password is hunter2'));
        Route::get('explicit-response', fn () => throw new HttpResponseException(response()->json(['custom' => true], 418)));
    });
});

it('reports every invalid field with a single error format', function () {
    $this->postJson('/api/test-errors/validation', ['amount' => 'abc'])
        ->assertStatus(422)
        ->assertExactJson([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => [
                    'amount' => ['The amount field must be an integer.'],
                    'currency' => ['The currency field is required.'],
                ],
            ],
        ]);
});

it('maps idempotency conflicts and a missing key to their error codes', function (string $path, int $status, string $code) {
    $this->getJson("/api/test-errors/{$path}")
        ->assertStatus($status)
        ->assertJsonPath('error.code', $code)
        ->assertJsonStructure(['error' => ['code', 'message']]);
})->with([
    'key reused' => ['reused', 409, 'IDEMPOTENCY_KEY_REUSED'],
    'key in progress' => ['in-progress', 409, 'IDEMPOTENCY_KEY_IN_PROGRESS'],
    'key missing' => ['missing-key', 400, 'IDEMPOTENCY_KEY_MISSING'],
]);

it('answers an unknown API route with the error format', function () {
    $this->getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'NOT_FOUND', 'message' => 'The requested resource was not found.']]);
});

it('hides internal details of an unexpected failure, even in debug mode', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/test-errors/crash');

    $response->assertStatus(500)
        ->assertExactJson(['error' => ['code' => 'INTERNAL_ERROR', 'message' => 'An unexpected error occurred.']]);
    expect($response->getContent())->not->toContain('hunter2');
});

it('answers other framework HTTP errors with their status and the error format', function () {
    $this->deleteJson('/api/test-errors/crash')
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'HTTP_ERROR', 'message' => 'The request could not be handled.']]);
});

it('keeps a response the code chose explicitly instead of turning it into an internal error', function () {
    $this->getJson('/api/test-errors/explicit-response')
        ->assertStatus(418)
        ->assertExactJson(['custom' => true]);
});
