{$sms_css nofilter}
<div class="sms-suite-dashboard">
    <div class="sms-page-header">
        <h2><i class="fas fa-tachometer-alt"></i> {$lang.module_name}</h2>
        <div>
            <a href="{$modulelink}&action=send" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> {$lang.menu_send_sms}
            </a>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li class="active"><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.menu_campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.menu_contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
        <li><a href="{$modulelink}&action=sender_ids">{$lang.menu_sender_ids}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=api_keys">{$lang.menu_api_keys}</a></li>
        <li><a href="{$modulelink}&action=billing">{$lang.menu_billing}</a></li>
        <li><a href="{$modulelink}&action=wa_rates">WA Rates</a></li>
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
    </ul>

    <!-- Stats Cards -->
    <div class="row" style="margin-bottom: 24px;">
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-purple"><i class="fas fa-envelope"></i></div>
                <h3 class="stat-value">{$total_messages}</h3>
                <p class="stat-label">{$lang.total_messages}</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-green"><i class="fas fa-check-double"></i></div>
                <h3 class="stat-value">{$delivered_messages}</h3>
                <p class="stat-label">{$lang.delivered}</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-blue"><i class="fas fa-calendar-day"></i></div>
                <h3 class="stat-value">{$today_messages}</h3>
                <p class="stat-label">{$lang.messages_today}</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-3" style="margin-bottom: 16px;">
            <div class="sms-stat-card">
                <div class="stat-icon bg-orange"><i class="fas fa-wallet"></i></div>
                <h3 class="stat-value">{$currency_symbol}{$balance|number_format:2}</h3>
                <p class="stat-label">{$lang.wallet_balance}</p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Messages -->
        <div class="col-md-8" style="margin-bottom: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock"></i> {$lang.client_recent_messages}</h3>
                </div>
                <div class="card-body">
                    {if $recent_messages|count > 0}
                    <div class="table-responsive">
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
                                    <td><strong>{$msg->to_number}</strong></td>
                                    <td>
                                        {if $msg->status == 'delivered'}
                                            <span class="badge badge-success">{$msg->status|ucfirst}</span>
                                        {elseif $msg->status == 'failed' || $msg->status == 'rejected'}
                                            <span class="badge badge-danger">{$msg->status|ucfirst}</span>
                                        {else}
                                            <span class="badge badge-secondary">{$msg->status|ucfirst}</span>
                                        {/if}
                                    </td>
                                    <td>{$msg->created_at}</td>
                                </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    <a href="{$modulelink}&action=logs" class="btn btn-outline-secondary btn-sm">
                        {$lang.view} {$lang.all} <i class="fas fa-arrow-right"></i>
                    </a>
                    {else}
                    <div class="text-center text-muted" style="padding: 40px 20px;">
                        <i class="fas fa-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                        <p style="margin-top: 12px;">{$lang.no_results}</p>
                    </div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bolt"></i> {$lang.quick_links}</h3>
                </div>
                <div class="card-body">
                    <a href="{$modulelink}&action=send" class="btn btn-primary btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-paper-plane"></i> {$lang.menu_send_sms}
                    </a>
                    <a href="{$modulelink}&action=inbox" class="btn btn-info btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-inbox"></i> Inbox
                    </a>
                    <a href="{$modulelink}&action=campaigns" class="btn btn-outline-secondary btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-bullhorn"></i> {$lang.menu_campaigns}
                    </a>
                    <a href="{$modulelink}&action=contacts" class="btn btn-outline-secondary btn-block" style="margin-bottom: 10px;">
                        <i class="fas fa-users"></i> {$lang.menu_contacts}
                    </a>
                    <a href="{$modulelink}&action=billing" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-credit-card"></i> {$lang.menu_billing}
                    </a>
                </div>
            </div>

            <!-- Sender IDs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-id-badge"></i> {$lang.sender_ids}</h3>
                </div>
                <div class="card-body">
                    {if $sender_ids|count > 0}
                    <ul class="list-unstyled" style="margin-bottom: 12px;">
                        {foreach $sender_ids as $sid}
                        <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                            <a href="{$modulelink}&action=sender_ids" style="text-decoration: none; color: var(--sms-dark); display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-check-circle" style="color: var(--sms-success);"></i>
                                <strong>{$sid->sender_id|escape:'html'}</strong>
                                {if $sid->source|default:'' eq 'assigned' || $sid->source|default:'' eq 'admin'}
                                <span class="badge badge-info" style="font-size: .65rem;">Admin</span>
                                {/if}
                            </a>
                        </li>
                        {/foreach}
                    </ul>
                    {else}
                    <p class="text-muted">{$lang.no_results}</p>
                    {/if}
                    <a href="{$modulelink}&action=sender_ids" class="btn btn-outline-secondary btn-sm">{$lang.sender_id_request}</a>
                </div>
            </div>
        </div>
    </div>
</div>
