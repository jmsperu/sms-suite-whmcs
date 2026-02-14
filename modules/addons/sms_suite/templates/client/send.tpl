{$sms_css nofilter}
<div class="sms-suite-send">
    <div class="sms-page-header">
        <h2><i class="fas fa-paper-plane"></i> {$lang.menu_send_sms}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li class="active"><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>{$lang.success}!</strong> {$success}
        {if $segment_info}
        <br><small>{$lang.segments}: {$segment_info.segments} | {$lang.encoding}: {$segment_info.encoding|upper}</small>
        {/if}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-edit"></i> {$lang.compose_message}</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="{$modulelink}&action=send" id="smsForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <input type="hidden" name="send_message" value="1">

                        <!-- Channel Selection -->
                        <div class="form-group">
                            <label for="channel">{$lang.channel}</label>
                            <select name="channel" id="channel" class="form-control">
                                <option value="sms" {if $posted.channel eq 'sms' || !$posted.channel}selected{/if}>SMS</option>
                                <option value="whatsapp" {if $posted.channel eq 'whatsapp'}selected{/if}>WhatsApp</option>
                            </select>
                        </div>

                        <!-- Recipients -->
                        <div class="form-group">
                            <label for="to">{$lang.recipient|default:'Recipients'} <span class="text-danger">*</span></label>
                            <textarea name="to" id="to" class="form-control" rows="3"
                                      placeholder="Enter phone numbers (one per line, or comma/space/tab separated)">{$posted.to|escape:'html'}</textarea>
                            <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <span class="form-text text-muted" style="margin: 0;">
                                    <span id="recipientCount" style="font-weight: 600; color: var(--sms-primary, #667eea);">0</span> recipient(s)
                                </span>
                                <span class="form-text text-muted" style="margin: 0;">|</span>
                                <label for="recipients_file" style="margin: 0; cursor: pointer; color: var(--sms-primary, #667eea); font-size: .8rem; font-weight: 500;">
                                    <i class="fas fa-file-upload"></i> Upload file (CSV, TXT, XLSX)
                                </label>
                                <input type="file" name="recipients_file" id="recipients_file" accept=".csv,.txt,.xlsx,.xls"
                                       style="display: none;" onchange="handleFileSelect(this)">
                                <span id="fileName" class="form-text text-muted" style="margin: 0; font-style: italic;"></span>
                            </div>
                        </div>

                        <!-- Sender ID (SMS only) -->
                        <div class="form-group" id="sender_id_group">
                            <label for="sender_id">{$lang.sender_id}</label>
                            {if $sender_ids && count($sender_ids) > 0}
                            <select name="sender_id" id="sender_id" class="form-control">
                                <option value="">{$lang.default_sender}</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}" {if ($posted.sender_id eq $sid->sender_id) || ($smarty.get.sender_id eq $sid->sender_id)}selected{/if}>
                                    {$sid->sender_id|escape:'html'}
                                    {if $sid->source|default:'' eq 'assigned' || $sid->source|default:'' eq 'admin'} (Admin){/if}
                                </option>
                                {/foreach}
                            </select>
                            {else}
                            <p class="form-control-static text-muted">{$lang.no_sender_ids}</p>
                            <input type="hidden" name="sender_id" value="">
                            {/if}
                        </div>

                        <!-- Gateway (optional) -->
                        {if $gateways && count($gateways) > 1}
                        <div class="form-group">
                            <label for="gateway_id">{$lang.gateway}</label>
                            <select name="gateway_id" id="gateway_id" class="form-control">
                                <option value="">{$lang.default_gateway}</option>
                                {foreach $gateways as $gw}
                                <option value="{$gw->id}" {if $posted.gateway_id eq $gw->id}selected{/if}>
                                    {$gw->name|escape:'html'}
                                </option>
                                {/foreach}
                            </select>
                        </div>
                        {/if}

                        <!-- Message -->
                        <div class="form-group">
                            <label for="message">{$lang.message} <span class="text-danger">*</span></label>
                            <textarea name="message" id="message" class="form-control" rows="5"
                                      placeholder="{$lang.message_placeholder}" required>{$posted.message|escape:'html'}</textarea>
                        </div>

                        <!-- Segment Counter -->
                        <div id="segmentInfo" style="background: var(--sms-light, #f8fafc); border: 1px solid var(--sms-border, #e2e8f0); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                            <div class="row">
                                <div class="col-3 text-center">
                                    <strong id="charCount" style="font-size: 1.35rem; color: var(--sms-dark, #1e293b);">0</strong><br>
                                    <small style="color: var(--sms-muted, #64748b);">{$lang.characters}</small>
                                </div>
                                <div class="col-3 text-center">
                                    <strong id="segmentCount" style="font-size: 1.35rem; color: var(--sms-dark, #1e293b);">0</strong><br>
                                    <small style="color: var(--sms-muted, #64748b);">{$lang.segments}</small>
                                </div>
                                <div class="col-3 text-center">
                                    <strong id="encoding" style="font-size: 1.35rem; color: var(--sms-dark, #1e293b);">GSM-7</strong><br>
                                    <small style="color: var(--sms-muted, #64748b);">{$lang.encoding}</small>
                                </div>
                                <div class="col-3 text-center">
                                    <strong id="remaining" style="font-size: 1.35rem; color: var(--sms-dark, #1e293b);">160</strong><br>
                                    <small style="color: var(--sms-muted, #64748b);">{$lang.remaining}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> {$lang.send_message}
                            </button>
                            <a href="{$modulelink}" class="btn btn-outline-secondary btn-lg">{$lang.cancel}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> {$lang.quick_info}</h3>
                </div>
                <div class="card-body">
                    <p><strong>{$lang.wallet_balance}:</strong> {$currency_symbol}{$balance|default:0|number_format:2}</p>
                    <p style="margin-bottom: 0;"><strong>{$lang.active_sender_ids}:</strong> <a href="{$modulelink}&action=sender_ids">{$sender_ids|@count|default:0}</a></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-book"></i> {$lang.encoding_guide}</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 4px;"><strong>GSM-7:</strong></p>
                    <ul class="small" style="padding-left: 18px; margin-bottom: 12px;">
                        <li>160 {$lang.chars_single}</li>
                        <li>153 {$lang.chars_per_segment}</li>
                        <li>{$lang.gsm7_description}</li>
                    </ul>
                    <p style="margin-bottom: 4px;"><strong>UCS-2 (Unicode):</strong></p>
                    <ul class="small" style="padding-left: 18px; margin-bottom: 0;">
                        <li>70 {$lang.chars_single}</li>
                        <li>67 {$lang.chars_per_segment}</li>
                        <li>{$lang.ucs2_description}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
