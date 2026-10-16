<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Payments\Domain\Acquirer\AcquirerDecision;
use App\Payments\Domain\Acquirer\AcquirerRequest;
use App\Payments\Domain\Ports\AcquirerGateway;
use Closure;
use LogicException;
use Throwable;

/**
 * Answers each authorization with the next scripted decision or exception.
 */
final class ScriptedAcquirerGateway implements AcquirerGateway
{
    /** @var list<AcquirerRequest> */
    public array $requests = [];

    /** @var list<AcquirerDecision|Throwable|Closure(AcquirerRequest): AcquirerDecision> */
    private array $script;

    public function __construct(AcquirerDecision|Throwable|Closure ...$script)
    {
        $this->script = array_values($script);
    }

    public function authorize(AcquirerRequest $request): AcquirerDecision
    {
        $this->requests[] = $request;
        $next = array_shift($this->script) ?? throw new LogicException('The acquirer was called more times than scripted.');

        return match (true) {
            $next instanceof Throwable => throw $next,
            $next instanceof Closure => $next($request),
            default => $next,
        };
    }

    public function calls(): int
    {
        return count($this->requests);
    }
}
