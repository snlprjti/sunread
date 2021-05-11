<?php

namespace Modules\UrlRewrite\Repositories;

use Modules\UrlRewrite\Entities\UrlRewrite;
use Modules\UrlRewrite\Contracts\UrlRewriteInterface;
use Modules\UrlRewrite\Exceptions\UrlRewriteAlreadyExistsException;
use Modules\UrlRewrite\Exceptions\UrlRewriteRegenerationFailedException;

class UrlRewriteRepository implements UrlRewriteInterface
{
    public const allowedTypes = [0, 1, 2];

    protected $model;

    public function __construct(UrlRewrite $model)
    {
        $this->model = $model;
    }

	public function getModel(): object
    {
        return $this->model;
    }

    public function setModel(object $model): object
    {
        $this->model = $model;

        return $this;
    }

    public function find(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function checkIfIdExists(int $id): bool
    {
        return $this->model->where('id', $id)->exists();
    }

    public function checkIfRequestPathExists(string $url): bool
    {
        return $this->model->where('request_path', $url)->exists();
    }

    public function getByRequestPath(string $url): ?object
    {
        return $this->model->where('request_path', $url)->first();
    }

    public function getByTypeAndAttributes(string $type, array $attributes): ?object
    {
        return $this->model->getByTypeAndAttributes($type, $attributes)->first();
    }

    public function getByTargetPath($url): ?object
    {
        return $this->model->where('target_path', $url)->first();
    }

    public function all(): ?object
    {
        return $this->model->all();
    }

    public function delete(int $id): bool
    {
        return $this->find($id)->delete();
    }

    public function regenerateAll(): void
    {
        if (empty($this->getTypes())) throw UrlRewriteRegenerationFailedException::noConfiguration();

        foreach ($this->getTypes() as $type) {
            $this->regenerateRoutesFromType($type);
        }
    }

    public function regenerateRoutesFromType(string $type): void
    {
        if (! array_key_exists($type, $this->getTypes())) throw UrlRewriteRegenerationFailedException::invalidType($type);

        $rewrites = $this->model->where('type', $type)->get();

        foreach ($rewrites as $rewrite) {
            $this->regenerateRoute($rewrite->request_path, $rewrite);
        }
    }

    public function regenerateRoute(string $requestPath, object $urlRewrite): object
    {

        // if (! array_key_exists($urlRewrite->type, $this->getTypes())) {
        //     throw UrlRewriteRegenerationFailedException::invalidType($urlRewrite->type);
        // }

        if (! \is_array($urlRewrite->type_attributes)) {
            throw UrlRewriteRegenerationFailedException::columnNotSet($urlRewrite, 'type_attributes');
        }

        return $this->update(
            [
                "target_path" => $this->targetPathFromRoute($urlRewrite->type, $urlRewrite->type_attributes),
                "request_path" => $requestPath
            ],
            $urlRewrite->id
        );
    }

    public function create(string $requestPath,
        ?string $targetPath,
        ?string $type = null,
        ?array $typeAttributes = null,
        int $redirectType = 0,
        ?bool $unique = false): object 
    {
      

        [$requestPath, $targetPath] = $this->validateCreate(
            $requestPath,
            $targetPath,
            $type,
            $typeAttributes,
            $redirectType,
            $unique
        );

        return $this->model->create(
            [
                'type' => $type,
                'type_attributes' => $typeAttributes,
                'request_path' => $requestPath,
                'target_path' => $targetPath,
                'redirect_type' => $redirectType
            ]
        );
    }

    public function update(array $data, int $id): object
    {
        $record = $this->find($id);

        $record->update($data);

        return $record;
    }

    protected function generateUnique(string $requestPath, int $id = 1): string
    {
        if ($this->checkIfRequestPathExists($requestPath.'-'.$id)) return $this->generateUnique($requestPath, $id + 1);
        return $requestPath.'-'.$id;
    }

    protected function getTypes(): array
    {
        return config('url-rewrite.types');
    }

    protected function targetPathFromRoute($type, $attributes): string
    {
        return route($type, $attributes['parameter'], false);
    }

    protected function validateCreate(string $requestPath,
    ?string $targetPath,
    ?string $type,
    ?array $typeAttributes,
    int $redirectType,
    ?bool $unique): array 
    {
        if (! in_array($redirectType, self::allowedTypes, true)) throw new \Exception('Redirect type must be 0, 1 or 2');

        if ($this->checkIfRequestPathExists($requestPath)) {
            if (! $unique) throw UrlRewriteAlreadyExistsException::requestPath($requestPath);
            $requestPath = $this->generateUnique($requestPath);
        }

        if ($targetPath === null && isset($type, $typeAttributes)) {
            $targetPath = $this->targetPathFromRoute($type, $typeAttributes);
        }

        return [$requestPath, $targetPath];
    }

    public function handleUrlRewrite(object $model, string $event, string $request_path): void
    {
        if( $event == "created" ) {
            $this->create($request_path,
                null,
                config("url-rewrite.types.$model->urlRewriteType.route"),
                $model->getUrlRewriteAttributesArray(),
                0,
                true
            );
        }

        if ( $event == "updated" ) {
            $this->regenerateRoute($request_path, $model->getUrlRewrite());
        }

        if( $event == "deleted" ){
            $this->delete($model->getUrlRewrite()->id);
        }
    }

}
