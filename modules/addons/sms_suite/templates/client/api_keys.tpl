<div class="sms-suite-api-keys">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.api_keys}</h2>
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
                <li class="active"><a href="{$modulelink}&action=api_keys">{$lang.api_keys}</a></li>
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

    {* Show new key credentials - only shown once! *}
    {if $new_key}
    <div class="alert alert-warning">
        <h4><i class="fas fa-exclamation-triangle"></i> {$lang.api_key_warning}</h4>
        <p><strong>API Key ID:</strong> <code>{$new_key.key_id}</code></p>
        <p><strong>API Secret:</strong> <code>{$new_key.secret}</code></p>
        <button type="button" class="btn btn-sm btn-default" onclick="copyToClipboard('{$new_key.key_id}:{$new_key.secret}')">
            <i class="fas fa-copy"></i> Copy Credentials
        </button>
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8">
            <!-- Existing API Keys -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Your API Keys</h3>
                </div>
                <div class="panel-body">
                    {if $api_keys && count($api_keys) > 0}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Key ID</th>
                                <th>Scopes</th>
                                <th>Rate Limit</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $api_keys as $key}
                            <tr>
                                <td><strong>{$key.name|escape:'html'}</strong></td>
                                <td><code>{$key.key_id}</code></td>
                                <td>
                                    {foreach $key.scopes as $scope}
                                    <span class="label label-info">{$scope}</span>
                                    {/foreach}
                                </td>
                                <td>{$key.rate_limit}/min</td>
                                <td>{if $key.last_used_at}{$key.last_used_at}{else}{$lang.never}{/if}</td>
                                <td>
                                    {if $key.status eq 'active'}
                                    <span class="label label-success">{$lang.active}</span>
                                    {else}
                                    <span class="label label-default">{$key.status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $key.status eq 'active'}
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to revoke this API key?');">
                                        <input type="hidden" name="revoke_key" value="1">
                                        <input type="hidden" name="key_id" value="{$key.id}">
                                        <button type="submit" class="btn btn-xs btn-danger">{$lang.api_key_revoke}</button>
                                    </form>
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted">You haven't created any API keys yet.</p>
                    {/if}
                </div>
            </div>

            <!-- Create New Key -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.api_key_create}</h3>
                </div>
                <div class="panel-body">
                    <form method="post">
                        <input type="hidden" name="create_key" value="1">

                        <div class="form-group">
                            <label for="key_name">{$lang.api_key_name} <span class="text-danger">*</span></label>
                            <input type="text" name="key_name" id="key_name" class="form-control"
                                   placeholder="e.g., My App Integration" required>
                        </div>

                        <div class="form-group">
                            <label>{$lang.api_key_scopes}</label>
                            <div class="checkbox-group">
                                {foreach $available_scopes as $scope => $description}
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="scopes[]" value="{$scope}"
                                               {if $scope eq 'send_sms' || $scope eq 'balance' || $scope eq 'logs'}checked{/if}>
                                        <strong>{$scope}</strong> - {$description}
                                    </label>
                                </div>
                                {/foreach}
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rate_limit">{$lang.api_key_rate_limit}</label>
                            <select name="rate_limit" id="rate_limit" class="form-control">
                                <option value="30">30 requests/minute</option>
                                <option value="60" selected>60 requests/minute</option>
                                <option value="120">120 requests/minute</option>
                                <option value="300">300 requests/minute</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> {$lang.api_key_create}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar - API Documentation -->
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">API Information</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Base URL:</strong></p>
                    <code style="word-break: break-all;">{$api_base_url}</code>

                    <hr>

                    <p><strong>Authentication:</strong></p>
                    <p class="small">Use HTTP headers:</p>
                    <pre style="font-size: 11px;">X-API-Key: your_key_id
X-API-Secret: your_secret</pre>

                    <p class="small">Or Basic Auth:</p>
                    <pre style="font-size: 11px;">Authorization: Basic base64(key_id:secret)</pre>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Quick Examples</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Send SMS (cURL):</strong></p>
                    <pre style="font-size: 10px; white-space: pre-wrap;">curl -X POST "{$api_base_url}?endpoint=send" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET" \
  -H "Content-Type: application/json" \
  -d '{literal}{"to":"+1234567890","message":"Hello!"}{/literal}'</pre>

                    <p><strong>Check Balance:</strong></p>
                    <pre style="font-size: 10px; white-space: pre-wrap;">curl "{$api_base_url}?endpoint=balance" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET"</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Credentials copied to clipboard!');
        });
    } else {
        // Fallback
        var textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Credentials copied to clipboard!');
    }
}
</script>

<style>
.sms-suite-api-keys code {
    background-color: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
}
.sms-suite-api-keys pre {
    background-color: #f8f8f8;
    border: 1px solid #e1e1e1;
    padding: 10px;
    overflow-x: auto;
}
.sms-suite-api-keys .checkbox-group {
    max-height: 200px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>
