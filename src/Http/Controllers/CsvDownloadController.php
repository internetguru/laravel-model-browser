<?php

namespace Internetguru\ModelBrowser\Http\Controllers;

use Illuminate\Http\Request;
use Internetguru\ModelBrowser\Components\BaseModelBrowser;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Livewire\Mechanisms\HandleComponents\HandleComponents;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownloadController
{
    /**
     * Stream a CSV export directly to the browser.
     *
     * Livewire's file-download mechanism buffers the whole response and
     * base64-encodes it into the component JSON payload, so large exports are
     * slow and memory-hungry. This endpoint rebuilds the component from its
     * checksum-verified snapshot (posted by the CSV button) and returns the
     * StreamedResponse directly, so rows reach the browser as they are read
     * from the database.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $snapshot = json_decode((string) $request->input('snapshot'), true);
        abort_unless(is_array($snapshot), 400);

        try {
            [$component] = app(HandleComponents::class)->fromSnapshot($snapshot);
        } catch (CorruptComponentPayloadException) {
            abort(403);
        }

        if (! $component instanceof BaseModelBrowser) {
            abort(403);
        }

        // Echo the client-generated token back as a cookie so the CSV button
        // can tell the download has started and hide its spinner.
        $token = (string) $request->input('token');
        if ($token !== '' && preg_match('/^[a-z0-9]{1,64}$/i', $token)) {
            cookie()->queue(cookie(
                name: 'mb_csv_download',
                value: $token,
                minutes: 1,
                httpOnly: false,
            ));
        }

        return $component->downloadCsv();
    }
}
