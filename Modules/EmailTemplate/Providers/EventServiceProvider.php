<?php

namespace Modules\EmailTemplate\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen('send.email', 'Modules\EmailTemplate\Listeners\SendEmailListener@sendEmail');
    }
}
