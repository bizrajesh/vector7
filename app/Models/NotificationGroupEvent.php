<?php

namespace App\Models;


class NotificationGroupEvent extends TenantModel
{

    protected $fillable = ['event_code'];

    public $timestamps = false;
}
