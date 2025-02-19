<?php

function globalFormat($value)
{
    return $value;
}

function formatDateTime($value)
{
    return \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
}
