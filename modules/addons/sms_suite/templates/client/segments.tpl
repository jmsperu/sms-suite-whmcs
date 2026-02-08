{$sms_css nofilter}
<div class="sms-suite-segments">
    <div class="sms-page-header">
        <h2><i class="fas fa-filter"></i> {$lang.segments|default:'Segments'}</h2>
        <div>
            <button type="button" class="btn btn-primary" id="btnCreateSegment" style="padding:10px 22px;">
                <i class="fas fa-plus"></i> {$lang.segment_create|default:'Create Segment'}
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
        <li><a href="{$modulelink}&action=tags">{$lang.tags|default:'Tags'}</a></li>
        <li class="active"><a href="{$modulelink}&action=segments">{$lang.segments|default:'Segments'}</a></li>
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

    <!-- Create Segment Panel -->
    <div id="createSegmentPanel" class="card" style="display:none;margin-bottom:16px;">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title" style="margin:0;"><i class="fas fa-filter"></i> {$lang.segment_create|default:'Create Segment'}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseSegment">&times; Close</button>
        </div>
        <div class="card-body">
            <form method="post" id="segmentForm">
                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                <input type="hidden" name="create_segment" value="1">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.segment_name|default:'Segment Name'} <span class="text-danger">*</span></label>
                            <input type="text" name="segment_name" class="form-control" required placeholder="e.g., Active VIP Contacts">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>{$lang.segment_match_type|default:'Match Type'}</label>
                            <select name="match_type" class="form-control">
                                <option value="all">{$lang.segment_match_all|default:'Match ALL conditions (AND)'}</option>
                                <option value="any">{$lang.segment_match_any|default:'Match ANY condition (OR)'}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>{$lang.description|default:'Description'}</label>
                    <input type="text" name="description" class="form-control" placeholder="Optional description">
                </div>

                <div class="form-group">
                    <label>{$lang.segment_conditions|default:'Conditions'}</label>
                    <div id="conditionsContainer">
                        <!-- Condition rows added by JS -->
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addConditionRow()" style="margin-top:8px;">
                        <i class="fas fa-plus"></i> {$lang.segment_add_condition|default:'Add Condition'}
                    </button>
                </div>

                <div style="text-align:right;padding-top:12px;border-top:1px solid #e2e8f0;">
                    <button type="submit" class="btn btn-primary" style="padding:10px 22px;"><i class="fas fa-save"></i> {$lang.segment_create|default:'Create Segment'}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Segments Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-layer-group"></i> Your Segments</h3>
        </div>
        <div class="card-body">
            {if $segments && count($segments) > 0}
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{$lang.name|default:'Name'}</th>
                            <th>{$lang.segment_match_type|default:'Match Type'}</th>
                            <th>{$lang.tag_contacts|default:'Contacts'}</th>
                            <th>Last Calculated</th>
                            <th>{$lang.actions|default:'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $segments as $segment}
                        <tr>
                            <td>
                                <strong>{$segment->name|escape:'html'}</strong>
                                {if $segment->description}<br><small class="text-muted">{$segment->description|escape:'html'}</small>{/if}
                            </td>
                            <td>
                                {if $segment->match_type eq 'all'}
                                <span class="badge badge-info">ALL</span>
                                {else}
                                <span class="badge badge-warning">ANY</span>
                                {/if}
                            </td>
                            <td>
                                <span class="badge badge-primary" style="font-size:.85rem;">{$segment->contact_count}</span>
                            </td>
                            <td>
                                <small>{if $segment->last_calculated_at}{$segment->last_calculated_at|date_format:"%Y-%m-%d %H:%M"}{else}-{/if}</small>
                            </td>
                            <td style="white-space:nowrap;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="recalculate_segment" value="1">
                                    <input type="hidden" name="segment_id" value="{$segment->id}">
                                    <button type="submit" class="btn btn-sm btn-info" title="Recalculate">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this segment?');">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <input type="hidden" name="delete_segment" value="1">
                                    <input type="hidden" name="segment_id" value="{$segment->id}">
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
                <i class="fas fa-filter" style="font-size:2.5rem;color:#cbd5e1;"></i>
                <p style="margin-top:15px;">No segments yet. Create your first segment to dynamically filter contacts.</p>
            </div>
            {/if}
        </div>
    </div>
</div>

<script>
var smsSegmentTags = {$tags_json nofilter};
var smsSegmentGroups = {$groups_json nofilter};
</script>
{literal}
<script>
(function() {
    var createPanel = document.getElementById('createSegmentPanel');
    var btnCreate = document.getElementById('btnCreateSegment');

    btnCreate.addEventListener('click', function() {
        createPanel.style.display = 'block';
        btnCreate.style.display = 'none';
        if (document.getElementById('conditionsContainer').children.length === 0) {
            addConditionRow();
        }
        createPanel.scrollIntoView({behavior:'smooth'});
    });
    document.getElementById('btnCloseSegment').addEventListener('click', function() {
        createPanel.style.display = 'none';
        btnCreate.style.display = '';
    });

    var conditionIndex = 0;

    var fieldOptions = {
        'first_name': {label: 'First Name', operators: ['equals','not_equals','contains','starts_with','ends_with','is_empty','is_not_empty'], valueType: 'text'},
        'last_name': {label: 'Last Name', operators: ['equals','not_equals','contains','starts_with','ends_with','is_empty','is_not_empty'], valueType: 'text'},
        'phone': {label: 'Phone', operators: ['equals','not_equals','contains','starts_with','ends_with'], valueType: 'text'},
        'email': {label: 'Email', operators: ['equals','not_equals','contains','starts_with','ends_with','is_empty','is_not_empty'], valueType: 'text'},
        'status': {label: 'Status', operators: ['equals','not_equals'], valueType: 'select', values: [{v:'active',l:'Active'},{v:'subscribed',l:'Subscribed'},{v:'unsubscribed',l:'Unsubscribed'},{v:'bounced',l:'Bounced'}]},
        'group_id': {label: 'Group', operators: ['equals','not_equals'], valueType: 'select', values: (smsSegmentGroups || []).map(function(g){return {v:g.id,l:g.name};})},
        'tag': {label: 'Tag', operators: ['has_tag','not_has_tag'], valueType: 'select', values: (smsSegmentTags || []).map(function(t){return {v:t.id,l:t.name};})},
        'created_at': {label: 'Created Date', operators: ['greater_than','less_than','between'], valueType: 'text'}
    };

    var operatorLabels = {
        'equals': 'Equals',
        'not_equals': 'Not Equals',
        'contains': 'Contains',
        'starts_with': 'Starts With',
        'ends_with': 'Ends With',
        'greater_than': 'After',
        'less_than': 'Before',
        'between': 'Between',
        'is_empty': 'Is Empty',
        'is_not_empty': 'Is Not Empty',
        'has_tag': 'Has Tag',
        'not_has_tag': 'Does Not Have Tag'
    };

    window.addConditionRow = function() {
        var idx = conditionIndex++;
        var container = document.getElementById('conditionsContainer');
        var row = document.createElement('div');
        row.className = 'row';
        row.style.cssText = 'margin-bottom:8px;align-items:center;';
        row.id = 'condRow' + idx;

        // Field select
        var fieldHtml = '<select name="conditions[' + idx + '][field]" class="form-control" onchange="updateConditionOps(' + idx + ', this.value)" style="flex:1;">';
        fieldHtml += '<option value="">-- Select Field --</option>';
        for (var key in fieldOptions) {
            fieldHtml += '<option value="' + key + '">' + fieldOptions[key].label + '</option>';
        }
        fieldHtml += '</select>';

        row.innerHTML =
            '<div class="col-md-3">' + fieldHtml + '</div>' +
            '<div class="col-md-3"><select name="conditions[' + idx + '][operator]" id="condOp' + idx + '" class="form-control"><option value="">-- Select Operator --</option></select></div>' +
            '<div class="col-md-4"><input type="text" name="conditions[' + idx + '][value]" id="condVal' + idx + '" class="form-control" placeholder="Value"></div>' +
            '<div class="col-md-2"><button type="button" class="btn btn-sm btn-danger" onclick="removeConditionRow(' + idx + ')"><i class="fas fa-times"></i></button></div>';

        container.appendChild(row);
    };

    window.updateConditionOps = function(idx, field) {
        var opSelect = document.getElementById('condOp' + idx);
        var valContainer = document.getElementById('condVal' + idx).parentNode;
        opSelect.innerHTML = '<option value="">-- Select Operator --</option>';

        if (!field || !fieldOptions[field]) return;

        var fo = fieldOptions[field];
        fo.operators.forEach(function(op) {
            var opt = document.createElement('option');
            opt.value = op;
            opt.textContent = operatorLabels[op] || op;
            opSelect.appendChild(opt);
        });

        // Update value input
        if (fo.valueType === 'select' && fo.values) {
            var sel = '<select name="conditions[' + idx + '][value]" id="condVal' + idx + '" class="form-control">';
            sel += '<option value="">-- Select --</option>';
            fo.values.forEach(function(v) {
                sel += '<option value="' + v.v + '">' + (v.l || v.v) + '</option>';
            });
            sel += '</select>';
            valContainer.innerHTML = sel;
        } else {
            valContainer.innerHTML = '<input type="text" name="conditions[' + idx + '][value]" id="condVal' + idx + '" class="form-control" placeholder="Value">';
        }
    };

    window.removeConditionRow = function(idx) {
        var row = document.getElementById('condRow' + idx);
        if (row) row.remove();
    };
})();
</script>
{/literal}
