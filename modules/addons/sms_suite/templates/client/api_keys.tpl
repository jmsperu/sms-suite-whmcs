{$sms_css nofilter}
<div class="sms-suite-api-keys">
    <div class="sms-page-header">
        <h2><i class="fas fa-key"></i> {$lang.api_keys}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
        <li class="active"><a href="{$modulelink}&action=api_keys">{$lang.api_keys}</a></li>
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
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

    {* Show new key credentials - only shown once! *}
    {if $new_key}
    <div class="alert alert-warning">
        <h4 style="margin-top: 0;"><i class="fas fa-exclamation-triangle"></i> {$lang.api_key_warning}</h4>
        <p><strong>API Key ID:</strong> <code style="background: rgba(0,0,0,.06); padding: 3px 8px; border-radius: 4px; font-size: .9rem;">{$new_key.key_id}</code></p>
        <p><strong>API Secret:</strong> <code style="background: rgba(0,0,0,.06); padding: 3px 8px; border-radius: 4px; font-size: .9rem;">{$new_key.secret}</code></p>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('{$new_key.key_id}:{$new_key.secret}')">
            <i class="fas fa-copy"></i> Copy Credentials
        </button>
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8" style="margin-bottom: 24px;">
            <!-- Existing API Keys -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Your API Keys</h3>
                </div>
                <div class="card-body">
                    {if $api_keys && count($api_keys) > 0}
                    <div class="table-responsive">
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
                                    <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: .8rem;">{$key.key_id}</code></td>
                                    <td>
                                        {foreach $key.scopes as $scope}
                                        <span class="badge badge-info">{$scope}</span>
                                        {/foreach}
                                    </td>
                                    <td>{$key.rate_limit}/min</td>
                                    <td><small>{if $key.last_used_at}{$key.last_used_at}{else}{$lang.never}{/if}</small></td>
                                    <td>
                                        {if $key.status eq 'active'}
                                        <span class="badge badge-success">{$lang.active}</span>
                                        {else}
                                        <span class="badge badge-secondary">{$key.status|ucfirst}</span>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $key.status eq 'active'}
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to revoke this API key?');">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="revoke_key" value="1">
                                            <input type="hidden" name="key_id" value="{$key.id}">
                                            <button type="submit" class="btn btn-sm btn-danger">{$lang.api_key_revoke}</button>
                                        </form>
                                        {/if}
                                    </td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {else}
                    <div class="text-center text-muted" style="padding: 30px;">
                        <i class="fas fa-key" style="font-size: 2rem; color: #cbd5e1;"></i>
                        <p style="margin-top: 10px;">You haven't created any API keys yet.</p>
                    </div>
                    {/if}
                </div>
            </div>

            <!-- Create New Key -->
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> {$lang.api_key_create}</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <input type="hidden" name="create_key" value="1">

                        <div class="form-group">
                            <label for="key_name">{$lang.api_key_name} <span class="text-danger">*</span></label>
                            <input type="text" name="key_name" id="key_name" class="form-control"
                                   placeholder="e.g., My App Integration" required>
                        </div>

                        <div class="form-group">
                            <label>{$lang.api_key_scopes}</label>
                            <div style="max-height: 200px; overflow-y: auto; padding: 12px; border: 1px solid var(--sms-border, #e2e8f0); border-radius: 8px; background: #f8fafc;">
                                {foreach $available_scopes as $scope => $description}
                                <div class="checkbox" style="margin: 6px 0;">
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

        <!-- Sidebar -->
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> API Information</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 8px;"><strong>Base URL:</strong></p>
                    <code style="word-break: break-all; background: #f1f5f9; padding: 6px 10px; border-radius: 6px; display: block; font-size: .8rem; margin-bottom: 16px;">{$api_base_url}</code>

                    <p style="margin-bottom: 8px;"><strong>Authentication:</strong></p>
                    <p class="small" style="margin-bottom: 6px;">Send credentials via HTTP headers:</p>
                    <pre style="font-size: .75rem; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">X-API-Key: your_key_id
X-API-Secret: your_secret</pre>

                    <p class="small" style="margin-bottom: 6px;">Or Basic Auth:</p>
                    <pre style="font-size: .75rem; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">Authorization: Basic base64(key_id:secret)</pre>

                    <p style="margin-bottom: 8px; margin-top: 16px;"><strong>Request Format:</strong></p>
                    <p class="small">Send parameters as form-encoded body or query string.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-code"></i> Quick Examples</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 6px;"><strong>Send SMS:</strong></p>
                    <pre style="font-size: .7rem; white-space: pre-wrap; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">curl -X POST "{$api_base_url}?route=send" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET" \
  -d "to=+1234567890&message=Hello!"</pre>

                    <p style="margin-bottom: 6px;"><strong>Check Balance:</strong></p>
                    <pre style="font-size: .7rem; white-space: pre-wrap; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">curl "{$api_base_url}?route=balance" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET"</pre>

                    <p style="margin-bottom: 6px;"><strong>Get Messages:</strong></p>
                    <pre style="font-size: .7rem; white-space: pre-wrap; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">curl "{$api_base_url}?route=messages" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET"</pre>

                    <p style="margin-bottom: 6px;"><strong>Create Contact:</strong></p>
                    <pre style="font-size: .7rem; white-space: pre-wrap; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; border: none;">curl -X POST "{$api_base_url}?route=contacts" \
  -H "X-API-Key: YOUR_KEY" \
  -H "X-API-Secret: YOUR_SECRET" \
  -d "phone=+1234567890&first_name=John"</pre>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Available Endpoints</h3>
                </div>
                <div class="card-body" style="font-size: .8rem;">
                    <table class="table table-sm" style="margin-bottom: 0;">
                        <tr><td><code>GET</code></td><td><strong>balance</strong></td><td>Wallet balance</td></tr>
                        <tr><td><code>GET</code></td><td><strong>messages</strong></td><td>Message history</td></tr>
                        <tr><td><code>GET</code></td><td><strong>status</strong></td><td>Message status</td></tr>
                        <tr><td><code>GET</code></td><td><strong>contacts</strong></td><td>List contacts</td></tr>
                        <tr><td><code>POST</code></td><td><strong>contacts</strong></td><td>Create contact</td></tr>
                        <tr><td><code>GET</code></td><td><strong>segments</strong></td><td>Count segments</td></tr>
                        <tr><td><code>POST</code></td><td><strong>send</strong></td><td>Send SMS</td></tr>
                        <tr><td><code>POST</code></td><td><strong>send/bulk</strong></td><td>Bulk send</td></tr>
                        <tr><td><code>GET</code></td><td><strong>senderids</strong></td><td>List sender IDs</td></tr>
                        <tr><td><code>GET</code></td><td><strong>campaigns</strong></td><td>List campaigns</td></tr>
                        <tr><td><code>GET</code></td><td><strong>templates</strong></td><td>List templates</td></tr>
                        <tr><td><code>POST</code></td><td><strong>templates</strong></td><td>Create template</td></tr>
                        <tr><td><code>GET</code></td><td><strong>transactions</strong></td><td>Transactions</td></tr>
                        <tr><td><code>GET</code></td><td><strong>usage</strong></td><td>Usage stats</td></tr>
                    </table>
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
