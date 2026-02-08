{$sms_css nofilter}
<div class="sms-suite-logs">
    <div class="sms-page-header">
        <h2><i class="fas fa-history"></i> {$lang.message_log}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
        <li class="active"><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> {$lang.message_log}</h3>
        </div>
        <div class="card-body">
            {if $messages|count > 0}
            <div class="table-responsive">
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
                            <td><strong>{$msg->to_number}</strong></td>
                            <td>{$msg->sender_id}</td>
                            <td title="{$msg->message|escape:'html'}">{$msg->message|truncate:50}</td>
                            <td>
                                {if $msg->status == 'delivered'}
                                    <span class="badge badge-success">{$msg->status|ucfirst}</span>
                                {elseif $msg->status == 'failed' || $msg->status == 'rejected'}
                                    <span class="badge badge-danger">{$msg->status|ucfirst}</span>
                                {elseif $msg->status == 'queued' || $msg->status == 'sending'}
                                    <span class="badge badge-warning">{$msg->status|ucfirst}</span>
                                {else}
                                    <span class="badge badge-secondary">{$msg->status|ucfirst}</span>
                                {/if}
                            </td>
                            <td>{$msg->segments}</td>
                            <td><small>{$msg->created_at}</small></td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fas fa-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p style="margin-top: 12px;">{$lang.no_results}</p>
            </div>
            {/if}
        </div>
    </div>
</div>
