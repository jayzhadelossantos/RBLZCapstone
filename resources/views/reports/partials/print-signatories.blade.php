<div class="d-none d-print-block mt-5 pt-3" style="page-break-inside: avoid;">
    <div class="row text-center">
        <div class="col-6">
            <div class="text-start mb-4">Prepared by:</div>
            <div class="border-top border-dark pt-1 fw-bold">
                {{ auth()->user()?->name ?? 'Supply Office Personnel' }}
            </div>
            <div class="small text-muted">{{ auth()->user()?->isAdmin() ? 'Administrator' : 'Supply Office Staff' }}</div>
        </div>
        <div class="col-6">
            <div class="text-start mb-4">Verified / Approved by:</div>
            <div class="border-top border-dark pt-1 fw-bold">
                {{ $activeAdmin?->name ?? 'School Administrator' }}
            </div>
            <div class="small text-muted">School Head / Administrator</div>
        </div>
    </div>
</div>
