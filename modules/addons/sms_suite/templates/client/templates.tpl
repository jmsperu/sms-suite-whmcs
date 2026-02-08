{$sms_css nofilter}
<div class="sms-suite-templates">
    <div class="sms-page-header">
        <h2><i class="fas fa-file-alt"></i> {$lang.templates|default:'Message Templates'}</h2>
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
        <li><a href="{$modulelink}&action=sender_ids">{$lang.menu_sender_ids}</a></li>
        <li class="active"><a href="{$modulelink}&action=templates">{$lang.templates|default:'Templates'}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
        <li><a href="{$modulelink}&action=api_keys">{$lang.menu_api_keys}</a></li>
        <li><a href="{$modulelink}&action=billing">{$lang.menu_billing}</a></li>
    </ul>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list"></i> {$lang.templates|default:'Message Templates'}</h3>
        </div>
        <div class="card-body">
            {if $templates|@count > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.name|default:'Name'}</th>
                            <th>{$lang.message|default:'Message'}</th>
                            <th>{$lang.category|default:'Category'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $templates as $tpl}
                        <tr>
                            <td><strong>{$tpl->name}</strong></td>
                            <td>{$tpl->message|truncate:80:'...'}</td>
                            <td>
                                {if $tpl->category}
                                <span class="badge badge-info">{$tpl->category}</span>
                                {else}
                                <span class="text-muted">-</span>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fas fa-file-alt" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p style="margin-top: 12px;">{$lang.no_results|default:'No templates found.'}</p>
            </div>
            {/if}
        </div>
    </div>
</div>
