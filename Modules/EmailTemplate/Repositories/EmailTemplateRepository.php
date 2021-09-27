<?php

namespace Modules\EmailTemplate\Repositories;

use Illuminate\Validation\ValidationException;
use Modules\Core\Repositories\BaseRepository;
use Modules\Core\Repositories\ConfigurationRepository;
use Modules\EmailTemplate\Entities\EmailTemplate;
use Exception;

class EmailTemplateRepository extends BaseRepository
{
    protected $config_variable, $config_template, $configurationRepository;

    public function __construct(EmailTemplate $emailTemplate, ConfigurationRepository $configurationRepository)
    {
        $this->model = $emailTemplate;
        $this->model_key = "email_template";
        $this->config_variable = config("email_variable");
        $this->config_template = config("email_template");

        $this->rules = [
            "name" => "required",
            "subject" => "required",
            "content" => "required",
            "email_template_code" => "required",
            "style" => "sometimes",
        ];
        $this->configurationRepository = $configurationRepository;
    }

    /**
     * get template group data from configuration file
    */
    public function getConfigGroup(): array
    {
        try
        {
            $config_data = $this->config_template;
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $config_data;
    }

    /**
     * get all template variables by email_template_code from configuration file
    */
    public function getConfigVariable(object $request): array
    {
        try
        {
            $elements = collect($this->config_variable);

            foreach ($elements as $element) {
                $parent = [];
                foreach ($element["variables"] as $variable) {
                    if (in_array($request->email_template_code, $variable["availability"]) || $variable["availability"] == ["all"]) {

                        unset($variable["availability"], $variable["source"], $variable["type"]);

                        $parent["label"] = $element["label"];
                        $parent["code"] = $element["code"];
                        $parent["variables"][] = $variable;
                    }
                }
                $data["groups"][] = $parent;
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    /**
     *  validate template group
    */
    public function templateGroupValidation(object $request): void
    {
        $all_groups = collect($this->config_template)->pluck("code")->toArray();
        if (!in_array($request->email_template_code, $all_groups)) throw ValidationException::withMessages(["email_template_code" => __("Invalid Template Code")]);
    }
}
