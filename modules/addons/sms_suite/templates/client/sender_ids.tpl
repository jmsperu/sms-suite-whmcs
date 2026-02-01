<div class="sms-suite-sender-ids">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.sender_ids}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
                <li class="active"><a href="{$modulelink}&action=sender_ids">{$lang.sender_ids}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
            </ul>
        </div>
    </div>

    {if $success}
    <div class="alert alert-success">
        <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8">
            <!-- Your Sender IDs -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Your Sender IDs</h3>
                </div>
                <div class="panel-body">
                    {if $sender_ids && count($sender_ids) > 0}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{$lang.sender_id}</th>
                                <th>{$lang.type}</th>
                                <th>{$lang.status}</th>
                                <th>{$lang.sender_id_validity}</th>
                                <th>{$lang.created}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $sender_ids as $sid}
                            <tr>
                                <td><strong>{$sid->sender_id|escape:'html'}</strong></td>
                                <td>
                                    {if $sid->type eq 'alphanumeric'}
                                    <span class="label label-info">{$lang.sender_id_type_alpha}</span>
                                    {else}
                                    <span class="label label-default">{$lang.sender_id_type_numeric}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $sid->status eq 'active'}
                                    <span class="label label-success">{$lang.sender_id_active}</span>
                                    {elseif $sid->status eq 'pending'}
                                    <span class="label label-warning">{$lang.sender_id_pending}</span>
                                    {elseif $sid->status eq 'rejected'}
                                    <span class="label label-danger">{$lang.sender_id_rejected}</span>
                                    {else}
                                    <span class="label label-default">{$lang.sender_id_expired}</span>
                                    {/if}
                                </td>
                                <td>
                                    {if $sid->validity_date}
                                    {$sid->validity_date}
                                    {else}
                                    -
                                    {/if}
                                </td>
                                <td>{$sid->created_at|date_format:"%Y-%m-%d"}</td>
                            </tr>
                            {if $sid->status eq 'rejected' && $sid->rejection_reason}
                            <tr>
                                <td colspan="5" class="text-danger small">
                                    <i class="fas fa-info-circle"></i> Rejection reason: {$sid->rejection_reason|escape:'html'}
                                </td>
                            </tr>
                            {/if}
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted">You haven't registered any sender IDs yet. Request one below to start sending messages with your own brand name.</p>
                    {/if}
                </div>
            </div>

            <!-- Request New Sender ID -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">{$lang.sender_id_request}</h3>
                </div>
                <div class="panel-body">
                    <form method="post" id="senderIdForm">
                        <input type="hidden" name="request_sender" value="1">

                        <div class="form-group">
                            <label>{$lang.type} <span class="text-danger">*</span></label>
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-default active">
                                    <input type="radio" name="sender_type" value="alphanumeric" checked>
                                    {$lang.sender_id_type_alpha}
                                    {if $alpha_price > 0}
                                    <br><small>${$alpha_price|number_format:2}</small>
                                    {else}
                                    <br><small>Free</small>
                                    {/if}
                                </label>
                                <label class="btn btn-default">
                                    <input type="radio" name="sender_type" value="numeric">
                                    {$lang.sender_id_type_numeric}
                                    {if $numeric_price > 0}
                                    <br><small>${$numeric_price|number_format:2}</small>
                                    {else}
                                    <br><small>Free</small>
                                    {/if}
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="sender_id">{$lang.sender_id} <span class="text-danger">*</span></label>
                            <input type="text" name="sender_id" id="sender_id" class="form-control"
                                   placeholder="e.g., MyBrand" required maxlength="11">
                            <small class="help-block" id="senderIdHelp">
                                Alphanumeric: 3-11 characters, letters and numbers only, must start with a letter
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes (optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2"
                                      placeholder="Purpose of this sender ID, company info, etc."></textarea>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> {$lang.sender_id_request}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">About Sender IDs</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Alphanumeric Sender ID:</strong></p>
                    <p class="small">A custom text name (e.g., "MyBrand") that appears as the sender of your messages. Great for branding.</p>

                    <hr>

                    <p><strong>Numeric Sender ID:</strong></p>
                    <p class="small">A phone number that appears as the sender. Recipients may be able to reply to this number.</p>

                    <hr>

                    <p><strong>Approval Process:</strong></p>
                    <ol class="small">
                        <li>Submit your request</li>
                        <li>Pay the registration fee (if applicable)</li>
                        <li>Wait for admin approval</li>
                        <li>Start using your sender ID</li>
                    </ol>
                </div>
            </div>

            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title">Important Notes</h3>
                </div>
                <div class="panel-body small">
                    <ul>
                        <li>Sender IDs are subject to carrier restrictions in some countries</li>
                        <li>Some countries require pre-registration</li>
                        <li>Misuse may result in suspension</li>
                        <li>Contact support for international requirements</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('input[name="sender_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        var helpText = document.getElementById('senderIdHelp');
        var input = document.getElementById('sender_id');

        if (this.value === 'alphanumeric') {
            helpText.textContent = 'Alphanumeric: 3-11 characters, letters and numbers only, must start with a letter';
            input.placeholder = 'e.g., MyBrand';
            input.maxLength = 11;
        } else {
            helpText.textContent = 'Numeric: Phone number in international format (7-15 digits)';
            input.placeholder = 'e.g., +15551234567';
            input.maxLength = 15;
        }
    });
});
</script>
