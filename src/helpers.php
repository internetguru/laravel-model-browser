<?php

function prettyPrint(mixed $value): string
{
    if ($value instanceof \Illuminate\Database\Eloquent\Model) {
        return $value->toJson(JSON_PRETTY_PRINT);
    }

    if ($value instanceof \Carbon\Carbon) {
        return $value->toDateTimeString();
    }

    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT);
    }

    if (is_object($value)) {
        return json_encode($value, JSON_PRETTY_PRINT);
    }

    if (! $value) {
        return '';
    }

    return $value;
}
