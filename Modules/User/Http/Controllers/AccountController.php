<?php

namespace Modules\User\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Modules\User\Entities\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\User\Transformers\AdminResource;
use Modules\User\Repositories\AdminRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Http\Controllers\BaseController;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\User\Exceptions\InvalidCredentialException;

class AccountController extends BaseController
{
    protected $repository;

    public function __construct(Admin $admin, AdminRepository $adminRepository)
    {
        $this->model = $admin;
        $this->model_name = "Admin account";
        $this->repository = $adminRepository;
        $exception_statuses = [
            InvalidCredentialException::class => 401
        ];

        parent::__construct($this->model, $this->model_name, $exception_statuses);
    }

    public function collection(object $data): ResourceCollection
    {
        return AdminResource::collection($data);
    }

    public function resource(object $data): JsonResource
    {
        return new AdminResource($data);
    }

    public function show(): JsonResponse
    {
        try
        {
            $fetched = auth()->guard('admin')->user();
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($fetched), $this->lang('fetch-success'));
    }

    public function update(Request $request): JsonResponse
    {
        try
        {
            $updated = auth()->guard('admin')->user();
            $merge = ["email" => "required|email|unique:admins,email,{$updated->id}"];
            if ( $request->has("current_password") ) {
                $current_password_validation = $request->has("password") ? "required" : "sometimes";
                $merge["current_password"] = "$current_password_validation|min:6|max:200";
            }

            $data = $this->repository->validateData($request, $merge);

            if ( $request->has("current_password") ) {
                if (!Hash::check($request->current_password, auth()->guard('admin')->user()->password)) {
                    throw new InvalidCredentialException(__("core::app.users.users.incorrect-password"));
                }

                $data["password"] = Hash::make($request->password);
            }

            unset($data["current_password"]);
            $updated = $this->repository->update($data, $updated->id);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), $this->lang('update-success'));
    }

    public function uploadProfileImage(Request $request): JsonResponse
    {
        try
        {
            $updated = auth()->guard('admin')->user();
            $this->repository->removeOldImage($updated->id);
            $updated = $this->repository->uploadProfileImage($request, $updated->id);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), "Profile image updated successfully.");
    }

    public function deleteProfileImage(): JsonResponse
    {
        try
        {
            $updated = auth()->guard('admin')->user();
            $updated = $this->repository->removeOldImage($updated->id);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), "Profile image deleted successfully.");
    }
}
