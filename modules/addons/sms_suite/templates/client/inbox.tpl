{$sms_css nofilter}
<div class="sms-suite-inbox">
    <div class="sms-page-header">
        <h2><i class="fas fa-inbox"></i> Inbox</h2>
        <div>
            <button class="btn btn-success" id="btnNewConversation" style="padding:10px 22px;">
                <i class="fas fa-plus"></i> New Conversation
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li class="active"><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
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

    <!-- New Conversation Panel -->
    <div id="newConversationPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-comment-dots"></i> New Conversation</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseNewConv">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="start_conversation" value="1">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" required placeholder="+254712345678">
                            <small class="form-text text-muted">Enter number with country code</small>
                        </div>
                    </div>
                    {if $sender_ids && count($sender_ids) > 0}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>From (Sender ID)</label>
                            <select name="sender_id" class="form-control">
                                <option value="">Default</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}">{$sid->sender_id} ({$sid->network})</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    {/if}
                </div>
                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea name="message" id="newConvMessage" class="form-control" rows="4" required placeholder="Type your message here..."></textarea>
                    <small class="form-text text-muted"><span id="newConvCharCount">0</span>/160 characters (<span id="newConvSegments">1</span> SMS)</small>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-success" style="padding:10px 22px;"><i class="fas fa-paper-plane"></i> Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Channel Filter Tabs + Search -->
    <div class="card" style="margin-bottom: 0; border-bottom: none; border-radius: 8px 8px 0 0;">
        <div class="card-body" style="padding: 12px 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <a href="{$modulelink}&action=inbox&channel=all{if $search}&search={$search|escape:'url'}{/if}" class="btn btn-sm {if $channel_filter eq 'all' || !$channel_filter}btn-primary{else}btn-outline-secondary{/if}">
                        <i class="fas fa-comments"></i> All {if $channel_counts.all}<span class="badge" style="background:rgba(255,255,255,.25);margin-left:4px;">{$channel_counts.all}</span>{/if}
                    </a>
                    {if $channel_counts.sms > 0}
                    <a href="{$modulelink}&action=inbox&channel=sms{if $search}&search={$search|escape:'url'}{/if}" class="btn btn-sm {if $channel_filter eq 'sms'}btn-primary{else}btn-outline-secondary{/if}">
                        <i class="fas fa-sms"></i> SMS <span class="badge" style="background:rgba(0,0,0,.1);margin-left:4px;">{$channel_counts.sms}</span>
                    </a>
                    {/if}
                    {if $channel_counts.whatsapp > 0}
                    <a href="{$modulelink}&action=inbox&channel=whatsapp{if $search}&search={$search|escape:'url'}{/if}" class="btn btn-sm {if $channel_filter eq 'whatsapp'}btn-success{else}btn-outline-secondary{/if}">
                        <i class="fab fa-whatsapp"></i> WhatsApp <span class="badge" style="background:rgba(0,0,0,.1);margin-left:4px;">{$channel_counts.whatsapp}</span>
                    </a>
                    {/if}
                    {if $channel_counts.telegram > 0}
                    <a href="{$modulelink}&action=inbox&channel=telegram{if $search}&search={$search|escape:'url'}{/if}" class="btn btn-sm {if $channel_filter eq 'telegram'}btn-info{else}btn-outline-secondary{/if}">
                        <i class="fab fa-telegram-plane"></i> Telegram <span class="badge" style="background:rgba(0,0,0,.1);margin-left:4px;">{$channel_counts.telegram}</span>
                    </a>
                    {/if}
                    {if $channel_counts.messenger > 0}
                    <a href="{$modulelink}&action=inbox&channel=messenger{if $search}&search={$search|escape:'url'}{/if}" class="btn btn-sm {if $channel_filter eq 'messenger'}btn-primary{else}btn-outline-secondary{/if}">
                        <i class="fab fa-facebook-messenger"></i> Messenger <span class="badge" style="background:rgba(0,0,0,.1);margin-left:4px;">{$channel_counts.messenger}</span>
                    </a>
                    {/if}
                </div>
                <form method="get" style="display:flex;gap:6px;margin:0;">
                    <input type="hidden" name="m" value="sms_suite">
                    <input type="hidden" name="action" value="inbox">
                    <input type="hidden" name="channel" value="{$channel_filter|default:'all'}">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{$search|escape:'html'}" style="width:180px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
                    {if $search}
                    <a href="{$modulelink}&action=inbox&channel={$channel_filter|default:'all'}" class="btn btn-sm btn-outline-danger" title="Clear"><i class="fas fa-times"></i></a>
                    {/if}
                </form>
            </div>
        </div>
    </div>

    <div class="card" style="border-radius: 0 0 8px 8px;">
        <div class="card-body" style="padding: 0;">
            {if $conversations && count($conversations) > 0}
            <div class="list-group" style="margin-bottom: 0;">
                {foreach $conversations as $conv}
                <a href="{$modulelink}&action=conversation{if $conv->id}&id={$conv->id}{else}&phone={$conv->phone|default:$conv->to_number|escape:'url'}{/if}" class="list-group-item {if $conv->unread_count > 0}list-group-item-info{/if}" style="text-decoration: none;">
                    <div class="row" style="display: flex; align-items: center;">
                        <div class="col-2 col-sm-1 text-center">
                            {if $conv->channel eq 'whatsapp'}
                            <div style="width: 44px; height: 44px; background: #25D366; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                <i class="fab fa-whatsapp"></i>
                            </div>
                            {elseif $conv->channel eq 'telegram'}
                            <div style="width: 44px; height: 44px; background: #0088cc; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                <i class="fab fa-telegram-plane"></i>
                            </div>
                            {elseif $conv->channel eq 'messenger'}
                            <div style="width: 44px; height: 44px; background: linear-gradient(135deg, #00B2FF, #006AFF); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                <i class="fab fa-facebook-messenger"></i>
                            </div>
                            {else}
                            <div style="width: 44px; height: 44px; background: {if $conv->unread_count > 0}linear-gradient(135deg, #667eea, #764ba2){else}#e2e8f0{/if}; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px;">
                                <i class="fas fa-sms"></i>
                            </div>
                            {/if}
                        </div>
                        <div class="col-7 col-sm-8">
                            <h4 style="margin: 0 0 4px; font-size: .95rem; font-weight: 600; color: #1e293b;">
                                {if $conv->contact_name}
                                    {$conv->contact_name|escape:'html'}
                                    <small class="text-muted">({$conv->phone|default:$conv->to_number})</small>
                                {else}
                                    {$conv->phone|default:$conv->to_number}
                                {/if}
                                {if $conv->unread_count > 0}
                                    <span class="badge badge-primary" style="font-size: .7rem; margin-left: 6px;">{$conv->unread_count} new</span>
                                {/if}
                            </h4>
                            <p style="margin: 0; color: #64748b; font-size: .85rem;">
                                {$conv->last_message|escape:'html'|truncate:60}
                            </p>
                        </div>
                        <div class="col-3 col-sm-3 text-right">
                            <small class="text-muted">{$conv->last_message_at|date_format:"%b %d, %H:%M"}</small>
                        </div>
                    </div>
                </a>
                {/foreach}
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 60px 20px;">
                <i class="fas fa-comments" style="font-size: 3rem; color: #cbd5e1;"></i>
                <h4 style="margin-top: 20px; color: #1e293b;">
                    {if $search}No conversations matching "{$search|escape:'html'}"{elseif $channel_filter neq 'all'}No {$channel_filter} conversations yet{else}No conversations yet{/if}
                </h4>
                <p>Start your first conversation by clicking the button above.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var panel = document.getElementById('newConversationPanel');
    var btnNew = document.getElementById('btnNewConversation');
    var btnClose = document.getElementById('btnCloseNewConv');
    var textarea = document.getElementById('newConvMessage');

    btnNew.addEventListener('click', function() {
        panel.style.display = 'block';
        btnNew.style.display = 'none';
        panel.scrollIntoView({behavior:'smooth'});
    });

    btnClose.addEventListener('click', function() {
        panel.style.display = 'none';
        btnNew.style.display = '';
    });

    if (textarea) {
        textarea.addEventListener('input', function() {
            var len = this.value.length;
            var segments = Math.ceil(len / 160) || 1;
            document.getElementById('newConvCharCount').textContent = len;
            document.getElementById('newConvSegments').textContent = segments;
        });
    }
})();
</script>
{/literal}
