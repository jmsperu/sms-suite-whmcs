{$sms_css nofilter}
<div class="sms-suite-contacts">
    <div class="sms-page-header">
        <h2><i class="fas fa-address-book"></i> {$lang.contacts}</h2>
        <div>
            <button type="button" class="btn btn-primary" id="btnAddContact" style="padding:10px 22px;">
                <i class="fas fa-plus"></i> {$lang.contact_add}
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnImport" style="padding:10px 22px;">
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
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
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

    <!-- Add Contact Panel -->
    <div id="addContactPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-user-plus"></i> {$lang.contact_add}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('addContactPanel').style.display='none';document.getElementById('btnAddContact').style.display='';">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="add_contact" value="1">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{$lang.contact_phone} <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="+254712345678" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{$lang.contact_first_name}</label>
                            <input type="text" name="first_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{$lang.contact_last_name}</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.contact_email}</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.contact_group}</label>
                            <select name="group_id" class="form-control">
                                <option value="">-- No Group --</option>
                                {foreach $groups as $group}
                                <option value="{$group->id}" {if $filters.group_id eq $group->id}selected{/if}>{$group->name|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> {$lang.save}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Panel -->
    <div id="importPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-upload"></i> Import Contacts</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('importPanel').style.display='none';document.getElementById('btnImport').style.display='';">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="import_contacts" value="1">

                <div class="alert alert-info" style="margin-bottom:16px;">
                    <strong>Supported formats:</strong> CSV, TXT, Excel (.xlsx)<br>
                    <strong>Column order:</strong> Phone, First Name, Last Name, Email<br>
                    You can also paste numbers directly (comma, newline, tab, or space separated).
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Upload File (CSV, TXT, XLSX)</label>
                            <input type="file" name="import_file" class="form-control" accept=".csv,.txt,.xlsx" id="importFileInput">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Import to Group</label>
                            <select name="import_group_id" class="form-control">
                                <option value="">-- No Group --</option>
                                {foreach $groups as $group}
                                <option value="{$group->id}" {if $filters.group_id eq $group->id}selected{/if}>{$group->name|escape:'html'}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Or paste phone numbers below</label>
                    <textarea name="paste_numbers" class="form-control" rows="4" placeholder="Enter phone numbers separated by comma, newline, tab, or space&#10;e.g. +254712345678, +254798765432"></textarea>
                </div>

                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-upload"></i> {$lang.import}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="padding:14px 20px;">
            <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                <input type="hidden" name="m" value="sms_suite">
                <input type="hidden" name="action" value="contacts">

                <select name="group_id" class="form-control" style="flex:1;min-width:180px;max-width:300px;">
                    <option value="">{$lang.all} {$lang.contact_groups|default:'Groups'}</option>
                    {foreach $groups as $group}
                    <option value="{$group->id}" {if $filters.group_id eq $group->id}selected{/if}>
                        {$group->name|escape:'html'} ({$group->contact_count})
                    </option>
                    {/foreach}
                </select>

                <input type="text" name="search" class="form-control" placeholder="{$lang.search}..."
                       value="{$filters.search|escape:'html'}" style="flex:2;min-width:200px;max-width:500px;">

                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> {$lang.filter}</button>

                {if $filters.group_id || $filters.search}
                <a href="{$modulelink}&action=contacts" class="btn btn-outline-secondary btn-sm">{$lang.reset}</a>
                {/if}
            </form>
        </div>
    </div>

    <!-- Export button (separate form) -->
    <div style="text-align:right;margin-bottom:8px;">
        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="{$csrf_token}">
            <input type="hidden" name="export_csv" value="1">
            <input type="hidden" name="export_group_id" value="{$filters.group_id}">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-download"></i> {$lang.export} CSV
            </button>
        </form>
    </div>

    <!-- Contacts Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><strong>{$total}</strong> {$lang.contacts}</h3>
        </div>
        <div class="card-body">
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
                            <th>{$lang.tags|default:'Tags'}</th>
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
                                        <a href="{$modulelink}&action=contact_groups&group_id={$g->id}" class="badge badge-info" style="text-decoration:none;">{$g->name|escape:'html'}</a>
                                    {/if}
                                {/foreach}
                            </td>
                            <td style="max-width:200px;">
                                {if isset($contact_tags[$contact->id])}
                                {foreach $contact_tags[$contact->id] as $ctag}
                                <span style="display:inline-flex;align-items:center;gap:2px;margin:1px;">
                                    <span class="badge" style="background-color:{$ctag->color};color:#fff;font-size:.75rem;padding:3px 8px;">{$ctag->name|escape:'html'}</span>
                                    <form method="post" style="display:inline;margin:0;">
                                        <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                        <input type="hidden" name="remove_tag" value="1">
                                        <input type="hidden" name="contact_id" value="{$contact->id}">
                                        <input type="hidden" name="tag_id" value="{$ctag->id}">
                                        <button type="submit" style="background:none;border:none;color:#dc3545;padding:0 2px;cursor:pointer;font-size:.7rem;" title="Remove tag">&times;</button>
                                    </form>
                                </span>
                                {/foreach}
                                {/if}
                                {if $all_tags && count($all_tags) > 0}
                                <div class="dropdown" style="display:inline-block;">
                                    <button class="btn btn-sm btn-outline-secondary" data-toggle="dropdown" style="padding:1px 6px;font-size:.7rem;" title="Add tag">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        {foreach $all_tags as $atag}
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                            <input type="hidden" name="assign_tag" value="1">
                                            <input type="hidden" name="contact_id" value="{$contact->id}">
                                            <input type="hidden" name="tag_id" value="{$atag->id}">
                                            <button type="submit" class="dropdown-item" style="display:flex;align-items:center;gap:8px;">
                                                <span style="width:12px;height:12px;border-radius:50%;background:{$atag->color};display:inline-block;"></span>
                                                {$atag->name|escape:'html'}
                                            </button>
                                        </form>
                                        {/foreach}
                                    </div>
                                </div>
                                {/if}
                            </td>
                            <td>
                                {if $contact->status eq 'active' || $contact->status eq 'subscribed'}
                                <span class="badge badge-success">{$lang.active}</span>
                                {else}
                                <span class="badge badge-secondary">{$contact->status|ucfirst}</span>
                                {/if}
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{$modulelink}&action=send&to={$contact->phone|urlencode}" class="btn btn-sm btn-primary" title="Send SMS">
                                    <i class="fas fa-paper-plane"></i>
                                </a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('{$lang.confirm_delete}');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_contact" value="1">
                                    <input type="hidden" name="contact_id" value="{$contact->id}">
                                    <button type="submit" class="btn btn-sm btn-danger" title="{$lang.delete}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

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
            <div class="text-center text-muted" style="padding:40px 20px;">
                <i class="fas fa-address-book" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p style="margin-top:12px;">No contacts found. Add your first contact or import from CSV.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var addPanel = document.getElementById('addContactPanel');
    var importPanel = document.getElementById('importPanel');
    var btnAdd = document.getElementById('btnAddContact');
    var btnImport = document.getElementById('btnImport');

    btnAdd.addEventListener('click', function() {
        addPanel.style.display = 'block';
        importPanel.style.display = 'none';
        btnAdd.style.display = 'none';
        btnImport.style.display = '';
        addPanel.scrollIntoView({behavior:'smooth'});
    });

    btnImport.addEventListener('click', function() {
        importPanel.style.display = 'block';
        addPanel.style.display = 'none';
        btnImport.style.display = 'none';
        btnAdd.style.display = '';
        importPanel.scrollIntoView({behavior:'smooth'});
    });
})();
</script>
{/literal}
