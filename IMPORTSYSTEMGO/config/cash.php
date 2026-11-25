<?php

return [
    'approval_variance_threshold' => (string) env('CASH_APPROVAL_VARIANCE_THRESHOLD', '50.00'),
    'denominations' => ['200.00', '100.00', '50.00', '20.00', '10.00', '5.00', '2.00', '1.00', '0.50', '0.20', '0.10'],
];
