<?php

namespace Modules\Core\Http\Controllers\StoreFront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Entities\Website;
use Modules\Core\Facades\Resolver;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Core\Repositories\ResolveRepository;
use Modules\Core\Transformers\StoreFront\Resolver\ResolveResource;

class ResolverController extends BaseController
{
    protected $repository;

    public function __construct(Website $website)
    {
        $this->model = $website;
        $this->model_name = "Website";

        parent::__construct($this->model, $this->model_name);
    }

    public function resolve(Request $request): JsonResponse
    {
        try
        {
            $fetched = Resolver::fetch($request);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($fetched, $this->lang('fetch-success'));
    }
}
