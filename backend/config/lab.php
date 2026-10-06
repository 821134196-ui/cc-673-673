<?php

return [
    // 隔离登记时默认复检截止日（天）
    'retest_days' => (int) env('LAB_RETEST_DAYS', 7),
    // 复检截止日前 N 天开始提醒
    'remind_before_days' => (int) env('LAB_REMIND_BEFORE_DAYS', 2),
];
