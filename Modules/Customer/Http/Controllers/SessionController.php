<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Controllers\BaseController;

/**
 * Session Controller for the Customer
 * @author    Hemant Achhami
 * @copyright 2020 Hazesoft Pvt Ltd
 */
class SessionController extends BaseController
{

    /**
     * Create a new controller instance.
     * @return void
     */
    public function __construct()
    {
      //  $this->middleware('guest:customer')->except(['logout']);
    }

    /**
     * Logs in user and geneates jwt token
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required'
            ]);
            $jwtToken = null;
            if (!$jwtToken = Auth::guard('customer')->attempt(request()->only('email', 'password'))) {
                return $this->errorResponse(400, "Invalid email or password");
            }
            $customer = auth()->guard('customer')->user();
            $payload = [
                'token' => $jwtToken,
                'user' => $customer
            ];
            return $this->successResponse(200, $payload, "Logged in successfully");
        } catch (ValidationException $exception) {
            return $this->errorResponse(400, $exception->getMessage());
        } catch (\Exception  $exception) {
            return $this->errorResponse(400, $exception->getMessage());
        }
    }

    /**
     * Logout the Admin
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            auth()->guard('customer')->logout();
            return $this->successResponse(200, null, "Customer logged out successfully");
        } catch (\Exception $exception) {
            return $this->errorResponse(400, $exception->getMessage());
        }

    }
}