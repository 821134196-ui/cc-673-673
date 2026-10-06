<?php

namespace App\Exceptions;

use App\Models\Sample;

class SampleUsageBlockedException extends \RuntimeException
{
    public function __construct(public readonly Sample $sample, string $reason)
    {
        parent::__construct("样本 [{$sample->code}] 无法领用：{$reason}");
    }
}
