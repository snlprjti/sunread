<?php

namespace Modules\UrlRewrite\Repositories;


use Modules\Core\Repositories\BaseRepository;
use Modules\Core\Rules\UrlRewriteRule;
use Modules\UrlRewrite\Entities\UrlRewrite;

class UrlRewriteMainRepository extends BaseRepository
{
    protected $repository;

    public function __construct(UrlRewrite $url_rewrite)
    {
        $this->model = $url_rewrite;
        $this->model_key = "url_rewrites";
        $this->rules = [
            "type" => "required",
            "type_attributes" =>"required|array",
            "request_path" => "required",
            "target_path" => "required",
            "redirect_type" => "sometimes",
        ];
    }

    public function geturlRewriteData(array $item): array
    {
        $urlRewrite = [];
        $config_base_name = "url-rewrite.types.".$item['type'];
        $urlRewrite['type'] = config("{$config_base_name}.route");

        switch ($item['type']) {
            case "product":
                $urlRewrite['type_attributes']["parameter"]["product"] = $item['parameter_id'];
                break;

            case "category":
                $urlRewrite['type_attributes']["parameter"]["category"] = $item['parameter_id'];
                break;
        }

        $urlRewrite['request_path'] = $item['request_path'];

        if($item['store_id']) $urlRewrite['type_attributes']["extra_fields"]["store_id"] = $item['store_id'];
        
        $urlRewrite['target_path'] = route($urlRewrite['type'],  $urlRewrite['type_attributes']["parameter"], false);
        

        return $urlRewrite;
    }

    public function ValidateUrlRewrite(object $request): array
    {
        $data = $request->validate([
            "type" => "required|in:product, category",
            "parameter_id" => [ "required", new UrlRewriteRule($request->type) ],
            "store_id" => "sometimes|exists:stores,id",
            "request_path" => "required" 
        ]);
        return $data;
    }
}
