<?php

use App\Models\User;
use Carbon\Carbon;

function globalFormat($value)
{
    return $value;
}

function formatDateTime($value)
{
    return Carbon::parse($value)->format('Y-m-d H:i:s');
}

function formatNameOfRow($value, $item)
{
    return $value . ' #' . $item->id;
}

function formatUserLink(User $user)
{
    return '<a href="/users/' . $user->id . '">' . e($user->name) . '</a>';
}

function rawUserAddress(User $user)
{
    return $user->name . ' <' . $user->email . '>';
}

function formatWithCurrentHour($value)
{
    return $value . ' at ' . now()->format('H');
}
