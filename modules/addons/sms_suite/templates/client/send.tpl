<div class="sms-suite-send">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.menu_send_sms}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li class="active"><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
                <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
            </ul>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success">
        <strong>{$lang.success}!</strong> {$success}
        {if $segment_info}
        <br><small>{$lang.segments}: {$segment_info.segments} | {$lang.encoding}: {$segment_info.encoding|upper}</small>
        {/if}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.compose_message}</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="{$modulelink}&action=send" id="smsForm">
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

                        <!-- Recipient -->
                        <div class="form-group">
                            <label for="to">{$lang.recipient} <span class="text-danger">*</span></label>
                            <input type="text" name="to" id="to" class="form-control"
                                   placeholder="+1234567890"
                                   value="{$posted.to|escape:'html'}" required>
                            <small class="help-block">{$lang.recipient_help}</small>
                        </div>

                        <!-- Sender ID -->
                        <div class="form-group">
                            <label for="sender_id">{$lang.sender_id}</label>
                            {if $sender_ids && count($sender_ids) > 0}
                            <select name="sender_id" id="sender_id" class="form-control">
                                <option value="">{$lang.default_sender}</option>
                                {foreach $sender_ids as $sid}
                                <option value="{$sid->sender_id}" {if $posted.sender_id eq $sid->sender_id}selected{/if}>
                                    {$sid->sender_id|escape:'html'}
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
                        <div class="form-group">
                            <div class="well well-sm" id="segmentInfo">
                                <div class="row">
                                    <div class="col-xs-3 text-center">
                                        <strong id="charCount">0</strong><br>
                                        <small>{$lang.characters}</small>
                                    </div>
                                    <div class="col-xs-3 text-center">
                                        <strong id="segmentCount">0</strong><br>
                                        <small>{$lang.segments}</small>
                                    </div>
                                    <div class="col-xs-3 text-center">
                                        <strong id="encoding">GSM-7</strong><br>
                                        <small>{$lang.encoding}</small>
                                    </div>
                                    <div class="col-xs-3 text-center">
                                        <strong id="remaining">160</strong><br>
                                        <small>{$lang.remaining}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> {$lang.send_message}
                            </button>
                            <a href="{$modulelink}" class="btn btn-default btn-lg">{$lang.cancel}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Quick Stats -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.quick_info}</h3>
                </div>
                <div class="panel-body">
                    <p><strong>{$lang.wallet_balance}:</strong>
                       {if $settings && $settings->currency}
                           {$settings->currency}
                       {else}
                           $
                       {/if}{$balance|default:0|number_format:2}
                    </p>
                    <p><strong>{$lang.active_sender_ids}:</strong> {$sender_ids|@count|default:0}</p>
                </div>
            </div>

            <!-- Encoding Guide -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.encoding_guide}</h3>
                </div>
                <div class="panel-body">
                    <p><strong>GSM-7:</strong></p>
                    <ul class="small">
                        <li>160 {$lang.chars_single}</li>
                        <li>153 {$lang.chars_per_segment}</li>
                        <li>{$lang.gsm7_description}</li>
                    </ul>
                    <p><strong>UCS-2 (Unicode):</strong></p>
                    <ul class="small">
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
(function() {
    // GSM-7 Basic Character Set (code points)
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

    // Extended chars (count as 2)
    var gsm7ExtChars = [12, 91, 92, 93, 94, 123, 124, 125, 126, 8364];

    var allGsmChars = gsm7Chars.concat(gsm7ExtChars);

    function getCodePoints(str) {
        var points = [];
        for (var i = 0; i < str.length; i++) {
            var code = str.codePointAt(i);
            points.push(code);
            if (code > 0xFFFF) i++; // Skip surrogate pair
        }
        return points;
    }

    function detectEncoding(codePoints) {
        var hasExtended = false;
        for (var i = 0; i < codePoints.length; i++) {
            if (allGsmChars.indexOf(codePoints[i]) === -1) {
                return 'ucs2';
            }
            if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) {
                hasExtended = true;
            }
        }
        return hasExtended ? 'gsm7ex' : 'gsm7';
    }

    function countSegments(message, channel) {
        if (!message || message.length === 0) {
            return { encoding: 'gsm7', length: 0, segments: 0, remaining: 160, perMessage: 160 };
        }

        // WhatsApp uses different limits
        if (channel === 'whatsapp') {
            var len = message.length;
            var segments = Math.ceil(len / 1000);
            return { encoding: 'whatsapp', length: len, segments: segments, remaining: (1000 * segments) - len, perMessage: 1000 };
        }

        var codePoints = getCodePoints(message);
        var encoding = detectEncoding(codePoints);

        var length = codePoints.length;

        // Count extended chars (they use 2 positions)
        if (encoding === 'gsm7ex') {
            for (var i = 0; i < codePoints.length; i++) {
                if (gsm7ExtChars.indexOf(codePoints[i]) !== -1) {
                    length++;
                }
            }
        } else if (encoding === 'ucs2') {
            // Surrogate pairs count as 2
            for (var i = 0; i < codePoints.length; i++) {
                if (codePoints[i] >= 65536) {
                    length++;
                }
            }
        }

        var singleLimit, multiLimit;
        if (encoding === 'gsm7' || encoding === 'gsm7ex') {
            singleLimit = 160;
            multiLimit = 153;
        } else {
            singleLimit = 70;
            multiLimit = 67;
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

        // Visual feedback for encoding type
        var infoBox = document.getElementById('segmentInfo');
        if (result.encoding === 'ucs2') {
            infoBox.className = 'well well-sm bg-warning';
        } else {
            infoBox.className = 'well well-sm';
        }
    }

    // Attach event listeners
    var messageField = document.getElementById('message');
    var channelField = document.getElementById('channel');

    if (messageField) {
        messageField.addEventListener('input', updateCounter);
        messageField.addEventListener('keyup', updateCounter);
        messageField.addEventListener('paste', function() {
            setTimeout(updateCounter, 10);
        });
    }

    if (channelField) {
        channelField.addEventListener('change', updateCounter);
    }

    // Initial count
    updateCounter();
})();
</script>

<style>
.sms-suite-send .well {
    margin-bottom: 0;
}
.sms-suite-send .well strong {
    font-size: 1.5em;
    color: #333;
}
.sms-suite-send .bg-warning {
    background-color: #fcf8e3;
    border-color: #faebcc;
}
.sms-suite-send .panel-info .panel-body p {
    margin-bottom: 5px;
}
</style>
