<?php
if (! function_exists('bouncer')) {
    function bouncer()
    {
        return app()->make(\Modules\User\Services\Bouncer::class);
    }
}
?>