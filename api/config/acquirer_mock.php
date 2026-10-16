<?php

return [

    /*
    | Only the acquirer-mock container enables these routes; they never exist in the API (DA-03).
    */
    'enabled' => (bool) env('ACQUIRER_MOCK_ENABLED', false),

];
