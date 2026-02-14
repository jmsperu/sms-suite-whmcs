{$sms_css nofilter}
<div class="sms-suite-chatbot">
    <div class="sms-page-header">
        <h2><i class="fas fa-robot"></i> AI Chatbot</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li class="active"><a href="{$modulelink}&action=chatbot">AI Chatbot</a></li>
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
    </ul>

    {if $success}
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> {$success}</div>
    {/if}
    {if $error}
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> {$error}</div>
    {/if}

    {if !$ai_available}
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> AI chatbot is not enabled by the system administrator. Contact support if you'd like to use this feature.
        </div>
    {else}
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Enable AI-powered auto-replies on your gateways. When someone messages your bot or number, the AI will respond automatically based on your custom instructions.
            {if !$system_key_set}
                <br><strong>Note:</strong> No system AI key is configured. You'll need to provide your own API key below.
            {/if}
        </div>

        {if $gateways|@count > 0}
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title">Your Gateways</h4></div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Gateway</th>
                                <th>Type</th>
                                <th>Chatbot Status</th>
                                <th>AI Provider</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$gateway_configs item=gc}
                                <tr>
                                    <td>{$gc.gateway->name|escape:'html'}</td>
                                    <td><span class="label label-info">{$gc.gateway->type|escape:'html'}</span></td>
                                    <td>
                                        {if $gc.config && $gc.config->enabled}
                                            <span class="label label-success"><i class="fas fa-check"></i> Enabled</span>
                                        {else}
                                            <span class="label label-default">Disabled</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $gc.config && $gc.config->provider}
                                            <span class="label label-primary">{$gc.config->provider|escape:'html'}</span>
                                            {if $gc.config->api_key}
                                                <span class="label label-warning" title="Using own API key"><i class="fas fa-key"></i></span>
                                            {/if}
                                        {else}
                                            <span class="text-muted">System default</span>
                                        {/if}
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editChatbot({$gc.gateway->id})">
                                            <i class="fas fa-cog"></i> Configure
                                        </button>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Configuration form (shown when editing) -->
            {foreach from=$gateway_configs item=gc}
                <div id="chatbot-form-{$gc.gateway->id}" class="panel panel-default" style="display:none">
                    <div class="panel-heading">
                        <h4 class="panel-title"><i class="fas fa-robot"></i> Chatbot Settings: {$gc.gateway->name|escape:'html'}</h4>
                    </div>
                    <div class="panel-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <input type="hidden" name="save_chatbot" value="1">
                            <input type="hidden" name="gateway_id" value="{$gc.gateway->id}">

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="enabled" value="1" {if $gc.config && $gc.config->enabled}checked{/if}>
                                    Enable AI auto-replies for this gateway
                                </label>
                            </div>

                            <div class="form-group">
                                <label>AI Provider</label>
                                <select name="provider" class="form-control chatbot-provider-select" data-gateway="{$gc.gateway->id}" onchange="updateClientModels(this)">
                                    {foreach from=$providers key=pId item=pName}
                                        <option value="{$pId}" {if $gc.config && $gc.config->provider == $pId}selected{/if}>{$pName}</option>
                                    {/foreach}
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Model</label>
                                <select name="model" id="chatbot-model-{$gc.gateway->id}" class="form-control">
                                    <!-- populated by JS -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Your API Key <small class="text-muted">(optional — leave blank to use system key)</small></label>
                                <div class="input-group">
                                    <input type="password" name="api_key" class="form-control" placeholder="{if $gc.config && $gc.config->api_key}Key saved (enter new to replace){else}Paste your API key here...{/if}">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" onclick="this.closest('.input-group').querySelector('input').type = this.closest('.input-group').querySelector('input').type === 'password' ? 'text' : 'password'">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </span>
                                </div>
                                {if $gc.config && $gc.config->api_key}
                                    <label class="checkbox-inline" style="margin-top:5px">
                                        <input type="checkbox" name="clear_api_key" value="1"> Remove saved key (use system key instead)
                                    </label>
                                {/if}
                                <p class="help-block">Provide your own API key to use your own AI account. If left blank, the system's API key will be used.</p>
                            </div>

                            <div class="form-group">
                                <label>Channels</label><br>
                                {assign var="cfg_channels" value=","|explode:($gc.config->channels|default:'whatsapp,telegram')}
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="channels[]" value="sms" {if 'sms'|in_array:$cfg_channels}checked{/if}> SMS
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="channels[]" value="whatsapp" {if 'whatsapp'|in_array:$cfg_channels}checked{/if}> WhatsApp
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="channels[]" value="telegram" {if 'telegram'|in_array:$cfg_channels}checked{/if}> Telegram
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="system_prompt_{$gc.gateway->id}">Custom Instructions</label>
                                <textarea name="system_prompt" id="system_prompt_{$gc.gateway->id}" class="form-control" rows="5"
                                    placeholder="Tell the AI about your business, how to respond, what tone to use...">{$gc.config->system_prompt|default:''|escape:'html'}</textarea>
                                <p class="help-block">Customize how the AI responds to your customers. Leave blank to use the system default.</p>
                            </div>

                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                            <button type="button" class="btn btn-default" onclick="document.getElementById('chatbot-form-{$gc.gateway->id}').style.display='none'">Cancel</button>
                        </form>
                    </div>
                </div>
            {/foreach}
        {else}
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> You don't have any gateways yet. Create a gateway first to configure chatbot auto-replies.
            </div>
        {/if}
    {/if}
</div>

<script>
var allModels = {$all_models_json nofilter};

function editChatbot(gatewayId) {
    // Hide all forms
    document.querySelectorAll('[id^="chatbot-form-"]').forEach(function(el) {
        el.style.display = 'none';
    });
    // Show selected
    var form = document.getElementById('chatbot-form-' + gatewayId);
    if (form) {
        form.style.display = 'block';
        // Initialize model dropdown
        var select = form.querySelector('.chatbot-provider-select');
        if (select) updateClientModels(select);
        form.scrollIntoView({ behavior: 'smooth' });
    }
}

function updateClientModels(selectEl) {
    var gatewayId = selectEl.getAttribute('data-gateway');
    var provider = selectEl.value;
    var modelSelect = document.getElementById('chatbot-model-' + gatewayId);
    var models = allModels[provider] || {};
    modelSelect.innerHTML = '';
    for (var id in models) {
        var opt = document.createElement('option');
        opt.value = id;
        opt.textContent = models[id];
        modelSelect.appendChild(opt);
    }
}

// Initialize model selects for any visible forms
document.querySelectorAll('.chatbot-provider-select').forEach(function(sel) {
    updateClientModels(sel);
});
</script>
