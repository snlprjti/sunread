<?php


namespace Modules\Attribute\Repositories;

use Modules\Attribute\Entities\AttributeOption;

class AttributeOptionRepository
{
    protected $model, $model_key, $translation;

    public function __construct(AttributeOption $attribute_option, AttributeOptionTranslationRepository $attributeOptionTranslationRepository)
    {
        $this->model = $attribute_option;
        $this->translation = $attributeOptionTranslationRepository;
        $this->model_key = "catalog.attribite.options";
    }

    /**
     * Create or update translation
     * 
     * @param array $data
     * @param Model $parent
     * @return void
     */
    public function createOrUpdate($data, $parent)
    {
        if ( !is_array($data) || count($data) ) return;

        Event::dispatch("{$this->model_key}.create.before");

        try
        {
            foreach ($data as $row){
                $check = [
                    "attribute_option_id" => $row["attribute_option_id"],
                    "attribute_id" => $parent->id
                ];
    
                $created = $this->model->firstorNew($check);
                $created->fill($row);
                $created->save();

                $this->translation->createOrUpdate($data, $created);
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        Event::dispatch("{$this->model_key}.create.after", $created);
    }
}
