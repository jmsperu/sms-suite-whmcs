{$sms_css nofilter}
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
                <li><a href="{$modulelink}&action=inbox">Inbox</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
                <li><a href="{$modulelink}&action=contacts">{$lang.contacts}</a></li>
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
                    <form method="post" id="senderIdForm" enctype="multipart/form-data">
                        <input type="hidden" name="request_sender" value="1">
                        <input type="hidden" name="csrf_token" value="{$csrf_token}">

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
                            <label for="network">Target Network <span class="text-danger">*</span></label>
                            <select name="network" id="network" class="form-control" required>
                                <option value="all">All Networks</option>
                                <option value="safaricom">Safaricom</option>
                                <option value="airtel">Airtel</option>
                                <option value="telkom">Telkom</option>
                            </select>
                            <small class="help-block">Select which network(s) you need this Sender ID for</small>
                        </div>

                        <div class="form-group">
                            <label for="company_name">Company/Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="company_name" class="form-control" required
                                   placeholder="Your registered company name">
                        </div>

                        <div class="form-group">
                            <label for="use_case">Use Case / Purpose <span class="text-danger">*</span></label>
                            <textarea name="use_case" id="use_case" class="form-control" rows="3" required
                                      placeholder="Describe how you will use this Sender ID (e.g., transactional notifications, marketing, OTP, etc.)"></textarea>
                        </div>

                        <hr>
                        <h4><i class="fas fa-file-upload"></i> Required Documents</h4>
                        <p class="text-muted small">Please upload the following documents for telco registration. Accepted formats: PDF, JPG, PNG (max 5MB each)</p>

                        <div class="form-group">
                            <label for="doc_certificate">
                                <i class="fas fa-file-pdf text-danger"></i> Certificate of Incorporation <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="doc_certificate" id="doc_certificate" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="help-block">Company registration certificate from the Registrar of Companies</small>
                        </div>

                        <div class="form-group">
                            <label for="doc_vat">
                                <i class="fas fa-receipt text-warning"></i> VAT Certificate <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="doc_vat" id="doc_vat" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="help-block">Valid VAT registration certificate</small>
                        </div>

                        <div class="form-group">
                            <label for="doc_kyc">
                                <i class="fas fa-id-card text-primary"></i> KYC Documents <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="doc_kyc" id="doc_kyc" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="help-block">Director's ID/Passport, KRA PIN Certificate, or other KYC documents</small>
                        </div>

                        <div class="form-group">
                            <label for="doc_authorization">
                                <i class="fas fa-file-signature text-success"></i> Letter of Authorization <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="doc_authorization" id="doc_authorization" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <small class="help-block">Official letter authorizing the use of the company name as Sender ID (on company letterhead)</small>
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

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Submit Sender ID Request
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
                    <h3 class="panel-title"><i class="fas fa-info-circle"></i> About Sender IDs</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Alphanumeric Sender ID:</strong></p>
                    <p class="small">A custom text name (e.g., "MyBrand") that appears as the sender of your messages. Great for branding.</p>

                    <hr>

                    <p><strong>Numeric Sender ID:</strong></p>
                    <p class="small">A phone number that appears as the sender. Recipients may be able to reply to this number.</p>
                </div>
            </div>

            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-list-ol"></i> Registration Process</h3>
                </div>
                <div class="panel-body">
                    <ol class="small">
                        <li><strong>Submit Request</strong><br>Fill the form with company details and upload required documents</li>
                        <li><strong>Document Review</strong><br>Our team reviews your documents (1-2 business days)</li>
                        <li><strong>Telco Submission</strong><br>We submit to Safaricom/Airtel/Telkom for approval</li>
                        <li><strong>Telco Approval</strong><br>Telcos process the request (3-7 business days)</li>
                        <li><strong>Activation</strong><br>Once approved, your Sender ID is activated</li>
                    </ol>
                </div>
            </div>

            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-file-alt"></i> Required Documents</h3>
                </div>
                <div class="panel-body small">
                    <ul>
                        <li><strong>Certificate of Incorporation</strong> - Company registration document</li>
                        <li><strong>VAT Certificate</strong> - Valid VAT registration</li>
                        <li><strong>KYC Documents</strong> - Director's ID, KRA PIN</li>
                        <li><strong>Authorization Letter</strong> - On company letterhead authorizing Sender ID use</li>
                    </ul>
                    <p class="text-muted">All documents must be clear and legible. Max file size: 5MB each.</p>
                </div>
            </div>

            <div class="panel panel-danger">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-exclamation-triangle"></i> Important Notes</h3>
                </div>
                <div class="panel-body small">
                    <ul>
                        <li>Each network (Safaricom, Airtel, Telkom) requires separate registration</li>
                        <li>Sender IDs must match company/brand name</li>
                        <li>Offensive or misleading Sender IDs will be rejected</li>
                        <li>Misuse may result in permanent suspension</li>
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
