<div class="sms-suite-billing">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.billing|default:'Billing & SMS Packages'}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=sender_ids">{$lang.sender_ids|default:'Sender IDs'}</a></li>
                <li class="active"><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
            </ul>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success">
        <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <!-- Account Summary Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-wallet"></i> {$lang.wallet_balance|default:'Wallet Balance'}</h3>
                </div>
                <div class="panel-body text-center">
                    <h2 style="margin: 0; font-size: 2.2em; color: #27ae60;">
                        {$currency_symbol}{$wallet->balance|number_format:2}
                    </h2>
                    <small class="text-muted">{$currency_code}</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-comment"></i> {$lang.sms_credits|default:'SMS Credits'}</h3>
                </div>
                <div class="panel-body text-center">
                    <h2 style="margin: 0; font-size: 2.2em; color: #2980b9;">
                        {$credits|number_format:0}
                    </h2>
                    <small class="text-muted">{$lang.credits_available|default:'credits available'}</small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-id-card"></i> {$lang.sender_ids|default:'Sender IDs'}</h3>
                </div>
                <div class="panel-body text-center">
                    <h2 style="margin: 0; font-size: 2.2em; color: #3498db;">
                        {$active_sender_ids|default:0}
                    </h2>
                    <small class="text-muted">{$lang.active_ids|default:'active'}</small>
                    {if $pending_requests > 0}
                    <br><span class="label label-warning">{$pending_requests} pending</span>
                    {/if}
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-cog"></i> {$lang.billing_mode|default:'Billing Mode'}</h3>
                </div>
                <div class="panel-body text-center">
                    <h4 style="margin: 10px 0; color: #7f8c8d;">
                        {$settings->billing_mode|ucfirst|replace:'_':' '}
                    </h4>
                    <small class="text-muted">{$lang.per_message_billing|default:'Per message billing'}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- SMS Packages -->
    {if $sms_packages && count($sms_packages) > 0}
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-shopping-cart"></i> {$lang.buy_sms_package|default:'Buy SMS Credits Package'}</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted">{$lang.package_description|default:'Choose an SMS credit package below. Credits will be added to your account immediately after payment.'}</p>

                    <div class="row">
                        {foreach $sms_packages as $package}
                        <div class="col-md-3 col-sm-6" style="margin-bottom: 15px;">
                            <div class="panel panel-default package-card {if $package->popular}panel-warning{/if}">
                                {if $package->popular}
                                <div class="ribbon"><span>Popular</span></div>
                                {/if}
                                <div class="panel-heading text-center">
                                    <h4 style="margin: 0;">{$package->name}</h4>
                                </div>
                                <div class="panel-body text-center">
                                    <div class="package-credits">
                                        <span class="credits-number">{$package->credits|number_format:0}</span>
                                        <span class="credits-label">{$lang.sms_credits|default:'SMS Credits'}</span>
                                    </div>
                                    <div class="package-price">
                                        <span class="price-amount">{$currency_symbol}{$package->price|number_format:2}</span>
                                        {if $package->bonus_credits > 0}
                                        <br><span class="label label-success">+{$package->bonus_credits} Bonus!</span>
                                        {/if}
                                    </div>
                                    <div class="package-rate">
                                        <small class="text-muted">
                                            {$currency_symbol}{($package->price / $package->credits)|number_format:4} per SMS
                                        </small>
                                    </div>
                                    {if $package->validity_days > 0}
                                    <div class="package-validity">
                                        <small class="text-muted">Valid for {$package->validity_days} days</small>
                                    </div>
                                    {/if}
                                </div>
                                <div class="panel-footer">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                        <input type="hidden" name="package_id" value="{$package->id}">
                                        <button type="submit" name="buy_package" class="btn btn-primary btn-block">
                                            <i class="fa fa-shopping-cart"></i> {$lang.buy_now|default:'Buy Now'}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        {/foreach}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    <div class="row">
        <!-- Wallet Top-up -->
        <div class="col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-plus-circle"></i> {$lang.wallet_topup|default:'Top Up Wallet'}</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted">{$lang.topup_description|default:'Add funds to your wallet for pay-per-message billing.'}</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                        <div class="form-group">
                            <label>{$lang.amount|default:'Amount'}</label>
                            <div class="input-group">
                                <div class="input-group-addon">{$currency_symbol}</div>
                                <input type="number" name="topup_amount" class="form-control"
                                       placeholder="Enter amount" min="5" max="10000" step="0.01"
                                       value="25.00">
                            </div>
                            <small class="text-muted">Min: {$currency_symbol}5.00 | Max: {$currency_symbol}10,000.00</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-credit-card"></i> {$lang.topup_now|default:'Top Up Now'}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-link"></i> {$lang.quick_actions|default:'Quick Actions'}</h3>
                </div>
                <div class="panel-body">
                    <a href="{$modulelink}&action=sender_ids" class="btn btn-default btn-lg btn-block" style="margin-bottom: 10px;">
                        <i class="fa fa-id-card"></i> {$lang.request_sender_id|default:'Request Sender ID'}
                    </a>
                    <a href="{$modulelink}&action=send" class="btn btn-success btn-lg btn-block" style="margin-bottom: 10px;">
                        <i class="fa fa-paper-plane"></i> {$lang.send_sms|default:'Send SMS'}
                    </a>
                    <a href="{$modulelink}&action=logs" class="btn btn-info btn-lg btn-block">
                        <i class="fa fa-history"></i> {$lang.view_history|default:'View Message History'}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {if $plan_credits && count($plan_credits) > 0}
    <!-- Active Credit Packages -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-gift"></i> {$lang.your_packages|default:'Your Credit Packages'}</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{$lang.package|default:'Package'}</th>
                                <th>{$lang.total_credits|default:'Total Credits'}</th>
                                <th>{$lang.remaining|default:'Remaining'}</th>
                                <th>{$lang.expires|default:'Expires'}</th>
                                <th>{$lang.status|default:'Status'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $plan_credits as $pc}
                            <tr>
                                <td><strong>Credit Package #{$pc->id}</strong></td>
                                <td>{$pc->total|number_format:0}</td>
                                <td>
                                    <strong class="text-primary">{$pc->remaining|number_format:0}</strong>
                                    <div class="progress" style="margin: 5px 0 0 0; height: 8px; max-width: 150px;">
                                        <div class="progress-bar progress-bar-success" style="width: {($pc->remaining/$pc->total)*100}%"></div>
                                    </div>
                                </td>
                                <td>{$pc->expires_at|date_format:"%b %d, %Y"}</td>
                                <td>
                                    {if $pc->remaining > 0}
                                    <span class="label label-success">{$lang.active|default:'Active'}</span>
                                    {else}
                                    <span class="label label-default">{$lang.exhausted|default:'Exhausted'}</span>
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {if $sender_id_usage && count($sender_id_usage) > 0}
    <!-- Credit Usage by Sender ID -->
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-pie-chart"></i> {$lang.usage_by_sender|default:'Usage by Sender ID'}</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{$lang.sender_id|default:'Sender ID'}</th>
                                <th>{$lang.network|default:'Network'}</th>
                                <th class="text-right">{$lang.messages|default:'Messages'}</th>
                                <th class="text-right">{$lang.credits|default:'Credits'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $sender_id_usage as $usage}
                            <tr>
                                <td><strong>{$usage->sender_id|default:'(Default)'}</strong></td>
                                <td>
                                    {if $usage->network eq 'safaricom'}
                                    <span class="label label-success">Safaricom</span>
                                    {elseif $usage->network eq 'airtel'}
                                    <span class="label label-danger">Airtel</span>
                                    {elseif $usage->network eq 'telkom'}
                                    <span class="label label-info">Telkom</span>
                                    {else}
                                    <span class="label label-default">{$usage->network|ucfirst|default:'All'}</span>
                                    {/if}
                                </td>
                                <td class="text-right">{$usage->message_count|number_format:0}</td>
                                <td class="text-right">{$usage->total_credits|number_format:0}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-signal"></i> {$lang.usage_by_network|default:'Usage by Network'}</h3>
                </div>
                <div class="panel-body">
                    {if $network_usage && count($network_usage) > 0}
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{$lang.network|default:'Network'}</th>
                                <th class="text-right">{$lang.messages|default:'Messages'}</th>
                                <th class="text-right">{$lang.credits|default:'Credits'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $network_usage as $nu}
                            <tr>
                                <td>
                                    {if $nu->network eq 'safaricom'}
                                    <span class="label label-success" style="font-size: 1em;">Safaricom</span>
                                    {elseif $nu->network eq 'airtel'}
                                    <span class="label label-danger" style="font-size: 1em;">Airtel</span>
                                    {elseif $nu->network eq 'telkom'}
                                    <span class="label label-info" style="font-size: 1em;">Telkom</span>
                                    {else}
                                    <span class="label label-default" style="font-size: 1em;">{$nu->network|ucfirst|default:'Other'}</span>
                                    {/if}
                                </td>
                                <td class="text-right">{$nu->message_count|number_format:0}</td>
                                <td class="text-right">{$nu->total_credits|number_format:0}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No usage data yet</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    {/if}

    <!-- Transaction History -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-list"></i> {$lang.transaction_history|default:'Transaction History'}</h3>
                </div>
                <div class="panel-body">
                    {if $transactions && count($transactions) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{$lang.date|default:'Date'}</th>
                                    <th>{$lang.type|default:'Type'}</th>
                                    <th>{$lang.description|default:'Description'}</th>
                                    <th class="text-right">{$lang.amount|default:'Amount'}</th>
                                    <th class="text-right">{$lang.balance|default:'Balance'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $transactions as $tx}
                                <tr>
                                    <td><small>{$tx->created_at|date_format:"%b %d, %Y %H:%M"}</small></td>
                                    <td>
                                        {if $tx->type eq 'topup' || $tx->type eq 'credit_add'}
                                        <span class="label label-success"><i class="fa fa-plus"></i> {$tx->type|ucfirst|replace:'_':' '}</span>
                                        {elseif $tx->type eq 'deduction' || $tx->type eq 'credit_deduction'}
                                        <span class="label label-warning"><i class="fa fa-minus"></i> {$tx->type|ucfirst|replace:'_':' '}</span>
                                        {elseif $tx->type eq 'refund'}
                                        <span class="label label-info"><i class="fa fa-undo"></i> {$lang.refund|default:'Refund'}</span>
                                        {elseif $tx->type eq 'package_purchase'}
                                        <span class="label label-primary"><i class="fa fa-shopping-cart"></i> Package</span>
                                        {else}
                                        <span class="label label-default">{$tx->type|ucfirst|replace:'_':' '}</span>
                                        {/if}
                                    </td>
                                    <td>{$tx->description|escape:'html'}</td>
                                    <td class="text-right">
                                        <strong class="{if $tx->amount >= 0}text-success{else}text-danger{/if}">
                                            {if $tx->amount >= 0}+{/if}{$currency_symbol}{$tx->amount|number_format:2}
                                        </strong>
                                    </td>
                                    <td class="text-right">
                                        {if $tx->balance_after !== null}
                                        {$currency_symbol}{$tx->balance_after|number_format:2}
                                        {else}
                                        -
                                        {/if}
                                    </td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {else}
                    <div class="text-center" style="padding: 30px;">
                        <i class="fa fa-inbox fa-3x text-muted"></i>
                        <p class="text-muted" style="margin-top: 15px;">{$lang.no_transactions|default:'No transactions yet. Purchase an SMS package to get started!'}</p>
                    </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sms-suite-billing .package-card {
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.sms-suite-billing .package-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.sms-suite-billing .package-credits {
    margin: 15px 0;
}
.sms-suite-billing .credits-number {
    display: block;
    font-size: 2.5em;
    font-weight: bold;
    color: #2c3e50;
}
.sms-suite-billing .credits-label {
    display: block;
    font-size: 0.9em;
    color: #7f8c8d;
}
.sms-suite-billing .package-price {
    margin: 15px 0;
}
.sms-suite-billing .price-amount {
    font-size: 1.8em;
    font-weight: bold;
    color: #27ae60;
}
.sms-suite-billing .package-rate,
.sms-suite-billing .package-validity {
    margin: 5px 0;
}
.sms-suite-billing .ribbon {
    position: absolute;
    right: -35px;
    top: 20px;
    transform: rotate(45deg);
    background: #e74c3c;
    color: white;
    padding: 5px 40px;
    font-size: 0.8em;
    font-weight: bold;
}
.sms-suite-billing .panel-warning .ribbon {
    background: #f39c12;
}
</style>