function countRecipients() {
    var toField = document.getElementById('to');
    if (!toField) return;
    var val = toField.value.trim();
    if (!val) {
        document.getElementById('recipientCount').textContent = '0';
        return;
    }
    var parts = val.split(/[,\n\r\t;|]+/);
    var count = 0;
    for (var i = 0; i < parts.length; i++) {
        var num = parts[i].replace(/[^\d+]/g, '');
        if (/^\+?\d{7,15}$/.test(num)) count++;
    }
    document.getElementById('recipientCount').textContent = count;
}

function handleFileSelect(input) {
    var nameSpan = document.getElementById('fileName');
    if (input.files && input.files[0]) {
        var file = input.files[0];
        nameSpan.textContent = file.name + ' (' + Math.round(file.size/1024) + ' KB)';

        // For CSV/TXT files, also preview numbers in the textarea
        var ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'csv' || ext === 'txt') {
            var reader = new FileReader();
            reader.onload = function(e) {
                var content = e.target.result;
                var toField = document.getElementById('to');
                var existing = toField.value.trim();
                // Extract numbers from file content
                var lines = content.split(/[\r\n]+/);
                var numbers = [];
                for (var i = 0; i < lines.length; i++) {
                    var fields = lines[i].split(/[,\t;|]+/);
                    for (var j = 0; j < fields.length; j++) {
                        var num = fields[j].trim().replace(/[^\d+]/g, '');
                        if (/^\+?\d{7,15}$/.test(num)) numbers.push(num);
                    }
                }
                if (numbers.length > 0) {
                    toField.value = (existing ? existing + '\n' : '') + numbers.join('\n');
                    countRecipients();
                }
            };
            reader.readAsText(file);
        }
    } else {
        nameSpan.textContent = '';
    }
}

var toField = document.getElementById('to');
if (toField) {
    toField.addEventListener('input', countRecipients);
    toField.addEventListener('keyup', countRecipients);
    toField.addEventListener('paste', function() { setTimeout(countRecipients, 10); });
    countRecipients();
}
{/literal}

