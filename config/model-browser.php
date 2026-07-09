<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CSV Export Limit
    |--------------------------------------------------------------------------
    |
    | Default maximum number of rows a CSV export may contain. Can be
    | overridden per component instance via the `exportLimit` parameter.
    | When the current (filtered) result count exceeds the limit, the
    | export button is disabled and the export endpoint refuses the
    | download. Set to 0 to disable the limit.
    |
    */

    'export_limit' => 1500,

];
