<div class="sms-suite-service">
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-comment"></i> SMS Credit Balance</h3>
                </div>
                <div class="panel-body text-center">
                    <h1 style="font-size: 3em; margin: 20px 0; color: #27ae60;">
                        {$credit_balance|number_format:0}
                    </h1>
                    <p class="text-muted">SMS Credits Available</p>
                    <a href="{$sms_suite_link}&action=send" class="btn btn-success btn-lg">
                        <i class="fa fa-paper-plane"></i> Send SMS Now
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-id-card"></i> Your Sender IDs</h3>
                </div>
                <div class="panel-body">
                    {if $sender_ids && count($sender_ids) > 0}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Sender ID</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $sender_ids as $sid}
                            <tr>
                                <td><strong>{$sid->sender_id}</strong></td>
                                <td>{$sid->type|ucfirst}</td>
                                <td><span class="label label-success">{$sid->status|ucfirst}</span></td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No active Sender IDs</p>
                    {/if}
                    <a href="{$sms_suite_link}&action=sender_ids" class="btn btn-info btn-block">
                        <i class="fa fa-plus"></i> Request Sender ID
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-history"></i> Recent Transactions</h3>
                </div>
                <div class="panel-body">
                    {if $transactions && count($transactions) > 0}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $transactions as $tx}
                            <tr>
                                <td>{$tx->created_at|date_format:"%b %d, %Y %H:%M"}</td>
                                <td>
                                    {if $tx->type eq 'package_purchase' || $tx->type eq 'package_renewal' || $tx->type eq 'admin_add'}
                                    <span class="label label-success">{$tx->type|replace:'_':' '|ucwords}</span>
                                    {else}
                                    <span class="label label-warning">{$tx->type|replace:'_':' '|ucwords}</span>
                                    {/if}
                                </td>
                                <td>{$tx->description}</td>
                                <td class="text-right {if $tx->amount > 0}text-success{else}text-danger{/if}">
                                    {if $tx->amount > 0}+{/if}{$tx->amount|number_format:0} SMS
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No transactions yet</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="well text-center">
                <p>Manage your SMS services, contacts, and campaigns from the SMS Suite dashboard.</p>
                <a href="{$sms_suite_link}" class="btn btn-primary btn-lg">
                    <i class="fa fa-dashboard"></i> Go to SMS Suite Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
