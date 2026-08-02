<?php

declare(strict_types=1);

namespace App\Domain\Auth\Pipelines;

use Illuminate\Pipeline\Pipeline;

class AuthPipeline
{
    /** @var array<int, class-string> */
    protected array $pipes = [
        CheckAccountStatusPipe::class,
        EnforceMfaPipe::class,
        IssueTokenPipe::class,
    ];

    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * Send the authentication context through the login pipeline.
     */
    public function execute(AuthContext $context): AuthContext
    {
        $result = $this->pipeline
            ->send($context)
            ->through($this->pipes)
            ->thenReturn();

        return $result instanceof AuthContext ? $result : $context;
    }
}
