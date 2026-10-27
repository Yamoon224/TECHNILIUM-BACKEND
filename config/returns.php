<?php

return [
    // Days after delivery during which a customer may open a return (cahier §5.8).
    'window_days' => (int) env('RETURNS_WINDOW_DAYS', 7),
];
