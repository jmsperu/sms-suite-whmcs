{$sms_css nofilter}
<div class="sms-suite-billing">
    <div class="sms-page-header">
        <h2><i class="fas fa-credit-card"></i> {$lang.billing|default:'Billing & SMS Packages'}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=sender_ids">{$lang.sender_ids|default:'Sender IDs'}</a></li>
        <li class="active"><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
        <li><a href="{$modulelink}&action=wa_rates">WA Rates</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <!-- Account Summary Cards -->
    <div class="row" style="margin-bottom: 24px;">
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-green"><i class="fas fa-wallet"></i></div>
                <h3 class="stat-value" style="color: #00c853;">{$currency_symbol}{$wallet->balance|number_format:2}</h3>
                <p class="stat-label">{$lang.wallet_balance|default:'Wallet Balance'}</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-blue"><i class="fas fa-comment"></i></div>
                <h3 class="stat-value" style="color: #155dfc;">{$credits|number_format:0}</h3>
                <p class="stat-label">{$lang.sms_credits|default:'SMS Credits'}</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-purple"><i class="fas fa-id-card"></i></div>
                <h3 class="stat-value">{$active_sender_ids|default:0}</h3>
                <p class="stat-label">{$lang.sender_ids|default:'Sender IDs'}</p>
                {if $pending_requests > 0}
                <span class="badge badge-warning" style="margin-top: 4px;">{$pending_requests} pending</span>
                {/if}
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-orange"><i class="fas fa-cog"></i></div>
                <h3 class="stat-value" style="font-size: 1.2rem;">{$settings->billing_mode|ucfirst|replace:'_':' '}</h3>
                <p class="stat-label">{$lang.billing_mode|default:'Billing Mode'}</p>
            </div>
        </div>
    </div>

    <!-- Credit Balance Graphic -->
    {if $total_purchased > 0}
    <div class="row">
        <div class="col-md-6 col-md-offset-3" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> {$lang.credit_overview|default:'Credit Balance Overview'}</h3>
                </div>
                <div class="card-body text-center">
                    {assign var="pct" value=0}
                    {if $total_purchased > 0}
                        {assign var="pct" value=($credit_balance / $total_purchased) * 100}
                    {/if}
                    {if $pct > 100}{assign var="pct" value=100}{/if}

                    <div class="credit-ring-container">
                        <div class="credit-ring {if $pct > 50}credit-ring-green{elseif $pct > 20}credit-ring-yellow{else}credit-ring-red{/if}" style="--pct: {$pct|number_format:1};">
                            <div class="credit-ring-inner">
                                <span class="credit-ring-number">{$credit_balance|number_format:0}</span>
                                <span class="credit-ring-label">{$lang.credits_remaining|default:'credits remaining'}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted" style="margin-top: 10px;">
                        of <strong>{$total_purchased|number_format:0}</strong> total purchased
                    </p>
                    <div class="row" style="margin-top: 15px;">
                        <div class="col-4">
                            <div style="color: #00c853;"><strong>{$total_purchased|number_format:0}</strong></div>
                            <small class="text-muted">{$lang.purchased|default:'Purchased'}</small>
                        </div>
                        <div class="col-4">
                            <div style="color: #ff9800;"><strong>{$total_used|number_format:0}</strong></div>
                            <small class="text-muted">{$lang.used|default:'Used'}</small>
                        </div>
                        <div class="col-4">
                            <div style="color: #ef4444;"><strong>{$total_expired|number_format:0}</strong></div>
                            <small class="text-muted">{$lang.expired|default:'Expired'}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    <!-- SMS Packages -->
    {if $sms_packages && count($sms_packages) > 0}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-shopping-cart"></i> {$lang.buy_sms_package|default:'Buy SMS Credits Package'}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom: 16px;">{$lang.package_description|default:'Choose an SMS credit package below. Credits will be added to your account immediately after payment.'}</p>
            <div class="row">
                {foreach $sms_packages as $package}
                <div class="col-md-3 col-sm-6" style="margin-bottom: 16px;">
                    <div style="background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); overflow: hidden; transition: transform .2s, box-shadow .2s; position: relative; {if $package->popular}border: 2px solid #ff9800;{/if}" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,.15)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                        {if $package->popular}
                        <div style="position: absolute; right: -30px; top: 18px; transform: rotate(45deg); background: linear-gradient(135deg, #ff9800, #ffb74d); color: #fff; padding: 4px 36px; font-size: .7rem; font-weight: 700;">Popular</div>
                        {/if}
                        <div style="padding: 20px; text-align: center; background: {if $package->popular}linear-gradient(135deg, rgba(255,152,0,.08), rgba(255,183,77,.08)){else}#f8fafc{/if};">
                            <h4 style="margin: 0; font-weight: 600;">{$package->name}</h4>
                        </div>
                        <div style="padding: 24px; text-align: center;">
                            <div style="font-size: 2.2rem; font-weight: 700; color: #1e293b;">{$package->credits|number_format:0}</div>
                            <div style="font-size: .8rem; color: #64748b; margin-bottom: 12px;">{$lang.sms_credits|default:'SMS Credits'}</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #00c853;">{$currency_symbol}{$package->price|number_format:2}</div>
                            {if $package->bonus_credits > 0}
                            <span class="badge badge-success" style="margin-top: 6px;">+{$package->bonus_credits} Bonus!</span>
                            {/if}
                            <div style="margin-top: 8px;">
                                <small class="text-muted">{$currency_symbol}{($package->price / $package->credits)|number_format:4} per SMS</small>
                            </div>
                            {if $package->validity_days > 0}
                            <div><small class="text-muted">Valid for {$package->validity_days} days</small></div>
                            {/if}
                        </div>
                        <div style="padding: 0 20px 20px;">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <input type="hidden" name="package_id" value="{$package->id}">
                                <button type="submit" name="buy_package" class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-cart"></i> {$lang.buy_now|default:'Buy Now'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                {/foreach}
            </div>
        </div>
    </div>
    {/if}

    <div class="row">
        <!-- Wallet Top-up -->
        <div class="col-md-6" style="margin-bottom: 24px;">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> {$lang.wallet_topup|default:'Top Up Wallet'}</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="margin-bottom: 16px;">{$lang.topup_description|default:'Add funds to your wallet for pay-per-message billing.'}</p>
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
                            <span class="form-text text-muted">Min: {$currency_symbol}5.00 | Max: {$currency_symbol}10,000.00</span>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> {$lang.topup_now|default:'Top Up Now'}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-md-6" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bolt"></i> {$lang.quick_actions|default:'Quick Actions'}</h3>
                </div>
                <div class="card-body">
                    <a href="{$modulelink}&action=sender_ids" class="btn btn-outline-secondary btn-lg btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-id-card"></i> {$lang.request_sender_id|default:'Request Sender ID'}
                    </a>
                    <a href="{$modulelink}&action=send" class="btn btn-success btn-lg btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-paper-plane"></i> {$lang.send_sms|default:'Send SMS'}
                    </a>
                    <a href="{$modulelink}&action=logs" class="btn btn-info btn-lg btn-block">
                        <i class="fas fa-history"></i> {$lang.view_history|default:'View Message History'}
                    </a>
                </div>
            </div>
        </div>
    </div>

    {if $plan_credits && count($plan_credits) > 0}
    <!-- Active Credit Packages -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-gift"></i> {$lang.your_packages|default:'Your Credit Packages'}</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
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
                                <strong style="color: var(--sms-primary);">{$pc->remaining|number_format:0}</strong>
                                <div class="progress" style="margin: 5px 0 0 0; max-width: 150px;">
                                    <div class="progress-bar bg-success" style="width: {($pc->remaining/$pc->total)*100}%"></div>
                                </div>
                            </td>
                            <td>{$pc->expires_at|date_format:"%b %d, %Y"}</td>
                            <td>
                                {if $pc->remaining > 0}
                                <span class="badge badge-success">{$lang.active|default:'Active'}</span>
                                {else}
                                <span class="badge badge-secondary">{$lang.exhausted|default:'Exhausted'}</span>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {/if}

    {if $sender_id_usage && count($sender_id_usage) > 0}
    <div class="row">
        <div class="col-md-6" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie"></i> {$lang.usage_by_sender|default:'Usage by Sender ID'}</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
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
                                        <span class="badge badge-success">Safaricom</span>
                                        {elseif $usage->network eq 'airtel'}
                                        <span class="badge badge-danger">Airtel</span>
                                        {elseif $usage->network eq 'telkom'}
                                        <span class="badge badge-info">Telkom</span>
                                        {else}
                                        <span class="badge badge-secondary">{$usage->network|ucfirst|default:'All'}</span>
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
        </div>

        <div class="col-md-6" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-signal"></i> {$lang.usage_by_network|default:'Usage by Network'}</h3>
                </div>
                <div class="card-body">
                    {if $network_usage && count($network_usage) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped">
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
                                        <span class="badge badge-success" style="font-size: .85em;">Safaricom</span>
                                        {elseif $nu->network eq 'airtel'}
                                        <span class="badge badge-danger" style="font-size: .85em;">Airtel</span>
                                        {elseif $nu->network eq 'telkom'}
                                        <span class="badge badge-info" style="font-size: .85em;">Telkom</span>
                                        {else}
                                        <span class="badge badge-secondary" style="font-size: .85em;">{$nu->network|ucfirst|default:'Other'}</span>
                                        {/if}
                                    </td>
                                    <td class="text-right">{$nu->message_count|number_format:0}</td>
                                    <td class="text-right">{$nu->total_credits|number_format:0}</td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {else}
                    <p class="text-muted text-center">No usage data yet</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    {/if}

    <!-- Transaction History -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exchange-alt"></i> {$lang.transaction_history|default:'Transaction History'}</h3>
        </div>
        <div class="card-body">
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
                                <span class="badge badge-success"><i class="fas fa-plus"></i> {$tx->type|ucfirst|replace:'_':' '}</span>
                                {elseif $tx->type eq 'deduction' || $tx->type eq 'credit_deduction'}
                                <span class="badge badge-warning"><i class="fas fa-minus"></i> {$tx->type|ucfirst|replace:'_':' '}</span>
                                {elseif $tx->type eq 'refund'}
                                <span class="badge badge-info"><i class="fas fa-undo"></i> {$lang.refund|default:'Refund'}</span>
                                {elseif $tx->type eq 'package_purchase'}
                                <span class="badge badge-primary"><i class="fas fa-shopping-cart"></i> Package</span>
                                {else}
                                <span class="badge badge-secondary">{$tx->type|ucfirst|replace:'_':' '}</span>
                                {/if}
                            </td>
                            <td>{$tx->description|escape:'html'}</td>
                            <td class="text-right">
                                <strong style="color: {if $tx->amount >= 0}#00c853{else}#ef4444{/if};">
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
            <div class="text-center" style="padding: 40px;">
                <i class="fas fa-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p class="text-muted" style="margin-top: 12px;">{$lang.no_transactions|default:'No transactions yet. Purchase an SMS package to get started!'}</p>
            </div>
            {/if}
        </div>
    </div>
</div>

<style>
/* Credit Balance Ring Chart */
.sms-suite-billing .credit-ring-container {
    display: flex;
    justify-content: center;
    padding: 10px 0;
}
.sms-suite-billing .credit-ring {
    position: relative;
    width: 180px;
    height: 180px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sms-suite-billing .credit-ring-green {
    background: conic-gradient(#00c853 0% calc(var(--pct) * 1%), #e2e8f0 calc(var(--pct) * 1%) 100%);
}
.sms-suite-billing .credit-ring-yellow {
    background: conic-gradient(#ff9800 0% calc(var(--pct) * 1%), #e2e8f0 calc(var(--pct) * 1%) 100%);
}
.sms-suite-billing .credit-ring-red {
    background: conic-gradient(#ef4444 0% calc(var(--pct) * 1%), #e2e8f0 calc(var(--pct) * 1%) 100%);
}
.sms-suite-billing .credit-ring-inner {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.sms-suite-billing .credit-ring-number {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}
.sms-suite-billing .credit-ring-label {
    font-size: .7rem;
    color: #64748b;
    margin-top: 4px;
}
</style>
