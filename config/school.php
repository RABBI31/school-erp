<?php

return [
    /*
    | Days with no school (Carbon dayOfWeek: 0 = Sunday … 5 = Friday, 6 = Saturday).
    | Attendance cannot be taken on these days, and the monthly sheet shades them.
    */
    'weekend_days' => [5],

    'fees' => [
        // Monthly invoices fall due on this day of the month (business-rules §3).
        'due_day' => 10,
    ],
];
