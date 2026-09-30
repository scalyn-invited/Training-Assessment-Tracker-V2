<section class="panel receipts" wire:poll.5s.keep-alive aria-label="Background check receipts">
<div class="section-heading"><h2>Background receipts</h2><span>Refreshes every 5 seconds</span></div>
<p class="subtle">Local notification sink · No outbound email</p>
<ul class="file-list" aria-live="polite">@forelse($jobs as $job)<li><div><strong>Foundation check</strong><small>{{ $job->created_at->format('d M, H:i') }} · {{ substr($job->id, 0, 8) }}</small></div><span class="badge">{{ ucfirst($job->status) }}</span></li>@empty<li>No checks requested yet.</li>@endforelse</ul>
</section>
