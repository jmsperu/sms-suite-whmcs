{$sms_css nofilter}
<div class="sms-suite-sender-ids">
    <div class="sms-page-header">
        <h2><i class="fas fa-id-badge"></i> {$lang.sender_ids}</h2>
    </div>

    <!-- Navigation -->
    <ul class="sms-nav">
        <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
        <li><a href="{$modulelink}&action=send">{$lang.menu_send_sms}</a></li>
        <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
        <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
        <li><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
        <li class="active"><a href="{$modulelink}&action=sender_ids">{$lang.sender_ids}</a></li>
        <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
    </ul>

    {if $success}
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>{$lang.success}!</strong> {$success}
    </div>
    {/if}

    {if $error}
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <strong>{$lang.error}!</strong> {$error}
    </div>
    {/if}

    <div class="row">
        <div class="col-md-8" style="margin-bottom: 24px;">
            <!-- Your Sender IDs -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-list"></i> Your Sender IDs</h3>
                </div>
                <div class="panel-body">
                    {if $sender_ids && count($sender_ids) > 0}
                    <div class="table-responsive">
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
                                    <td>{if $sid->validity_date}{$sid->validity_date}{else}-{/if}</td>
                                    <td><small>{$sid->created_at|date_format:"%Y-%m-%d"}</small></td>
                                </tr>
                                {if $sid->status eq 'rejected' && $sid->rejection_reason}
                                <tr>
                                    <td colspan="5">
                                        <small class="text-danger"><i class="fas fa-info-circle"></i> Rejection reason: {$sid->rejection_reason|escape:'html'}</small>
                                    </td>
                                </tr>
                                {/if}
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {else}
                    <div class="text-center text-muted" style="padding: 30px;">
                        <i class="fas fa-id-badge" style="font-size: 2rem; color: #cbd5e1;"></i>
                        <p style="margin-top: 10px;">You haven't registered any sender IDs yet. Request one below to start sending messages with your own brand name.</p>
                    </div>
                    {/if}
                </div>
            </div>

            <!-- Request New Sender ID -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-plus-circle"></i> {$lang.sender_id_request}</h3>
                </div>
                <div class="panel-body">
                    <form method="post" id="senderIdForm" enctype="multipart/form-data">
                        <input type="hidden" name="request_sender" value="1">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">

                        <div class="form-group">
                            <label>{$lang.type} <span class="text-danger">*</span></label>
                            <div style="display: flex; gap: 10px;">
                                <label style="flex: 1; text-align: center; padding: 12px; background: #f1f5f9; border-radius: 8px; cursor: pointer; border: 2px solid #667eea;" id="alphaLabel">
                                    <input type="radio" name="sender_type" value="alphanumeric" checked style="display: none;">
                                    <strong>{$lang.sender_id_type_alpha}</strong>
                                    {if $alpha_price > 0}<br><small>${$alpha_price|number_format:2}</small>{else}<br><small>Free</small>{/if}
                                </label>
                                <label style="flex: 1; text-align: center; padding: 12px; background: #f1f5f9; border-radius: 8px; cursor: pointer; border: 2px solid transparent;" id="numericLabel">
                                    <input type="radio" name="sender_type" value="numeric" style="display: none;">
                                    <strong>{$lang.sender_id_type_numeric}</strong>
                                    {if $numeric_price > 0}<br><small>${$numeric_price|number_format:2}</small>{else}<br><small>Free</small>{/if}
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="sender_id">{$lang.sender_id} <span class="text-danger">*</span></label>
                            <input type="text" name="sender_id" id="sender_id" class="form-control"
                                   placeholder="e.g., MyBrand" required maxlength="11">
                            <span class="help-block" id="senderIdHelp">
                                Alphanumeric: 3-11 characters, letters and numbers only, must start with a letter
                            </span>
                        </div>

                        <div class="form-group">
                            <label for="network">Target Network <span class="text-danger">*</span></label>
                            <select name="network" id="network" class="form-control" required>
                                <option value="all">All Networks</option>
                                <option value="safaricom">Safaricom</option>
                                <option value="airtel">Airtel</option>
                                <option value="telkom">Telkom</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="company_name">Company/Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="company_name" class="form-control" required
                                   placeholder="Your registered company name">
                        </div>

                        <div class="form-group">
                            <label for="use_case">Use Case / Purpose <span class="text-danger">*</span></label>
                            <textarea name="use_case" id="use_case" class="form-control" rows="3" required
                                      placeholder="Describe how you will use this Sender ID"></textarea>
                        </div>

                        <div style="background: #f8fafc; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                            <h5 style="margin-top: 0;"><i class="fas fa-file-upload"></i> Required Documents</h5>
                            <p class="text-muted" style="font-size: .8rem;">Accepted formats: PDF, JPG, PNG (max 5MB each)</p>

                            <div class="form-group">
                                <label for="doc_certificate">
                                    <i class="fas fa-file-pdf text-danger"></i> Certificate of Incorporation <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="doc_certificate" id="doc_certificate" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>

                            <div class="form-group">
                                <label for="doc_vat">
                                    <i class="fas fa-receipt" style="color: var(--sms-warning);"></i> VAT Certificate <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="doc_vat" id="doc_vat" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>

                            <div class="form-group">
                                <label for="doc_kyc">
                                    <i class="fas fa-id-card" style="color: var(--sms-primary);"></i> KYC Documents <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="doc_kyc" id="doc_kyc" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="doc_authorization">
                                    <i class="fas fa-file-signature" style="color: var(--sms-success);"></i> Letter of Authorization <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="doc_authorization" id="doc_authorization" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes (optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2"
                                      placeholder="Any additional information..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Processing Time:</strong> Sender ID registration with telcos typically takes 3-7 business days after document verification.
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Sender ID Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-info-circle"></i> About Sender IDs</h3>
                </div>
                <div class="panel-body">
                    <p style="margin-bottom: 4px;"><strong>Alphanumeric:</strong></p>
                    <p class="small" style="margin-bottom: 12px;">A custom text name (e.g., "MyBrand") that appears as the sender. Great for branding.</p>
                    <p style="margin-bottom: 4px;"><strong>Numeric:</strong></p>
                    <p class="small" style="margin-bottom: 0;">A phone number that appears as the sender. Recipients may be able to reply.</p>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-list-ol"></i> Registration Process</h3>
                </div>
                <div class="panel-body">
                    <ol class="small" style="padding-left: 18px;">
                        <li style="margin-bottom: 8px;"><strong>Submit Request</strong><br>Fill form with company details & documents</li>
                        <li style="margin-bottom: 8px;"><strong>Document Review</strong><br>Our team reviews (1-2 business days)</li>
                        <li style="margin-bottom: 8px;"><strong>Telco Submission</strong><br>Submitted to Safaricom/Airtel/Telkom</li>
                        <li style="margin-bottom: 8px;"><strong>Telco Approval</strong><br>Processing (3-7 business days)</li>
                        <li><strong>Activation</strong><br>Your Sender ID goes live</li>
                    </ol>
                </div>
            </div>

            <div class="panel panel-danger">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-exclamation-triangle"></i> Important Notes</h3>
                </div>
                <div class="panel-body small">
                    <ul style="padding-left: 18px;">
                        <li>Each network requires separate registration</li>
                        <li>Must match company/brand name</li>
                        <li>Offensive names will be rejected</li>
                        <li>Misuse may result in suspension</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var alphaLabel = document.getElementById('alphaLabel');
var numericLabel = document.getElementById('numericLabel');
[alphaLabel, numericLabel].forEach(function(label) {
    label.addEventListener('click', function() {
        var radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        alphaLabel.style.borderColor = 'transparent';
        numericLabel.style.borderColor = 'transparent';
        this.style.borderColor = '#667eea';

        var helpText = document.getElementById('senderIdHelp');
        var input = document.getElementById('sender_id');
        if (radio.value === 'alphanumeric') {
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
