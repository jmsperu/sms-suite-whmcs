{$sms_css nofilter}
<div class="sms-suite-templates">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.module_name} - {$lang.templates|default:'Templates'}</h2>
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
                <li><a href="{$modulelink}&action=sender_ids">{$lang.menu_sender_ids}</a></li>
                <li class="active"><a href="{$modulelink}&action=templates">{$lang.templates|default:'Templates'}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li><a href="{$modulelink}&action=api_keys">{$lang.menu_api_keys}</a></li>
                <li><a href="{$modulelink}&action=billing">{$lang.menu_billing}</a></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.templates|default:'Message Templates'}</h3>
                </div>
                <div class="panel-body">
                    {if $templates|@count > 0}
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
                                        <td>{$tpl->name}</td>
                                        <td>{$tpl->message|truncate:80:'...'}</td>
                                        <td>{$tpl->category|default:'-'}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    {else}
                        <p class="text-muted">{$lang.no_results|default:'No templates found.'}</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</div>
