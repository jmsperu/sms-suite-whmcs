<div class="sms-suite-inbox">
    <div class="row">
        <div class="col-sm-8">
            <h2><i class="fas fa-inbox"></i> Inbox</h2>
        </div>
        <div class="col-sm-4 text-right">
            <button class="btn btn-success" data-toggle="modal" data-target="#newConversationModal">
                <i class="fas fa-plus"></i> New Conversation
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin: 20px 0;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li class="active"><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
                <li><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
                <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
            </ul>
        </div>
    </div>

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

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Conversations</h3>
        </div>
        <div class="panel-body" style="padding: 0;">
            {if $conversations && count($conversations) > 0}
            <div class="list-group" style="margin-bottom: 0;">
                {foreach $conversations as $conv}
                <a href="{$modulelink}&action=conversation&phone={$conv->to_number|escape:'url'}" class="list-group-item {if $conv->unread_count > 0}list-group-item-info{/if}">
                    <div class="row">
                        <div class="col-xs-2 col-sm-1 text-center">
                            <div style="width: 45px; height: 45px; background: {if $conv->unread_count > 0}#5bc0de{else}#ddd{/if}; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="col-xs-7 col-sm-8">
                            <h4 class="list-group-item-heading" style="margin-bottom: 5px;">
                                {if $conv->contact_name}
                                    {$conv->contact_name|escape:'html'}
                                    <small class="text-muted">({$conv->to_number})</small>
                                {else}
                                    {$conv->to_number}
                                {/if}
                                {if $conv->unread_count > 0}
                                    <span class="badge">{$conv->unread_count} new</span>
                                {/if}
                            </h4>
                            <p class="list-group-item-text text-muted" style="margin-bottom: 0;">
                                {if $conv->last_direction == 'outbound'}
                                    <i class="fas fa-arrow-right text-primary"></i>
                                {else}
                                    <i class="fas fa-arrow-left text-success"></i>
                                {/if}
                                {$conv->last_message|escape:'html'}
                            </p>
                        </div>
                        <div class="col-xs-3 col-sm-3 text-right">
                            <small class="text-muted">{$conv->last_message_at|date_format:"%b %d, %H:%M"}</small>
                            <br>
                            <small class="text-muted">{$conv->message_count} messages</small>
                        </div>
                    </div>
                </a>
                {/foreach}
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 60px 20px;">
                <i class="fas fa-comments fa-4x" style="color: #ddd;"></i>
                <h4 style="margin-top: 20px;">No conversations yet</h4>
                <p>Start your first conversation by clicking the button below.</p>
                <button class="btn btn-success btn-lg" data-toggle="modal" data-target="#newConversationModal">
                    <i class="fas fa-plus"></i> Start New Conversation
                </button>
            </div>
            {/if}
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal fade" id="newConversationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="start_conversation" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-comment-dots"></i> New Conversation</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" required placeholder="+254712345678">
                        <small class="help-block">Enter number with country code</small>
                    </div>

                    {if $sender_ids && count($sender_ids) > 0}
                    <div class="form-group">
                        <label>From (Sender ID)</label>
                        <select name="sender_id" class="form-control">
                            <option value="">Default</option>
                            {foreach $sender_ids as $sid}
                            <option value="{$sid->sender_id}">{$sid->sender_id} ({$sid->network})</option>
                            {/foreach}
                        </select>
                    </div>
                    {/if}

                    <div class="form-group">
                        <label>Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Type your message here..."></textarea>
                        <small class="help-block"><span id="charCount">0</span>/160 characters (1 SMS)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('textarea[name="message"]').addEventListener('input', function() {
    var len = this.value.length;
    var segments = Math.ceil(len / 160) || 1;
    document.getElementById('charCount').textContent = len;
    this.nextElementSibling.innerHTML = '<span id="charCount">' + len + '</span>/160 characters (' + segments + ' SMS)';
});
</script>
