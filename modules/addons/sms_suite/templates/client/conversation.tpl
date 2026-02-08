{$sms_css nofilter}
<div class="sms-suite-conversation">
    <!-- Header -->
    <div class="panel panel-default" style="margin-bottom: 16px;">
        <div class="panel-body" style="padding: 14px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <a href="{$modulelink}&action=inbox" class="btn btn-default btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Inbox
                </a>
                <h4 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #1e293b;">
                    <i class="fas fa-user-circle" style="color: var(--sms-primary, #667eea);"></i>
                    {if $contact}
                        {$contact->first_name|escape:'html'} {$contact->last_name|escape:'html'}
                        <small class="text-muted">({$phone})</small>
                    {else}
                        {$phone}
                        <a href="{$modulelink}&action=contacts&add_phone={$phone|escape:'url'}" class="btn btn-xs btn-default" style="margin-left: 8px;">Add to Contacts</a>
                    {/if}
                </h4>
            </div>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {$error}
    </div>
    {/if}

    <!-- Chat Messages -->
    <div class="panel panel-default">
        <div class="panel-body" id="chatMessages" style="height: 420px; overflow-y: auto; background: #f8fafc; padding: 20px;">
            {if $messages && count($messages) > 0}
                {foreach $messages as $msg}
                <div style="margin-bottom: 16px; display: flex; {if $msg->direction == 'outbound'}justify-content: flex-end;{else}justify-content: flex-start;{/if}">
                    <div style="max-width: 70%;">
                        <div style="background: {if $msg->direction == 'outbound'}linear-gradient(135deg, #667eea, #764ba2); color: white;{else}white; color: #1e293b; border: 1px solid #e2e8f0;{/if} padding: 12px 16px; border-radius: {if $msg->direction == 'outbound'}16px 16px 4px 16px{else}16px 16px 16px 4px{/if}; box-shadow: 0 1px 4px rgba(0,0,0,.06); font-size: .9rem; line-height: 1.5;">
                            {$msg->message|escape:'html'|nl2br}
                        </div>
                        <div style="font-size: .75rem; color: #94a3b8; margin-top: 4px; {if $msg->direction == 'outbound'}text-align: right;{/if}">
                            {$msg->created_at|date_format:"%b %d, %H:%M"}
                            {if $msg->direction == 'outbound'}
                                {if $msg->status == 'delivered'}
                                    <i class="fas fa-check-double" style="color: var(--sms-success);" title="Delivered"></i>
                                {elseif $msg->status == 'sent'}
                                    <i class="fas fa-check" style="color: var(--sms-primary);" title="Sent"></i>
                                {elseif $msg->status == 'failed'}
                                    <i class="fas fa-times" style="color: var(--sms-danger);" title="Failed"></i>
                                {else}
                                    <i class="fas fa-clock" style="color: #94a3b8;" title="{$msg->status}"></i>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>
                {/foreach}
            {else}
                <div class="text-center text-muted" style="padding: 80px 20px;">
                    <i class="fas fa-comments" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                    <p style="margin-top: 15px;">No messages yet. Send the first message below.</p>
                </div>
            {/if}
        </div>
    </div>

    <!-- Reply Form -->
    <div class="panel panel-default">
        <div class="panel-body" style="padding: 16px 20px;">
            <form method="post" id="replyForm">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="send_reply" value="1">

                <div class="row">
                    <div class="col-sm-9" style="margin-bottom: 8px;">
                        <textarea name="message" class="form-control" rows="2" placeholder="Type your message..." required id="messageInput" style="resize: none;"></textarea>
                    </div>
                    <div class="col-sm-3">
                        {if $sender_ids && count($sender_ids) > 0}
                        <select name="sender_id" class="form-control" style="margin-bottom: 8px;">
                            <option value="">Default Sender</option>
                            {foreach $sender_ids as $sid}
                            <option value="{$sid->sender_id}">{$sid->sender_id}</option>
                            {/foreach}
                        </select>
                        {/if}
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>

                <small class="text-muted" style="display: block; margin-top: 6px;">
                    <span id="charCount">0</span>/160 characters |
                    <span id="smsCount">1</span> SMS |
                    Press Enter to send, Shift+Enter for new line
                </small>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chatBox = document.getElementById('chatMessages');
    chatBox.scrollTop = chatBox.scrollHeight;
});

var messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    var len = this.value.length;
    var segments = Math.ceil(len / 160) || 1;
    document.getElementById('charCount').textContent = len;
    document.getElementById('smsCount').textContent = segments;
});

messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            document.getElementById('replyForm').submit();
        }
    }
});
</script>
