{$sms_css nofilter}
<div class="sms-suite-contact-groups">
    <div class="sms-page-header">
        <h2><i class="fas fa-folder"></i> {$lang.contact_groups|default:'Groups'}</h2>
        <div>
            <button class="btn btn-primary" data-toggle="modal" data-target="#createGroupModal">
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
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> {$error}
    </div>
    {/if}

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fas fa-layer-group"></i> Your Contact Groups</h3>
        </div>
        <div class="panel-body">
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
                            <td><strong>{$group->name|escape:'html'}</strong></td>
                            <td>{$group->description|escape:'html'|default:'-'}</td>
                            <td>
                                <span class="label label-primary">{$group->contact_count}</span>
                                {if $group->contact_count > 0}
                                <a href="{$modulelink}&action=contacts&group_id={$group->id}" class="btn btn-xs btn-default" style="margin-left: 4px;">View</a>
                                {/if}
                            </td>
                            <td><small>{$group->created_at|date_format:"%Y-%m-%d"}</small></td>
                            <td>
                                <button class="btn btn-xs btn-primary" onclick='editGroup({$group->id}, "{$group->name|escape:'javascript'}", "{$group->description|escape:'javascript'}")'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-xs btn-danger" onclick='confirmDeleteGroup({$group->id}, "{$group->name|escape:'javascript'}", {$group->contact_count})'>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding: 40px;">
                <i class="fas fa-folder-open" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                <p style="margin-top: 15px;">No contact groups yet. Create your first group to organize your contacts.</p>
                <button class="btn btn-primary" data-toggle="modal" data-target="#createGroupModal">
                    <i class="fas fa-plus"></i> Create Your First Group
                </button>
            </div>
            {/if}
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="create_group" value="1">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-folder-plus"></i> Create Contact Group</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Group Name <span class="text-danger">*</span></label>
                        <input type="text" name="group_name" class="form-control" required placeholder="e.g., VIP Customers">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description for this group..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="update_group" value="1">
                <input type="hidden" name="group_id" id="edit_group_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-edit"></i> Edit Contact Group</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Group Name <span class="text-danger">*</span></label>
                        <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="delete_group" value="1">
                <input type="hidden" name="group_id" id="delete_group_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-trash"></i> Delete Group</h4>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<strong id="delete_group_name"></strong>"?</p>
                    <p id="delete_warning" class="text-danger" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_btn"><i class="fas fa-trash"></i> Delete Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editGroup(id, name, description) {
    document.getElementById('edit_group_id').value = id;
    document.getElementById('edit_group_name').value = name;
    document.getElementById('edit_description').value = description || '';
    $('#editGroupModal').modal('show');
}

function confirmDeleteGroup(id, name, contactCount) {
    document.getElementById('delete_group_id').value = id;
    document.getElementById('delete_group_name').textContent = name;
    var warning = document.getElementById('delete_warning');
    var btn = document.getElementById('delete_btn');
    if (contactCount > 0) {
        warning.textContent = 'This group has ' + contactCount + ' contact(s). Remove them first before deleting.';
        warning.style.display = 'block';
        btn.disabled = true;
    } else {
        warning.style.display = 'none';
        btn.disabled = false;
    }
    $('#deleteGroupModal').modal('show');
}
</script>
