<?php

namespace  Modules\ClubHouse\Exceptions;


class ClubHouseNotFoundException extends \Exception
{
	public function __construct()
	{
		parent::__construct(__("core::app.response.not-found", ["name" => "ClubHouse"]));
	}
}
