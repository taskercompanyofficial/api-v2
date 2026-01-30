<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KPI Benchmarks
    |--------------------------------------------------------------------------
    |
    | Target completion percentages by days to complete.
    | KPI Score = min(100, (Actual % / Target %) × 100)
    |
    */
    'kpi' => [
        1 => 87,  // 1 day = 87% target
        2 => 80,  // 2 days = 80% target
        3 => 70,  // 3 days = 70% target
        4 => 60,  // 4 days = 60% target
        5 => 55,  // 5 days = 55% target
        6 => 50,  // 6 days = 50% target
        7 => 45,  // 7+ days = 45% target
    ],

    /*
    |--------------------------------------------------------------------------
    | NPS Score Benchmarks
    |--------------------------------------------------------------------------
    |
    | NPS score thresholds for rating classification.
    |
    */
    'nps' => [
        'excellent' => 70,   // Score >= 70 is excellent
        'good' => 50,        // Score >= 50 is good
        'average' => 0,      // Score >= 0 is average
        // Below 0 is "Needs Improvement"
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Date Ranges
    |--------------------------------------------------------------------------
    |
    | Default date range for each dashboard section (in days).
    |
    */
    'defaults' => [
        'worker_efficiency' => 7,   // Last 7 days
        'kpi_trend' => 7,           // Last 7 days
        'nps' => 30,                // Last 30 days
        'pipeline' => 30,           // Last 30 days
    ],
];
