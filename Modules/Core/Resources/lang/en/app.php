<?php
return [

    'response' => [
        'being-used' => 'This resource :name is getting used in :source',
        'cannot-delete-default' => 'Cannot delete the default channel',
        'create-success' => ':name created successfully.',
        'update-success' => ':name updated successfully.',
        'delete-success' => ':name deleted successfully.',
        'delete-failed' => 'Error encountered while deleting :name.',
        'last-delete-error' => 'At least one :name is required.',
        'user-define-error' => 'Can not delete system :name',
        'cancel-success' => ':name canceled successfully.',
        'cancel-error' => ':name can not be canceled.',
        'already-taken' => 'The :name has already been taken.',

    ],
    'users' => [
        'forget-password' => [
            'title' => 'Forget Password',
            'header-title' => 'Recover Password',
            'email' => 'Registered Email',
            'password' => 'Password',
            'confirm-password' => 'Confirm Password',
            'back-link-title' => 'Back to Sign In',
            'submit-btn-title' => 'Send Password Reset Email'
        ],

        'reset-password' => [
            'title' => 'Reset Password',
            'email' => 'Registered Email',
            'password' => 'Password',
            'confirm-password' => 'Confirm Password',
            'back-link-title' => 'Back to Sign In',
            'submit-btn-title' => 'Reset Password',
        ],

        'roles' => [
            'title' => 'Roles',
            'add-role-title' => 'Add Role',
            'edit-role-title' => 'Edit Role',
            'save-btn-title' => 'Save Role',
            'general' => 'General',
            'name' => 'Name',
            'description' => 'Description',
            'access-control' => 'Access Control',
            'permissions' => 'Permissions',
            'custom' => 'Custom',
            'all' => 'All'
        ],

        'users' => [
            'title' => 'User',
            'add-user-title' => 'Add User',
            'edit-user-title' => 'Edit User',
            'save-btn-title' => 'Save User',
            'general' => 'General',
            'email' => 'Email',
            'name' => 'Name',
            'password' => 'Password',
            'confirm-password' => 'Confirm Password',
            'status-and-role' => 'Status and Role',
            'role' => 'Role',
            'status' => 'Status',
            'account-is-active' => 'Account is Active',
            'current-password' => 'Enter Current Password',
            'confirm-delete' => 'Confirm Delete This Account',
            'confirm-delete-title' => 'Confirm password before delete',
            'delete-last' => 'At least one admin is required.',
            'delete-success' => 'Success! User deleted',
            'incorrect-password' => 'The password you entered is incorrect',
            'password-match' => 'Current password does not match.',
            'account-save' => 'Account changes saved successfully.',
            'password-reset-success' => 'Password reset successfully',

            'login-error' => 'Please check your credentials and try again.',
            'login-success' => 'Logged in successfully',
            'logout-success' => 'Logged out successfully',
            'activate-warning' => 'Your account is yet to be activated, please contact administrator.'
        ],

        'sessions' => [
            'title' => 'Sign In',
            'email' => 'Email',
            'password' => 'Password',
            'forget-password-link-title' => 'Forget Password ?',
            'remember-me' => 'Remember Me',
            'submit-btn-title' => 'Sign In'
        ]
    ],
];