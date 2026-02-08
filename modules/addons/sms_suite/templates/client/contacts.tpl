{$sms_css nofilter}
<div class="sms-suite-contacts">
    <div class="sms-page-header">
        <h2><i class="fas fa-address-book"></i> {$lang.contacts}</h2>
        <div>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addContactModal">
                <i class="fas fa-plus"></i> {$lang.contact_add}
            </button>
            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-upload"></i> {$lang.import}
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
        <li class="active"><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle"></i> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i> {$error}
    </div>
    {/if}

    <!-- Filters -->
    <div class="panel panel-default" style="margin-bottom: 16px;">
        <div class="panel-body" style="padding: 14px 20px;">
            <form method="get" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <input type="hidden" name="m" value="sms_suite">
                <input type="hidden" name="action" value="contacts">

                <select name="group_id" class="form-control" style="width: auto; min-width: 160px;">
                    <option value="">{$lang.all} {$lang.contact_groups|default:'Groups'}</option>
                    {foreach $groups as $group}
                    <option value="{$group->id}" {if $filters.group_id eq $group->id}selected{/if}>
                        {$group->name|escape:'html'} ({$group->contact_count})
                    </option>
                    {/foreach}
                </select>

                <input type="text" name="search" class="form-control" placeholder="{$lang.search}..."
                       value="{$filters.search|escape:'html'}" style="width: auto; min-width: 180px;">

                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> {$lang.filter}</button>

                {if $filters.group_id || $filters.search}
                <a href="{$modulelink}&action=contacts" class="btn btn-default btn-sm">{$lang.reset}</a>
                {/if}

                <form method="post" style="display: inline; margin-left: auto;">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <input type="hidden" name="export_csv" value="1">
                    <input type="hidden" name="export_group_id" value="{$filters.group_id}">
                    <button type="submit" class="btn btn-default btn-sm">
                        <i class="fas fa-download"></i> {$lang.export}
                    </button>
                </form>
            </form>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><strong>{$total}</strong> {$lang.contacts}</h3>
        </div>
        <div class="panel-body">
            {if $contacts && count($contacts) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.contact_phone}</th>
                            <th>{$lang.contact_first_name}</th>
                            <th>{$lang.contact_last_name}</th>
                            <th>{$lang.contact_email}</th>
                            <th>{$lang.contact_group}</th>
                            <th>{$lang.status}</th>
                            <th>{$lang.actions}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $contacts as $contact}
                        <tr>
                            <td><strong>{$contact->phone|escape:'html'}</strong></td>
                            <td>{$contact->first_name|escape:'html'}</td>
                            <td>{$contact->last_name|escape:'html'}</td>
                            <td>{$contact->email|escape:'html'}</td>
                            <td>
                                {foreach $groups as $g}
                                    {if $g->id eq $contact->group_id}
                                        <span class="label label-info">{$g->name|escape:'html'}</span>
                                    {/if}
                                {/foreach}
                            </td>
                            <td>
                                {if $contact->status eq 'active'}
                                <span class="label label-success">{$lang.active}</span>
                                {else}
                                <span class="label label-default">{$contact->status|ucfirst}</span>
                                {/if}
                            </td>
                            <td>
                                <a href="{$modulelink}&action=send&to={$contact->phone|urlencode}" class="btn btn-xs btn-primary" title="Send SMS">
                                    <i class="fas fa-paper-plane"></i>
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('{$lang.confirm_delete}');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_contact" value="1">
                                    <input type="hidden" name="contact_id" value="{$contact->id}">
                                    <button type="submit" class="btn btn-xs btn-danger" title="{$lang.delete}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            {* Pagination *}
            {if $total_pages > 1}
            <nav>
                <ul class="pagination">
                    {if $page > 1}
                    <li><a href="{$modulelink}&action=contacts&page={$page-1}{if $filters.group_id}&group_id={$filters.group_id}{/if}{if $filters.search}&search={$filters.search|urlencode}{/if}">&laquo;</a></li>
                    {/if}

                    {for $i=1 to $total_pages}
                        {if $i <= 3 || $i > $total_pages - 3 || ($i >= $page - 1 && $i <= $page + 1)}
                        <li class="{if $i eq $page}active{/if}">
                            <a href="{$modulelink}&action=contacts&page={$i}{if $filters.group_id}&group_id={$filters.group_id}{/if}{if $filters.search}&search={$filters.search|urlencode}{/if}">{$i}</a>
                        </li>
                        {elseif $i == 4 || $i == $total_pages - 3}
                        <li class="disabled"><span>...</span></li>
                        {/if}
                    {/for}

                    {if $page < $total_pages}
                    <li><a href="{$modulelink}&action=contacts&page={$page+1}{if $filters.group_id}&group_id={$filters.group_id}{/if}{if $filters.search}&search={$filters.search|urlencode}{/if}">&raquo;</a></li>
                    {/if}
                </ul>
            </nav>
            {/if}

            {else}
            <div class="text-center text-muted" style="padding: 40px 20px;">
                <i class="fas fa-address-book" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p style="margin-top: 12px;">No contacts found. Add your first contact or import from CSV.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="add_contact" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-user-plus"></i> {$lang.contact_add}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{$lang.contact_phone} <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="+1234567890" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{$lang.contact_first_name}</label>
                                <input type="text" name="first_name" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{$lang.contact_last_name}</label>
                                <input type="text" name="last_name" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{$lang.contact_email}</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>{$lang.contact_group}</label>
                        <select name="group_id" class="form-control">
                            <option value="">-- No Group --</option>
                            {foreach $groups as $group}
                            <option value="{$group->id}">{$group->name|escape:'html'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{$lang.cancel}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {$lang.save}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="import_csv" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-file-csv"></i> {$lang.contact_import}</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>CSV Format:</strong> Phone, First Name, Last Name, Email<br>
                        First row can be a header (will be skipped).
                    </div>
                    <div class="form-group">
                        <label>CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
                    </div>
                    <div class="form-group">
                        <label>Import to Group</label>
                        <select name="import_group_id" class="form-control">
                            <option value="">-- No Group --</option>
                            {foreach $groups as $group}
                            <option value="{$group->id}">{$group->name|escape:'html'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{$lang.cancel}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> {$lang.import}</button>
                </div>
            </form>
        </div>
    </div>
</div>
