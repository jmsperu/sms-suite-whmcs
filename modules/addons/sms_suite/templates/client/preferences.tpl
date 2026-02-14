{$sms_css nofilter}
<div class="sms-suite-preferences">
    <div class="sms-page-header">
        <h2><i class="fas fa-cog"></i> {$lang.preferences|default:'Notification Preferences'}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
        <li class="active"><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <form method="post" action="{$modulelink}&action=preferences">
        <input type="hidden" name="csrf_token" value="{$csrf_token}">

        <div class="row">
            <!-- Phone Number & Verification -->
            <div class="col-md-6" style="margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-phone"></i> {$lang.phone_settings|default:'Phone Number & Verification'}</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>{$lang.phone_number|default:'Phone Number'}</label>
                            <div class="input-group">
                                <input type="text" name="phone_number" class="form-control" value="{$client->phonenumber}" placeholder="+1234567890">
                                <span class="input-group-addon">
                                    {if $phone_verified}
                                    <span style="color: #00c853;"><i class="fas fa-check-circle"></i> {$lang.verified|default:'Verified'}</span>
                                    {else}
                                    <span style="color: #ff9800;"><i class="fas fa-exclamation-circle"></i> {$lang.not_verified|default:'Not Verified'}</span>
                                    {/if}
                                </span>
                            </div>
                            <span class="form-text text-muted">{$lang.phone_help|default:'Enter your phone number to receive SMS notifications'}</span>
                        </div>

                        {if !$phone_verified && $client->phonenumber}
                        <div class="form-group">
                            <button type="submit" name="verify_phone" class="btn btn-warning btn-sm">
                                <i class="fas fa-mobile-alt"></i> {$lang.send_verification|default:'Send Verification Code'}
                            </button>
                        </div>
                        <div class="form-group">
                            <label>{$lang.verification_code|default:'Verification Code'}</label>
                            <div class="input-group">
                                <input type="text" name="verification_code" class="form-control" placeholder="Enter 6-digit code" maxlength="6">
                                <span class="input-group-btn">
                                    <button type="submit" name="confirm_verification" class="btn btn-success">
                                        <i class="fas fa-check"></i> {$lang.verify|default:'Verify'}
                                    </button>
                                </span>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-shield-alt"></i> {$lang.two_factor_auth|default:'Two-Factor Authentication'}</h3>
                    </div>
                    <div class="card-body">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="two_factor_enabled" value="1" {if $settings->two_factor_enabled}checked{/if} {if !$phone_verified}disabled{/if}>
                                <strong>{$lang.enable_2fa|default:'Enable SMS Two-Factor Authentication'}</strong>
                            </label>
                            <span class="form-text text-muted">{$lang.2fa_help|default:'Require SMS verification code when logging in for extra security'}</span>
                        </div>
                        {if !$phone_verified}
                        <div class="alert alert-warning" style="margin-top: 10px; margin-bottom: 0;">
                            <i class="fas fa-info-circle"></i> {$lang.verify_phone_first|default:'Please verify your phone number to enable two-factor authentication.'}
                        </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="col-md-6" style="margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bell"></i> {$lang.sms_notifications|default:'SMS Notifications'}</h3>
                    </div>
                    <div class="card-body">
                        <!-- Global Opt-In/Out -->
                        <div style="background: #f8fafc; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
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
                            <p class="text-muted" style="font-size: .8rem; margin-bottom: 12px;">{$lang.select_notifications|default:'Select which notifications you want to receive:'}</p>

                            {foreach from=$notification_types key=group_key item=group}
                            <div style="margin-bottom: 16px;">
                                <h5 style="border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 10px; font-weight: 600; font-size: .9rem; color: #1e293b;">
                                    {$group.label}
                                </h5>
                                {foreach from=$group.types key=type_key item=type_label}
                                <div class="checkbox" style="margin: 6px 0 6px 16px;">
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

                <!-- WhatsApp Notification Preferences -->
                <div class="card" style="margin-top: 16px;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fab fa-whatsapp"></i> {$lang.wa_notifications|default:'WhatsApp Notifications'}</h3>
                    </div>
                    <div class="card-body">
                        <div style="background: #e8f5e9; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                            <div class="checkbox" style="margin: 0;">
                                <label>
                                    <input type="checkbox" name="accept_whatsapp" value="1" id="accept_whatsapp" {if $settings->accept_whatsapp}checked{/if}>
                                    <strong>{$lang.accept_whatsapp|default:'Receive notifications via WhatsApp'}</strong>
                                </label>
                            </div>
                            <span class="form-text text-muted" style="display: block; margin-top: 6px; font-size: .8rem;">
                                {$lang.wa_notif_help|default:'When enabled, important notifications (invoices, orders, tickets) will also be sent to your WhatsApp number.'}
                            </span>
                        </div>

                        <div id="whatsapp_number_field" {if !$settings->accept_whatsapp}style="opacity: 0.5; pointer-events: none;"{/if}>
                            <div class="form-group">
                                <label>{$lang.whatsapp_number|default:'WhatsApp Number'}</label>
                                <input type="text" name="whatsapp_number" class="form-control" value="{$settings->whatsapp_number}" placeholder="+1234567890">
                                <span class="form-text text-muted">{$lang.wa_number_help|default:'Leave blank to use your SMS phone number above.'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <button type="submit" name="save_preferences" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> {$lang.save_preferences|default:'Save Preferences'}
                </button>
            </div>
        </div>
    </form>

    <!-- WhatsApp Business Configuration -->
    <div class="row" style="margin-top: 30px;">
        <div class="col-md-6" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="display: inline-block;">
                        <i class="fab fa-whatsapp"></i> {$lang.wa_config_title|default:'WhatsApp Business Configuration'}
                    </h3>
                    {if $wa_gateway}
                        {if $wa_gateway->status}
                            <span class="label label-success" style="margin-left: 10px;"><i class="fas fa-check"></i> {$lang.wa_status_active|default:'Active'}</span>
                        {else}
                            <span class="label label-warning" style="margin-left: 10px;"><i class="fas fa-clock"></i> {$lang.wa_status_pending|default:'Pending Approval'}</span>
                        {/if}
                    {/if}
                </div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom: 16px;">
                        {$lang.wa_config_help|default:'Connect your own Meta WhatsApp Business account to send messages using your own number. Credentials require admin approval before activation.'}
                    </p>

                    {if $meta_configured}
                    <div style="background: #e3f2fd; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: center;">
                        <p style="margin-bottom: 12px; color: #1565c0; font-weight: 600;">
                            <i class="fab fa-facebook"></i> Connect with one click — no need to copy credentials manually
                        </p>
                        <button type="button" onclick="launchWhatsAppSignup()" class="btn btn-primary btn-lg">
                            <i class="fab fa-facebook"></i> Connect WhatsApp Account
                        </button>
                        <div id="es_status" style="margin-top: 10px;"></div>
                    </div>
                    <p class="text-muted text-center" style="margin-bottom: 16px; font-size: .85rem;">— or enter credentials manually below —</p>
                    {/if}

                    <form method="post" action="{$modulelink}&action=preferences">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">

                        <div class="form-group">
                            <label>{$lang.wa_phone_number_id|default:'Phone Number ID'}</label>
                            <input type="text" name="wa_phone_number_id" id="wa_phone_number_id" class="form-control" value="{$wa_config.phone_number_id}" placeholder="e.g. 123456789012345">
                            <span class="form-text text-muted">{$lang.wa_phone_number_id_help|default:'From Meta Business Suite > WhatsApp > API Setup'}</span>
                        </div>

                        <div class="form-group">
                            <label>{$lang.wa_access_token|default:'Access Token'}</label>
                            <input type="password" name="wa_access_token" id="wa_access_token" class="form-control" placeholder="{if $wa_config.access_token_masked}{$wa_config.access_token_masked}{else}Enter your permanent access token{/if}">
                            {if $wa_config.access_token_masked}
                            <span class="form-text text-muted">{$lang.wa_token_current|default:'Current token:'} {$wa_config.access_token_masked}</span>
                            {/if}
                        </div>

                        <div class="form-group">
                            <label>{$lang.wa_waba_id|default:'WABA ID'}</label>
                            <input type="text" name="wa_waba_id" id="wa_waba_id" class="form-control" value="{$wa_config.waba_id}" placeholder="e.g. 123456789012345">
                            <span class="form-text text-muted">{$lang.wa_waba_id_help|default:'WhatsApp Business Account ID from Meta Business Settings'}</span>
                        </div>

                        <div style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="submit" name="save_whatsapp_gateway" class="btn btn-success">
                                <i class="fab fa-whatsapp"></i> {$lang.wa_save|default:'Save Configuration'}
                            </button>
                            {if $wa_gateway}
                            <button type="submit" name="register_whatsapp_phone" class="btn btn-warning">
                                <i class="fas fa-phone"></i> Register Phone
                            </button>
                            <button type="submit" name="test_whatsapp_gateway" class="btn btn-info">
                                <i class="fas fa-plug"></i> {$lang.wa_test|default:'Test Connection'}
                            </button>
                            <button type="submit" name="delete_whatsapp_gateway" class="btn btn-danger" onclick="return confirm('{$lang.wa_confirm_delete|default:'Remove your WhatsApp Business configuration?'}');">
                                <i class="fas fa-trash"></i> {$lang.wa_remove|default:'Remove'}
                            </button>
                            {/if}
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6" style="margin-bottom: 24px;">
            {if $wa_gateway}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> {$lang.wa_status_title|default:'Gateway Status'}</h3>
                </div>
                <div class="card-body">
                    <table class="table" style="margin-bottom: 0;">
                        <tr>
                            <td style="width: 40%; font-weight: 600;">{$lang.wa_label_status|default:'Status'}</td>
                            <td>
                                {if $wa_gateway->status}
                                    <span class="label label-success"><i class="fas fa-check"></i> {$lang.wa_status_active|default:'Active'}</span>
                                {else}
                                    <span class="label label-warning"><i class="fas fa-clock"></i> {$lang.wa_status_pending|default:'Pending Approval'}</span>
                                {/if}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">{$lang.wa_label_type|default:'Gateway Type'}</td>
                            <td>Meta WhatsApp Cloud API</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">{$lang.wa_label_channel|default:'Channel'}</td>
                            <td><span class="label label-success"><i class="fab fa-whatsapp"></i> WhatsApp</span></td>
                        </tr>
                    </table>
                </div>
            </div>
            {/if}

            {if $wa_webhook_url}
            <div class="card" {if $wa_gateway}style="margin-top: 16px;"{/if}>
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-link"></i> {$lang.wa_callback_title|default:'Webhook Callback URL'}</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size: .85rem; margin-bottom: 10px;">
                        {$lang.wa_callback_help|default:'Paste this URL in Meta Business Suite > WhatsApp > Configuration > Webhook URL for delivery receipts and inbound messages.'}
                    </p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="wa_webhook_url" value="{$wa_webhook_url}" readonly style="background: #fff; font-family: monospace; font-size: .85rem;">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="button" onclick="var el=document.getElementById('wa_webhook_url');el.select();document.execCommand('copy');this.innerHTML='<i class=\'fas fa-check\'></i> Copied!';">
                                <i class="fas fa-copy"></i> {$lang.wa_copy|default:'Copy'}
                            </button>
                        </span>
                    </div>
                </div>
            </div>
            {/if}

            {if !$wa_gateway}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> {$lang.wa_setup_title|default:'Setup Guide'}</h3>
                </div>
                <div class="card-body">
                    <ol style="padding-left: 18px; margin-bottom: 0; line-height: 2;">
                        <li>{$lang.wa_step1|default:'Go to <strong>Meta Business Suite</strong> and set up a WhatsApp Business account'}</li>
                        <li>{$lang.wa_step2|default:'Navigate to <strong>WhatsApp > API Setup</strong> to get your credentials'}</li>
                        <li>{$lang.wa_step3|default:'Enter your <strong>Phone Number ID</strong>, <strong>Access Token</strong>, and <strong>WABA ID</strong>'}</li>
                        <li>{$lang.wa_step4|default:'Click <strong>Save</strong> and wait for admin approval'}</li>
                        <li>{$lang.wa_step5|default:'Once approved, configure the <strong>Webhook URL</strong> in Meta'}</li>
                    </ol>
                </div>
            </div>
            {/if}
        </div>
    </div>
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

document.getElementById('accept_whatsapp').addEventListener('change', function() {
    var waField = document.getElementById('whatsapp_number_field');
    if (this.checked) {
        waField.style.opacity = '1';
        waField.style.pointerEvents = 'auto';
    } else {
        waField.style.opacity = '0.5';
        waField.style.pointerEvents = 'none';
    }
});
</script>

{if $meta_configured}
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<script>
window.fbAsyncInit = function() {
    FB.init({
        appId: '{$meta_app_id}',
        autoLogAppEvents: true,
        xfbml: true,
        version: 'v24.0'
    });
};

window.addEventListener('message', function(event) {
    if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
    try {
        var data = JSON.parse(event.data);
        if (data.type === 'WA_EMBEDDED_SIGNUP') {
            var d = data.data;
            if (d.phone_number_id) {
                var f = document.getElementById('wa_phone_number_id');
                if (f) f.value = d.phone_number_id;
                esStatus('<i class="fas fa-check text-success"></i> Phone Number ID captured');
            }
            if (d.waba_id) {
                var f = document.getElementById('wa_waba_id');
                if (f) f.value = d.waba_id;
            }
        }
    } catch(e) {}
});

function launchWhatsAppSignup() {
    esStatus('<i class="fas fa-spinner fa-spin"></i> Opening Meta signup...');
    FB.login(function(response) {
        if (response.authResponse) {
            esStatus('<i class="fas fa-spinner fa-spin"></i> Exchanging token...');
            exchangeCodeForToken(response.authResponse.code);
        } else {
            esStatus('<i class="fas fa-times text-danger"></i> Signup cancelled or failed');
        }
    }, {
        config_id: '{$meta_config_id}',
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            setup: {},
            featureType: '',
            sessionInfoVersion: 3
        }
    });
}

function exchangeCodeForToken(code) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '{$modulelink}&action=ajax_meta_token_exchange', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && resp.access_token) {
                    var f = document.getElementById('wa_access_token');
                    if (f) { f.value = resp.access_token; f.type = 'text'; }
                    esStatus('<i class="fas fa-check-circle text-success"></i> <strong>Connected!</strong> Credentials populated. Click <strong>Save Configuration</strong> to finish.');
                } else {
                    esStatus('<i class="fas fa-times text-danger"></i> ' + (resp.error || 'Token exchange failed'));
                }
            } catch(e) {
                esStatus('<i class="fas fa-times text-danger"></i> Unexpected response from server');
            }
        }
    };
    xhr.send('code=' + encodeURIComponent(code) + '&csrf_token={$csrf_token}');
}

function esStatus(html) {
    var el = document.getElementById('es_status');
    if (el) el.innerHTML = html;
}
</script>
{/if}
