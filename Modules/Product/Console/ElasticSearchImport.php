<?php

namespace Modules\Product\Console;

use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Entities\Website;
use Modules\Product\Entities\Product;
use Modules\Product\Jobs\BulkIndexing;
use Modules\Product\Jobs\ConfigurableIndexing;
use Modules\Product\Jobs\ElasticSearchIndexingJob;
use Modules\Product\Jobs\SingleIndexing;
use Modules\Product\Jobs\VariantIndexing;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ElasticSearchImport extends Command
{
    protected $signature = 'reindexer:reindex';

    protected $description = 'Import all the data to the elasticsearch';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $batch = Bus::batch([])->onQueue("index")->dispatch();
        
        $products = Product::whereType("simple")->whereParentId(null)->get();
        foreach($products as $product)
        {
            $stores = Website::find($product->website_id)->channels->map(function ($channel) {
                return $channel->stores;
            })->flatten(1);
            
            foreach($stores as $store) $batch->add(new SingleIndexing($product, $store));
        }

        $configurable_products = Product::whereType("configurable")->get();
        foreach($configurable_products as $configurable_product)
        {
            $stores = Website::find($configurable_product->website_id)->channels->map(function ($channel) {
                return $channel->stores;
            })->flatten(1);
            $variants = $configurable_product->variants()->with(["categories", "product_attributes", "catalog_inventories", "attribute_options_child_products"])->get();
    
            foreach( $stores as $store) {
                $batch->add(new ConfigurableIndexing($configurable_product, $store));
                foreach($variants as $variant) $batch->add(new VariantIndexing($configurable_product, $variants, $variant, $store));
            }
        }
        $this->info("All data imported successfully");
    }
}
