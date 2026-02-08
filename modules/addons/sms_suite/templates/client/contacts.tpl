{$sms_css nofilter}
<div class="sms-suite-contacts">
    <div class="row">
        <div class="col-sm-8">
            <h2>{$lang.contacts}</h2>
        </div>
        <div class="col-sm-4 text-right">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addContactModal">
                <i class="fas fa-plus"></i> {$lang.contact_add}
            </button>
            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-upload"></i> {$lang.import}
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin: 20px 0;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
                <li class="active"><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
                <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
            </ul>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {$error}
    </div>
    {/if}

    <!-- Filters -->
    <div class="panel panel-default">
        <div class="panel-body">
            <form method="get" class="form-inline">
                <input type="hidden" name="m" value="sms_suite">
                <input type="hidden" name="action" value="contacts">

                <div class="form-group">
                    <select name="group_id" class="form-control">
                        <option value="">{$lang.all} {$lang.contact_groups|default:'Groups'}</option>
                        {foreach $groups as $group}
                        <option value="{$group->id}" {if $filters.group_id eq $group->id}selected{/if}>
                            {$group->name|escape:'html'} ({$group->contact_count})
                        </option>
                        {/foreach}
                    </select>
                </div>

                <div class="form-group">
                    <input type="text" name="search" class="form-control" placeholder="{$lang.search}..."
                           value="{$filters.search|escape:'html'}">
                </div>

                <button type="submit" class="btn btn-default">{$lang.filter}</button>

                {if $filters.group_id || $filters.search}
                <a href="{$modulelink}&action=contacts" class="btn btn-link">{$lang.reset}</a>
                {/if}

                <form method="post" style="display: inline; margin-left: 20px;">
                    <input type="hidden" name="export_csv" value="1">
                    <input type="hidden" name="export_group_id" value="{$filters.group_id}">
                    <button type="submit" class="btn btn-default">
                        <i class="fas fa-download"></i> {$lang.export}
                    </button>
                </form>
            </form>
        </div>
    </div>

    <!-- Contacts Table -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>{$total}</strong> {$lang.contacts}
        </div>
        <div class="panel-body">
            {if $contacts && count($contacts) > 0}
            <table class="table table-striped table-hover">
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
            <p class="text-muted text-center">No contacts found. Add your first contact or import from CSV.</p>
            {/if}
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal fade" id="addContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="add_contact" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{$lang.contact_add}</h4>
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
                    <button type="submit" class="btn btn-primary">{$lang.save}</button>
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
                <input type="hidden" name="import_csv" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{$lang.contact_import}</h4>
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
                    <button type="submit" class="btn btn-primary">{$lang.import}</button>
                </div>
            </form>
        </div>
    </div>
</div>
