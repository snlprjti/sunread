<?php

namespace Modules\Erp\Traits;

use Exception;
use Illuminate\Support\Collection;
use Modules\Erp\Entities\ErpImport;
use Illuminate\Support\Facades\Http;
use Modules\Erp\Entities\ErpImportDetail;
use Modules\Erp\Jobs\ImportErpData;

trait HasErpMapper
{
    protected $url = "https://bc.sportmanship.se:7148/sportmanshipbcprodapi/api/NaviproAB/web/beta/";

    private function basicAuth(): object
    {
        return Http::withBasicAuth(env("ERP_API_USERNAME"), env("ERP_API_PASSWORD"));
    }

    public function erpImport( string $type, string $url, ?string $skip_token = null ): Collection
    {
        try
        {
            $response_json = $this->getResponse($type, $url, $skip_token, function ($response_json_array, $new_skip_token) use ($type) {
                ImportErpData::dispatch($type, $response_json_array);
                get_class()::dispatch($new_skip_token);
            });
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $this->generateCollection($response_json, $type);
    }

    public function getResponse(string $type, string $url, ?string $skip_token = null, callable $callback = null): array
    {
        try
        {
            $response = $this->basicAuth()->get($url, $skip_token);

            $response_json_array = $response->json()["value"];
            $skip_token = $this->skipToken(end($response_json_array), $type);

            if (!empty($response_json_array) && array_key_exists("@odata.nextLink", $response->json())) {
                if ( $callback ) $callback($response_json_array, $skip_token);
            } else {
                ErpImport::whereType($type)->update(["status" => 1]);
                if ( $type == "listProducts" ) ErpImport::whereType("productDescriptions")->update(["status" => 1]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $response_json_array;
    }

    private function skipToken( array $data, string $type ): string
    {
        $data = (object) $data;
        $prepend = "\$skiptoken=";

        switch ($type) {
            case 'webAssortments':
                $token = "{$prepend}'{$data->itemNo}','SR','{$data->colorCode}'";
            break;
            
            case 'listProducts':
                $token = "{$prepend}'{$data->no}','{$data->webAssortmentWeb_Setup}','{$data->webAssortmentColor_Code}','{$data->languageCode}','{$data->auxiliaryIndex1}','{$data->auxiliaryIndex2}','{$data->auxiliaryIndex3}','{$data->auxiliaryIndex4}'";
            break;
            
            case 'attributeGroups':
                $token = "{$prepend}'{$data->itemNo}','{$data->sortKey}','{$data->groupCode}','{$data->attributeID}','{$data->name}','{$data->auxiliaryIndex1}'";
            break;
            
            case 'productVariants':
                $token = "{$prepend}'{$data->pfVerticalComponentCode}','{$data->itemNo}'";
            break;

            case 'salePrices':
                $token = "{$prepend}'{$data->itemNo}','{$data->salesCode}','{$data->currencyCode}','{$data->startingDate}','{$data->salesType}','{$data->minimumQuantity}','{$data->unitofMeasureCode}','{$data->variantCode}'";
            break;

            case 'eanCodes':
                $token = "{$prepend}'{$data->itemNo}','{$data->variantCode}','{$data->unitofMeasure}','{$data->crossReferenceType}','{$data->crossReferenceTypeNo}','{$data->crossReferenceNo}'";
            break;

            case 'webInventories':
                $token = "{$prepend}'{$data->Item_No}','{$data->Code}'";
            break;
        }

        return $token;
    }

    private function generateCollection( array $data, string $type ): Collection
    {
        switch ($type) {
            case 'listProducts':
                $collection = collect($data)->where("webAssortmentWeb_Active", true)
                    ->where("webAssortmentWeb_Setup", "SR")
                    ->chunk(50)
                    ->flatten(1);
                break;

            case 'webAssortments':
                $collection = collect($data)->where("webActive", true)
                    ->where("webSetup", "SR")
                    ->chunk(50)
                    ->flatten(1);
                break;

            default :
                $collection = collect($data)
                    ->chunk(50)
                    ->flatten(1);
                break;
        }

        return $collection;
    }

    public function storeDescription(string $sku): bool
    {
        try
        {
            $erp_import_id = ErpImport::whereType("productDescriptions")->first()->id;

            $url = "{$this->url}webExtendedTexts(tableName='Item',No='{$sku}',Language_Code='ENU',textNo=1)/Data";
            $response = $this->basicAuth()->get($url);

            if ( $response->status() == 200 )
            {
                $value = json_encode(["description" => $response->body(), "lang" => "ENU"]);
                $hash = md5($erp_import_id.$sku.json_encode($value));
                $existing_details = ErpImportDetail::where("hash", $hash)->first();
                if ( !$existing_details ) return true;
                ErpImportDetail::insert([
                    "erp_import_id" => $erp_import_id,
                    "sku" => $sku,
                    "value" => json_encode($value),
                    "hash" => $hash
                ]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }
}
