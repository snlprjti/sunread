<?php

namespace Modules\Erp\Jobs\Mapper;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Erp\Entities\ErpImportDetail;
use Modules\Erp\Traits\HasErpValueMapper;
use Modules\Product\Entities\Product;

class ErpMigratorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasErpValueMapper;
    
    public $tries = 10;
    public $timeout = 1200;

    protected $detail;

    public function __construct(object $detail)
    {
        $this->detail = $detail;
    }

    public function handle(): void
    {
        try
        {
            $variants = $this->getDetailCollection("productVariants", $this->detail->sku);
            $check_migratable_variants = ($this->getValue($variants)->filter(fn ($variant) => $variant["webActive"] == true)->count() >= 1);
            $check_variants = ($this->getValue($variants)->filter(fn ($variant) => $variant["webActive"] == true)->count() <= 1);
            
            if ($check_migratable_variants) {
                $type = ($check_variants) ? "simple" : "configurable";

                $match = [
                    "website_id" => 1,
                    "sku" => $this->detail->sku
                ];
                $product_data = array_merge($match, [
                    "attribute_set_id" => 1,
                    "type" => $type,
                ]);
                if ($check_variants) $product_data["parent_id"] = null;
                $product = Product::updateOrCreate($match, $product_data);
                $product->categories()->sync(1);
                //visibility attribute value
                $visibility = ($check_variants) ? 8 : 5;
                $this->createAttributeValue($product, $this->detail, false, $visibility);
                
                if (!$check_variants) $this->createVariants($product, $this->detail);
                $this->mapstoreImages($product, $this->detail);
    
                $this->createInventory($product, $this->detail);
                ErpImportDetail::whereId($this->detail->id)->first()?->update(["status" => 1]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }
}
