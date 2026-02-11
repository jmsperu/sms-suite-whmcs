{$sms_css nofilter}
<div class="sms-suite-campaigns">
    <div class="sms-page-header">
        <h2><i class="fas fa-bullhorn"></i> {$lang.campaigns}</h2>
        <div>
            <button type="button" class="btn btn-primary" id="btnCreateCampaign" style="padding:10px 22px;font-size:.95rem">
                <i class="fas fa-plus"></i> {$lang.campaign_create}
            </button>
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
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
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

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> Your Campaigns</h3>
        </div>
        <div class="card-body">
            {if $campaigns && count($campaigns) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.campaign_name}</th>
                            <th>{$lang.channel}</th>
                            <th>{$lang.campaign_recipients}</th>
                            <th>Progress</th>
                            <th>{$lang.status}</th>
                            <th>{$lang.created}</th>
                            <th>{$lang.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $campaigns as $campaign}
                        <tr>
                            <td><a href="{$modulelink}&action=campaigns&campaign_id={$campaign->id}" style="color:var(--sms-primary,#667eea);font-weight:600;text-decoration:none;"><strong>{$campaign->name|escape:'html'}</strong></a></td>
                            <td>
                                {if $campaign->channel eq 'whatsapp'}
                                <span class="badge badge-success">WhatsApp</span>
                                {else}
                                <span class="badge badge-info">SMS</span>
                                {/if}
                            </td>
                            <td>{$campaign->total_recipients|number_format:0}</td>
                            <td>
                                {if $campaign->total_recipients > 0}
                                {assign var="progress" value=(($campaign->sent_count + $campaign->failed_count) / $campaign->total_recipients * 100)}
                                <div class="progress" style="margin: 0; min-width: 100px;">
                                    <div class="progress-bar bg-success" style="width: {($campaign->sent_count / $campaign->total_recipients * 100)|round}%"></div>
                                    <div class="progress-bar progress-bar-danger" style="width: {($campaign->failed_count / $campaign->total_recipients * 100)|round}%"></div>
                                </div>
                                <small class="text-muted">{$campaign->sent_count} sent, {$campaign->failed_count} failed</small>
                                {else}
                                -
                                {/if}
                            </td>
                            <td>
                                {if $campaign->status eq 'completed'}
                                <span class="badge badge-success">{$lang.campaign_completed}</span>
                                {elseif $campaign->status eq 'sending'}
                                <span class="badge badge-info">{$lang.campaign_sending}</span>
                                {elseif $campaign->status eq 'scheduled'}
                                <span class="badge badge-warning">{$lang.campaign_scheduled}</span>
                                {elseif $campaign->status eq 'queued'}
                                <span class="badge badge-warning">{$lang.campaign_queued}</span>
                                {elseif $campaign->status eq 'paused'}
                                <span class="badge badge-secondary">{$lang.campaign_paused}</span>
                                {elseif $campaign->status eq 'cancelled'}
                                <span class="badge badge-secondary">{$lang.campaign_cancelled}</span>
                                {elseif $campaign->status eq 'failed'}
                                <span class="badge badge-danger">{$lang.campaign_failed}</span>
                                {else}
                                <span class="badge badge-secondary">{$lang.campaign_draft}</span>
                                {/if}
                            </td>
                            <td><small>{$campaign->created_at|date_format:"%Y-%m-%d"}</small></td>
                            <td style="white-space:nowrap;">
                                {if $campaign->status eq 'draft' || $campaign->status eq 'scheduled'}
                                <a href="{$modulelink}&action=campaigns&campaign_id={$campaign->id}" class="btn btn-sm btn-info" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Send this campaign now?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="send_campaign" value="1">
                                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                                    <button type="submit" class="btn btn-sm btn-success" title="Send Now">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </form>
                                {/if}

                                {if $campaign->status eq 'sending'}
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="pause_campaign" value="1">
                                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                                    <button type="submit" class="btn btn-sm btn-warning" title="Pause">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                </form>
                                {elseif $campaign->status eq 'paused'}
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="resume_campaign" value="1">
                                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                                    <button type="submit" class="btn btn-sm btn-success" title="Resume">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </form>
                                {/if}

                                {if $campaign->status eq 'draft' || $campaign->status eq 'cancelled'}
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this campaign?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_campaign" value="1">
                                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                {elseif $campaign->status neq 'completed' && $campaign->status neq 'cancelled'}
                                <form method="post" style="display:inline;" onsubmit="return confirm('Cancel this campaign?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="cancel_campaign" value="1">
                                    <input type="hidden" name="campaign_id" value="{$campaign->id}">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fas fa-bullhorn" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p style="margin-top: 12px;">No campaigns yet. Create your first bulk messaging campaign.</p>
            </div>
            {/if}
        </div>
    </div>

    <!-- Create Campaign Panel (hidden by default) -->
    <div id="createCampaignPanel" class="card" style="display:none;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-bullhorn"></i> {$lang.campaign_create}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelCampaign">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="create_campaign" value="1">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.campaign_name} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Campaign name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.channel}</label>
                            <select name="channel" class="form-control">
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>{$lang.campaign_message} <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Type your message..."></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.sender_id}</label>
                            <select name="sender_id" class="form-control">
                                <option value="">{$lang.default_sender}</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}">{$sid->sender_id|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.gateway}</label>
                            <select name="gateway_id" class="form-control">
                                <option value="">{$lang.default_gateway}</option>
                                {foreach $gateways as $gw}
                                <option value="{$gw->id}">{$gw->name|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>{$lang.campaign_recipients}</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                        <label style="flex:1;min-width:120px;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid #667eea;transition:all .2s;" class="recipientTypeLabel" data-type="manual">
                            <input type="radio" name="recipient_type" value="manual" checked style="display:none;">
                            <i class="fas fa-keyboard"></i> Manual Entry
                        </label>
                        <label style="flex:1;min-width:120px;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all .2s;" class="recipientTypeLabel" data-type="group">
                            <input type="radio" name="recipient_type" value="group" style="display:none;">
                            <i class="fas fa-users"></i> Group
                        </label>
                        <label style="flex:1;min-width:120px;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all .2s;" class="recipientTypeLabel" data-type="segment">
                            <input type="radio" name="recipient_type" value="segment" style="display:none;">
                            <i class="fas fa-filter"></i> Segment
                        </label>
                        <label style="flex:1;min-width:120px;text-align:center;padding:10px;background:#f1f5f9;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:all .2s;" class="recipientTypeLabel" data-type="tag">
                            <input type="radio" name="recipient_type" value="tag" style="display:none;">
                            <i class="fas fa-tag"></i> Tag
                        </label>
                    </div>

                    <div id="manualRecipients" class="recipientSection">
                        <textarea name="recipients" class="form-control" rows="4"
                                  placeholder="Enter phone numbers (one per line or comma-separated)"></textarea>
                    </div>

                    <div id="groupRecipients" class="recipientSection" style="display:none;">
                        <select name="group_id" class="form-control">
                            <option value="">-- Select Group --</option>
                            {foreach $groups as $group}
                            <option value="{$group->id}">{$group->name|escape:'html'} ({$group->contact_count} contacts)</option>
                            {/foreach}
                        </select>
                    </div>

                    <div id="segmentRecipients" class="recipientSection" style="display:none;">
                        <select name="segment_id" class="form-control">
                            <option value="">-- Select Segment --</option>
                            {if $segments}
                            {foreach $segments as $segment}
                            <option value="{$segment->id}">{$segment->name|escape:'html'} ({$segment->contact_count} contacts)</option>
                            {/foreach}
                            {/if}
                        </select>
                    </div>

                    <div id="tagRecipients" class="recipientSection" style="display:none;">
                        <select name="recipient_tag_id" class="form-control">
                            <option value="">-- Select Tag --</option>
                            {if $tags}
                            {foreach $tags as $tag}
                            <option value="{$tag->id}">{$tag->name|escape:'html'} ({$tag->contact_count} contacts)</option>
                            {/foreach}
                            {/if}
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>{$lang.campaign_schedule}</label>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="send_now" value="1" checked> Send immediately
                        </label>
                    </div>
                    <div id="scheduleTime" style="display:none;margin-top:8px;">
                        <input type="datetime-local" name="scheduled_at" class="form-control">
                    </div>
                </div>

                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-outline-secondary" id="btnCancelCampaign2">{$lang.cancel}</button>
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-rocket"></i> {$lang.campaign_create}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var panel = document.getElementById('createCampaignPanel');
    var btnOpen = document.getElementById('btnCreateCampaign');
    var btnClose1 = document.getElementById('btnCancelCampaign');
    var btnClose2 = document.getElementById('btnCancelCampaign2');

    function showPanel() { panel.style.display = 'block'; btnOpen.style.display = 'none'; panel.scrollIntoView({behavior:'smooth'}); }
    function hidePanel() { panel.style.display = 'none'; btnOpen.style.display = ''; }

    btnOpen.addEventListener('click', showPanel);
    btnClose1.addEventListener('click', hidePanel);
    btnClose2.addEventListener('click', hidePanel);

    // Toggle recipient input type
    var recipientLabels = document.querySelectorAll('.recipientTypeLabel');
    var recipientSections = {
        manual: document.getElementById('manualRecipients'),
        group: document.getElementById('groupRecipients'),
        segment: document.getElementById('segmentRecipients'),
        tag: document.getElementById('tagRecipients')
    };
    recipientLabels.forEach(function(label) {
        label.addEventListener('click', function() {
            var radio = this.querySelector('input[type="radio"]');
            var type = this.getAttribute('data-type');
            radio.checked = true;
            recipientLabels.forEach(function(l) { l.style.borderColor = 'transparent'; l.style.background = '#f1f5f9'; });
            this.style.borderColor = '#667eea';
            this.style.background = 'rgba(102,126,234,.08)';
            for (var key in recipientSections) {
                if (recipientSections[key]) {
                    recipientSections[key].style.display = (key === type) ? 'block' : 'none';
                }
            }
        });
    });

    // Toggle schedule time
    document.querySelector('input[name="send_now"]').addEventListener('change', function() {
        document.getElementById('scheduleTime').style.display = this.checked ? 'none' : 'block';
    });
})();
</script>
{/literal}
