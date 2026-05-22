<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Email template definitions for the Surveyors module.
 *
 * CONVENTION: All module email templates MUST be defined here,
 * not in install.php or surveyors.php.
 *
 * install.php calls surveyors_register_email_templates() on activation.
 */

if (!function_exists('surveyors_email_templates')) {

function surveyors_email_templates(): array
{
    return [
        [
            'subject' => 'New Surveyor Registration Pending: {client_company}',
            'message' => '<p>Hi,</p><p>A new surveyor registration is awaiting your approval.</p><p><strong>Company:</strong> {client_company}</p><p>Please log in to the admin panel to review and approve this registration.</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'New Surveyor Registration (Sent to Admin)',
            'slug'    => 'new-surveyor-registered-to-admin',
            'active'  => 1,
        ],
        [
            'subject' => 'Your Registration Has Been Approved',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>Congratulations! Your registration with <strong>{companyname}</strong> has been approved. You can now log in to your account.</p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Surveyor Registration Approved',
            'slug'    => 'surveyor-registration-confirmed',
            'active'  => 1,
        ],
        [
            'subject' => 'Your registration has been received - {companyname}',
            'message' => 'Dear {contact_firstname} {contact_lastname},<br /><br />Thank you for registering on the <strong>{companyname}</strong> portal.<br /><br />Your registration is currently <strong>pending approval</strong>. We will notify you once your account has been reviewed.<br /><br />Kind Regards,<br />{email_signature}<br /><br />(This is an automated email, so please don\'t reply to this email address)',
            'type'    => 'surveyors',
            'name'    => 'Surveyor Registration Received (Welcome Email)',
            'slug'    => 'new-surveyor-created',
            'active'  => 1,
        ],
        [
            'subject' => 'Set Your Password - {companyname}',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>Please click the link below to set your password:</p><p><a href="{set_password_url}">{set_password_url}</a></p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Set Password',
            'slug'    => 'surveyor-set-password',
            'active'  => 1,
        ],
        [
            'subject' => 'Your Registration Was Not Approved',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>Unfortunately your registration for <strong>{client_company}</strong> was not approved.</p><p>Please contact us if you have any questions.</p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Surveyor Registration Rejected',
            'slug'    => 'surveyor-registration-rejected',
            'active'  => 1,
        ],
        [
            'subject' => 'Reset Your Password - {companyname}',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>We received a request to reset your password. Click the link below to reset it:</p><p><a href="{reset_password_url}">{reset_password_url}</a></p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Forgot Password',
            'slug'    => 'surveyor-forgot-password',
            'active'  => 1,
        ],
        [
            'subject' => 'Your Password Has Been Reset',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>Your password has been successfully reset. You can now log in with your new password.</p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Password Reset Confirmation',
            'slug'    => 'surveyor-password-reseted',
            'active'  => 1,
        ],
        [
            'subject' => 'Verify Your Email Address - {companyname}',
            'message' => '<p>Dear {contact_firstname} {contact_lastname},</p><p>Please verify your email address by clicking the link below:</p><p><a href="{surveyor_verification_url}">{surveyor_verification_url}</a></p><p>Kind Regards,</p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Email Verification',
            'slug'    => 'surveyor-verification-email',
            'active'  => 1,
        ],
        [
            'subject' => 'Surveyor Profile File Uploaded: {client_company}',
            'message' => '<p>Hi,</p><p>A new file has been uploaded to the surveyor profile of <strong>{client_company}</strong>.</p><p>View files: <a href="{surveyor_profile_files_admin_link}">{surveyor_profile_files_admin_link}</a></p><p>{email_signature}</p>',
            'type'    => 'surveyors',
            'name'    => 'Surveyor Profile File Uploaded (Sent to Staff)',
            'slug'    => 'new-surveyor-profile-file-uploaded-to-staff',
            'active'  => 1,
        ],
    ];
}

function surveyors_register_email_templates(): void
{
    foreach (surveyors_email_templates() as $tpl) {
        create_email_template(
            $tpl['subject'],
            $tpl['message'],
            $tpl['type'],
            $tpl['name'],
            $tpl['slug'],
            $tpl['active'] ?? 1
        );
    }
}

} // end function_exists guard
