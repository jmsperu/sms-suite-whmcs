<div class="sms-suite-dashboard">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.module_name}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li class="active"><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
                <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
                <li><a href="{$modulelink}&action=sender_ids">{$lang.menu_sender_ids}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li><a href="{$modulelink}&action=api_keys">{$lang.menu_api_keys}</a></li>
                <li><a href="{$modulelink}&action=billing">{$lang.menu_billing}</a></li>
            </ul>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-sm-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4>{$total_messages}</h4>
                    <p>{$lang.total_messages}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4>{$delivered_messages}</h4>
                    <p>{$lang.delivered}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4>{$today_messages}</h4>
                    <p>{$lang.messages_today}</p>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4>{$balance|number_format:4}</h4>
                    <p>{$lang.wallet_balance}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Messages -->
        <div class="col-sm-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.client_recent_messages}</h3>
                </div>
                <div class="panel-body">
                    {if $recent_messages|count > 0}
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{$lang.message_to}</th>
                                    <th>{$lang.status}</th>
                                    <th>{$lang.date}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach $recent_messages as $msg}
                                    <tr>
                                        <td>{$msg->to_number}</td>
                                        <td>
                                            {if $msg->status == 'delivered'}
                                                <span class="label label-success">{$msg->status|ucfirst}</span>
                                            {elseif $msg->status == 'failed' || $msg->status == 'rejected'}
                                                <span class="label label-danger">{$msg->status|ucfirst}</span>
                                            {else}
                                                <span class="label label-default">{$msg->status|ucfirst}</span>
                                            {/if}
                                        </td>
                                        <td>{$msg->created_at}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                        <a href="{$modulelink}&action=logs" class="btn btn-default btn-sm">{$lang.view} {$lang.all}</a>
                    {else}
                        <p class="text-muted">{$lang.no_results}</p>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-sm-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.quick_links}</h3>
                </div>
                <div class="panel-body">
                    <a href="{$modulelink}&action=send" class="btn btn-primary btn-block">
                        <i class="fa fa-paper-plane"></i> {$lang.menu_send_sms}
                    </a>
                    <a href="{$modulelink}&action=campaigns" class="btn btn-default btn-block">
                        <i class="fa fa-bullhorn"></i> {$lang.menu_campaigns}
                    </a>
                    <a href="{$modulelink}&action=contacts" class="btn btn-default btn-block">
                        <i class="fa fa-users"></i> {$lang.menu_contacts}
                    </a>
                    <a href="{$modulelink}&action=billing" class="btn btn-default btn-block">
                        <i class="fa fa-credit-card"></i> {$lang.menu_billing}
                    </a>
                </div>
            </div>

            <!-- Sender IDs -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.sender_ids}</h3>
                </div>
                <div class="panel-body">
                    {if $sender_ids|count > 0}
                        <ul class="list-unstyled">
                            {foreach $sender_ids as $sid}
                                <li><i class="fa fa-check-circle text-success"></i> {$sid->sender_id}</li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-muted">{$lang.no_results}</p>
                    {/if}
                    <a href="{$modulelink}&action=sender_ids" class="btn btn-default btn-sm">{$lang.sender_id_request}</a>
                </div>
            </div>
        </div>
    </div>
</div>
