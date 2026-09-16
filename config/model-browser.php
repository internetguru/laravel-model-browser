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

    /*
    |--------------------------------------------------------------------------
    | Column Statistics Limit
    |--------------------------------------------------------------------------
    |
    | Default largest result count the column statistics are computed for.
    | Can be overridden per component instance via the `statsLimit`
    | parameter. Summarizing walks the whole filtered result set, so above
    | this many rows nothing is computed and the statistics menu asks for
    | narrower filters instead. Set to 0 to disable the limit.
    |
    */

    'stats_limit' => 1500,

];
