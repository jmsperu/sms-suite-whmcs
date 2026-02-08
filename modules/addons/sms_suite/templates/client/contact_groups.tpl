{$sms_css nofilter}
<div class="sms-suite-contact-groups">
    <div class="sms-page-header">
        <h2><i class="fas fa-folder"></i> {$lang.contact_groups|default:'Groups'}</h2>
        <div>
            <button type="button" class="btn btn-primary" id="btnCreateGroup" style="padding:10px 22px;">
                <i class="fas fa-plus"></i> Create Group
            </button>
        </div>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
        <li class="active"><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
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

    <!-- Create Group Panel -->
    <div id="createGroupPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-folder-plus"></i> Create Contact Group</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseCreate">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="create_group" value="1">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Group Name <span class="text-danger">*</span></label>
                            <input type="text" name="group_name" class="form-control" required placeholder="e.g., VIP Customers">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Optional description">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> Create Group</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Group Panel -->
    <div id="editGroupPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-edit"></i> Edit Group</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseEdit">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="update_group" value="1">
                <input type="hidden" name="group_id" id="edit_group_id">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Group Name <span class="text-danger">*</span></label>
                            <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" id="edit_description" class="form-control">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Groups Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-layer-group"></i> Your Contact Groups</h3>
        </div>
        <div class="card-body">
            {if $groups && count($groups) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Group Name</th>
                            <th>Description</th>
                            <th>Contacts</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $groups as $group}
                        <tr>
                            <td>
                                <a href="{$modulelink}&action=contact_groups&group_id={$group->id}" style="color:var(--sms-primary,#667eea);font-weight:600;text-decoration:none;">
                                    <strong>{$group->name|escape:'html'}</strong>
                                </a>
                            </td>
                            <td>{$group->description|escape:'html'|default:'-'}</td>
                            <td>
                                <a href="{$modulelink}&action=contact_groups&group_id={$group->id}" class="badge badge-primary" style="text-decoration:none;font-size:.85rem;">{$group->contact_count}</a>
                            </td>
                            <td><small>{$group->created_at|date_format:"%Y-%m-%d"}</small></td>
                            <td style="white-space:nowrap;">
                                <a href="{$modulelink}&action=contact_groups&group_id={$group->id}" class="btn btn-sm btn-info" title="View Contacts">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-primary" onclick="editGroup({$group->id}, '{$group->name|escape:'javascript'}', '{$group->description|escape:'javascript'}')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                {if $group->contact_count == 0}
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this group?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_group" value="1">
                                    <input type="hidden" name="group_id" value="{$group->id}">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding:40px;">
                <i class="fas fa-folder-open" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p style="margin-top:15px;">No contact groups yet. Create your first group to organize your contacts.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var createPanel = document.getElementById('createGroupPanel');
    var editPanel = document.getElementById('editGroupPanel');
    var btnCreate = document.getElementById('btnCreateGroup');

    btnCreate.addEventListener('click', function() {
        createPanel.style.display = 'block';
        editPanel.style.display = 'none';
        btnCreate.style.display = 'none';
        createPanel.scrollIntoView({behavior:'smooth'});
    });
    document.getElementById('btnCloseCreate').addEventListener('click', function() {
        createPanel.style.display = 'none';
        btnCreate.style.display = '';
    });
    document.getElementById('btnCloseEdit').addEventListener('click', function() {
        editPanel.style.display = 'none';
        btnCreate.style.display = '';
    });

    window.editGroup = function(id, name, description) {
        document.getElementById('edit_group_id').value = id;
        document.getElementById('edit_group_name').value = name;
        document.getElementById('edit_description').value = description || '';
        editPanel.style.display = 'block';
        createPanel.style.display = 'none';
        btnCreate.style.display = 'none';
        editPanel.scrollIntoView({behavior:'smooth'});
    };
})();
</script>
{/literal}
