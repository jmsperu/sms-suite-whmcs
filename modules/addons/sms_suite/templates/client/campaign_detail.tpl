{$sms_css nofilter}
<div class="sms-suite-campaigns">
    <div class="sms-page-header">
        <h2><i class="fas fa-bullhorn"></i> {$campaign->name|escape:'html'}</h2>
        <div>
            <a href="{$modulelink}&action=campaigns" class="btn btn-outline-secondary" style="padding:8px 18px;">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li class="active"><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle"></i> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i> {$error}
    </div>
    {/if}

    <!-- Campaign Status Bar -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;padding:16px 24px;">
            <div>
                <small style="color:#64748b;">Status</small><br>
                {if $campaign->status eq 'completed'}
                <span class="badge badge-success" style="font-size:.85rem;padding:6px 12px;">Completed</span>
                {elseif $campaign->status eq 'sending'}
                <span class="badge badge-info" style="font-size:.85rem;padding:6px 12px;">Sending</span>
                {elseif $campaign->status eq 'scheduled'}
                <span class="badge badge-warning" style="font-size:.85rem;padding:6px 12px;">Scheduled</span>
                {elseif $campaign->status eq 'queued'}
                <span class="badge badge-warning" style="font-size:.85rem;padding:6px 12px;">Queued</span>
                {elseif $campaign->status eq 'paused'}
                <span class="badge badge-secondary" style="font-size:.85rem;padding:6px 12px;">Paused</span>
                {elseif $campaign->status eq 'cancelled'}
                <span class="badge badge-secondary" style="font-size:.85rem;padding:6px 12px;">Cancelled</span>
                {elseif $campaign->status eq 'failed'}
                <span class="badge badge-danger" style="font-size:.85rem;padding:6px 12px;">Failed</span>
                {else}
                <span class="badge badge-secondary" style="font-size:.85rem;padding:6px 12px;">Draft</span>
                {/if}
            </div>
            <div>
                <small style="color:#64748b;">Recipients</small><br>
                <strong>{$campaign->total_recipients|number_format:0}</strong>
            </div>
            <div>
                <small style="color:#64748b;">Sent</small><br>
                <strong style="color:#00c853;">{$campaign->sent_count|number_format:0}</strong>
            </div>
            <div>
                <small style="color:#64748b;">Failed</small><br>
                <strong style="color:#ef4444;">{$campaign->failed_count|number_format:0}</strong>
            </div>
            <div>
                <small style="color:#64748b;">Delivered</small><br>
                <strong style="color:#155dfc;">{$campaign->delivered_count|number_format:0}</strong>
            </div>
            <div>
                <small style="color:#64748b;">Created</small><br>
                <strong>{$campaign->created_at}</strong>
            </div>

            {if $is_editable}
            <div style="margin-left:auto;">
                <form method="post" style="display:inline;" onsubmit="return confirm('Send this campaign now?');">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="send_campaign" value="1">
                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                    <button type="submit" class="btn btn-success" style="padding:10px 22px;">
                        <i class="fas fa-paper-plane"></i> Send Now
                    </button>
                </form>
            </div>
            {/if}
        </div>
    </div>

    {if $is_editable}
    <!-- Editable Campaign Form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-edit"></i> Edit Campaign</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="update_campaign" value="1">
                <input type="hidden" name="campaign_id" value="{$campaign->id}">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{$campaign->name|escape:'html'}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Channel</label>
                            <select name="channel" class="form-control">
                                <option value="sms" {if $campaign->channel eq 'sms'}selected{/if}>SMS</option>
                                <option value="whatsapp" {if $campaign->channel eq 'whatsapp'}selected{/if}>WhatsApp</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required>{$campaign->message|escape:'html'}</textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sender ID</label>
                            <select name="sender_id" class="form-control">
                                <option value="">Default</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}" {if $campaign->sender_id eq $sid->sender_id}selected{/if}>{$sid->sender_id|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gateway</label>
                            <select name="gateway_id" class="form-control">
                                <option value="">Default</option>
                                {foreach $gateways as $gw}
                                <option value="{$gw->id}" {if $campaign->gateway_id eq $gw->id}selected{/if}>{$gw->name|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Recipients</label>
                    <div style="display:flex;gap:8px;margin-bottom:12px;">
                        <label style="flex:1;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid {if $campaign->recipient_type neq 'group'}#667eea{else}transparent{/if};transition:all .2s;" id="manualLabel">
                            <input type="radio" name="recipient_type" value="manual" {if $campaign->recipient_type neq 'group'}checked{/if} style="display:none;">
                            <i class="fas fa-keyboard"></i> Manual Entry
                        </label>
                        <label style="flex:1;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid {if $campaign->recipient_type eq 'group'}#667eea{else}transparent{/if};transition:all .2s;" id="groupLabel">
                            <input type="radio" name="recipient_type" value="group" {if $campaign->recipient_type eq 'group'}checked{/if} style="display:none;">
                            <i class="fas fa-users"></i> Contact Group
                        </label>
                    </div>

                    <div id="manualRecipients" {if $campaign->recipient_type eq 'group'}style="display:none;"{/if}>
                        <textarea name="recipients" class="form-control" rows="4" placeholder="Enter phone numbers (one per line or comma-separated)">{$recipients_text}</textarea>
                        <small class="text-muted">{$recipient_list|count} number(s) loaded</small>
                    </div>

                    <div id="groupRecipients" {if $campaign->recipient_type neq 'group'}style="display:none;"{/if}>
                        <select name="group_id" class="form-control">
                            <option value="">-- Select Group --</option>
                            {foreach $groups as $group}
                            <option value="{$group->id}" {if $campaign->recipient_group_id eq $group->id}selected{/if}>{$group->name|escape:'html'} ({$group->contact_count} contacts)</option>
                            {/foreach}
                        </select>
                    </div>
                </div>

                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <a href="{$modulelink}&action=campaigns" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    {else}
    <!-- Read-only Campaign Details -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-info-circle"></i> Campaign Details</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Channel:</strong> {$campaign->channel|upper}</p>
                    <p><strong>Sender ID:</strong> {$campaign->sender_id|default:'Default'}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Recipient Type:</strong> {$campaign->recipient_type|default:'manual'|ucfirst}</p>
                    <p><strong>Total Recipients:</strong> {$campaign->total_recipients|number_format:0}</p>
                </div>
            </div>
            <div class="form-group">
                <strong>Message:</strong>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-top:6px;white-space:pre-wrap;">{$campaign->message|escape:'html'}</div>
            </div>
        </div>
    </div>
    {/if}

    {if $messages && count($messages) > 0}
    <!-- Message Log -->
    <div class="card" style="margin-top:20px;">
        <div class="card-header">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-list-alt"></i> Message Log ({$messages|count})</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>To</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $messages as $msg}
                        <tr>
                            <td>{$msg->to_number|escape:'html'}</td>
                            <td>
                                {if $msg->status eq 'delivered'}
                                <span class="badge badge-success">Delivered</span>
                                {elseif $msg->status eq 'sent'}
                                <span class="badge badge-info">Sent</span>
                                {elseif $msg->status eq 'failed'}
                                <span class="badge badge-danger">Failed</span>
                                {else}
                                <span class="badge badge-secondary">{$msg->status|escape:'html'}</span>
                                {/if}
                            </td>
                            <td><small>{$msg->created_at}</small></td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {/if}
</div>

{literal}
<script>
(function() {
    // Toggle recipient input type
    var labels = document.querySelectorAll('#manualLabel, #groupLabel');
    if (labels.length) {
        labels.forEach(function(label) {
            label.addEventListener('click', function() {
                var radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                labels.forEach(function(l) { l.style.borderColor = 'transparent'; l.style.background = '#f1f5f9'; });
                this.style.borderColor = '#667eea';
                this.style.background = 'rgba(102,126,234,.08)';
                document.getElementById('manualRecipients').style.display = radio.value === 'manual' ? 'block' : 'none';
                document.getElementById('groupRecipients').style.display = radio.value === 'group' ? 'block' : 'none';
            });
        });
    }
})();
</script>
{/literal}
