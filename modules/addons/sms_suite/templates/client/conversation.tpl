{$sms_css nofilter}
<div class="sms-suite-conversation">
    <!-- Header -->
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-6">
                    <a href="{$modulelink}&action=inbox" class="btn btn-default btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Inbox
                    </a>
                </div>
                <div class="col-sm-6 text-right">
                    <h4 style="margin: 0;">
                        <i class="fas fa-user-circle"></i>
                        {if $contact}
                            {$contact->first_name|escape:'html'} {$contact->last_name|escape:'html'}
                            <small class="text-muted">({$phone})</small>
                        {else}
                            {$phone}
                            <a href="{$modulelink}&action=contacts&add_phone={$phone|escape:'url'}" class="btn btn-xs btn-link">Add to Contacts</a>
                        {/if}
                    </h4>
                </div>
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
        <div class="panel-body" id="chatMessages" style="height: 400px; overflow-y: auto; background: #f5f5f5;">
            {if $messages && count($messages) > 0}
                {foreach $messages as $msg}
                <div class="chat-message {if $msg->direction == 'outbound'}outbound{else}inbound{/if}" style="margin-bottom: 15px; clear: both;">
                    <div style="max-width: 70%; {if $msg->direction == 'outbound'}float: right; text-align: right;{else}float: left;{/if}">
                        <div style="background: {if $msg->direction == 'outbound'}#007bff; color: white;{else}white;{/if} padding: 10px 15px; border-radius: 18px; display: inline-block; text-align: left; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            {$msg->message|escape:'html'|nl2br}
                        </div>
                        <div style="font-size: 11px; color: #999; margin-top: 3px; {if $msg->direction == 'outbound'}text-align: right;{/if}">
                            {$msg->created_at|date_format:"%b %d, %H:%M"}
                            {if $msg->direction == 'outbound'}
                                {if $msg->status == 'delivered'}
                                    <i class="fas fa-check-double text-success" title="Delivered"></i>
                                {elseif $msg->status == 'sent'}
                                    <i class="fas fa-check text-primary" title="Sent"></i>
                                {elseif $msg->status == 'failed'}
                                    <i class="fas fa-times text-danger" title="Failed"></i>
                                {else}
                                    <i class="fas fa-clock text-muted" title="{$msg->status}"></i>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>
                {/foreach}
            {else}
                <div class="text-center text-muted" style="padding: 100px 20px;">
                    <i class="fas fa-comments fa-3x"></i>
                    <p style="margin-top: 15px;">No messages yet. Send the first message below.</p>
                </div>
            {/if}
        </div>
    </div>

    <!-- Reply Form -->
    <div class="panel panel-default">
        <div class="panel-body">
            <form method="post" id="replyForm">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="send_reply" value="1">

                <div class="row">
                    <div class="col-sm-9">
                        <div class="form-group" style="margin-bottom: 10px;">
                            <textarea name="message" class="form-control" rows="2" placeholder="Type your message..." required id="messageInput"></textarea>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        {if $sender_ids && count($sender_ids) > 0}
                        <div class="form-group" style="margin-bottom: 10px;">
                            <select name="sender_id" class="form-control">
                                <option value="">Default Sender</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}">{$sid->sender_id}</option>
                                {/foreach}
                            </select>
                        </div>
                        {/if}
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12">
                        <small class="text-muted">
                            <span id="charCount">0</span>/160 characters |
                            <span id="smsCount">1</span> SMS |
                            Press Enter to send, Shift+Enter for new line
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.chat-message::after {
    content: "";
    display: table;
    clear: both;
}
#chatMessages {
    scroll-behavior: smooth;
}
</style>

<script>
// Scroll to bottom on load
document.addEventListener('DOMContentLoaded', function() {
    var chatBox = document.getElementById('chatMessages');
    chatBox.scrollTop = chatBox.scrollHeight;
});

// Character counter
var messageInput = document.getElementById('messageInput');
messageInput.addEventListener('input', function() {
    var len = this.value.length;
    var segments = Math.ceil(len / 160) || 1;
    document.getElementById('charCount').textContent = len;
    document.getElementById('smsCount').textContent = segments;
});

// Enter to send
messageInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) {
            document.getElementById('replyForm').submit();
        }
    }
});
</script>
