<div class="sms-suite-reports">
    <div class="row">
        <div class="col-sm-12">
            <h2>{$lang.reports}</h2>
        </div>
    </div>

    <!-- Navigation -->
    <div class="row" style="margin: 20px 0;">
        <div class="col-sm-12">
            <ul class="nav nav-pills">
                <li><a href="{$modulelink}">{$lang.menu_dashboard}</a></li>
                <li><a href="{$modulelink}&action=logs">{$lang.menu_messages}</a></li>
                <li><a href="{$modulelink}&action=campaigns">{$lang.campaigns}</a></li>
                <li class="active"><a href="{$modulelink}&action=reports">{$lang.reports}</a></li>
            </ul>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="panel panel-default">
        <div class="panel-body">
            <form method="get" class="form-inline">
                <input type="hidden" name="m" value="sms_suite">
                <input type="hidden" name="action" value="reports">

                <div class="form-group">
                    <label>{$lang.report_from}:</label>
                    <input type="date" name="start_date" class="form-control" value="{$start_date}">
                </div>

                <div class="form-group">
                    <label>{$lang.report_to}:</label>
                    <input type="date" name="end_date" class="form-control" value="{$end_date}">
                </div>

                <button type="submit" class="btn btn-primary">{$lang.report_generate}</button>

                <a href="{$modulelink}&action=reports&start_date={$start_date}&end_date={$end_date}&export=csv" class="btn btn-default">
                    <i class="fas fa-download"></i> {$lang.export} CSV
                </a>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-body text-center">
                    <h3 style="margin: 0;">{$summary.total_messages|number_format:0}</h3>
                    <p class="text-muted">{$lang.total_messages}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-body text-center">
                    <h3 style="margin: 0;">{$summary.total_segments|number_format:0}</h3>
                    <p class="text-muted">{$lang.segments}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-body text-center">
                    <h3 style="margin: 0;">{$summary.by_status.delivered|default:0|number_format:0}</h3>
                    <p class="text-muted">{$lang.delivered}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-body text-center">
                    <h3 style="margin: 0;">${$summary.total_cost|number_format:2}</h3>
                    <p class="text-muted">Total Cost</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Status Breakdown -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Status Breakdown</h3>
                </div>
                <div class="panel-body">
                    {if $summary.by_status}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{$lang.status}</th>
                                <th class="text-right">Count</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $summary.by_status as $status => $count}
                            <tr>
                                <td>
                                    {if $status eq 'delivered'}
                                    <span class="label label-success">{$status|ucfirst}</span>
                                    {elseif $status eq 'sent'}
                                    <span class="label label-info">{$status|ucfirst}</span>
                                    {elseif $status eq 'failed' || $status eq 'undelivered'}
                                    <span class="label label-danger">{$status|ucfirst}</span>
                                    {else}
                                    <span class="label label-default">{$status|ucfirst}</span>
                                    {/if}
                                </td>
                                <td class="text-right">{$count|number_format:0}</td>
                                <td class="text-right">
                                    {if $summary.total_messages > 0}
                                    {($count / $summary.total_messages * 100)|number_format:1}%
                                    {else}
                                    0%
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No data for selected period.</p>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Channel Breakdown -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">By Channel</h3>
                </div>
                <div class="panel-body">
                    {if $summary.by_channel}
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{$lang.channel}</th>
                                <th class="text-right">Count</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $summary.by_channel as $channel => $count}
                            <tr>
                                <td>
                                    {if $channel eq 'whatsapp'}
                                    <span class="label label-success">WhatsApp</span>
                                    {else}
                                    <span class="label label-info">SMS</span>
                                    {/if}
                                </td>
                                <td class="text-right">{$count|number_format:0}</td>
                                <td class="text-right">
                                    {if $summary.total_messages > 0}
                                    {($count / $summary.total_messages * 100)|number_format:1}%
                                    {else}
                                    0%
                                    {/if}
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {else}
                    <p class="text-muted text-center">No data for selected period.</p>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Stats -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Daily Activity</h3>
        </div>
        <div class="panel-body">
            {if $daily_stats && count($daily_stats) > 0}
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>{$lang.date}</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">{$lang.delivered}</th>
                        <th class="text-right">{$lang.failed}</th>
                        <th class="text-right">{$lang.segments}</th>
                        <th class="text-right">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $daily_stats as $day}
                    <tr>
                        <td>{$day->date}</td>
                        <td class="text-right">{$day->total|number_format:0}</td>
                        <td class="text-right text-success">{$day->delivered|number_format:0}</td>
                        <td class="text-right text-danger">{$day->failed|number_format:0}</td>
                        <td class="text-right">{$day->segments|number_format:0}</td>
                        <td class="text-right">${$day->cost|number_format:2}</td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
            {else}
            <p class="text-muted text-center">No data for selected period.</p>
            {/if}
        </div>
    </div>
</div>
