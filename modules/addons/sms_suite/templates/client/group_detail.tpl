{$sms_css nofilter}
<div class="sms-suite-contact-groups">
    <div class="sms-page-header">
        <h2><i class="fas fa-folder-open"></i> {$group->name|escape:'html'}</h2>
        <div>
            <a href="{$modulelink}&action=contact_groups" class="btn btn-outline-secondary" style="padding:8px 18px;">
                <i class="fas fa-arrow-left"></i> Back to Groups
            </a>
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
        <li><a href="{$modulelink}&action=preferences">{$lang.preferences|default:'Preferences'}</a></li>
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

    <!-- Group Info -->
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;padding:16px 24px;">
            <div>
                <small style="color:#64748b;">Group</small><br>
                <strong style="font-size:1.1rem;">{$group->name|escape:'html'}</strong>
            </div>
            {if $group->description}
            <div>
                <small style="color:#64748b;">Description</small><br>
                {$group->description|escape:'html'}
            </div>
            {/if}
            <div>
                <small style="color:#64748b;">Contacts</small><br>
                <strong>{$contacts|count}</strong>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button type="button" class="btn btn-primary" id="btnAddToGroup" style="padding:8px 18px;">
                    <i class="fas fa-user-plus"></i> Add Contact
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnImportToGroup" style="padding:8px 18px;">
                    <i class="fas fa-upload"></i> Import
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnAddExisting" style="padding:8px 18px;">
                    <i class="fas fa-link"></i> Add Existing
                </button>
            </div>
        </div>
    </div>

    <!-- Add Contact Panel -->
    <div id="addContactPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-user-plus"></i> Add New Contact to Group</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary panelClose">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post" action="{$modulelink}&action=contacts">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="add_contact" value="1">
                <input type="hidden" name="group_id" value="{$group->id}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="+254712345678" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary" style="padding:8px 18px;"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Panel -->
    <div id="importPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-upload"></i> Import Contacts to {$group->name|escape:'html'}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary panelClose">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="import_to_group" value="1">
                <input type="hidden" name="group_id" value="{$group->id}">

                <div class="alert alert-info" style="margin-bottom:16px;">
                    <strong>Supported:</strong> CSV, TXT, Excel (.xlsx) or paste numbers below.<br>
                    <strong>CSV format:</strong> Phone, First Name, Last Name, Email
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Upload File</label>
                            <input type="file" name="import_file" class="form-control" accept=".csv,.txt,.xlsx">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Or paste phone numbers</label>
                            <textarea name="paste_numbers" class="form-control" rows="3" placeholder="Comma, newline, tab, or space separated"></textarea>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary" style="padding:8px 18px;"><i class="fas fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Existing Contacts Panel -->
    <div id="addExistingPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-link"></i> Add Existing Contacts</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary panelClose">&times; Close</button>
        </div>
        <div class="card-body">
            {if $ungrouped_contacts && count($ungrouped_contacts) > 0}
            <div class="table-responsive" style="max-height:300px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $ungrouped_contacts as $uc}
                        <tr>
                            <td>{$uc->phone|escape:'html'}</td>
                            <td>{$uc->first_name|escape:'html'} {$uc->last_name|escape:'html'}</td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="add_contact_to_group" value="1">
                                    <input type="hidden" name="group_id" value="{$group->id}">
                                    <input type="hidden" name="contact_id" value="{$uc->id}">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <p class="text-muted">No other contacts available. Add contacts from the <a href="{$modulelink}&action=contacts">Contacts</a> page first.</p>
            {/if}
        </div>
    </div>

    <!-- Contacts in Group -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users"></i> Contacts in this Group ({$contacts|count})</h3>
        </div>
        <div class="card-body">
            {if $contacts && count($contacts) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                                {if $contact->status eq 'active' || $contact->status eq 'subscribed'}
                                <span class="badge badge-success">Active</span>
                                {else}
                                <span class="badge badge-secondary">{$contact->status|ucfirst}</span>
                                {/if}
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{$modulelink}&action=send&to={$contact->phone|urlencode}" class="btn btn-sm btn-primary" title="Send SMS">
                                    <i class="fas fa-paper-plane"></i>
                                </a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Remove this contact from the group?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="remove_from_group" value="1">
                                    <input type="hidden" name="contact_id" value="{$contact->id}">
                                    <button type="submit" class="btn btn-sm btn-warning" title="Remove from group">
                                        <i class="fas fa-unlink"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding:40px 20px;">
                <i class="fas fa-users" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p style="margin-top:12px;">No contacts in this group yet. Add contacts, import, or link existing contacts.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var panels = ['addContactPanel','importPanel','addExistingPanel'];
    var btns = {
        'addContactPanel': document.getElementById('btnAddToGroup'),
        'importPanel': document.getElementById('btnImportToGroup'),
        'addExistingPanel': document.getElementById('btnAddExisting')
    };

    function hideAll() {
        panels.forEach(function(id) { document.getElementById(id).style.display = 'none'; });
    }

    Object.keys(btns).forEach(function(panelId) {
        btns[panelId].addEventListener('click', function() {
            hideAll();
            document.getElementById(panelId).style.display = 'block';
            document.getElementById(panelId).scrollIntoView({behavior:'smooth'});
        });
    });

    document.querySelectorAll('.panelClose').forEach(function(btn) {
        btn.addEventListener('click', function() { hideAll(); });
    });
})();
</script>
{/literal}
