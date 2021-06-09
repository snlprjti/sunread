<?php


namespace Modules\Core\Observers;

use Modules\Core\Entities\Channel;
use Modules\Core\Facades\Audit;

class ChannelObserver
{
    public function created(Channel $channel)
    {
        Audit::log($channel, __FUNCTION__);
    }

    public function updated(Channel $channel)
    {
        $channel->products->map(function ($product) {
            $product->searchable();
        });
        Audit::log($channel, __FUNCTION__);
    }

    public function deleted(Channel $channel)
    {
        $channel->products->map(function ($product) {
            $product->searchable();
        });
        Audit::log($channel, __FUNCTION__);
    }
}
