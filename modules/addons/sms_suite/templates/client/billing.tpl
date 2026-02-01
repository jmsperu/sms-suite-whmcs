<div class="sms-suite-billing">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.billing}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li class="active"><a href="{$modulelink}&action=billing">{$lang.billing}</a></li>
                <li><a href="{$modulelink}&action=api_keys">{$lang.api_keys}</a></li>
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

    <div class="row">
        <!-- Balance Cards -->
        <div class="col-md-4">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.wallet_balance}</h3>
                </div>
                <div class="panel-body text-center">
                    <h2 style="margin: 0; font-size: 2.5em;">
                        ${$wallet->balance|number_format:2}
                    </h2>
                    <p class="text-muted">{$lang.billing_mode}: {$settings->billing_mode|ucfirst|replace:'_':' '}</p>
                </div>
            </div>
        </div>

        {if $settings->billing_mode eq 'plan'}
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.credits_remaining}</h3>
                </div>
                <div class="panel-body text-center">
                    <h2 style="margin: 0; font-size: 2.5em;">
                        {$credits|number_format:0}
                    </h2>
                    <p class="text-muted">{$lang.credits}</p>
                </div>
            </div>
        </div>
        {/if}

        <!-- Top-up Form -->
        <div class="col-md-{if $settings->billing_mode eq 'plan'}4{else}8{/if}">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.wallet_topup}</h3>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-inline">
                        <div class="form-group">
                            <label class="sr-only">Amount</label>
                            <div class="input-group">
                                <div class="input-group-addon">$</div>
                                <input type="number" name="topup_amount" class="form-control"
                                       placeholder="Amount" min="5" max="10000" step="0.01"
                                       value="25.00" style="width: 120px;">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> {$lang.wallet_topup}
                        </button>
                    </form>
                    <small class="text-muted">Minimum: $5.00 | Maximum: $10,000.00</small>
                </div>
            </div>
        </div>
    </div>

    {if $plan_credits && count($plan_credits) > 0}
    <!-- Plan Credits -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Your Credit Packages</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Total Credits</th>
                                <th>Remaining</th>
                                <th>Expires</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $plan_credits as $pc}
                            <tr>
                                <td>Credit Package #{$pc->id}</td>
                                <td>{$pc->total|number_format:0}</td>
                                <td>
                                    <strong>{$pc->remaining|number_format:0}</strong>
                                    <div class="progress" style="margin: 5px 0 0 0; height: 5px;">
                                        <div class="progress-bar" style="width: {($pc->remaining/$pc->total)*100}%"></div>
                                    </div>
                                </td>
                                <td>{$pc->expires_at|date_format:"%Y-%m-%d"}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
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
                    <h3 class="panel-title">{$lang.wallet_transactions}</h3>
                </div>
                <div class="panel-body">
                    {if $transactions && count($transactions) > 0}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{$lang.date}</th>
                                <th>{$lang.type}</th>
                                <th>{$lang.description}</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Balance After</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $transactions as $tx}
                            <tr>
                                <td>{$tx->created_at|date_format:"%Y-%m-%d %H:%M"}</td>
                                <td>
                                    {if $tx->type eq 'topup'}
                                    <span class="label label-success">{$lang.transaction_topup}</span>
                                    {elseif $tx->type eq 'deduction'}
                                    <span class="label label-warning">{$lang.transaction_deduction}</span>
                                    {elseif $tx->type eq 'refund'}
                                    <span class="label label-info">{$lang.transaction_refund}</span>
                                    {elseif $tx->type eq 'credit_add'}
                                    <span class="label label-success">Credits Added</span>
                                    {elseif $tx->type eq 'credit_deduction'}
                                    <span class="label label-warning">Credits Used</span>
                                    {else}
                                    <span class="label label-default">{$tx->type|ucfirst}</span>
                                    {/if}
                                </td>
                                <td>{$tx->description|escape:'html'}</td>
                                <td class="text-right {if $tx->amount >= 0}text-success{else}text-danger{/if}">
                                    {if $tx->amount >= 0}+{/if}{$tx->amount|number_format:4}
                                </td>
                                <td class="text-right">
                                    {if $tx->balance_after !== null}
                                    ${$tx->balance_after|number_format:2}
                                    {else}
                                    -
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No transactions yet.</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sms-suite-billing .panel-success .panel-body h2,
.sms-suite-billing .panel-info .panel-body h2 {
    color: #333;
}
</style>
