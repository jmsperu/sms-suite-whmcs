{$sms_css nofilter}
<div class="sms-suite-logs">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.message_log}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
                <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
                <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
                <li class="active"><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
            </ul>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-body">
            {if $messages|count > 0}
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.message_to}</th>
                            <th>{$lang.message_from}</th>
                            <th>{$lang.message}</th>
                            <th>{$lang.status}</th>
                            <th>{$lang.message_segments}</th>
                            <th>{$lang.date}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $messages as $msg}
                            <tr>
                                <td>{$msg->to_number}</td>
                                <td>{$msg->sender_id}</td>
                                <td title="{$msg->message|escape:'html'}">{$msg->message|truncate:50}</td>
                                <td>
                                    {if $msg->status == 'delivered'}
                                        <span class="label label-success">{$msg->status|ucfirst}</span>
                                    {elseif $msg->status == 'failed' || $msg->status == 'rejected'}
                                        <span class="label label-danger">{$msg->status|ucfirst}</span>
                                    {elseif $msg->status == 'queued' || $msg->status == 'sending'}
                                        <span class="label label-warning">{$msg->status|ucfirst}</span>
                                    {else}
                                        <span class="label label-default">{$msg->status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td>{$msg->segments}</td>
                                <td>{$msg->created_at}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            {else}
                <p class="text-muted">{$lang.no_results}</p>
            {/if}
        </div>
    </div>
</div>
