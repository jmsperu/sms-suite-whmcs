{$sms_css nofilter}
<div class="sms-suite-wa-rates">
    <div class="sms-page-header">
        <h2><i class="fab fa-whatsapp"></i> WhatsApp Platform Rates</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=billing">{$lang.billing|default:'Billing'}</a></li>
        <li class="active"><a href="{$modulelink}&action=wa_rates">WA Rates</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    <p class="text-muted" style="margin-bottom: 20px;">
        Meta charges per-message fees for the WhatsApp Business Platform. Rates vary by destination market and message category.
        {if $effective_date}Rates effective: <strong>{$effective_date}</strong>{/if}
    </p>

    <!-- Country Search -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 20px;">
            <h4 style="margin-top: 0;"><i class="fas fa-search"></i> Look Up Country Rate</h4>
            <div class="form-group" style="margin-bottom: 0;">
                <input type="text" id="countrySearch" class="form-control" placeholder="Type a country name (e.g. Kenya, United States, Brazil...)">
            </div>
            <div id="countryResult" style="margin-top: 12px; display: none;"></div>
        </div>
    </div>

    <!-- Rate Card by Market -->
    {if $all_rates}
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Market</th>
                            <th class="text-right">Marketing</th>
                            <th class="text-right">Utility</th>
                            <th class="text-right">Authentication</th>
                            <th class="text-right">Auth Intl</th>
                            <th class="text-right">Service</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$all_rates key=market item=rates}
                        <tr>
                            <td><strong>{$market}</strong></td>
                            <td class="text-right">
                                {if isset($rates.marketing)}${$rates.marketing|number_format:4}{else}<span class="text-muted">&mdash;</span>{/if}
                            </td>
                            <td class="text-right">
                                {if isset($rates.utility)}${$rates.utility|number_format:4}{else}<span class="text-muted">&mdash;</span>{/if}
                            </td>
                            <td class="text-right">
                                {if isset($rates.authentication)}${$rates.authentication|number_format:4}{else}<span class="text-muted">&mdash;</span>{/if}
                            </td>
                            <td class="text-right">
                                {if isset($rates.authentication_international)}${$rates.authentication_international|number_format:4}{else}<span class="text-muted">&mdash;</span>{/if}
                            </td>
                            <td class="text-right">
                                {if isset($rates.service)}${$rates.service|number_format:4}{else}<span class="text-muted">&mdash;</span>{/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="margin-top: 16px; padding: 16px; background: #f8f9fa; border-radius: 8px;">
        <h5 style="margin-top: 0;"><i class="fas fa-info-circle"></i> About Message Categories</h5>
        <ul style="margin-bottom: 0; padding-left: 20px;">
            <li><strong>Marketing</strong> &mdash; Promotions, offers, product announcements, and re-engagement messages</li>
            <li><strong>Utility</strong> &mdash; Transaction updates, order confirmations, delivery alerts, and account notifications</li>
            <li><strong>Authentication</strong> &mdash; One-time passwords (OTP) and verification codes</li>
            <li><strong>Auth International</strong> &mdash; Authentication messages sent to a different country than the business phone number</li>
            <li><strong>Service</strong> &mdash; Free-form messages within the 24-hour customer service window</li>
        </ul>
    </div>
    {else}
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> WhatsApp rate information is not yet available. Please check back later.
    </div>
    {/if}

    <!-- Country Lookup Data -->
    <script>
    var countryData = {literal}{{/literal}
    {foreach from=$country_lookup key=code item=info}
        "{$code}": {literal}{{/literal}"name": "{$info.name|escape:'javascript'}", "market": "{$info.market|escape:'javascript'}"{literal}}{/literal},
    {/foreach}
    {literal}}{/literal};

    var rateData = {literal}{{/literal}
    {foreach from=$all_rates key=market item=rates}
        "{$market|escape:'javascript'}": {literal}{{/literal}
            {if isset($rates.marketing)}"marketing": {$rates.marketing},{/if}
            {if isset($rates.utility)}"utility": {$rates.utility},{/if}
            {if isset($rates.authentication)}"authentication": {$rates.authentication},{/if}
            {if isset($rates.authentication_international)}"authentication_international": {$rates.authentication_international},{/if}
            {if isset($rates.service)}"service": {$rates.service},{/if}
        {literal}}{/literal},
    {/foreach}
    {literal}}{/literal};

    document.getElementById('countrySearch').addEventListener('input', function() {literal}{{/literal}
        var q = this.value.toLowerCase().trim();
        var el = document.getElementById('countryResult');
        if (q.length < 2) {literal}{{/literal} el.style.display = 'none'; return; {literal}}{/literal}

        var matches = [];
        for (var code in countryData) {literal}{{/literal}
            var c = countryData[code];
            if (c.name.toLowerCase().indexOf(q) !== -1 || code.toLowerCase() === q) {literal}{{/literal}
                matches.push({literal}{{/literal} code: code, name: c.name, market: c.market {literal}}{/literal});
            {literal}}{/literal}
        {literal}}{/literal}

        if (matches.length === 0) {literal}{{/literal}
            el.innerHTML = '<p class="text-muted">No matching country found.</p>';
            el.style.display = 'block';
            return;
        {literal}}{/literal}

        var html = '';
        matches.slice(0, 5).forEach(function(m) {literal}{{/literal}
            var rates = rateData[m.market] || {literal}{}{/literal};
            html += '<div style="padding:10px 14px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:8px;">';
            html += '<strong>' + m.name + '</strong> <code>' + m.code + '</code>';
            html += ' &rarr; <span class="text-muted">' + m.market + '</span>';
            html += '<div style="margin-top:6px; font-size:0.9em;">';
            if (rates.marketing) html += '<span style="margin-right:12px;">Marketing: <strong>$' + rates.marketing.toFixed(4) + '</strong></span>';
            if (rates.utility) html += '<span style="margin-right:12px;">Utility: <strong>$' + rates.utility.toFixed(4) + '</strong></span>';
            if (rates.authentication) html += '<span style="margin-right:12px;">Auth: <strong>$' + rates.authentication.toFixed(4) + '</strong></span>';
            if (rates.authentication_international) html += '<span style="margin-right:12px;">Auth Intl: <strong>$' + rates.authentication_international.toFixed(4) + '</strong></span>';
            html += '</div></div>';
        {literal}}{/literal});

        if (matches.length > 5) html += '<p class="text-muted">' + (matches.length - 5) + ' more results...</p>';
        el.innerHTML = html;
        el.style.display = 'block';
    {literal}}{/literal});
    </script>
</div>
