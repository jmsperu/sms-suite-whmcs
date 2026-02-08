{$sms_css nofilter}
<div class="sms-suite-preferences">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.preferences|default:'Notification Preferences'}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
                <li class="active"><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
            </ul>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success">
        <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <form method="post" action="{$modulelink}&action=preferences">
        <input type="hidden" name="csrf_token" value="{$csrf_token}">

        <div class="row">
            <!-- Phone Number & Verification -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-phone"></i> {$lang.phone_settings|default:'Phone Number & Verification'}</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <label>{$lang.phone_number|default:'Phone Number'}</label>
                            <div class="input-group">
                                <input type="text" name="phone_number" class="form-control" value="{$client->phonenumber}" placeholder="+1234567890">
                                <span class="input-group-addon">
                                    {if $phone_verified}
                                    <span class="text-success"><i class="fa fa-check-circle"></i> {$lang.verified|default:'Verified'}</span>
                                    {else}
                                    <span class="text-warning"><i class="fa fa-exclamation-circle"></i> {$lang.not_verified|default:'Not Verified'}</span>
                                    {/if}
                                </span>
                            </div>
                            <p class="help-block">{$lang.phone_help|default:'Enter your phone number to receive SMS notifications'}</p>
                        </div>

                        {if !$phone_verified && $client->phonenumber}
                        <div class="form-group">
                            <button type="submit" name="verify_phone" class="btn btn-warning">
                                <i class="fa fa-mobile"></i> {$lang.send_verification|default:'Send Verification Code'}
                            </button>
                        </div>
                        <div class="form-group">
                            <label>{$lang.verification_code|default:'Verification Code'}</label>
                            <div class="input-group">
                                <input type="text" name="verification_code" class="form-control" placeholder="Enter 6-digit code" maxlength="6">
                                <span class="input-group-btn">
                                    <button type="submit" name="confirm_verification" class="btn btn-success">
                                        <i class="fa fa-check"></i> {$lang.verify|default:'Verify'}
                                    </button>
                                </span>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-shield"></i> {$lang.two_factor_auth|default:'Two-Factor Authentication'}</h3>
                    </div>
                    <div class="panel-body">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="two_factor_enabled" value="1" {if $settings->two_factor_enabled}checked{/if} {if !$phone_verified}disabled{/if}>
                                <strong>{$lang.enable_2fa|default:'Enable SMS Two-Factor Authentication'}</strong>
                            </label>
                            <p class="help-block">{$lang.2fa_help|default:'Require SMS verification code when logging in for extra security'}</p>
                        </div>
                        {if !$phone_verified}
                        <div class="alert alert-warning" style="margin-top: 10px;">
                            <i class="fa fa-info-circle"></i> {$lang.verify_phone_first|default:'Please verify your phone number to enable two-factor authentication.'}
                        </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-bell"></i> {$lang.sms_notifications|default:'SMS Notifications'}</h3>
                    </div>
                    <div class="panel-body">
                        <!-- Global Opt-In/Out -->
                        <div class="well" style="margin-bottom: 15px;">
                            <div class="checkbox" style="margin: 0;">
                                <label>
                                    <input type="checkbox" name="accept_sms" value="1" id="accept_sms" {if $settings->accept_sms}checked{/if}>
                                    <strong>{$lang.accept_sms|default:'I want to receive SMS notifications'}</strong>
                                </label>
                            </div>
                            <div class="checkbox" style="margin: 5px 0 0 0;">
                                <label>
                                    <input type="checkbox" name="accept_marketing_sms" value="1" {if $settings->accept_marketing_sms}checked{/if}>
                                    {$lang.accept_marketing|default:'I want to receive marketing and promotional SMS'}
                                </label>
                            </div>
                        </div>

                        <!-- Per-Type Notifications -->
                        <div id="notification_types" {if !$settings->accept_sms}style="opacity: 0.5; pointer-events: none;"{/if}>
                            <p class="text-muted"><small>{$lang.select_notifications|default:'Select which notifications you want to receive:'}</small></p>

                            {foreach from=$notification_types key=group_key item=group}
                            <div class="notification-group" style="margin-bottom: 15px;">
                                <h5 style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">
                                    {$group.label}
                                </h5>
                                {foreach from=$group.types key=type_key item=type_label}
                                <div class="checkbox" style="margin: 5px 0 5px 15px;">
                                    <label>
                                        <input type="checkbox" name="notifications[]" value="{$type_key}" {if in_array($type_key, $enabled_notifications)}checked{/if}>
                                        {$type_label}
                                    </label>
                                </div>
                                {/foreach}
                            </div>
                            {/foreach}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <button type="submit" name="save_preferences" class="btn btn-primary btn-lg">
                    <i class="fa fa-save"></i> {$lang.save_preferences|default:'Save Preferences'}
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('accept_sms').addEventListener('change', function() {
    var typesDiv = document.getElementById('notification_types');
    if (this.checked) {
        typesDiv.style.opacity = '1';
        typesDiv.style.pointerEvents = 'auto';
    } else {
        typesDiv.style.opacity = '0.5';
        typesDiv.style.pointerEvents = 'none';
    }
});
</script>

<style>
.sms-suite-preferences .panel {
    margin-bottom: 20px;
}
.sms-suite-preferences .notification-group h5 {
    font-weight: 600;
    color: #333;
}
.sms-suite-preferences .checkbox {
    margin-bottom: 8px;
}
.sms-suite-preferences .well {
    background-color: #f9f9f9;
}
</style>
