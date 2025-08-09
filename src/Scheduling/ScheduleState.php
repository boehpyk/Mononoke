<?php

declare(strict_types=1);

namespace Kekke\Mononoke\Scheduling;

/**
 * Holds runtime state of a scheduled task.
 */
final class ScheduleState
{
    public function __construct(
        private ?int $previousInvocation = null
    ) {}

    public function getPreviousInvocation(): ?int
    {
        return $this->previousInvocation;
    }

    public function updateInvocationTime(int $timestamp): void
    {
        $this->previousInvocation = $timestamp;
    }
}