(function() {
    var gsm7Chars = [
        10, 12, 13, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44,
        45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60,
        61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76,
        77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92,
        93, 94, 95, 97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107,
        108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120,
        121, 122, 123, 124, 125, 126, 161, 163, 164, 165, 167, 191, 196,
        197, 198, 199, 201, 209, 214, 216, 220, 223, 224, 228, 229, 230,
        232, 233, 236, 241, 242, 246, 248, 249, 252, 915, 916, 920, 923,
        926, 928, 931, 934, 936, 937, 8364
    ];
    var gsm7ExtChars = [12, 91, 92, 93, 94, 123, 124, 125, 126, 8364];
    var allGsmChars = gsm7Chars.concat(gsm7ExtChars);

    function getCodePoints(str) {
        var points = [];
        for (var i = 0; i < str.length; i++) {
            var code = str.codePointAt(i);
            points.push(code);
            if (code > 0xFFFF) i++;
        }
        return points;
    }

    function detectEncoding(codePoints) {
        var hasExtended = false;
        for (var i = 0; i < codePoints.length; i++) {
            if (allGsmChars.indexOf(codePoints[i]) === -1) return 'ucs2';
            if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) hasExtended = true;
        }
        return hasExtended ? 'gsm7ex' : 'gsm7';
    }

    function countSegments(message, channel) {
        if (!message || message.length === 0) {
            return { encoding: 'gsm7', length: 0, segments: 0, remaining: 160, perMessage: 160 };
        }
        if (channel === 'whatsapp') {
            var len = message.length;
            var segments = Math.ceil(len / 1000);
            return { encoding: 'whatsapp', length: len, segments: segments, remaining: (1000 * segments) - len, perMessage: 1000 };
        }
        var codePoints = getCodePoints(message);
        var encoding = detectEncoding(codePoints);
        var length = codePoints.length;

        if (encoding === 'gsm7ex') {
            for (var i = 0; i < codePoints.length; i++) {
                if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) length++;
            }
        } else if (encoding === 'ucs2') {
            for (var i = 0; i < codePoints.length; i++) {
                if (codePoints[i] >= 65536) length++;
            }
        }

        var singleLimit, multiLimit;
        if (encoding === 'gsm7' || encoding === 'gsm7ex') {
            singleLimit = 160; multiLimit = 153;
        } else {
            singleLimit = 70; multiLimit = 67;
        }

        var segments, perMessage;
        if (length <= singleLimit) {
            segments = length > 0 ? 1 : 0;
            perMessage = singleLimit;
        } else {
            segments = Math.ceil(length / multiLimit);
            perMessage = multiLimit;
        }
        var remaining = (perMessage * Math.max(segments, 1)) - length;
        return { encoding: encoding, length: length, segments: segments, remaining: remaining, perMessage: perMessage };
    }

    function updateCounter() {
        var message = document.getElementById('message').value;
        var channel = document.getElementById('channel').value;
        var result = countSegments(message, channel);

        document.getElementById('charCount').textContent = result.length;
        document.getElementById('segmentCount').textContent = result.segments;
        document.getElementById('remaining').textContent = result.remaining;

        var encodingDisplay = result.encoding.toUpperCase();
        if (encodingDisplay === 'GSM7EX') encodingDisplay = 'GSM-7 EXT';
        if (encodingDisplay === 'GSM7') encodingDisplay = 'GSM-7';
        document.getElementById('encoding').textContent = encodingDisplay;

        var infoBox = document.getElementById('segmentInfo');
        if (result.encoding === 'ucs2') {
            infoBox.style.borderColor = '#ff9800';
            infoBox.style.background = 'rgba(255,152,0,.06)';
        } else {
            infoBox.style.borderColor = '';
            infoBox.style.background = '';
        }
    }

    var messageField = document.getElementById('message');
    var channelField = document.getElementById('channel');
    if (messageField) {
        messageField.addEventListener('input', updateCounter);
        messageField.addEventListener('keyup', updateCounter);
        messageField.addEventListener('paste', function() { setTimeout(updateCounter, 10); });
    }
    if (channelField) {
        channelField.addEventListener('change', function() {
            updateCounter();
            toggleChannelFields();
        });
    }

    function toggleChannelFields() {
        var ch = document.getElementById('channel').value;
        var senderGroup = document.getElementById('sender_id_group');
        var segmentInfo = document.getElementById('segmentInfo');
        if (ch === 'whatsapp') {
            if (senderGroup) senderGroup.style.display = 'none';
            if (segmentInfo) segmentInfo.style.display = 'none';
        } else {
            if (senderGroup) senderGroup.style.display = '';
            if (segmentInfo) segmentInfo.style.display = '';
        }
    }

    updateCounter();
    toggleChannelFields();
})();
</script>
