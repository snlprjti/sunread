<?php

namespace Modules\User\Http\Controllers;

use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Controllers\BaseController;
use Modules\User\Entities\Admin;

/**
 * Forgot Password controller for the Admin
 * @author    Hemant Achhami
 * @copyright 2020 Hazesoft Pvt Ltd
 */
class ForgotPasswordController extends BaseController
{

    use SendsPasswordResetEmails;


    /**
     *  Generate a token and sends token in email for user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $this->validate($request, ['email' => 'required|email']);
            $email = $request->get('email');
            $admin = Admin::where('email', $email)->first();
            if (!$admin) {
                return $this->errorResponse(400, "Email does not exist");
            }
            $response = $this->broker()->sendResetLink(request(['email']));
            if ($response == Password::RESET_LINK_SENT) {
                return $this->successResponse(200, $admin, "Reset Link sent to your email {$admin->email}");
            }
            return $this->errorResponse(400, $admin, "Unable to generate token. Try again later");
        } catch (ValidationException $exception) {
            return $this->errorResponse(400, $exception->errors());
        } catch (\Exception $e) {
            return $this->errorResponse(400, $e->getMessage());
        }
    }

    /**
     * Get the broker to be used during password reset
     * @return \Illuminate\Contracts\Auth\PasswordBroker
     */
    public function broker()
    {
        return Password::broker('admins');
    }
}