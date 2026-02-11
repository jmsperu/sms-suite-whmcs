{$sms_css nofilter}
<div class="sms-suite-tags">
    <div class="sms-page-header">
        <h2><i class="fas fa-tags"></i> {$lang.tags|default:'Tags'}</h2>
        <div>
            <button type="button" class="btn btn-primary" id="btnCreateTag" style="padding:10px 22px;">
                <i class="fas fa-plus"></i> {$lang.tag_create|default:'Create Tag'}
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
        <li><a href="{$modulelink}&action=contact_groups">{$lang.contact_groups|default:'Groups'}</a></li>
        <li class="active"><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
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

    <!-- Create Tag Panel -->
    <div id="createTagPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-tag"></i> {$lang.tag_create|default:'Create Tag'}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseCreate">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="create_tag" value="1">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{$lang.tag_name|default:'Tag Name'} <span class="text-danger">*</span></label>
                            <input type="text" name="tag_name" class="form-control" required placeholder="e.g., VIP" maxlength="50">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>{$lang.tag_color|default:'Color'}</label>
                            <input type="color" name="tag_color" class="form-control" value="#667eea" style="height:38px;padding:4px;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.description|default:'Description'}</label>
                            <input type="text" name="description" class="form-control" placeholder="Optional description" maxlength="255">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> {$lang.tag_create|default:'Create Tag'}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tag Panel -->
    <div id="editTagPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-edit"></i> {$lang.tag_edit|default:'Edit Tag'}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseEdit">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="update_tag" value="1">
                <input type="hidden" name="tag_id" id="edit_tag_id">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>{$lang.tag_name|default:'Tag Name'} <span class="text-danger">*</span></label>
                            <input type="text" name="tag_name" id="edit_tag_name" class="form-control" required maxlength="50">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>{$lang.tag_color|default:'Color'}</label>
                            <input type="color" name="tag_color" id="edit_tag_color" class="form-control" style="height:38px;padding:4px;">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.description|default:'Description'}</label>
                            <input type="text" name="description" id="edit_tag_description" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> {$lang.save|default:'Save Changes'}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tags Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tags"></i> Your Tags</h3>
        </div>
        <div class="card-body">
            {if $tags && count($tags) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.tag_name|default:'Tag Name'}</th>
                            <th>{$lang.description|default:'Description'}</th>
                            <th>{$lang.tag_contacts|default:'Contacts'}</th>
                            <th>{$lang.created|default:'Created'}</th>
                            <th>{$lang.actions|default:'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $tags as $tag}
                        <tr>
                            <td>
                                <span class="badge" style="background-color:{$tag->color};color:#fff;font-size:.85rem;padding:5px 12px;">
                                    {$tag->name|escape:'html'}
                                </span>
                            </td>
                            <td>{$tag->description|escape:'html'|default:'-'}</td>
                            <td>
                                <span class="badge badge-primary" style="font-size:.85rem;">{$tag->contact_count}</span>
                            </td>
                            <td><small>{$tag->created_at|date_format:"%Y-%m-%d"}</small></td>
                            <td style="white-space:nowrap;">
                                <button class="btn btn-sm btn-primary" onclick="editTag({$tag->id}, '{$tag->name|escape:'javascript'}', '{$tag->color|escape:'javascript'}', '{$tag->description|escape:'javascript'}')" title="{$lang.edit|default:'Edit'}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this tag? It will be removed from all contacts.');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_tag" value="1">
                                    <input type="hidden" name="tag_id" value="{$tag->id}">
                                    <button type="submit" class="btn btn-sm btn-danger" title="{$lang.delete|default:'Delete'}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-center text-muted" style="padding:40px;">
                <i class="fas fa-tags" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p style="margin-top:15px;">No tags yet. Create your first tag to label and organize your contacts.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
(function() {
    var createPanel = document.getElementById('createTagPanel');
    var editPanel = document.getElementById('editTagPanel');
    var btnCreate = document.getElementById('btnCreateTag');

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

    window.editTag = function(id, name, color, description) {
        document.getElementById('edit_tag_id').value = id;
        document.getElementById('edit_tag_name').value = name;
        document.getElementById('edit_tag_color').value = color || '#667eea';
        document.getElementById('edit_tag_description').value = description || '';
        editPanel.style.display = 'block';
        createPanel.style.display = 'none';
        btnCreate.style.display = 'none';
        editPanel.scrollIntoView({behavior:'smooth'});
    };
})();
</script>
{/literal}
